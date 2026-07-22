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
- **1c ✅** Precio/margen de reventa: `ProductPrice`/`ProductPriceLog`, `ProductPriceWriter` (espejo de RecipePriceWriter),
  `ProductPriceController` (`update` JSON espejo del de recetas con costo=cost_per_unit; `matrix`).
  Matriz de reventa `products/prices` (`products.prices.matrix`), copia adaptada de la de recetas (solo reventa,
  paginación propia → sin conflicto). **Solapas** Elaborados/Reventa en ambas matrices; link desde el catálogo.
  La matriz de recetas quedó intacta salvo la solapa.
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

## Convenciones nuevas del módulo
- `barcode` unique por tenant, nullable (varios NULL conviven; el mismo código puede repetirse en otro tenant).
- Toda superficie de pricing reusa el patrón de celda inline Alpine de `price-lists/matrix` (fetch PATCH → JSON con margen/color).
- Tests reusan el helper global `tenantUserAs(TenantUserRole)` (definido en `IngredientCrudTest`).

## Tests (512 verdes al cierre de 1c)
`FactoriesTest` (+2 producto), `ProductCrudTest` (16), `ProductPriceTest` (9). StockMovement sigue sin factory a propósito.
