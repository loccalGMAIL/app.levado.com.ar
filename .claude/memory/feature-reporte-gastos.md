---
name: feature-reporte-gastos
description: Reporte imprimible/PDF de gastos (v0.12.18) — arquitectura de plantilla única compartida, FixedCostReport, dompdf
metadata:
  type: project
---

# Reporte de gastos imprimible (v0.12.18)

Rama `v0.12.18-impresion-gastos`, creada desde `origin/master` (0.12.17) — **no** desde
`v0.13.1/produccion`, que se frenó para priorizar este fix. Da salida en papel a
[[feature-dashboard]]/gastos fijos: hasta acá no había ninguna forma de imprimir ni exportar nada
del módulo, con `FixedCostHistory` (histórico mensual, v0.12.17) ya listo para alimentar un reporte.

## Qué se agregó
- `fixed-costs/report` (vista imprimible, `window.print()`) y `fixed-costs/report/pdf` (descarga),
  ambas GET en el grupo de lectura, **antes** del binding `fixed-costs/{fixedCost}/...` en
  `routes/web.php` (mismo motivo que `fixed-costs/history`: si no, `report` se toma como un id).
- Modal `fixed-costs/modals/report.blade.php`: elige un **rango de fechas** (dos `<input
  type="date">`, Desde/Hasta — mismo patrón que `variable-expenses/index.blade.php:80-89`, no
  `x-month-select`) y qué secciones incluir (vigentes, histórico mensual, detalle por gasto, gastos
  variables del período — opcional a pedido del usuario). Un checkbox "Aplicar los filtros de la
  pantalla" es **explícito**, no automático: un reporte que omite gastos por un filtro olvidado es
  un reporte que el contador firma mal.
  - **Primera versión** (implementación base) usaba un selector de mes (`x-month-select`) + una
    cantidad de meses hacia atrás (6/12/24). Se reemplazó por el rango de fechas a pedido explícito
    del usuario: más simple de leer, y habilita que la sección de variables use granularidad de día
    en vez de forzar el mes calendario completo (ver abajo).
- Botón «Imprimir» en `fixed-costs/index.blade.php` e `history.blade.php`, fuera de
  `@can('manage-costs')` (mismo acceso que la ruta — cualquier usuario del tenant ya ve los montos
  en el listado).

## Arquitectura — una sola plantilla, no dos
`resources/views/fixed-costs/report/{_styles,_document,screen,pdf}.blade.php`. `_styles` y
`_document` son **CSS vanilla + tablas**, compartidos íntegro por la vista de pantalla y el PDF.
Decisión deliberada: dompdf no soporta Tailwind (ni flex/grid/custom properties), así que dos
plantillas divergirían en silencio — nadie mira el PDF hasta que un cliente lo imprime mal. El
costo se paga una sola vez, en `_styles.blade.php`: todo bajo `.levado-report` con cada propiedad
declarada explícita (el preflight de Tailwind resetea bordes/márgenes en pantalla; en el PDF no hay
preflight — declarar todo a mano hace que ambas salidas se vean igual sin depender de qué reset esté
activo alrededor). Paleta calcada a mano de `tailwind.config.js` (`corteza #3D2B1F`, `masa-madre
#6B5B45`, `miga #F2EAD8`, `harina #FAF7F2`, `horno #C8622A`) porque Tailwind no está disponible ahí.

**La vista recibe sólo arrays y escalares, nunca un modelo Eloquent** (regla no negociable): el
proyecto corre con `Model::preventLazyLoading()`, y una excepción por lazy load dentro del render
de dompdf deja un PDF corrupto en vez de un error legible.

## `App\Services\FixedCostReport` — único dueño del armado
`build(Tenant, array $options): array` recibe `from`/`to` (fechas exactas, no un período+cantidad de
meses) y compone 4 secciones sin duplicar SQL de los servicios ya existentes. Internamente deriva
`$fromMonth`/`$toMonth` (`startOfMonth()` de cada extremo) y `$months = diffInMonths(fromMonth,
toMonth) + 1` — `months` es un valor **calculado**, no un parámetro que el usuario elige.
- `current` (la foto de "vigentes") se calcula **al cierre del rango** (`$toMonth`, no un período
  propio): mismo `when(search)`/`when(status)` que `FixedCostController@index`, + monto real vía
  `FixedCostHistory::amountsForPeriod()` (no `monthly_amount` del modelo, que es sólo el vigente).
- `monthly` enumera directamente `[fromMonth, toMonth]` (antes: `months` hacia atrás desde un
  período ancla). **No reusa** `FixedCostHistory::monthlyTotals()`: esa ventana está anclada a
  `Carbon::now()` (útil para un dashboard), no al rango elegido. Reusa `totalForPeriod()` mes a mes
  en su lugar. Trampa real encontrada en desarrollo (primera versión, con período+meses): reusar
  `monthlyTotals()` + un filtro posterior `<= period` recorta mal la serie para cualquier período
  que no sea el mes actual.
