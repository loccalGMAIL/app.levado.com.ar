---
name: domain-model-articulos
description: "ADR del modelo de dominio Insumos vs Artículos: el Artículo es dueño único de costo y precio; roadmap de centralización (rama v0.13.0)"
metadata:
  type: project
---

# Modelo de dominio: Insumos vs Artículos (ADR, jul 2026)

Ratificado con el usuario antes de reestructurar Producción. Ver [[feature-articulos-produccion]], [[project-roadmap]].

## Decisión
Separar **Insumos** (lo que se compra para consumir en producción; parte de las recetas; no se venden)
de **Artículos** (lo único comercializable: elaborados vía Producción o de reventa), y **centralizar toda
la lógica comercial (costo + precio) en el Artículo**. Compras, Producción, Stock y Ventas consumen esa
información sin duplicar reglas.

- **Evolucionar** el modelo actual, NO unificar a un "Item master" único (rechazado por riesgo). Insumos =
  `Ingredient` + `Packaging`; Artículos = `Product` (`ProductType` manufactured|resale); Stock único vía `CatalogItemType`.
- **Artículo = agregado raíz comercial** (`Product`), único dueño de:
  - **Costo vigente** `Product::currentCost()`: elaborado → cache `recipe.unit_cost` (mantenido por
    `RecipeCostPropagator`; insumos+MO+sub-recetas, **sin overhead**); reventa → `cost_per_unit` (último costo, de Compras).
  - **Precio** (futuro): `precioPara(lista)` con política (manual/margen/recargo). Hoy el precio del elaborado
    vive en la Receta (`recipe_prices`) — **se decidió moverlo al Artículo** (Fase 2).
- **Receta = BOM puro** de un elaborado (1—1). Semielaborados siguen siendo Recetas intermedias (phantom, no Artículos).
- El **cómputo** del costo elaborado ya vive en `RecipeCostCalculator`/`RecipeCostPropagator`; el Artículo solo **expone** la lectura.
- No existe módulo de **Ventas** todavía (greenfield). El Dashboard de rentabilidad hoy es **receta-céntrico**
  (`recipes.unit_cost` + overhead vs `recipe_prices`) — se repuntará al Artículo en Fase 2.

## Roadmap de centralización
- **P1 ✅ (hecho)**: `Product::currentCost()` + `currentCostSource()` (extrae la regla que estaba inline en
  `products/index`); se usa para **valuar elaborados** en `/stock` (index + kardex; antes valuaban 0) a nivel de
  **lectura** (sin tocar el ledger). `StockController` eager-loada `recipe` para no lazy-loadear. `ProductCostTest` (5). 566 tests.
- **P2 ✅ (hecho)**: el precio vive en el **Artículo** (`product_prices`), fuente única. Decisiones: margen contra
  **costo total** (`Product::fullCost()` = currentCost + prorrateo de overhead; reventa sin overhead); **campo precio
  quitado del form de receta** (Receta = BOM); **edición inline mantenida** en Dashboard/`/recipes`/detalle/matriz pero
  **repuntada al artículo** vía `Recipe::manufacturedProduct` (recetas sin artículo → precio no editable, "—").
  Endpoint único `ProductPriceController::update` (`products.prices.update`) — devuelve margen con fullCost.
  Migración backfill `recipe_prices`→`product_prices` (318 precios). Todas las lecturas (Dashboard SQL, `/recipes`,
  detalle, matriz, catálogo) salen de `product_prices` vía el producto. Sugerencias por % de la matriz operan sobre
  el artículo. **Retirado**: `RecipePriceController`, `RecipePriceWriter`, ruta `recipes.prices.update`, campo
  `selling_price` de forms/requests, y la escritura de precio en `RecipeController` store/update/copy. `recipe_prices`
  (tabla + modelo + factory) queda **latente** (dato preservado). `ProductPriceTest` reemplaza `RecipePriceUpdateTest`.
  Commits: catálogo → dashboard → /recipes → detalle → matriz → limpieza. **561 tests verdes.**
