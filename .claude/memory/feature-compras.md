---
name: feature-compras
description: "Módulo de compras — tablas, flujos, servicios, estado de las fases, renglones sin cargo y notas de crédito"
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
- `is_bonus` (boolean, default false, v0.12.14): renglón sin cargo — obsequio, promo o muestra. **No es un cuarto estado**: es un matiz del renglón *aplicado* (ver «Renglones sin cargo»)
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

## Memoria de vinculación por proveedor (v0.12.7)

Hasta acá el vínculo vivía **sólo** en `purchase_lines.purchaseable_type/id`: la corrección
humana se perdía y cada factura dependía de que la IA volviera a acertar. El `raw_name` nunca
se consultaba contra el historial. Estaba anotado como diferido en este mismo archivo.

### Tabla `supplier_product_links`

`id, tenant_id, supplier_id, raw_name_normalized, raw_name_sample, purchaseable_type,
purchaseable_id, pkg_qty (nullable), times_confirmed, last_used_at, timestamps`

- Unique `(tenant_id, supplier_id, raw_name_normalized)` — la clave natural.
- `purchaseable_id` **sin FK**, igual que en `purchase_lines` (apunta a dos tablas).
- Modelo bajo `BelongsToTenant`. **Borrado físico**, contra la convención general: es un caché
  de decisiones, no registro histórico, y olvidar es una operación legítima del usuario.
- `pkg_qty` es el divisor **ya expresado en la unidad del catálogo**. El costo unitario NO se
  guarda: ese cambia en cada compra.

### `ProductLinkMemory`

`recall()` / `recallMany()` / `remember()` / `forget()` / `fold()`.

- `recallMany()` resuelve la factura entera en una query (una factura de 30 líneas no puede
  costar 30 queries). `recall()` delega en él.
- `fold()` = criterio de `SupplierMatcher::fold()` **más colapso de espacios**: las facturas
  imprimen `"HARINA  000   X 25KG"` con espaciado irregular. Duplicado a propósito respecto de
  SupplierMatcher — no hay acoplamiento de corrección entre las dos y ahora divergen.
- `recall*()` **valida pertenencia al tenant antes de devolver**: sin FK, un vínculo puede
  apuntar a un ítem borrado o ajeno, y un id inválido en los hidden inputs daría un match
  fantasma sin nombre. Incluye ítems **inactivos** a propósito (regla del proveedor inactivo).
- `remember()` conserva un `pkg_qty` viejo mientras el vínculo apunte al mismo ítem: la
  confirmación en masa no manda divisor, y sin eso el primer clic en «Aplicar N sugerencias»
  borraría el que se cargó a mano.

### Dónde se lee y dónde se escribe

| Punto | Qué hace |
|---|---|
| `PurchaseScanController::scan()` | pisa `matched_type/id` de la IA con lo recordado (decisión humana > conjetura) |
| `PurchaseScanController::store()` | **vuelve a consultar** con el `supplier_id` definitivo |
| `PurchaseController::matchLine()` | `remember()` en la rama de match; `forget()` en «— sin asociar —» y en `'excluded'` |
| `PurchaseController::applyLineSuggestions()` | `remember()` por cada línea aplicada |
| `PurchaseLineRecorder::apply()` | prefiere el `pkg_qty` recordado sobre `parseDescPkgQty()` |

**Por qué `store()` no es redundante con `scan()`:** en la pantalla de revisión el usuario puede
cambiar el proveedor o crear uno nuevo, y los hidden inputs quedaron congelados contra el que
adivinó la IA. En `store()` el proveedor ya es el definitivo.

**Por qué `applyLineSuggestions()` también escribe:** sin eso la memoria sólo aprendería de las
correcciones una por una, y el camino más usado (aceptar en masa) no enseñaría nada.

### Invariante de la feature

**Un renglón recordado queda PENDIENTE, nunca aplicado.** La memoria pre-selecciona; ningún
costo ni stock se mueve sin confirmación. Hay un test-ancla dedicado. Si esto se rompe, un texto
ambiguo puede ensuciar costos y stock en silencio.

**El `'excluded'` olvida pero no recuerda.** La tabla de alias para consumo personal recurrente
sigue fuera de alcance (ver más abajo); recordar exclusiones acá rompería la invariante de los
tres estados.

### Divisor en la UI

