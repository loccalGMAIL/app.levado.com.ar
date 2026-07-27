---
name: feature-compras
description: "Módulo de compras — tablas, flujos, servicios y estado de las fases"
metadata:
  node_type: memory
  type: project
---

# Módulo de Compras

Rama: `feature/compras` — versión 0.7.1

## Tablas en BD

### `purchases`
`id, tenant_id, supplier_id, invoice_number, invoice_date, invoice_total (decimal 14,2 nullable), notes, invoice_image_path, timestamps`

- `invoice_total`: total con IVA tal como figura en la factura (capturado por la IA)
- La imagen se guarda en disco `public` bajo `purchases/{tenant_id}/`

### `purchase_lines`
`id, purchase_id, raw_name (nullable), purchaseable_type (nullable), purchaseable_id (nullable), quantity_purchased (decimal 10,4), purchase_unit, unit_price (decimal 14,4), iva_rate (decimal 5,4 default 0.21), subtotal (decimal 14,4), cost_applied_at (nullable), timestamps`

- `purchaseable_type/id`: match con `ingredient` o `packaging` del catálogo (nullable = pendiente)
- `iva_rate`: alícuota de IVA almacenada por renglón (0, 0.105, 0.21)
- `cost_applied_at`: null = pendiente de imputar; filled = costo ya aplicado al insumo
- **Precision fix:** `unit_price` y `subtotal` cambiados de `decimal(10,4)` a `decimal(14,4)` para soportar subtotales > $999.999 (ej.: 200 bolsas × $13.891 = $2.778.280)

## Servicios

### `InvoiceExtractor`
- Llama a Claude vision (Haiku 4.5) vía `Http::post` (sin SDK)
- Fase 1 = transcripción FIEL: copia cantidad/precio tal como figura, sin cálculos de IVA ni pack-math
- Prompt incluye catálogo de insumos y envases para sugerir match (`matched_type/matched_id`)
- Control de consistencia: `unit_price × quantity ≈ total del renglón` (evita confundir P.UNIT con Total)
- Maneja decimales AR (1.234,56) y US (1,148,785.38)
- Requiere `ANTHROPIC_API_KEY` en `.env`. Sin key → error amigable, no rompe.
- Modelo configurable con `ANTHROPIC_MODEL` (default `claude-haiku-4-5`)

### `PurchaseLineRecorder`
- `storePending()`: crea renglón sin imputar costo. Guarda `iva_rate`.
- `apply()`: imputa precio al insumo/envase (price log + propagación). Marca `cost_applied_at`.
- `recompute()`: edición — recalcula subtotal, actualiza `iva_rate`, re-imputa si ya estaba aplicado.
- `record()` = `storePending()` + `apply()` (alta manual: imputa al instante).

## Flujos

### Fase 1 — Captura (✅ completa)

1. `purchases.scan.create` → formulario de subida de foto/PDF
2. `PurchaseScanController@scan` → llama a `InvoiceExtractor` → muestra `scan/review.blade.php`
3. Vista de revisión: cabecera editable (proveedor, N° factura, fecha), tabla de renglones con checkbox de inclusión, cantidad, unidad, precio unit., **alícuota IVA** (select: 21%/10,5%/0%), IVA $ y Subtotal c/IVA calculados en Alpine
4. `purchases.scan.store` → `PurchaseScanController@store` → guarda compra + renglones con `storePending()` (incluyendo `iva_rate`)

### Fase 3 — UX: Selects con buscador y límite de decimales (✅ completa — v0.7.3)

**Tom Select** integrado en todos los selects del módulo:
- `purchases/modals/create.blade.php`: supplier select con `data-searchable`; el handler `supplier-created` usa `el._ts.addOption()` + `setValue()`.
- `purchases/scan/review.blade.php`: supplier select migrado de Alpine `x-for` a `@foreach` server-side; Tom Select inicializado con `x-init`/`$nextTick` y `supplierTs` guardado en Alpine data; handler `supplier-created` usa la instancia.
- `purchases/match.blade.php`: select de insumo/descartable por renglón, Tom Select via `x-init`/`$nextTick`, coexiste con `x-model` + `@change` (Tom Select dispara `change` en el native select).

**Límite de 4 decimales:**
- `data-maxdecimals="4"` en inputs de cantidad y precio en show, edit-line, scan/review y match.
- `step="0.0001"` corregido en todos los inputs de precio (era `step="0.01"` en algunos lugares).
- Listener global en `app.js`: evento `input` trunca el valor si supera los 4 decimales.

**CSS:** `app.css` importa `tom-select/dist/css/tom-select.default.css` con overrides para colores `corteza`, `horno`, `miga`.

### Fase 2 — Match e imputación (✅ completa — v0.7.2)