- **P3 🔶 (en curso, 3 de 4 pasos hechos)**: políticas de precio (manual/margen/recargo) + métodos de costeo de
  reventa (último/promedio) configurables. **Decisiones**: ambos features juntos; política de precio **por
  artículo × lista**; método de costeo **default por negocio (tenant_settings) + override por artículo**;
  promedio ponderado **al momento de comprar** (MVP; editar/revertir compras viejas no recalcula histórico).
  - **Paso 1 ✅ (commit `d338c8d`)** costeo de reventa: enum `CostingMethod` (last|average), `products.costing_method`
    (override, null→default), `Product::effectiveCostingMethod(default)`, setting `resale.costing_method` (Mi negocio),
    `PurchaseLineRecorder::applyProductCost` promedia contra el stock existente antes del alta. `ProductCostingTest` (9).
  - **Paso 2 ✅ (commit `8a4d4d1`)** modelo de política de precio: enum `PricingPolicy` (manual|margin|markup) con
    `priceFor(cost,value)` (margen=costo/(1-%), recargo=costo×(1+%)); columnas `product_prices.policy_type`+`policy_value`
    (y `price` ahora NULLABLE = precio computado cacheado); `ProductPriceWriter::setPolicy` computa+cachea; servicio
    `ArticlePriceRecalculator` (`recompute(product)` / `recomputeForTenant(tenant)`) mantiene el `price` al día con
    **triggers**: compra reventa (`PurchaseLineRecorder`), costo de receta (`RecipeCostPropagator::propagateFrom`),
    overhead (`FixedCostController` + `BusinessController`); comando `products:refresh-prices`; `ProductPriceController::update`
    acepta `policy_type`+`policy_value` (retrocompat: sin policy → manual) y devuelve `policy_type`/`policy_value`+precio+margen.
    `ProductPricingPolicyTest` (7). **575 tests verdes.** Migraciones aplicadas.
  - **Paso 3 ✅** UI de política en las **5 superficies**: factory `Alpine.data('priceCell')`
    (`resources/js/pricing/price-cell.js`) + componente `<x-price-cell-editor>` (popover **teletransportado a `body`**
    para no recortarse en el `overflow` de las tablas — se posiciona con coords `fixed` calculadas en JS). Selector
    Manual/Margen/Recargo + badge; escribir a mano vuelve a Manual; los 5 controllers/viewmodels pasan `policy_type`/
    `policy_value` (helper `ProductPrice::policyPayload()`). Detalle de receta (`recipes/show`) in-place con preview
    en vivo del precio efectivo (`recomputePreview()`). Factory + contrato de `save()` verificados en el bundle real.
  - **Paso 4 🔶 (deploy)**: correr `products:refresh-prices` sobre la base + cerrar CHANGELOG. **⚠️ Bug de deploy
    encontrado y corregido**: en un deploy nuevo la migración de backfill (`000004`) corre dentro de `migrate` con la
    tabla `products` recién creada (vacía) → copia **0 precios** → las listas quedan sin valores (los precios siguen
    intactos en `recipe_prices`; solo no llegan a `product_prices`, la fuente única de la UI). Nuevo comando
    **`products:backfill-prices`** (idempotente; `BackfillProductPricesTest` 6) copia `recipe_prices → product_prices`
    **después** de `products:from-recipes`. **Orden de deploy correcto**: `migrate` → `npm run build` →
    `products:from-recipes` → **`products:backfill-prices`** → `products:refresh-prices`. Ver [[feature-articulos-produccion]].
- **P4 ✅ (02/09/2026)**: **no** fue "reestructurar Producción" sino cerrar el origen del costo. Decisión: **dos
  columnas, una puerta** — `recipes.unit_cost` y `products.cost_per_unit` se quedan con su escritor natural (no se
  consolidan ni se migran) y `Product::currentCost()` es la **única lectura** del costo de un artículo. Se sumó
  `product_cost_logs` (historial de la reventa con procedencia y vínculo a la factura), la etiqueta de origen
  Receta/Compra/Manual en catálogo y stock, la alerta de salto de costo para reventa, y se completó la compra de
  reventa en toda la plomería (memoria de vínculos, aplicar en lote, escaneo, extractor de IA). **La valuación por
  producción quedó explícitamente fuera**: `StockService:76` sin tocar. Ver [[feature-articulos-produccion]].
  - **Distinción que hay que respetar**: `currentCostSource()` responde *de qué columna* sale el costo (derivado del
    `type`); la **procedencia** del valor vigente sale del último `ProductCostLog`. Para una reventa con costo tipeado
    a mano, la regla igual dice `'compra'`. No unificar los dos conceptos en un método.
- **Órdenes de producción ✅ (P5)**. **Costo de producción + historial + alerta ✅ (16/09/2026)**: ver
  [[feature-articulos-produccion]] → "Punto 1 resuelto". Ratifica este ADR, no lo cambia — `currentCost()` sigue
  siendo la única fuente del costo vigente del elaborado.
- **Próximo 🔲**: semi-elaborados stockeables / movimientos de stock por destino / mermas (parkeados, sin trabajar).
  Luego Ventas.

## Notas de diseño
- `currentCost()` es el **costo estándar** del artículo (para valuar existencias); el costo del **evento** de
  producción (`productions.total_cost`/`material_cost`/`labor_cost`, consumo físico) queda aparte a propósito.
  **Confirmado de nuevo (16/09/2026)**: se evaluó explícitamente propagar `productions.unit_cost` a `currentCost()`
  y se descartó — sería reemplazar el costo completo y siempre actualizado de la receta (insumos+MO+sub-recetas,
  recalculado cada vez que cambia un insumo) por uno parcial y congelado en el tiempo. No volver a proponerlo.
- Overhead de gastos fijos: excluido de `currentCost()`; si el pricing lo necesita, se decide en P2.
- Unificar `Ingredient`+`Packaging` en un solo "Insumo": posible mejora futura, baja prioridad, fuera de alcance.
