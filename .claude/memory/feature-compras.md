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

### Fase 2 — Match e imputación (🔲 pendiente de UI)

Los métodos ya existen en `PurchaseController`:
- `matchLine()`: asocia un renglón con un insumo/envase y aplica el costo
- `applyLineSuggestions()`: aplica todas las sugerencias pendientes de la IA de una vez

Falta: definir dónde vive la pantalla de match (separada del detalle de compra).

## Vistas

### `purchases/index.blade.php`
- Tabla con filtros (proveedor, fecha desde/hasta), paginación 20
- Columna Total: si `purchase_price_includes_iva` → muestra `invoice_total`; si no → suma neta de subtotales
- Acciones: ojo (ver detalle), papelera (eliminar con confirm). Eliminar hace cascade en líneas + borra imagen del storage.

### `purchases/show.blade.php`
- Header: proveedor, fecha, total renglones (sin IVA), total factura (con IVA si existe), botón "Ver factura original" (modal con imagen servida por `purchases.invoice`)
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
