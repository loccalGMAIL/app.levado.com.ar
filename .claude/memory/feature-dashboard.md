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
- Gastos fijos con **2 decimales** (antes 0). Badge `x-semi-badge` en la tabla, sufijo `/ u` en
  precio, `Unit::short()` en el rinde.
- Selector de lista de precios (fallback a default si el param es inválido/ajeno/inactivo).

## Tests
`DashboardCachedCostTest` y `DashboardRentabilidadTest`. Se agregaron 4 anclas: `margin_filter=high`
y `=low` (assertan sobre el total del footer "de N recetas", no sobre todo el HTML), datos de
gráficos presentes, y estado vacío (sin recetas con margen, el bloque barras+dona no se renderiza —
"Sin datos suficientes" es inalcanzable porque vive dentro del `@if($topRecipesForChart)`).
