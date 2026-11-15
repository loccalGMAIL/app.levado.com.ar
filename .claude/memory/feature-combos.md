---
name: feature-combos
description: "Combos (v0.22.0): tipo de artículo que agrupa otros con código y precio propios; sin stock propio; descuento listo para Ventas"
metadata:
  type: project
---

# Combos (v0.22.0, 15/11/2026)

Ver [[domain-model-articulos]], [[feature-existencias]].

## Decisiones del usuario (15/11/2026)
- El combo es **un Artículo** (`ProductType::Combo`), no una entidad aparte: hereda barcode único/EAN-13, listas de precios, políticas, categorías.
- **Sin stock propio**: se filtra de Existencias, Producción y Compras ("es el stock propio de cada artículo").
- Stock insuficiente de un componente: **se permite negativo**, igual que el resto del sistema ("por el momento").
- El descuento se deja **listo para Ventas**; hoy ninguna pantalla lo dispara. No se pidió salida manual ni combos en pedidos de reparto.

## Modelo
- `product_combo_lines` (combo_id → products cascade, product_id → products restrict, quantity en la unidad del componente, unique combo+product).
- `Product`: `isCombo()`, `comboLines()`, `comboMemberships()`, `scopeStockable()` (= no combo), `comboNamesUsingAsComponent()`.
- Costo: `currentCost()`/`fullCost()` suman componentes (null si falta alguno o no hay líneas); se autocargan `comboLines.component.recipe` (por `preventLazyLoading`), los listados lo eager-loadean.
- Combo siempre en unidad `u`. Componentes: cualquier artículo activo que no sea combo (incluye presentaciones). Mínimo: 2 líneas, o 1 línea con cantidad > 1.

## Servicios
- `ComboExploder::registerSale($combo, $qty, $location, $user, $referenceType, $referenceId)`: un movimiento `StockMovementType::Sale` negativo por componente, ordenados por id (locks), en una transacción; reason `Combo: {nombre}`; sin referencia apunta a `combo`/id. **Ventas debe pasar su propio renglón como referencia** para poder revertir con `StockService::reverseMovementsFor()`.
- `ComboExploder::availability()`: min(floor(stock/cantidad)) en una sucursal.
- `StockService::guardStockable()` aborta 422 cualquier movimiento o mínimo a nombre de un combo (punto único).
- `ArticlePriceRecalculator::recompute()` cascada a los combos que contienen el artículo.

## UI
- Pestaña `products.combos.index` (`resources/views/products/combos.blade.php`), **un solo modal** alta/edición (`modals/combo.blade.php`, estado `comboForm`), picker TomSelect que agrega renglón en `change`. Precio con `priceCell` y baja con `products.toggle-active` (reusados).
- `products.store/update` rechazan el tipo combo (update → 404). El Catálogo excluye combos; la Matriz los incluye.

## Pendiente
- Ventas/POS: llamar `registerSale()`. Combos en pedidos de reparto: no pedido.
