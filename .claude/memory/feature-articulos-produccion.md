---
name: feature-articulos-produccion
description: "Módulo Artículos (Productos) + Producción — diseño product-céntrico, decisiones y estado por etapas (rama v0.13.0/articulos-produccion)"
metadata:
  type: project
---

# Módulo Artículos (Productos) + Producción

Rama: `v0.13.0/articulos-produccion` (sobre `master`). En curso desde 21/07/2026.
Plan completo en el archivo de plan de la sesión. Ver [[project-roadmap]], [[feature-existencias]].

## Qué es y por qué
Lo que arrancó como "Módulo de Producción" se amplió a un **catálogo de Productos (Artículos)**
product-céntrico con **Producción como su motor**. Un **Producto** es el SKU vendible/stockeable;
la **Receta** queda como fórmula/BOM. Es la Etapa 3 que el roadmap ya preveía.

## Decisiones de diseño (validadas con el usuario)
- **Producto = entidad nueva** (no se extiende la receta). Elaborado apunta a `recipe_id`; reventa sin receta.
- **Producción consumo-only**: descuenta insumos (BOM completo: ingredientes + descartables + sub-recetas),
  y el output se stockea como stock del Producto elaborado (llega en Etapa 3).
- **Sub-recetas siempre phantom** (se explotan al vuelo). Flag stockeable de semi-elaborados = mejora futura.
- **Pricing repartido por tipo (clave, bajo riesgo):** el precio de venta del **elaborado sigue en la receta**
  (`recipe_prices`, matriz y Dashboard de recetas **intactos** — se conserva la revisión del precio en recetas).
  El de **reventa** vive en el producto (`product_prices`, espejo de `recipe_prices`). **No se migra `recipe_prices`.**
  Margen unificado: elaborado → precio de su receta; reventa → precio propio.
- **Reuso máximo:** ledger + `StockService` (stock), `RecipeCostCalculator/Propagator` (costo elaborado),
  `PurchaseLineRecorder` (costo/stock de reventa), listas de precios, `UnitConverter`.

## Modelo de datos
- `products`: tenant_id, name, `type` (enum `ProductType`: manufactured|resale), `recipe_id` (nullable, solo manufactured),
  unit, `cost_per_unit` (nullable; reventa lo usa, elaborado null → deriva de la receta), sku, `barcode`
  (**unique (tenant_id, barcode)**, para el futuro lector), active. Relación `Tenant::products()`.
- `product_prices` / `product_price_logs`: **espejo exacto** de `recipe_prices`/`recipe_price_logs`, solo para reventa.
- `ProductType` enum (`Manufactured`/`Resale`, con `label()` y `usesRecipe()`).

## Estado por etapas
- **1a ✅** Fundación: enum, migración `products`, modelo, `ProductFactory` (estados manufactured/resale/inactive,
  la receta hereda tenant vía closure sobre `$attributes['tenant_id']`).
- **1b ✅** CRUD Artículos: `ProductController` (index/store/update/toggleActive), Store/UpdateProductRequest
  (recipe_id/cost_per_unit `requiredIf` según tipo; barcode unique scoped, ignore self en update),
  `ProductPolicy`, vistas index + modales create/edit (radios de tipo togglean receta vs. costo con Alpine),
  componente `x-product-type-badge`, ítem "Artículos" en grupo Existencias (sidebar + breadcrumb + drawer mobile).
  El controller **nulea** el campo que no corresponde al tipo (`normalizeByType`).
- **1c ✅ (⚠️ UI retirada en v0.13.0)** Precio/margen de reventa: `ProductPrice`/`ProductPriceLog`, `ProductPriceWriter`
  (espejo de RecipePriceWriter). **La matriz de reventa se ELIMINÓ** (decisión del usuario, jul 2026): se borraron
  `ProductPriceController`, la vista `products/prices.blade.php`, las rutas `products.prices.matrix|update`, `ProductPriceTest`,
  la solapa Elaborados/Reventa de `price-lists/matrix` y el botón "Precios de reventa" del catálogo. Los artículos se
  organizan por **categoría**; los de reventa mantienen su costo (de Compras) sin pantalla de precio de venta.
  **Capa de datos latente (no borrada):** tablas `product_prices`/`product_price_logs`, modelos, `ProductPriceWriter`
  (aún lo usa `DemoCatalogSeeder`) y las relaciones `Product::prices()/priceLogs()` siguen existiendo por si se retoma.
