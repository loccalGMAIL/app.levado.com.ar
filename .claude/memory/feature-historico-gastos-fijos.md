---
name: feature-historico-gastos-fijos
description: "Histórico mensual de gastos fijos (v0.12.17) — FixedCostHistory, grilla mensual, timeline por gasto, carry-forward, borrado con SoftDeletes, y por qué overheadPerHour() sigue sin tocarse"
metadata:
  type: project
---

# Histórico mensual de gastos fijos (v0.12.17 — 2026-09-06)

## Qué resuelve y qué NO resuelve

Antes de esta versión, `fixed_costs.monthly_amount` era el único monto: editarlo pisaba el
valor anterior. Existía `fixed_cost_logs` desde el alta del módulo (v0.2, mayo 2026) pero era
**write-only** — se escribía al crear/editar un gasto fijo y ninguna pantalla la leía.

- **Alcance elegido: consultar, no re-costear.** `Tenant::totalFixedCosts()` /
  `overheadPerHour()` siguen siendo la única fórmula que alimenta el costeo de recetas. El
  histórico responde "cuánto regía en tal mes" pero **no** se puede valuar una receta a un mes
  pasado todavía — ver "Puerta abierta" abajo.
- **La grilla del mes en curso SÍ es la carga real:** guardarla actualiza `monthly_amount` (y por
  lo tanto el overhead de hoy). Guardar un mes pasado sólo registra, no toca nada vigente.
- **Carry-forward:** un gasto sin monto propio en el mes vale el último cargado antes de esa
  fecha, marcado "arrastrado". Nunca hay que recargar un alquiler que no cambió.

## Modelo de datos

`fixed_cost_logs` pasó de `valid_from` (fecha suelta) a `period` (primer día del mes,
`unique(fixed_cost_id, period)`). Migración con backfill: agrupa por `(fixed_cost_id, mes de
valid_from)`, conserva el id más alto de cada grupo (el último cambio del mes es el que rigió al
cerrarlo), y recién ahí impone el unique.

**Trampa de MySQL en la migración** (`2026_09_06_120000_...`): el índice viejo
`[fixed_cost_id, valid_from]` sostiene la foreign key hacia `fixed_costs`. MySQL rechaza
`dropIndex` sobre el único índice que respalda una FK. Solución: crear el `unique(fixed_cost_id,
period)` nuevo **antes** de borrar el índice viejo (también empieza por `fixed_cost_id`, así que
la FK nunca se queda sin soporte). El error real si se hace al revés:
`Cannot drop index '...': needed in a foreign key constraint`. `down()` espeja el mismo orden
invertido.

**Trampa de `$dateFormat`:** `FixedCostLog` necesita `protected $dateFormat = 'Y-m-d';` en el
modelo. Sin esto, `Model::fromDateTime()` -lo que Eloquent usa para serializar un atributo con
cast `date` al guardar- **ignora el formato del cast** (`'date:Y-m-d'` no alcanza) y siempre usa
`getDateFormat()`, que por defecto trae hora (`Y-m-d H:i:s`). MySQL la trunca porque la columna es
`DATE`; SQLite (dynamic typing, los tests) la guarda tal cual con `00:00:00`, y entonces cualquier
comparación por string contra `'Y-m-d'` (como hace `FixedCostHistory::amountsForPeriod`) deja de
matchear en silencio. Mismo motivo por el que `FixedCostHistory::record()` pasa
`->toDateString()` en vez del objeto `Carbon` al array de búsqueda de `updateOrCreate()`: ese
array va a un `where()` plano que tampoco pasa por `fromDateTime()`.

## `App\Services\FixedCostHistory` — único dueño de la lectura por período

- `amountsForPeriod($tenant, $period)`: monto vigente de cada gasto en un mes, con `carried`.
  Subquery correlacionada (no window function — corre igual en MySQL y en el SQLite de tests).
- `totalForPeriod`, `monthlyTotals`, `timelineFor` (variación % contra el registro anterior),
  `record()` (el `updateOrCreate` idempotente por `(fixed_cost_id, period)`).
- `periodLabel()`: "Septiembre 2026" a mano, sin `translatedFormat()` — el proyecto no fija
  `Carbon::setLocale()` en ningún lado pese a `config('app.locale') === 'es'`, así que
  `translatedFormat('F Y')` daría meses en inglés.

