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

## P5 — Órdenes de producción, pedidos y destino ✅ (04/09/2026)
De las cuatro direcciones que quedaron abiertas al cerrar P4, el usuario eligió **órdenes de producción** — pero no
en el sentido "ciclo de estados sobre producir uno a la vez" que se había anotado: la idea central resultó ser
**órdenes que agrupan pedidos con destino** (sucursales y repartidores), pensando ya en reparto y, más adelante,
facturación/cuentas de clientes. Plan completo en la sesión; ver [[decision-multi-sucursal]] para el encuadre
de qué es por-sucursal.

**Decisiones tomadas** (no re-litigar):
1. **Tres niveles**: Orden (`production_orders`) → Pedidos (`production_order_requests`, uno por destino) → Líneas
   (`production_order_lines`, artículo + cantidad).
2. **El stock producido entra al obrador** (Casa Central), como ya hacía "producir ahora". El destino es
   **informativo** (arma la planilla de reparto) — **no genera movimientos de stock todavía**. Transferencias
   entre sucursales y salida por reparto quedan para la fase siguiente.
3. **Repartidor = entidad propia simple** (`delivery_people`: nombre, teléfono, notas, activo), con
   **`user_id` nullable sin usar** desde ya: a futuro el repartidor va a tener login para ver su estado de cuenta,
   modificar los pedidos de sus clientes y su propia administración. El rol no se agrega hasta que haya pantallas
   que lo justifiquen.
4. **Las sub-recetas siguen phantom** — no se tocó nada de semi-elaborados.

**Lo que se hizo** (708→755 tests verdes):
- `ProductionOrderService`: `aggregate()` (suma cantidad por artículo a través de todos los pedidos — el mismo pan
  pedido por dos destinos se suma), `preview()` (consumo de insumos **combinado** de todos los artículos de la
  orden), `produce()` (una `Production` por artículo, todas atadas a `production_order_id`, en una transacción) y
  `cancel()` (revierte cada producción vía `ProductionService::cancel()`, que ya era idempotente).
- **Reuso del motor, no duplicación**: `ProductionService` se partió en `baseConsumption()` (guardProducible +
  factorFor + explode, sin escribir) y `summarize()` (arma las líneas de preview desde un consumo base) — su
  propio `preview()` pasó a ser `summarize(baseConsumption(...))`. `ProductionOrderService::preview()` llama
  `baseConsumption()` por cada artículo agregado y **suma por insumo entre artículos distintos** (`mergeBase()`,
  misma idea que la agregación interna de `RecipeExploder::add()` pero a otro nivel) antes de pasarlo a `summarize()`.
- `ProductionOrderStatus`: `Draft → Confirmed → (InProduction) → Done`, más `Cancelled`. **`canTransitionTo()`
  es el dueño único de las transiciones.** `Done` y `Cancelled` tienen puerta propia: sólo se llega vía
  `produce()`/`cancel()` (que mueven o revierten stock), nunca por el `transitionTo()` genérico — evita que un
  cambio de estado a mano deje el stock desincronizado del status. `Done → Cancelled` es válido (anular algo ya
  producido revierte el stock).
- **Plantillas sin tablas espejo**: viven en `production_orders` con `is_template=true` + `scheduled_for=null`.
  `ProductionOrder` lleva un **global scope** (`ExcludeTemplatesScope`) que las excluye de cualquier query por
  defecto, con `withTemplates()`/`onlyTemplates()` para optar explícito — evita a propósito la trampa que hoy paga
  `is_semi_elaborate` (6 `where` sueltos, sin scope). El route-model-binding implícito de Eloquent también respeta
  ese scope, así que **una plantilla nunca resuelve por `{productionOrder}`**: `ProductionOrderTemplateController`
  toma el id como `int` plano y resuelve a mano con `onlyTemplates()->findOrFail()` (sigue siendo tenant-safe: el
  scope de `BelongsToTenant` es aparte y sigue activo).
- `ProductionOrderDuplicator::duplicate()`: una sola operación cubre **repetir** una orden (copia fechada, normal)
  e **instanciar una plantilla** (mismo código, `templateName` en vez de `scheduledFor`). Ojo pagado: al crear las
  `production_order_requests` de la copia hay que pasar `tenant_id` **a mano** — la relación `HasMany::create()`
  sólo llena la FK que conoce (`production_order_id`), y el auto-fill de `BelongsToTenant` depende de que haya un
  `Tenant` bound en el container, que no está garantizado fuera de un request HTTP (ej. tests de servicio).
- **Destino polimórfico a propósito** (`DeliveryDestinationType`: `location`/`delivery_person`), no dos FK
  nullables: el repartidor va a tener clientes propios (ver más abajo) y sumar `Customer` es un `case` más +
  una entrada más en el morph map, sin re-modelar `production_order_requests`. Fusionado con `CatalogItemType`
  en el **mismo** `Relation::enforceMorphMap()` de `AppServiceProvider` — llamarlo dos veces pisa el primero.
- `Product::scopeProducible()`: se extrajo el filtro que antes vivía inline sólo en `ProductionController::create`
  (activo + manufactured + con receta + categoría "se produce"). Ahora lo comparten esa pantalla y las líneas de
  un pedido de orden.
- Rutas anidadas a 3 niveles con `scopeBindings()`
  (`production-orders/{productionOrder}/requests/{productionOrderRequest}/lines/{line}`): la relación en
  `ProductionOrder` se llama **`productionOrderRequests()`**, no `requests()` — Laravel busca el método por el
  **plural del nombre del parámetro de ruta**, mismo mecanismo que ya usa `RecipeLineController`
  (`{ingredientLine}` → `Recipe::ingredientLines()`). `production_order_lines` no tiene `tenant_id` propio y aun
  así queda tenant-safe: el scoping de 3 niveles pasa siempre por `ProductionOrderRequest` (que sí es
  `BelongsToTenant`), así que un `{line}` ajeno nunca resuelve.