- **2A ✅** Producto stockeable (ops manuales): `CatalogItemType` +Product (aditivo); `StockService`/`StockLevel`/
  `StockMovement`/`Product` con firmas `Ingredient|Packaging|Product` y relaciones; pestaña **Productos** en `/stock`
  (index + kardex + ajuste/recuento/mínimo; rutas `whereIn` +product). `displayUnit` usa `unit->short()` para product.
  El tab muestra todos los productos activos; valuación por `cost_per_unit` (manufactured=null→0 hasta Producción).
  `ProductStockTest` (8). **520 tests verdes.**
- **2B ✅** Compra de reventa: `PurchaseLine::isProduct()`/`product()`; `PurchaseLineRecorder::apply()/applyWithCost()`
  con rama de producto (= ingrediente sin subdivisiones; `applyProductCost()` solo actualiza `cost_per_unit`,
  sin price log ni propagación ni alerta — no interviene en recetas; guard `isResale()`); `syncStockFromExplicitCost`
  amplió tipo. `PurchaseController::match()` pasa `$products` (solo reventa) + `$productCatalog`; `matchLine` `$belongs`
  con match de 3 tipos. Vista: optgroup "Productos (reventa)" + `window.MATCH_PRODUCT_CATALOG` **aparte** del de
  ingredientes (los ids colisionan); `match.js` elige catálogo por tipo. `ProductPurchaseTest` (8). **528 tests verdes.**
  Requiere `npm run build` para reflejar `match.js`.
- **3A ✅** Producción (backend): `RecipeExploder::explode(Recipe, factor)` aplana el BOM a insumos base
  (ingredientes + descartables), explotando sub-recetas phantom recursivamente (`childFactor =
  factor × convert(quantity_used, unit, child.yield_unit) / child.yield_quantity`), agrega por ítem, ignora
  mano de obra. `StockService::registerMovement` **generalizó la referencia**: `?PurchaseLine $reference`
  → escalares `?string $referenceType, ?int $referenceId` (compras pasan `'purchase_line'`); nuevo
  `reverseMovementsFor(type, id, user)` (eager-loada `location`+stockables para no violar preventLazyLoading).
  Tabla `productions` (cabezal/snapshot: product/recipe/quantity/unit/unit_cost/total_cost/status/produced_at/
  cancelled_at), enum `ProductionStatus` (Confirmed/Cancelled), `Production` modelo (`movements()` por
  `reference_type='production'`), `Tenant::productions()`, `ProductionFactory`. `ProductionService`:
  `preview()` (puro, marca faltantes), `produce()` (**cantidad en unidades del producto**;
  `factor = convert(qty, product.unit, recipe.yield_unit)/yield_quantity`; emite movimientos **ordenados por
  `(stockable_type, stockable_id)`** para evitar deadlocks; consumos − y elaborado +, todos referenciados a la
  producción), `cancel()` (reverseMovementsFor + status). **Decisión: sin valuación del elaborado por ahora** —
  producir no toca la regla de `unit_cost` de `StockService` (solo `Purchase` pisa `stock_levels.unit_cost`);
  el elaborado sigue valuado a `cost_per_unit` (null→0) en `/stock`. `ProductionTest` (12). **540 tests verdes.**
- **3B ✅** Producción (UI): `ProductionController` (index/create/`preview` JSON/store→produce/show leyendo el
  ledger por referencia/`cancel` PATCH→anula), rutas lectura (index/show + create con `role:`) y escritura
  (preview/store/cancel), `ProductionPolicy`, `StoreProductionRequest` (product_id `exists` manufactured + qty `gt:0`).
  Vistas `production/index` (tabla + card mobile), `production/create` (**preview en vivo con Alpine inline + fetch
  al endpoint** — sin módulo JS de build nuevo; avisa faltantes, permite stock negativo), `production/show`
  (resumen + insumos consumidos desde `movements()`, botón Anular con confirm). Componente `x-production-status-badge`.
  Ítem "Producción" en el grupo Producción del sidebar + sección Producción en el drawer mobile + breadcrumbs en
  `layouts/navigation`. `ProductionControllerTest` (8). **548 tests verdes.** Requiere `npm run build`.