**Ruta:** `GET purchases/{purchase}/match` → `PurchaseController@match` → `purchases/match.blade.php`

**Flujo:**
1. Índice de compras: botón cadena (ámbar = pendientes, verde = todos aplicados)
2. Vista match: tabla con descripción, precio/u, select de insumo y costo unitario editable
3. Alpine.js calcula el costo reactivamente al cambiar el select:
   - Unidades compatibles (kg↔gr, L↔ml): auto-convierte, sin campo divisor
   - Unidades incompatibles (u → kg): campo divisor editable, pre-llenado del parser
4. Parser de descripción (`parseDesc()`): regex detecta "X N unidad" en el `raw_name` para sugerir el divisor (marcado ✦ en ámbar)
5. Submit envía `match` + `unit_cost` al controller
6. Controller usa `applyWithCost()` si hay `unit_cost`, sino `apply()` (conversión automática)

**Servicios:**
- `PurchaseLineRecorder::applyWithCost(line, unitCost)`: aplica costo explícito sin conversión de unidades

**Aplicación masiva:** botón "Aplicar N sugerencias de la IA" llama `applyLineSuggestions()` — solo aplica líneas con `purchaseable_id` set y unidades compatibles; las incompatibles quedan pendientes.

## Vistas

### `purchases/index.blade.php`
- Tabla con filtros (proveedor, fecha desde/hasta), paginación 20
- Columna Total: si `purchase_price_includes_iva` → muestra `invoice_total`; si no → suma neta de subtotales
- Acciones: ojo (ver detalle), papelera (eliminar con confirm). Eliminar hace cascade en líneas + borra imagen del storage.

### `purchases/show.blade.php`
- Header: proveedor, fecha, total renglones (sin IVA), total factura (con IVA si existe), botón "Ver factura original" (modal con imagen servida por `purchases.invoice`)
- v0.8.14: si la factura no tiene IVA (`$totalIva == 0`, todos los renglones con alícuota 0) el header oculta "Total factura (con IVA)" y omite el sufijo "(sin IVA)"; la columna IVA $ (por renglón y tfoot) muestra "—" cuando el monto es 0, igual que Percepción
- Tabla de renglones: Descripción, Cantidad, Unidad, Precio unit., Subtotal, **IVA $**, **Subtotal c/IVA** (calculados server-side desde `$line->iva_rate`)
- Acciones: lápiz (modal edit-line), papelera (elimina renglón)
- Form "Agregar renglón": incluye selector de alícuota IVA
- Modal edit-line: muestra card de discriminación IVA (selector + IVA $ + Subtotal c/IVA reactivo via Alpine)

### `purchases/scan/create.blade.php`
- Dropzone con preview de imagen, acepta JPG/PNG/PDF hasta 10 MB
- Spinner "Leyendo la factura…" mientras procesa

### `purchases/scan/review.blade.php`
- Cabecera editable con select de proveedor dinámico (Alpine + `x-for`)
- Modal quick-create de proveedor integrado: si la IA no matchea el proveedor, aparece "creálo acá" → abre modal → evento `supplier-created` actualiza el select y lo selecciona
- Tabla de renglones con 8 columnas: checkbox, descripción (editable), cantidad, unidad, precio unit., alícuota IVA (`name="lines[n][iva_rate]"`), IVA $ y Subtotal c/IVA (Alpine reactivo)

## Operativo

- `ANTHROPIC_API_KEY` requerida en `.env` de producción (Hostinger) para que funcione el escaneo
- `purchase_price_includes_iva` en `tenant_settings` (default `'1'`) controla si el índice muestra totales con o sin IVA
- Imagen de factura servida por `PurchaseController@invoiceImage` (evita 403 del symlink en dev)

## Compra manual con comprobante (v0.9.1)

El flujo manual (`purchases.modals.create` → `PurchaseController::store()`) ya existía en paralelo al escaneo con IA (sin match server-side; líneas se cargan a mano vía "Agregar renglón"). Lo único que faltaba era poder adjuntar la imagen del comprobante sin pasar por la IA — pensado para tickets manuscritos que el modelo de visión interpreta mal.

