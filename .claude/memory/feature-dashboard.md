---
name: feature-dashboard
description: Rediseño gráfico del Dashboard (KPIs, gauge, barras, dona) — arquitectura, decisiones y trampas (v0.12.1)
metadata:
  type: project
---
# Dashboard nuevo — v0.12.1 (rama `v0.12.1/CambioDashboard`)

Rediseño gráfico de la pantalla de inicio (`/dashboard`): saludo, franja de alertas, 4 KPI cards
(recetas activas, gastos fijos, overhead/hora, utilidad promedio), gauge de rentabilidad,
bar chart de top recetas por margen, dona de distribución de costos, y la tabla de recetas con
edición inline de precio. Portado de la rama de maqueta `pruebaGrafica` y adaptado a las
convenciones actuales. Archivos: [DashboardController](app/Http/Controllers/DashboardController.php),
[dashboard.blade.php](resources/views/dashboard.blade.php),
[dashboard-charts.js](resources/js/dashboard-charts.js).

## Arquitectura de datos (dos caminos, a propósito)
- **La tabla** trabaja EXCLUSIVAMENTE sobre los caches `recipes.unit_cost` + `recipes.labor_hours`
  que mantiene `RecipeCostPropagator`: no carga líneas ni recalcula en PHP, y ordena/pagina/filtra
  en SQL. Costo/u = `unit_cost + labor_hours × overhead ÷ yield`. Ver [[project_status]] (v0.12.0).
- **Los gráficos** (gauge/barras/dona) necesitan el desglose por componente (ingredientes / mano de
  obra / descartables) que el cache NO guarda, así que se calculan aparte recorriendo las recetas
  activas una vez con `RecipeCostCalculator`. Los gráficos reflejan el catálogo COMPLETO; el
  `margin_filter` sólo afecta la tabla (por eso el nombre de una receta filtrada sigue apareciendo
  en el JSON del bar chart — no assertar `assertDontSee` sobre todo el HTML en tests).

## Trampa: división entera en SQLite (bug encontrado y corregido)
`marginPctSql` calculaba `(price - cost) / price * 100`. En **SQLite** (que usa la suite de tests)
con operandos enteros eso hace **división entera** → `90/100 = 0`, y el margen salía `0` para todas
las recetas: `margin_filter=high` (≥60%) no matcheaba ninguna y `low` (<20%) matcheaba todas. En
MySQL (producción) la división decimal andaba bien, por eso no se había detectado. **Fix:** multiplicar
por `100.0` (literal real) ANTES de dividir → `(price - cost) * 100.0 / price`, que fuerza aritmética
real en SQLite y es idéntico en MySQL. Beneficia también el orden por `margin_pct`. Regla general:
**toda división en SQL que deba dar decimales necesita un operando real (`* 100.0` o `CAST(... AS REAL)`)
o SQLite trunca a entero — y los tests corren en SQLite aunque producción sea MySQL.**

## Trampa: `@json([...])` con array literal multilínea (bug encontrado y corregido)
`@json([ 'a' => $x, ... ])` con el array literal escrito en varias líneas dentro del Blade **se
mal-parsea**: Blade se come el `]` de cierre y genera PHP inválido (`json_encode([... ) ?>`),
tirando `ParseError: Unclosed '['` y 500 en toda la pantalla (rompió 28 tests: los del dashboard +
Onboarding y TenantIsolation, que redirigen o pegan a `/dashboard`). **Fix:** armar el array en un
bloque `@php $chartData = [...]; @endphp` y pasar la **variable** a `@json($chartData)`. Regla:
**pasar siempre una variable a `@json`, nunca un array literal multilínea.**

## ApexCharts self-hosted (no CDN)
Se vendorizó ApexCharts vía **npm + Vite** (no `cdn.jsdelivr.net`), coherente con la postura
offline/PWA del proyecto (service worker, páginas de error autónomas). La inicialización vive en el
módulo `resources/js/dashboard-charts.js` (importado en `resources/js/app.js`), que hace early-return
si no está en el dashboard. Los datos PHP→JS viajan por un `<script type="application/json"
id="dashboard-chart-data">` con `@json`, y `dashboard-charts.js` los lee con `JSON.parse`. No queda
JS inline: el filtro de margen se cablea por listeners sobre `.margin-filter-btn` (data-filter).
Nota: `@vite` carga como `type="module"` (deferred), así que un `<script>` inline no-module NO vería
`window.ApexCharts` — por eso la inicialización DEBE ir en un módulo Vite, no inline. El
`@stack('scripts')` del layout quedó (infra reutilizable, ya nadie lo usa).

## Otros detalles del port
- Tipografía **Inter** en títulos/números/charts (se descartaron Playfair Display y DM Sans);
  JetBrains Mono sigue para montos. Escala de color `brown` agregada en `tailwind.config.js`.
- Gastos fijos con **2 decimales** (antes 0) — **revertido a 0 en v0.12.6, ver abajo.** Badge
  `x-semi-badge` en la tabla, sufijo `/ u` en precio, `Unit::short()` en el rinde.
- Selector de lista de precios (fallback a default si el param es inválido/ajeno/inactivo).