**El invariante que cierra el modelo:** `FixedCostController::toggleActive()` registra el período
en curso al desactivar (monto `0`, "no aplicó desde acá") y al reactivar (el monto vigente). Sin
esto, `totalForPeriod()` seguiría contando el monto de un gasto ya desactivado, porque el
histórico no tiene noción propia de activo/inactivo — a diferencia de `totalFixedCosts()`, que
filtra por `active` en la query. Cubierto por test:
`FixedCostHistory::totalForPeriod($tenant, hoy) === $tenant->totalFixedCosts()` (para gastos que
sí pasaron por un toggle; uno desactivado a mano en base de datos sin pasar por el controller
queda contado igual en el histórico — comportamiento esperado, documentado en el test).

## UI

- `x-month-select` (componente nuevo, `resources/views/components/month-select.blade.php`):
  select de meses "YYYY-MM" con label en español. Reemplaza el `<input type="date">` "Vigente
  desde" de los modales de alta/edición (ahora "Mes de vigencia").
- `/fixed-costs/history`: grilla mensual, navegación `‹ mes ›` + select, inputs editables sólo
  para `manage-costs` (viewer ve montos de solo lectura). Banda de aviso cuando el período es el
  mes en curso.
- `/fixed-costs/{id}/history`: timeline de un gasto, variación % + barra CSS. Sin paginación (no
  hace falta `x-data-table`: pocos meses por gasto).
- Botón "Historial" junto a "Categorías"/"+ Nuevo gasto" en el índice; ícono de reloj por fila
  (sin texto — decisión explícita del usuario), visible a todos los roles, hacia el timeline del
  gasto.

## Borrado (soft delete)

`FixedCost` tiene `SoftDeletes` (`deleted_at`) — **primer uso del trait en todo el proyecto**, no
hay otro modelo que lo use. Antes no se podía borrar un gasto fijo ("borrarlo alteraría costos
históricos"); con el histórico ya separado en `fixed_cost_logs`, esa objeción cae.

- **Un solo punto no respeta el global scope de `SoftDeletes` a propósito**:
  `FixedCostHistory::amountsForPeriod()` usa `DB::table()` crudo (no Eloquent), así que un gasto
  borrado sigue devolviendo su monto real en los meses anteriores al borrado — es justo lo que
  hace que "no romper ningún cálculo" salga gratis, sin filtrar `deleted_at` en esa query.
- Todo lo demás que toca `FixedCost` es Eloquent puro y el scope se aplica solo, sin tocar código:
  `Tenant::totalFixedCosts()` deja de contarlo, el guard de
  `FixedCostCategoryController::destroy()` libera la categoría cuando todos sus gastos quedan
  borrados, y el `withExists(['fixedCosts'])` del onboarding (`AppServiceProvider`) puede volver a
  pedir el paso 1 si se borran todos los gastos fijos de un tenant — efecto secundario esperado.
- `FixedCostController::destroy()` registra un log en `0` para el mes en curso **antes** de
  `$fixedCost->delete()` (incondicional, no sólo si estaba activo) — mismo mecanismo que
  `toggleActive()` al desactivar, para que el invariante
  `FixedCostHistory::totalForPeriod($tenant, hoy) === $tenant->totalFixedCosts()` no se rompa
  nunca, sea cual sea el estado previo del gasto.
- UI: tercer ícono (`<x-icon name="trash">`) en la fila de la tabla, y un botón "Eliminar" de
  texto rojo chico en una segunda fila de la card mobile (para no competir visualmente con
  "Desactivar", que ya usa rojo). `confirm()` de JS nativo — mismo patrón que
  `variable-expenses`/`credit-notes`, sin modal de confirmación custom.
- No hay pantalla de "papelera"/restaurar: el dato no se pierde, pero recuperarlo hoy es manual
  (`FixedCost::withTrashed()`/tinker). Puerta abierta si hace falta en el futuro.

## Puerta abierta (explícitamente fuera de alcance de v0.12.17)

Valuar recetas/dashboard a un mes pasado: falta `Tenant::overheadPerHourForPeriod()` (divide
`FixedCostHistory::totalForPeriod()` por `productive_hours_month`) y pasar el período por los 5
call sites de overhead (`DashboardController:50`, `RecipePriceController:35`,
`BusinessController:19`, `RecipeShowViewModel:67`, getter Alpine en `recipes/show.blade.php:112`).
`FixedCostHistory` ya devuelve el total por período, así que el día que se decida es cablear, no
rediseñar.

Ver [[feature-existencias]] (mismo patrón de historial por entidad, aunque ahí es append-only y
acá es corrección por `updateOrCreate`) y [[project-roadmap]].