- **Migración receta→producto** (`products:from-recipes`, `CreateProductsFromRecipes`): crea un producto elaborado
  por cada receta no-semi activa **con precio** (`recipe_prices`) que aún no tenga producto (`whereNotExists` por
  `recipe_id`+manufactured). Producto hereda `name`, `unit = yield_unit`, `cost_per_unit = null`, activo; **no migra
  precios** (siguen en la receta). Idempotente; flags `--all` (incluir sin precio), `--tenant=`, `--dry-run`, `--force`.
  Preview con Laravel Prompts + confirmación. Sin scope de tenant en CLI → itera todos los tenants salvo `--tenant`.
  `CreateProductsFromRecipesTest` (8). Correr una vez tras el deploy de v0.13.0 (dry-run: 141 recetas en la base real).
  Acepta `--category=NOMBRE` (crea/reusa la categoría por negocio y la asigna a los productos creados).
- **Categorías de artículos + visibilidad en Producción**: tabla `product_categories` (tenant_id, name,
  `producible` bool, unique tenant+name) + `products.product_category_id` nullable nullOnDelete. Espejo del patrón
  de categorías de gastos: `ProductCategory` modelo, `ProductCategoryController` (store/update/destroy, guard de
  borrado si tiene artículos, `wantsJson` para alta rápida, unicidad scoped), rutas `product-categories.*`, componente
  **propio** `product-categories-modal` (toggle "se produce"; **no** se tocó el de gastos). Modales de producto con
  `<select>` de categoría + "+ nueva" al vuelo; índice con columna y filtro. **El objetivo**: `ProductionController::create`
  filtra `whereHas('category', producible=true)` → **solo aparecen elaborados de una categoría marcada "se produce"**;
  sin categoría o categoría no-producible → ocultos (decisión del usuario: cafetería se costea pero no se produce hasta
  el módulo de ventas). El filtro es solo del select; `produce()` no lo revalida (no es un guard duro). `ProductCategoryTest`
  (10) + filtro en `ProductionControllerTest` (3). **570 tests verdes.** Los 131 productos de Orfano quedaron sin categoría
  → hay que clasificarlos para producirlos.
- **P3 · UI de política de precio (paso 3 ✅)**: factory compartido `Alpine.data('priceCell')`
  (`resources/js/pricing/price-cell.js`, registrado en `app.js` antes de `Alpine.start()`) + componente
  `<x-price-cell-editor>` — popover **teletransportado a `body`** con posición `fixed` calculada en JS (`startEdit`
  computa `popTop/popLeft`) para no quedar recortado por el `overflow-x-auto` del contenedor de tablas. Selector
  Manual/Margen/Recargo + badge en las **5 superficies** (catálogo, dashboard, /recipes card+tabla, matriz, y detalle
  de receta **in-place** con `recomputePreview()`). Escribir a mano vuelve a Manual. Los 5 controllers/viewmodels pasan
  `policy_type`/`policy_value` (helper `ProductPrice::policyPayload()`). Backend/enum en [[domain-model-articulos]] (P3).
- **Fase A · Artículos como hub de precios**: la matriz pasó a **product-céntrica** dentro de Artículos
  (`ProductController::matrix` + vistas `products/matrix` + `products/tabs`, pestañas Catálogo | Matriz), muestra **todos**
  los artículos (elaborados + reventa) × listas, reusa `priceCell`. Se eliminó la matriz receta-céntrica de
  `PriceListController` (view + ruta `price-lists.matrix`). La **gestión de listas** (`price-lists.index`) se movió del
  grupo Costos al de **Administración** (sidebar + mobile-nav + breadcrumbs). `PriceListMatrixTest` reescrito
  product-céntrico. Las **sugerencias masivas** siguen siendo solo de elaborados (`sellableManufacturedProducts`) —
  las celdas de reventa muestran la sugerencia pero se confirman a mano (pendiente chico si se quiere extender).
- **Fase B · Código EAN-13 para todos**: `barcode` es el identificador escaneable único (real para reventa; para los
  demás se genera un **EAN-13 interno** prefijo 2). `Ean13Generator` (genera/valida con dígito verificador) +
  `ProductCodeAssigner::assignIfMissing` (único por negocio, reintenta ante colisión). Se asigna al crear/editar desde
  el catálogo si el campo queda vacío (`ProductController::store/update`) y en `products:from-recipes`; comando de
  backfill `products:assign-codes` (`--tenant`, `--dry-run`). `Ean13GeneratorTest` (3) + `AssignProductCodesTest` (6).
  Queda como base del futuro POS/lector (decisión: código compartido por negocio, ver [[decision-multi-sucursal]]).