## v0.12.6 — los montos de 8-9 dígitos se salían de las KPI cards
`$ 123.456.789,00` mide 230px en JetBrains Mono a `text-2xl`, y la card del grid de 2 columnas deja
113px de interior en un celular de 375px: el número se pasaba 117px y arrastraba scroll horizontal a
toda la página. **No era un problema de mobile:** se salía en todos los anchos por debajo de ~1440px,
porque el interior disponible **no es monótono** respecto del viewport (a 1024px las 4 columnas dejan
136px, *menos* que un celular de 390px). Ver [[pattern-cifras-responsive]] para el mecanismo.

Lo específico del dashboard:
- **Gastos fijos y Overhead por hora toman `col-span-2` mientras el grid sea de 2 columnas**, con
  `order-3` / `order-4` para que Recetas activas y Utilidad promedio compartan la primera fila. Sin
  el `order` la auto-colocación deja media fila vacía arriba y abajo. En mobile son 3 filas en vez
  de 2: a media pantalla el monto no entra a ningún tamaño legible.
- **Los dos KPI de importe vuelven a 0 decimales** (revierte a propósito la decisión de v0.12.1):
  son 3 caracteres que a ese tamaño no sobran y en un total mensual los centavos no aportan. El
  valor exacto pasa al `title`. **El cambio es sólo del KPI** — tabla, Resumen operativo, detalle de
  receta y Mi negocio siguen con 2 decimales.
- Las filas de ícono + píldora llevan `flex-wrap` (la píldora baja en vez de salirse, y así el
  arreglo no depende de las métricas exactas de Inter). `↑ activas` se oculta en mobile y
  `↑ obj. 38%` se acorta a `↑ 38%`.
- Anclas en `MontosGrandesEnMobileTest`: el utilitario en la cifra, el `col-span` de las cards de
  importe y el `title` con el valor exacto. Verificadas contra un revert del arreglo — fallan.

## Tests
`DashboardCachedCostTest` y `DashboardRentabilidadTest`. Se agregaron 4 anclas: `margin_filter=high`
y `=low` (assertan sobre el total del footer "de N recetas", no sobre todo el HTML), datos de
gráficos presentes, y estado vacío (sin recetas con margen, el bloque barras+dona no se renderiza —
"Sin datos suficientes" es inalcanzable porque vive dentro del `@if($topRecipesForChart)`).

## v0.13.2 — Quick actions del saludo abren modales, no navegan (sesión 19/09/2026)
Los 4 botones de arriba del saludo ("+ Nuevo pedido", "⚡ Orden instantánea", "+ Nueva receta",
"Compra") abren los mismos modales `<x-crud-modal>` que sus páginas de origen (Producción, Recetas,
Compras) en vez de navegar al índice — mismo patrón que [[feedback-crud-modals]]. Detalle en
[[feature-articulos-produccion]] (producción) y en este archivo (recetas/compras, agregados después).

- **Los 4 botones comparten un solo `@can('manage-costs')`** y el mismo estilo (outline neutro,
  ícono SVG `w-3.5 h-3.5`, mismo padding). Antes "Nueva receta"/"Compra" eran links visibles a
  cualquier rol (incluido Viewer) y sólo navegaban al índice; ahora, al ser altas reales, quedan
  gateadas igual que en `recipes/index.blade.php` y `purchases/index.blade.php` — coherencia sobre
  visibilidad para Viewer.
- **Mobile: `grid grid-cols-2` fijo, no `flex-wrap`.** Con `flex-wrap` cada botón envuelve según el
  largo de su propio texto — "Nuevo pedido"/"Orden instantánea" quedaban solos en su fila y "Nueva
  receta"+"Compra" apareados, visualmente descompaginado. El grid 2×2 fuerza el mismo ancho para los
  4 (`w-full sm:w-auto` en cada botón); desde `sm:` el contenedor vuelve a `flex flex-wrap` de ancho
  natural.
- **Colisión de variable al reusar dos modales ajenos en la misma página**: `recipes/modals/create.blade.php`
  y `purchases/modals/create.blade.php` NO calculan su propio `$errorsInCreate` (a diferencia de los
  modales de producción, que sí lo hacen con un `@php` propio) — esperan que la página que los
  incluye ya lo haya definido, como hacen sus índices originales. Si el dashboard incluye ambos, no
  puede tener una sola `$errorsInCreate` (los dos modales la pisarían). Se resolvió calculando
  `$errorsInRecipeCreate`/`$errorsInPurchaseCreate` en el dashboard y pasándolos con
  `@include('recipes.modals.create', ['errorsInCreate' => $errorsInRecipeCreate])` (mismo truco para
  compras). Ver gotcha ampliado en [[feedback-crud-modals]].
- El modal de compra trae en cascada `suppliers.modals.quick-create` (su "+ Nuevo proveedor") y
  `$suppliers` (mismo query que `PurchaseController::index()`, sin extraer a servicio — es una sola
  línea, no ameritaba compartir método como sí se hizo con `destinationAndCatalogData()`).
- Los 3 stores (`recipes.store`, `purchases.store`, `production-requests.store`/`production-orders.instant.store`)
  redirigen a su propio `show()` en éxito, nunca de vuelta al dashboard — igual que cuando se abren
  desde su índice original, así que no hizo falta tocar los controllers de escritura.
