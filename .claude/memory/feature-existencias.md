---
name: feature-existencias
description: "Módulo de existencias — ledger inmutable, StockService, integración con compras (incluye bonificaciones), edición inline en catálogos, orden por columnas"
metadata:
  type: project
---

# Módulo de Existencias (v0.9.0, act. v0.12.14 — 2026-09-01)

## Arquitectura
- **Ledger inmutable `stock_movements`:** cada movimiento (purchase, bonus, adjustment, count; production/sale/transfer reservados) con cantidad firmada en la unidad del ítem, costo snapshot, `reason`, `user_id`, `reference_type/id` (línea de compra) y `reverses_movement_id`. Nunca se edita ni borra: toda corrección es un contramovimiento.
- **Cache `stock_levels`:** saldo por tenant/sucursal/ítem + `min_quantity` + `unit_cost` (último costo de compra). Alerta visual si negativo o bajo mínimo (`hasAlert()`); el stock negativo está permitido.
- **`StockMovementType::Bonus` (v0.12.14):** entrada por renglón de compra sin cargo. Tener tipo propio es lo que evita que una bonificación pise `stock_levels.unit_cost` — esa escritura ya estaba condicionada a `Purchase` — y lo que hace que el kardex la distinga de una compra pagada. `activePurchaseEntryFor()` busca por los dos tipos, así un renglón que pasa de compra a bonificación revierte su entrada en vez de duplicarla.
- **`StockService` es el ÚNICO punto de escritura** (lock pesimista sobre la fila del cache). Métodos: `registerMovement`, `applyCount` (delta entre contado y cache; null si no hay diferencia), `registerAdjustment`, `syncPurchaseLineEntry` (idempotente, con `$type` para distinguir compra de bonificación), `reversePurchaseLineEntry`, `setMinQuantity`, `levelFor`.
- **Sucursal:** todo va a `Tenant::defaultLocation()` ("Casa Central", lazy). `StockController::resolveLocation()` acepta `?location_id` como costura para multi-sucursal.

## Integración con Compras
- `PurchaseLineRecorder::apply()` registra la entrada al imputar costo (convierte a la unidad del ítem con UnitConverter, maneja subdivisiones y pista de descripción).
- Editar/eliminar línea, eliminar compra o desasociar renglón revierte con contramovimientos exactos.
- **Renglón sin cargo (`purchase_lines.is_bonus`, v0.12.14):** obsequio/promo de la distribuidora. Entra al stock como movimiento `bonus` valuado al `cost_per_unit` **vigente del ítem** (no a $0: la mercadería vale lo mismo se haya pagado o no, y valuarla en cero haría mentir a la valuación de existencias). No imputa costo: sin price log, sin `update` de `cost_per_unit`, sin propagación a recetas y sin alerta de salto de costo. Ver [[feature-compras]].

## UI
- `/stock`: tabs insumos/descartables, stock, mínimo, valuación, alertas; modales ajuste/recuento (delta en vivo)/mínimo. `/stock/{tipo}/{id}`: kardex.
- **Columnas ordenables (v0.9.2):** Nombre, Stock actual y Mínimo se ordenan vía `sort`/`dir` (mismo patrón que el resto de las tablas del sistema). Como Stock/Mínimo viven en `stock_levels` (no en la tabla paginada de Ingredient/Packaging), `StockController::index()` hace un `LEFT JOIN` a `stock_levels` filtrado por `location_id` antes del `paginate()` — la unicidad `(tenant_id, stockable_type, stockable_id, location_id)` garantiza que el join no duplica filas.
- **Catálogos `/ingredients` y `/packaging`:** columna Stock con edición inline (patrón Alpine del costo inline): guardar un valor absoluto llama a `PATCH stock/{tipo}/{id}/level` (`stock.level.update`, JSON), que reutiliza `applyCount` → registra la diferencia como recuento. Ícono de reloj → kardex. Cards mobile: stock read-only con link "historial".
- Controllers de catálogo cargan `stockLevels` keyed by `stockable_id` (una query, sin N+1).

## Historia
- La rama `v0.9.0/feature/stock-insumos` (junio 2026) fue una primera implementación con modelos duplicados por entidad y columna mutable `stock_on_hand`; se descartó a favor de esta arquitectura. Solo se rescató la edición inline de stock en catálogos, re-implementada sobre `StockService`.
- **v0.9.2:** se eliminó la función "Merma" (quedaba redundante con "Ajuste" — mismo mecanismo de entrada/salida con motivo). Se quitó el caso `Waste` del enum `StockMovementType`, el botón/ruta/controlador/form request/modal; no había registros históricos de tipo merma, así que no hizo falta migración de datos. `stock_movements` y `StockLevel` no se tocaron (compartidos con Ajuste/Recuento/Compra).

- **v0.12.14:** se agregó el tipo `Bonus` para los renglones de compra sin cargo. No hizo falta migrar datos: los movimientos históricos siguen siendo `purchase`.

Ver [[project-roadmap]].