- **Planilla de reparto** imprimible: `<style>@media print{...}</style>` embebido en la propia vista (oculta
  `nav`/`aside` por tag, sin tocar el layout compartido) — sin librería nueva.

## Diseño con vista al futuro (P5, no construido — condiciona el modelo)
1. **Movimientos de stock por destino**: el pedido ya tiene todo lo necesario (`tenant_id`, destino, líneas). La
   plomería es genérica y ya existe (`StockService::registerMovement`/`reverseMovementsFor` toman
   `referenceType`/`referenceId` desde la etapa 3A) — un futuro despacho emitiría con
   `reference_type='production_order_request'` sin tocar `StockService`. Falta: distinguir Sucursal-tiene-stock
   de Repartidor-no (gancho: `DeliveryDestinationType::holdsStock()`, ya escrito, sin uso), y separar "pedido" de
   "despachado" (`dispatched_quantity` en `production_order_lines`, aditivo).
2. **Repartidor con login**: `delivery_people.user_id` nace nullable sin usar. El rol propio y las pantallas de
   estado de cuenta/pedidos de clientes quedan para cuando existan — las policies de esta fase ya comparan sólo
   `tenant_id` y delegan el rol a la ruta, así que sumarlo después es tocar rutas, no reescribir policies.
3. **Facturación/cuentas corrientes**: horizonte reconocido, nada construido; el *pedido* es la unidad natural
   donde colgaría la factura futura.

## Punto 1 resuelto — costo de producción, historial y alerta (16/09/2026)

**No** se hizo en la forma que decía la nota vieja ("propagar `productions.unit_cost` al costo del artículo").
Al mirarlo con el código, ese camino era un retroceso: `recipes.unit_cost` (el costo vigente) suma insumos +
descartables + **mano de obra** + sub-recetas y se recalcula solo; `productions.unit_cost` explotaba la misma
receta con los mismos `cost_per_unit` pero **sin mano de obra** y quedaba congelado. Propagarlo habría reemplazado
un número completo y actualizado por uno incompleto y viejo. El usuario lo objetó apenas se lo planteé, antes de
ver el código — su instinto era correcto. **Esto ratifica el ADR de [[domain-model-articulos]]: `currentCost()`
sigue siendo la única fuente del costo vigente; el costo del evento de producción queda aparte a propósito. No
volver a proponer que producir alimente `currentCost()`.**

Lo que sí se hizo, sobre los huecos reales:
1. **Mano de obra en el costo de producción**: `RecipeExploder::explodeWithLabor()` recorre el mismo BOM que
   `explode()` (sub-recetas phantom incluidas) y acumula `labor_cost`/`labor_hours` aparte — así el costo de
   producir es comparable con `recipes.unit_cost` en vez de venir sistemáticamente más bajo. `productions` sumó
   `material_cost`/`labor_cost` (backfill: viejas = material, MO en 0, honesto porque antes el exploder la ignoraba).
   El movimiento de entrada del elaborado pasa a valuarse al costo completo — es el snapshot del evento en el
   ledger, no toca `stock_levels.unit_cost` (guard intacto) ni el costo vigente.
2. **Historial de fabricaciones del elaborado**: **sin tabla nueva.** `Product::productions()` alcanza — cantidad,
   costo, fecha y estado ya viven ahí. Se descartó a propósito sumar `product_cost_logs` para elaborados (lo
   proponía el primer borrador del plan): habría duplicado esos mismos campos y roto la semántica de
   `latestCostLog` como *procedencia del costo vigente* (que para un elaborado sigue siendo la receta, nunca una
   producción). `ProductCostLogTest` ("un elaborado no registra logs de costo") sigue intacto y sigue siendo cierto.
   El badge del catálogo dice **Receta** para el elaborado (la regla, no una procedencia) y abre el mismo modal.
3. **Alerta de salto de costo al fabricar**: se dispara **al producir**, contra la producción confirmada anterior
   del mismo artículo — no desde `RecipeCostPropagator`, que recalcula el cierre completo de ancestros en un lote
   (una suba de harina toca decenas de las 304 recetas reales de una sola vez) y hubiera sido una tormenta de
   alertas por un costo que todavía nadie fabricó. Mismo umbral/toggle que la alerta de compra
   (`alerts.cost_spike.*`), sin setting propio todavía.

**777 tests, todos verdes** (22 nuevos: `ProductionTest` ampliado, `ProductionCostHistoryTest`,
`ProductionCostSpikeTest`, ajustes en `ProductCostSourceTest`/`ProductionControllerTest`).

## Próximo — lo que sigue sobre la mesa (parkeado, no para la sesión actual)
2. **Semi-elaborados stockeables** — las sub-recetas son siempre phantom (`RecipeExploder`). BOM multinivel real.
3. **Movimientos de stock por destino** de P5 (transferencias/reparto) — ver la sección de arriba.
4. **Mermas / rendimiento real** — el único camino real hacia un costo de fabricación distinto del teórico (el
   punto 1 no lo daba: mientras el consumo se derive de la receta, no hay desvío que medir). Depende de que la
   valuación por producción exista para tener dónde impactar el ajuste — parcialmente cubierto por lo de arriba.
Después: **Ventas / POS** (usará el EAN-13 y la política de precio; probablemente se cruce con clientes/reparto).

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