- Hidden `pkg_qty` en `match.blade.php` (antes el form sólo mandaba `unit_cost` ya dividido).
- `matchRow()` recibe `remembered = { selection, pkgQty }`. Atado a la selección: el divisor es
  del ítem, no del renglón, y `onSelect()` lo descarta al cambiar de ítem.
- Rótulo **↻ verde** distinto del **✦ ámbar**: el ✦ significa «detectado en la descripción» y
  mentiría sobre algo que confirmó el usuario.
- **Efecto no obvio:** `applyLineSuggestions()` salteaba **siempre** los renglones de unidades
  incompatibles (`apply()` abortaba 422). Con divisor recordado los resuelve. Es el mayor
  ahorro de clicks de la feature.

### Backfill

`php artisan purchases:backfill-product-links [--dry-run]` — sólo renglones con
`cost_applied_at` (decisiones consumadas; un pendiente puede ser una sugerencia de IA que nadie
confirmó). Ante textos repetidos gana la imputación más reciente. Deja `pkg_qty` en null: no
está persistido en ningún lado y derivarlo sería recrear el fallback que ya existe.

## Unidad y precio unitario en la carga (v0.12.12)

`unit_price` se guarda **por `purchase_unit`**. El select de unidad estaba desconectado del
resto del formulario en las tres pantallas de carga, así que cambiarlo dejaba el precio en la
unidad vieja sin ninguna señal — y `PurchaseLineRecorder::apply()` propaga eso al costo del
insumo con el factor de conversión encima (kg→gr son 1000×).

### Piezas

| Archivo | Rol |
|---|---|
| `resources/js/units.js` | `UNIT_CONV` / `UNIT_ALIASES` + `convertAmount()`, `convertUnitPrice()`, `compatibleUnits()`. Salieron de `purchases/match.js`, que ahora importa de acá |
| `resources/js/purchases/line-form.js` | `window.purchaseLine()`: unidad, cantidad, precio, IVA, percepción y totales del renglón |
| `resources/views/components/unit-price-hint.blade.php` | El cartel de conversión |
| `tests/Unit/UnitConverterJsParityTest.php` | Ancla PHP↔JS |

Las tres vistas que lo usan: `modals/add-line`, `modals/edit-line`, `scan/review`. En
`match.blade.php` **no** hay selector de unidad (`match.js` recibe `purchaseUnit` fijo).

### Decisiones que no se deducen solas

- **La conversión no se aplica sola.** Cambiar la unidad puede significar «me equivoqué de
  unidad» (el número está bien) o «reexpreso la medida» (el número tiene que cambiar). Se
  ofrece y decide el usuario; automatizarlo rompe uno de los dos casos.
- **Sólo se convierte el precio, nunca la cantidad**, por lo mismo.
- **`purchaseLine()` reemplazó tres copias** del mismo objeto Alpine. Antes de tocar el
  cálculo de IVA de una vista, mirar que ahora es compartido.
- **La tabla JS duplica `UnitConverter` a propósito** (el form convierte sin ida y vuelta al
  servidor). El test de paridad es lo único que evita que se desincronice al crecer el enum
  `Unit`; si se agrega una unidad, hay que tocar los dos lados.
- El default de unidad en el alta manual es **kg**. Antes ninguna opción estaba marcada y el
  browser caía en `gr`, el primer caso del enum.

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

**Ojo:** la memoria de vinculación de la v0.12.7 **no** cubre el primer punto. Recuerda a qué
ítem del catálogo corresponde un texto, no que un texto sea consumo personal — `matchLine()`
*olvida* el vínculo al excluir. Autosugerir exclusiones sigue siendo trabajo aparte.

### Renombrado a «No es un insumo» (v0.12.15)

Facturas con un renglón de "Servicios administrativos" (u otro cargo del proveedor que tampoco
es insumo, pero tampoco es consumo personal del titular) no tenían dónde ir: la única opción,
«Consumo personal», mentía sobre el motivo.

**No cambió nada de datos ni de comportamiento** — sigue siendo el mismo `excluded_at`/
`exclusion_note`, el mismo centinela `PurchaseController::EXCLUDED_MATCH = 'excluded'`, la misma
invariante de los tres estados. Sólo se renombró la etiqueta en toda la UI para que cubra
cualquier concepto de la factura que no es un insumo, no sólo el personal:

