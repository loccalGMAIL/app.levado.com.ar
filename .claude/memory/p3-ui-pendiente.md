---
name: p3-ui-pendiente
description: "P3 paso 3 (UI de política de precio) — referencia para retomar: qué falta, superficies, contrato del endpoint, backend ya listo"
metadata:
  type: project
---

# P3 · Paso 3 — UI de política de precio (PENDIENTE)

Rama `v0.13.0/articulos-produccion`. El **backend de P3 ya está completo y verde** (pasos 1 y 2, commits
`d338c8d` y `8a4d4d1` — ver [[domain-model-articulos]]). Falta **exponer la edición de política en la UI**.

## Qué falta (paso 3)
Hoy las celdas de precio inline solo editan **precio manual** (mandan `{price}` al endpoint). Falta poder
elegir la **política** (Manual / Margen % / Recargo %) y ver un **badge** de la política + el precio efectivo.

## Backend ya listo (no tocar, solo consumir)
- **Endpoint**: `PATCH products.prices.update` (`ProductPriceController::update`). Acepta body:
  - `{ policy_type: 'manual', price: <número|null> }` (manual; null borra), **o**
  - `{ policy_type: 'margin'|'markup', policy_value: <%> }`.
  - Sin `policy_type` → se trata como manual (retrocompatible: las celdas actuales mandan `{price}`).
  - Devuelve JSON: `selling_price`, `selling_price_formatted`, **`policy_type`**, **`policy_value`**, `margin`,
    `margin_formatted`, `margin_pct`, `margin_pct_formatted`, `margin_color`. El margen es contra `fullCost` (con overhead).
- **Enum** `App\Enums\PricingPolicy` (Manual|Margin|Markup, `label()`, `priceFor(cost,value)`).
- **Datos**: `product_prices` tiene `policy_type` (default 'manual'), `policy_value` (decimal nullable), y `price`
  (NULLABLE = precio efectivo cacheado). `Product::currentPrice($list)` devuelve el precio efectivo (cacheado).
- El precio con política se **recalcula solo** al cambiar el costo (triggers ya cableados); la UI no computa nada.

## Las 5 superficies con celda de precio inline (bloques Alpine duplicados, todas PATCHean products.prices.update)
1. **Catálogo** `resources/views/products/index.blade.php` — fila `<tr x-data>` con `price/priceFormatted/marginPct/marginColor` + `savePrice()`. Controller `ProductController::index` pasa `$priceMap` (product_id→price) desde `product_prices`; **para el badge/edición hay que pasar también la política** (cargar los rows `ProductPrice` keyed by product_id, no solo `price`).
2. **Dashboard** `resources/views/dashboard.blade.php` — `<tr x-data>` con price+margin, editable solo si `$row['product']` (el artículo elaborado vinculado). `DashboardController` arma `$recipeRows` con `'product' => $recipe->manufacturedProduct`.
3. **/recipes índice** `resources/views/recipes/index.blade.php` — dos celdas (card + tabla) con `savePrice()`, editables solo si `$recipe->manufacturedProduct`. `RecipeController::index` pasa `$prices` (recipe_id→precio del artículo) + `manufacturedProduct`.
4. **Detalle de receta** `resources/views/recipes/show.blade.php` — editor propio con selector de lista (`allPrices`, `selectedListId`, `productId`, `savePrice()`). `RecipeShowViewModel` arma `allPrices` + `manufacturedProduct`.
5. **Matriz** `resources/views/price-lists/matrix.blade.php` — celda `<div x-data>` con `price/suggested/marginPct` + `savePrice()`. `PriceListController::matrix` arma `$prices` [recipe_id][list_id]→precio + `$costsPerUnit` (fullCost) + `manufacturedProduct`.

## Enfoque propuesto (del plan aprobado)
- **Editar la política** en el **catálogo** (por artículo, con selector de lista) y la **matriz** (por celda artículo×lista)
  — son los lugares naturales. Selector `Manual | Margen % | Recargo %` + input de valor; `savePrice` manda
  `{policy_type, policy_value}` o `{price}`. Mostrar precio efectivo + **badge** de política (ej. "margen 40%").
- **Mostrar el badge** (solo lectura) en Dashboard/`/recipes`/detalle para consistencia; ahí la edición inline puede
  quedar como precio manual (o read-only para celdas con política) — decidir al retomar.
- Ideal DRY: extraer la lógica Alpine a un componente compartido (Blade `<x-article-price-cell>` o `Alpine.data`),
  pero las 5 markup difieren (fila vs celda vs editor con selector) — evaluar componente vs enhance-in-place por superficie.
- Recetas sin artículo elaborado (`manufacturedProduct` null) → precio no editable ("—", ya implementado en P2).

## Verificación al terminar
- `php artisan test --compact` verde. `vendor/bin/pint --dirty` + `npm run build`.
- Manual: poner un artículo en margen 40% desde el catálogo/matriz; ver el badge y el precio = costo/0.6; que se
  mantenga al cambiar el costo (compra de reventa o cambio de costo de receta).
- Paso 4: `php artisan products:refresh-prices` sobre la base; cerrar memoria + CHANGELOG.