## P4 — origen del costo + la reventa de primera clase ✅ (02/09/2026)
P4 **no** terminó siendo "reestructurar Producción". Al revisar el código con el usuario quedó claro que el problema
real era otro: **el costo vive en dos columnas con dos escritores, y el lado de la reventa nunca se completó**.

**Decisiones tomadas** (no re-litigar):
1. **Dos columnas, una puerta.** `recipes.unit_cost` (elaborado, lo escribe `RecipeCostPropagator`) y
   `products.cost_per_unit` (reventa, lo escribe `PurchaseLineRecorder` + el form) **se quedan como están**: no se
   consolidan ni se migran. `Product::currentCost()` es la única lectura del costo de un artículo.
2. **Alcance completo en compras de reventa**, incluido el extractor de IA.
3. **Valuación por producción FUERA de alcance.** `StockService:76` no se tocó: `ProductionService` sigue escribiendo
   su propio `unit_cost` sin pisar `stock_levels.unit_cost`.

**Lo que se hizo** (8 commits, `7eab2bf`→`3453b56`, 708 tests verdes):
- **La reventa era ciudadano de segunda en toda la plomería de Compras** — el código estaba escrito para dos tipos y
  el producto caía en el `else`: `ProductLinkMemory::ownedIds()` validaba ids de producto contra `packagings`;
  `apply()` no consultaba `rememberedPkgQty()` en la rama de producto; `applyLineSuggestions()` lo acumulaba en
  `$touchedPackagingIds` y propagaba recetas ajenas; `match.blade` mostraba el envase de un descartable homónimo.
- `product_cost_logs` + `CostLogSource` + `x-cost-source-badge` + endpoint `products.cost-history`.
- Alerta de salto de costo para reventa (`raiseCostSpike` acepta `Product`; `subjectType` sale de `purchaseable_type`).
- El escaneo acepta reventa (regla del request desde el enum, `validSuggestion` de tres ramas, guard de `scan()`).
- La IA sugiere reventa **sólo si el negocio tiene** artículos de reventa (catálogo condicional + fail-closed).
- **Bug encontrado de paso**: `applyCount()`/`registerAdjustment()` valuaban con `$item->cost_per_unit`, que en un
  elaborado es NULL → cada recuento y ajuste se asentaba en el ledger **a $0**. Mismo bug que P1 arregló del lado de
  la lectura y dejó vivo del lado de la escritura. Helper `StockService::unitCostOf()`.

**Gotchas que costaron tiempo** (ver [[feedback-crud-modals]] para los del modal):
- `wasChanged()` refleja el **último** save: en `ProductController::update()` hay que leerlo **antes** de
  `assignIfMissing()`, que guarda el EAN-13 y reemplaza el changeset.
- `Product::priceLogs()` ya existía y es el precio de **VENTA**. El historial de costo va como `costLogs()`.
- `currentCostSource()` responde "de qué columna" (derivado del `type`), **no** la procedencia: para una reventa con
  costo tipeado a mano igual dice `'compra'`. La procedencia sale del último `ProductCostLog`. Son dos preguntas.
- El promedio ponderado contra `defaultLocation()` **no es un bug**: es coherente con dónde cae el stock de la compra
  y con [[decision-multi-sucursal]] (costo por-negocio). Tiene comentario en el código para que no lo "arreglen".

## Pendiente — pasar insumos mal clasificados a artículos de reventa (consultado 03/09/2026)
El usuario tiene insumos que en realidad son **reventa** (gaseosas, aguas, jugos: CC Zero id 254, Sprite, Levite,
Powerade, Cepita, Schweps, aguas Villavicencio, leche chocolatada…). Quiere pasarlos a Artículos para ponerles margen
y que Compras los tome como reventa. **Decisión postergada a propósito: se resuelve cuando el módulo esté terminado.**

**⚠️ El blocker no es mover datos, es que falta la feature.** Casi todos esos ítems tienen `subdivisions` (6, 8, 12):
se compran por bulto y se venden por unidad. `products` **no tiene** `subdivisions`/`subdivision_label`/`cost_per_package`,
y la rama de producto de `PurchaseLineRecorder::apply()` **no hace matemática de bulto** (el comentario lo dice:
"se compra como un ingrediente sin subdivisiones"). Caso real medido sobre la BD de dev (tenant 3):

| | Hoy como insumo | Si se convierte hoy |
|---|---|---|
| `CC ZERO 600MLX6`, qty 5 u, unit_price $9.622,50 | costo **$1.603,75**/botella · ingresan **30** | costo **$9.622,50**/botella · ingresan **5** |