- Select/badge: «Consumo personal» → **«No es un insumo»** (`match.blade.php`, `show.blade.php`).
- Badge compacto (tabla/card): «Personal» → **«Sin insumo»**.
- Botón: «Marcar como personal» → **«Confirmar»**.
- Mensaje flash: «Renglón marcado como consumo personal.» → **«Renglón marcado como "no es un
  insumo".»**
- Placeholder de la nota: ahora sugiere «servicios administrativos, consumo personal…» en vez de
  sólo el ejemplo de consumo personal.
- Comentarios actualizados en `PurchaseController`, `PurchaseLine::isExcluded()`,
  `NotificationService`, `ProductLinkMemory` y `resources/js/purchases/match.js` — todos decían
  literalmente "consumo personal" en el docblock, lo que habría confundido a quien lo lea después
  de este cambio si no se tocaban.
- Nombres de test **no** se tocaron (siguen diciendo "consumo personal" en varios `tests/Feature/
  Purchase*`, `StockPurchaseIntegrationTest`, `ProductLinkMemoryTest`): describen el caso de uso
  histórico que motivó la feature, no la etiqueta actual de la UI, y ningún test asertaba el
  string exacto del label (se verificó antes de renombrar).

## Renglones sin cargo / bonificaciones (v0.12.14)

**El problema:** las distribuidoras mandan mercadería de regalo, que en la factura viene a $0. Asociarla al insumo hacía que `apply()` imputara ese $0 como costo nuevo, lo propagara a todas las recetas y les tirara abajo el precio de venta. El cliente lo esquivaba dejando esos renglones **sin asociar**, y así la mercadería nunca entraba al stock.

**La solución** es el espejo de «consumo personal»: aquel resuelve el renglón sin imputar **nada**; éste **sí suma stock y no toca el costo**.

### Modelo

`is_bonus` convive con `purchaseable_id` y `cost_applied_at`, y nunca con `excluded_at`. Los tres estados del renglón (pendiente / personal / aplicado) **no cambiaron**: un renglón bonificado es un renglón *aplicado* cuya aplicación no imputó costo. Mantenerlo así evitó reescribir el índice, el contador de resueltos y `isResolved()`. `PurchaseLine::isBonus()`.

### Imputación

`PurchaseLineRecorder::apply()` ramifica sobre `is_bonus`: saltea `applyIngredientCost()`/`applyPackagingCost()` completos (price log, `update` de `cost_per_unit`/`cost_per_package`, propagación y `raiseCostSpike`) y tampoco toca el ítem en memoria. La aritmética de conversión de unidades y subdivisiones es la misma de siempre: depende de las cantidades, no del precio.

El movimiento se registra con `StockMovementType::Bonus` y se valúa al `cost_per_unit` **vigente del ítem**, no al $0 de la factura. Ver [[feature-existencias]].

### El divisor tiene que venir del formulario

Con unidades incompatibles (`u` → `kg`, «ACEITE X 5 LTS»), el camino normal es `applyWithCost()`, que deriva cuánto trae el bulto como `unit_price / unitCost`. **Con precio $0 eso da cero y la línea no registraba stock.** Por eso `apply()` tiene el parámetro `pkgQtyOverride`, que gana sobre el divisor recordado y sobre el parseado de la descripción; `matchLine()` se lo pasa desde el `pkg_qty` que el form ya enviaba. Las bonificaciones nunca pasan por `applyWithCost()`.

### Auto-detección

Vive en `storePending()` (`resolveBonus()`), que es por donde pasan los tres caminos de alta — escaneo con IA, alta manual y revisión previa —: un `unit_price` en cero pre-marca el renglón. **Lo que mande el formulario siempre gana** (hay facturas que ponen el precio y descuentan el 100%). En los modales el checkbox va precedido de un `<input type="hidden" name="is_bonus" value="0">`, porque un checkbox destildado no viaja y sin el 0 explícito el servidor volvería a inferir por precio.

### UI