- `details` — método `FixedCostHistory::timelinesFor(array $ids, Carbon $until, int $months)`
  (`$until` = `$toMonth`, `$months` = el derivado): timelines de N gastos en una sola query
  (`whereIn` + `groupBy` en memoria) en vez de repetir `timelineFor()` gasto por gasto (N+1). El
  cálculo de `change_pct` se extrajo a un `private` compartido (`withChangePct()`) para que
  `timelineFor()` y `timelinesFor()` no diverjan. Tope de **50 gastos** (los de mayor monto), con
  footnote si se trunca.
- `variable` — usa `from`/`to` **tal cual**, sin bordear a mes calendario (`$tenant
  ->variableExpenses()->between($from, $to)`; ver `VariableExpense::scopeBetween`,
  [[feature-compras]] no lo toca). Con el diseño anterior (período+meses) esta sección forzaba el
  rango al mes calendario completo aunque `VariableExpense` tiene fecha propia por día — perdía
  precisión sin necesidad. El rango de fechas lo arregla de raíz. Ojo: el row necesita `name`
  explícito además de category/supplier/description — se olvidó en el primer borrador y el test de
  esta sección lo agarró (`assertSee` sobre el nombre del gasto, no sobre su descripción).

**Tope de rango**: 24 meses, validado a mano en `FixedCostReportRequest::withValidator()` (no con
la regla `after_or_equal:from`, para controlar el mensaje en español y compartir el mismo chequeo
con la validación de "hasta" no puede ser anterior a "desde"). Sin `from`/`to` en la request, el
default es 12 meses terminando hoy (`to = now()`, `from = to->subMonths(11)->startOfMonth()`) —
mismo alcance que tenía antes `months=12` por default, ahora anclado a fechas reales.

**Logo del tenant → data URI**, resuelto una sola vez en `logoDataUri()`, sirve para pantalla y PDF:
dompdf no acepta `Storage::url()` (necesita filesystem local o base64). Cae al nombre del negocio en
tipografía grande si no hay logo, o si es SVG/WebP (dompdf no los renderiza) o pesa más de 512 KB.

## dompdf
`barryvdh/laravel-dompdf` v3.1.x (soporta Laravel 13 vía `illuminate/support ^13.0`). Config en
`config/dompdf.php`: `default_font = 'DejaVu Sans'` (las fuentes core PDF no son UTF-8, rompen «ñ»,
«ó», «í»), `enable_remote` ya viene en `false` por defecto. `enable_php` se dejó en `false` a
propósito — se descartó la numeración de página vía `<script type="text/php">` para no habilitar
ejecución de PHP embebido en dompdf, aun siendo HTML 100% server-generado. En
`FixedCostReportController::download()`, sólo ahí, `ini_set('memory_limit','256M')` +
`set_time_limit(60)` — acotado porque `months`/`details` ya tienen tope duro en el FormRequest/service.

## Fix colateral: impresión rompía en toda la app, no sólo acá
`print:hidden` agregado a 4 elementos que están fuera del flujo normal y **nunca se ocultaban al
imprimir ninguna pantalla**: `components/pwa-install-banner.blade.php`,
`components/mobile-bottom-nav.blade.php` (el `<nav>` interno queda oculto por su propia regla, pero
el `<div class="sm:hidden">` contenedor no — y al imprimir el ancho suele caer bajo el breakpoint
`sm`), `components/flash-messages.blade.php`, y el banner de impersonación en
`layouts/app.blade.php`. El ocultamiento de `nav`/`aside` (el chrome normal del layout) queda
**scopeado a `screen.blade.php`** vía un `<style>@media print{...}</style>` propio de esa página, no
global — no hay otra pantalla con función de imprimir todavía.

## Tests
`tests/Feature/FixedCostReportTest.php`, 12 tests. Setup propio (`reportSetup()`) en vez de
reusar `ownerForFixedCost()` de `FixedCostCrudTest.php`: una función global declarada en otro
archivo de test no está garantizada disponible según el orden en que Pest cargue los archivos.
Trampa de factory: `FixedCost::factory()->for($category)` falla (`fixedCostCategory()` no existe,
Laravel adivina el nombre de relación por el nombre de la clase) — hace falta
`->for($category, 'category')` explícito, la relación real del modelo.

## Versión
0.12.18. `package-lock.json` venía desincronizado de `package.json` **desde antes** de este cambio
(0.12.16 vs 0.12.17) — el bump a 0.12.18 realinea los 4 lugares de una. Ver [[project-architecture]]
→ Convenciones.