Costo 6× inflado que alimenta directo el margen. **No convertir nada hasta agregar subdivisiones a la reventa.**

Lo que haría falta, si se retoma:
1. `subdivisions` + `subdivision_label` + `cost_per_package` en `products`, y espejar en la rama de producto de
   `apply()` la cuenta que ya hace la de ingrediente (`if ($item->subdivisions && purchaseUnit === u && item->unit === u)`).
2. Comando `ingredients:to-resale {ids} --dry-run`: crea el artículo y **re-apunta** `supplier_product_links` (para que
   Compras lo sugiera como reventa de ahí en más), `stock_levels`, `stock_movements`, `purchase_lines` e
   `ingredient_price_logs` → `product_cost_logs`. Desactiva el insumo viejo.
   - **Punto delicado**: `StockMovement` es ledger inmutable (`booted()` tira `LogicException` en update/delete).
     Re-apuntar el `stockable_type` exige escritura cruda y una decisión explícita del usuario. Sólo cambia el TIPO
     al que apunta la fila, no cantidades ni costos, así que la historia sigue siendo cierta.
3. Decidir si se migra todo (stock + kardex + compras), sólo el stock, o se arranca de cero recontando.

**Cómo identificar candidatos**: insumos sin ninguna fila en `recipe_ingredient_lines`. En tenant 3 son 84, pero sólo
~25 son reventa real — el resto (semillas, premezclas, chocolates, mermeladas) son insumos que todavía no entraron en
una receta. **La lista hay que revisarla con el usuario, no automatizarla.**

## Próximo — Producción (las direcciones siguen sobre la mesa)
Ninguna se implementó; siguen siendo candidatas y se pueden combinar:
1. **Valuación del elaborado por producción** — que fabricar alimente el costo del artículo. `productions.unit_cost`
   ya se calcula y se snapshotea en el movimiento; falta propagarlo. Ojo: hoy `stock_levels.unit_cost` **no lo lee
   nadie**, así que sin un lector el trabajo no cambia ninguna pantalla.
2. **Semi-elaborados stockeables** — las sub-recetas son siempre phantom (`RecipeExploder`). BOM multinivel real.
3. **Órdenes de producción / planificación** — ciclo de estados en vez de "producir ahora".
4. **Mermas / rendimiento real** — depende de que exista (1) para tener dónde impactar el ajuste.
Después: **Ventas / POS** (usará el EAN-13 y la política de precio).

## ⚠️ Deploy de v0.13.0 — ORDEN (o las listas de precios se ven vacías)
El precio vive en `product_prices` (**fuente única** de toda la UI de precios). La migración de backfill (`000004`)
corre dentro de `migrate` con la tabla `products` recién creada (vacía) → copia **0 precios**. Hay que copiarlos
**después** de crear los productos. **Secuencia**: `migrate` → `npm run build` → `products:from-recipes` →
**`products:backfill-prices`** (idempotente, copia `recipe_prices→product_prices`; `BackfillProductPricesTest` 6) →
`products:assign-codes` (EAN-13 interno a los que no tengan código; idempotente) → `products:refresh-prices` →
clasificar elaborados por categoría "se produce". (P4 sumó la tabla `product_cost_logs`: la crea el mismo `migrate`
y arranca vacía, no necesita backfill — se puebla desde la primera compra o edición de costo posterior.) Los precios **nunca se pierden**: quedan
en `recipe_prices` (latente) hasta que `backfill-prices` los copia. Detectado el 26/08/2026 al ensayar el deploy con
la BD de producción (`recipe_prices`: 460, `product_prices` sin poblar tras `migrate`).

## Convenciones nuevas del módulo
- `barcode` unique por tenant, nullable (varios NULL conviven; el mismo código puede repetirse en otro tenant).
- La edición de precio de las 5 superficies usa el **factory compartido `priceCell`** + `<x-price-cell-editor>` (reemplazó
  al patrón de celda inline duplicado que copiaba a `price-lists/matrix`). PATCH → JSON con precio efectivo + margen + política.
- Tests reusan el helper global `tenantUserAs(TenantUserRole)` (definido en `IngredientCrudTest`).

## Tests (512 verdes al cierre de 1c)
`FactoriesTest` (+2 producto), `ProductCrudTest` (16), `ProductPriceTest` (9). StockMovement sigue sin factory a propósito.