- `purchases/match.blade.php`: tilde «Sin cargo» dentro del bloque de cálculo. El costo unitario se deshabilita, el divisor **sigue visible** cuando hace falta, y el `:disabled` del botón pasó a `(!isBonus && unitCost <= 0)` — una bonificación tiene costo 0 legítimamente. El campo viaja como hidden bindeado (`(isBonus && !isExcluded) ? 1 : 0`), no como checkbox con `name`: el bloque se oculta con `x-show` pero sigue en el DOM.
- Badge **violeta** «Sin cargo» en `purchases/show` (tabla y cards) y en el renglón aplicado de `match`, en lugar del check verde. El verde significa «se imputó un costo», y acá no se imputó ninguno. Mismo violeta que el badge del kardex.
- `add-line` y `edit-line` tienen el mismo tilde, con `onPriceInput()` en `purchaseLine()` (`resources/js/purchases/line-form.js`) que pre-marca mientras el usuario no lo haya tocado a mano.

### Lo que NO hace

- No revierte el costo ya imputado si un renglón aplicado pasa a sin cargo (sí contramueve su entrada de stock). Misma política que «Desasociar».
- `applyLineSuggestions()` no acumula los ítems bonificados en la lista de tocados: sin costo nuevo no hay nada que propagar.
- No hay backfill de los renglones históricos que el cliente dejó sin asociar. Se resuelven a mano desde la pantalla de asociación, que ahora los propone sola como sin cargo por venir a $0.

## Notas de crédito de compra (v0.12.15)

**El problema:** dos casos reales del cliente sin forma de registrarse — una distribuidora facturó mercadería que nunca llegó, y en otro caso reconoció por escrito la rotura de insumos en el transporte. La única salida hasta acá era borrar la compra entera (se pierde la factura) o un ajuste de stock a mano sin documento de por medio.

### Tablas nuevas

`credit_notes`: `id, tenant_id, supplier_id, purchase_id (nullable, nullOnDelete), note_number (nullable), note_date, notes, timestamps`. Unique `(tenant_id, supplier_id, note_number)`, mismo criterio que `purchases_tenant_supplier_invoice_unique`.

`credit_note_lines`: `id, credit_note_id, purchase_line_id (nullable, nullOnDelete), description (nullable), quantity, unit, unit_price, iva_rate (default 0.21), subtotal, affects_stock (boolean default true), stock_applied_at (nullable), timestamps`.

- `purchase_line_id` null = renglón libre (reconocimiento económico puro, ej. la rotura ya ajustada por recuento). Con valor, ata la devolución al renglón que hizo entrar esa mercadería.
- `CreditNoteLine::affectsStock()` exige **las dos cosas**: `affects_stock` tildado **y** `purchase_line_id` presente. Sin renglón de origen no hay entrada que revertir, aunque el tilde esté marcado — el modelo lo garantiza, no sólo la UI.

### Cómo sale el stock — proporcional, no recalculado

**Decisión clave:** la NC **no repite** la cascada de conversión de unidades de `PurchaseLineRecorder::apply()` (bultos, subdivisiones, `UnitConverter`). En vez de eso, deriva la salida **proporcional a la entrada vigente** del renglón de compra:

```
salida = |entradaVigente.quantity| × (nc.quantity ÷ purchaseLine.quantity_purchased)
unitCost = entradaVigente.unit_cost   (snapshot, no el costo de hoy)
```

Sale gratis en corrección para bonificaciones y subdivisiones (una devolución de 1 de 2 maples de huevos devuelve exactamente 12 de los 24 huevos que entraron), y una devolución total deja el neto en cero exacto.

`CreditNoteLineRecorder::applyStock()` resuelve la entrada vigente con `StockService::activePurchaseEntryFor()` (pública a propósito, para no duplicar esa query) y aborta 422 si el renglón de origen no está aplicado, está excluido, o si la cantidad devuelta supera lo comprado.

### `StockMovementType::Return` — no es un contramovimiento

Tipo propio, con `reverses_movement_id` **siempre null**. Si fuera un contramovimiento de la entrada de compra, `activePurchaseEntryFor()` (que sólo mira `Purchase`/`Bonus`, sin cambios) dejaría de ver la entrada original como "activa" apenas se creara la devolución, y una edición posterior del renglón de compra (`recompute()` → `syncPurchaseLineEntry()`) volvería a registrar stock desde cero en vez de partir del neto ya descontado — duplicando mercadería. Con la devolución como movimiento propio, la entrada de compra sigue intacta para `syncPurchaseLineEntry()`, y el nivel de stock simplemente acumula ambos movimientos por separado. Test-ancla: *"editar el renglón de compra después de una devolución no duplica stock"*.