- `StorePurchaseRequest`/`UpdatePurchaseRequest`: campo `invoice` opcional (`file`, mismos mimetypes/10 MB que el escaneo).
- `PurchaseController::storeInvoiceImage()`: helper privado que reutiliza `InvoiceImagePreparer` (downscale) y guarda en `purchases/{tenant_id}/{uuid}.{ext}` — mismo patrón que `PurchaseScanController::scan()`, sin extraer una clase compartida (poca duplicación, solo 2 controllers).
- `store()`: si viene `invoice`, sube y setea `invoice_image_path` antes de crear.
- `update()`: si viene `invoice`, sube el nuevo archivo y borra el anterior del storage (reemplazo); si no viene, conserva el que ya había.
- Modales `create.blade.php` y `edit-purchase.blade.php`: input de archivo compacto (no dropzone completo, es un modal), reutiliza `window.compressInvoiceImage` (mismo compresor cliente que el scan) y el patrón `onPick` de `scan/create.blade.php`. `enctype="multipart/form-data"` agregado a ambos forms.
- `show.blade.php` no necesitó cambios: el botón "Ver factura original" ya era condicional a `invoice_image_path`, sin importar si vino de IA o de carga manual.
- Tests: `tests/Feature/PurchaseCrudTest.php` (no existía cobertura de `purchases.store`/`update` antes de esto).

## Renglones no imputables — consumo personal (v0.12.4)

Feedback real de Confitería Orfano: el dueño mete compras personales en la misma factura del proveedor de insumos. Antes esos renglones sólo podían quedar «— sin asociar —», que **es** el estado pendiente, así que la factura nunca llegaba a verde.

### Tres estados del renglón (mutuamente excluyentes)

| Estado | Cómo se reconoce | Imputa costo | Mueve stock |
|---|---|---|---|
| Pendiente | `! isResolved()` | no | no |
| Aplicado | `cost_applied_at` | sí | sí |
| Consumo personal | `excluded_at` | no | no |

- Columnas nuevas en `purchase_lines`: `excluded_at` (timestamp nullable) y `exclusion_note` (string 255 nullable). Aditivas, sin backfill.
- Helpers en `PurchaseLine`: `isExcluded()`, `isResolved()` (aplicado **o** excluido), `isPending()`.
- **Invariante:** un renglón excluido nunca conserva `purchaseable_id` ni `cost_applied_at`. La garantiza `matchLine()`, que escribe los tres campos juntos en un único `update()` — no depende del orden de las ramas.

### Flujo

El select de `match.blade.php` manda el centinela `'excluded'` (constante `PurchaseController::EXCLUDED_MATCH`), interceptado **antes** del `explode(':')`. No colisiona con un match real, que siempre viaja como `tipo:id`.

- Si el renglón ya estaba aplicado, se revierte su entrada de stock con contramovimiento vía `StockService::reversePurchaseLineEntry()` — igual que la rama de «— sin asociar —».
- **El costo del insumo NO se revierte** al excluir, igual que hoy no se revierte al desasociar. Sólo el stock. El historial de precios es append-only.
- Se audita como `purchase_line.excluded`.
- Asociar a un ítem del catálogo, o volver a «— sin asociar —», limpia `excluded_at` y `exclusion_note`.

### Cambio de semántica del contador (lo que no se deduce solo)

El indicador de completitud pasó de contar **aplicados** a contar **resueltos** = aplicados + excluidos. Si esto queda mal, la feature no sirve. Lugares afectados:

- `PurchaseController::index()`: `applied_count` → **`resolved_count`** (el `orWhere` del `withCount` va envuelto en closure).
- `purchases/index.blade.php`: ícono ámbar/verde y títulos («N renglón(es) sin resolver»).
- `purchases/match.blade.php`: contador `resueltos/total`, cartel «No queda ningún renglón por resolver».
- `purchases/show.blade.php`: banner, badge neutro «Personal» por renglón, y línea informativa `Consumo personal: $X` bajo los totales — **el total de la factura no cambia**, tiene que cerrar contra el papel.
- `NotificationService::syncUnappliedPurchases()`: la alerta «Compra sin imputar» ignora los excluidos (si no, una factura resuelta seguiría avisando).
- `applyLineSuggestions()`: `->whereNull('excluded_at')`.

### UI

Opción «Consumo personal — no es del negocio» en un `<optgroup>` aparte del catálogo. El componente Alpine (`resources/js/purchases/match.js`) expone el getter `isExcluded` y sale temprano de `recalc()`: se oculta todo el bloque de cálculo (divisor, costo unitario, hint ✦), aparece un input de nota opcional y el botón pasa a «Marcar como personal». Los renglones ya excluidos **siguen en la rama interactiva** (no en la estática de aplicados) para poder volver atrás desde el mismo select.

Badge **neutro** (`bg-miga`/`text-masa-madre`), deliberadamente **no ámbar**: el ámbar significa «pendiente» en todo el módulo.

### Fuera de alcance (decidido, no implementar sin pedido)

Tabla de alias para autosugerir renglones personales recurrentes; reporte mensual de retiros del titular; un cuarto destino para gastos del negocio que no son insumo (ferretería) con alta automática de `variable_expense`; distinguir si el consumo personal se pagó con plata del negocio; la opción de exclusión en el modal de edición de renglón de `purchases/show`.
