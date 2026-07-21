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
- **2 🔲** Stock de productos + compra de reventa: extender `CatalogItemType` con `Product` (aditivo) → ramificar
  `StockService`/`StockLevel`/`StockMovement` (firmas `Ingredient|Packaging` → +Product), pestaña en `/stock`,
  match de Compras a `product`.
- **3 🔲** Producción: `RecipeExploder` (BOM completo, phantom), generalizar referencia de `StockService::registerMovement`
  + `reverseMovementsFor()`, tabla `productions` + `ProductionService::produce()/cancel()`, UI grupo Producción con preview.
  Ojo: bloquear las filas de `stock_levels` en orden determinista (deadlocks).

## Convenciones nuevas del módulo
- `barcode` unique por tenant, nullable (varios NULL conviven; el mismo código puede repetirse en otro tenant).
- Toda superficie de pricing reusa el patrón de celda inline Alpine de `price-lists/matrix` (fetch PATCH → JSON con margen/color).
- Tests reusan el helper global `tenantUserAs(TenantUserRole)` (definido en `IngredientCrudTest`).

## Tests (512 verdes al cierre de 1c)
`FactoriesTest` (+2 producto), `ProductCrudTest` (16), `ProductPriceTest` (9). StockMovement sigue sin factory a propósito.
