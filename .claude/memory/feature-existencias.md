---
name: feature-existencias
description: "Módulo de existencias v0.9.0 — ledger inmutable, StockService, integración con compras, edición inline en catálogos"
metadata:
  type: project
---

# Módulo de Existencias (v0.9.0, 2026-07-10)

## Arquitectura
- **Ledger inmutable `stock_movements`:** cada movimiento (purchase, adjustment, waste, count; production/sale/transfer reservados) con cantidad firmada en la unidad del ítem, costo snapshot, `reason`, `user_id`, `reference_type/id` (línea de compra) y `reverses_movement_id`. Nunca se edita ni borra: toda corrección es un contramovimiento.
- **Cache `stock_levels`:** saldo por tenant/sucursal/ítem + `min_quantity` + `unit_cost` (último costo de compra). Alerta visual si negativo o bajo mínimo (`hasAlert()`); el stock negativo está permitido.
- **`StockService` es el ÚNICO punto de escritura** (lock pesimista sobre la fila del cache). Métodos: `registerMovement`, `applyCount` (delta entre contado y cache; null si no hay diferencia), `registerAdjustment`, `registerWaste`, `syncPurchaseLineEntry` (idempotente), `reversePurchaseLineEntry`, `setMinQuantity`, `levelFor`.
- **Sucursal:** todo va a `Tenant::defaultLocation()` ("Casa Central", lazy). `StockController::resolveLocation()` acepta `?location_id` como costura para multi-sucursal.

## Integración con Compras
- `PurchaseLineRecorder::apply()` registra la entrada al imputar costo (convierte a la unidad del ítem con UnitConverter, maneja subdivisiones y pista de descripción).
- Editar/eliminar línea, eliminar compra o desasociar renglón revierte con contramovimientos exactos.

## UI
- `/stock`: tabs insumos/descartables, stock, mínimo, valuación, alertas; modales ajuste/merma/recuento (delta en vivo)/mínimo. `/stock/{tipo}/{id}`: kardex.
- **Catálogos `/ingredients` y `/packaging`:** columna Stock con edición inline (patrón Alpine del costo inline): guardar un valor absoluto llama a `PATCH stock/{tipo}/{id}/level` (`stock.level.update`, JSON), que reutiliza `applyCount` → registra la diferencia como recuento. Ícono de reloj → kardex. Cards mobile: stock read-only con link "historial".
- Controllers de catálogo cargan `stockLevels` keyed by `stockable_id` (una query, sin N+1).

## Historia
- La rama `v0.9.0/feature/stock-insumos` (junio 2026) fue una primera implementación con modelos duplicados por entidad y columna mutable `stock_on_hand`; se descartó a favor de esta arquitectura. Solo se rescató la edición inline de stock en catálogos, re-implementada sobre `StockService`.

Ver [[project-roadmap]].