`StockService` generaliza `registerMovement()` a `PurchaseLine|CreditNoteLine|null $reference` (antes sólo `PurchaseLine`), con `referenceTypeFor()` mapeando la clase a `'purchase_line'`/`'credit_note_line'`. Métodos nuevos, espejo exacto de los de compra: `syncCreditNoteLineExit()` (idempotente igual que `syncPurchaseLineEntry()`) y `reverseCreditNoteLineExit()`.

### Invariante: el costo NO se toca

Igual que al desasociar un renglón de compra o marcarlo consumo personal: **la NC nunca imputa costo**. Sin price log, sin `update` de `cost_per_unit`, sin propagación a recetas. Una devolución no dice nada sobre lo que cuesta reponer el insumo.

### Controller / rutas

`CreditNoteController` calca la forma de `PurchaseController`: mismo patrón de `destroy()` (revertir el stock de las líneas aplicadas **antes** del `delete()`, dentro de `DB::transaction`, porque el cascade de la FK se lleva las líneas y con ellas la referencia). Rutas bajo `credit-notes.*`, mismos dos grupos de middleware que `purchases.*` (lectura para todos los roles, escritura para `role:super_admin,owner,admin`).

### UI

Sección nueva en el sidebar (*Existencias*, debajo de Compras). `purchases/show.blade.php` lista las notas de crédito de esa compra con el total acreditado — **sin** alterar `invoice_total` ni el neto de la factura, que tiene que seguir cerrando contra el papel. El kardex (`stock/show.blade.php`) linkea el movimiento «Devolución» a la nota igual que ya linkea las entradas de compra a su factura.

## `subdivisions` vacío ≠ `subdivisions = 1` (v0.12.16)

Feedback real de Panadería Orfano: un insumo que se usa entero (crema de leche, pote de 200 cc, sin subdividir) no se podía cargar — el campo «Unidades por envase» tiene `min:2` y escribir `1` bloqueaba el formulario. El caso **ya funcionaba** dejando el campo vacío (`subdivisions = null`: `unit = u`, `cost_per_unit` = precio del envase, sin `cost_per_package`); el problema era sólo de interfaz, en tres capas:

- `min="2"` **HTML nativo** en los cuatro modales (create/edit de `ingredients` y `packaging`) cancelaba el submit con el tooltip genérico del navegador, sin explicar la alternativa. Se sacó — la regla real sigue viviendo sólo en el FormRequest.
- `resources/views/ingredients/index.blade.php` no incluía `subdivisions`/`subdivision_label` en el `hasAny(...)` que decide reabrir el modal con errores (`packaging/index.blade.php` sí lo hacía). Un error de validación en ese campo cerraba el modal en silencio, con los datos perdidos.
- `lang/es/validation.php` tiene `'attributes' => []` global, así que el mensaje salía como *"El campo subdivisions debe ser al menos 2."* — se agregó `attributes()`/`messages()` en los cuatro FormRequests en vez de tocar el archivo global (evita pisar el rótulo distinto que usa cada pantalla: «envase» en ingredientes, «presentación» en envases).

**Decisión: `1` sigue sin ser un valor válido.** No se bajó `min:2` a `min:1`. `subdivisions` significa "en cuántas partes se divide el envase" — `1` no es una subdivisión, es la ausencia de una. Además `app/Console/Commands/FixIngredientSubdivisionCosts.php:123-125,144-145` (`ingredients:fix-subdivision-costs`) depende del invariante `subdivisions >= 2` para distinguir el caso A ("nunca dividido", `cost_per_unit == cost_per_package`) del caso B ("envase viejo"): con `subdivisions = 1` esa igualdad sería legítima y rompería el diagnóstico. Ver [[project-architecture]].

Los cuatro modales ahora rotulan el campo "(opcional)", explican en texto cuándo dejarlo vacío vs. cuándo completarlo, y muestran un aviso Alpine en vivo con botón "Vaciar" si el usuario tipea `0` o `1` — resuelve el caso antes del submit; el servidor queda como red de contención.

Lo que **no** se tocó, detectado pero fuera de alcance: `IngredientController::store()`/`update()` no limpian `subdivisions`/`subdivision_label` cuando `unit` deja de ser `u` (sólo limpian `cost_per_package`) — un insumo en `kg` puede quedar con `subdivisions` colgado sin que ninguna validación lo impida.
