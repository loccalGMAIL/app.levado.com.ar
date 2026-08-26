---
name: decision-multi-sucursal
description: "Qué es por-negocio vs por-sucursal (Location): catálogo/código compartidos, stock por sucursal, precio único por negocio vía listas (per-sucursal diferido)"
metadata:
  type: project
---

# Alcance multi-sucursal (Location) — decisión 26/08/2026

Ratificado con el usuario al planear la Fase A del refinamiento de Artículos.
Ver [[domain-model-articulos]], [[feature-articulos-produccion]], [[feature-existencias]].

## Estado del modelo hoy
- **`Location` (sucursal)**: `name/address/city/is_default/active`, CRUD propio (`locations.*`). Cada negocio
  arranca con una "Casa Central" default (`Tenant::defaultLocation()` la crea si no hay).
- **Stock ES por sucursal**: `stock_levels`, `stock_movements`, `productions` tienen `location_id`. La pantalla
  de Stock tiene selector de sucursal (`StockController::getLocation()` → request `location_id` o default).
- **Operaciones aún escriben a la default**: Compras, Producción (`ProductionService`), y el stock de insumos
  en Ingredient/Packaging controllers usan `defaultLocation()`. **No hay "sucursal actual" global** (ni contexto
  ni middleware). El multi-sucursal es sólido para leer stock, parcial en operaciones.
- **Catálogo, código, precios, listas**: solo `tenant_id` (compartidos por todo el negocio).

## Decisión (qué es por-negocio vs por-sucursal)
- **Identidad del artículo** (nombre, tipo, receta, **código/EAN-13**): **por negocio, compartida**. El mismo
  artículo y el mismo código en todas las sucursales (estándar retail; no fragmentar el catálogo ni el POS).
- **Stock**: **por sucursal** (ya está).
- **Precio**: **único por negocio por ahora**, vía la lista default. **NO** se agrega precio/columna por sucursal.
- **Precio por sucursal = diferido, sin deuda de diseño**: el día que se necesite, se asigna **una lista de precios
  por sucursal** (`location.price_list_id` o pivote) y cada local resuelve *su* lista. `product_prices`
  (artículo × lista) **no se re-modela** — ya lo soporta. Por eso **las listas de precios siguen siendo un concepto
  de primera clase** (son el vehículo del precio por sucursal y por tipo de cliente); en la Fase A se mueve solo la
  *pantalla* (matriz → Artículos, gestión de listas → Administración), no el concepto.
- **Costo**: **por-negocio hoy** (costo de insumos y último costo de compra son tenant-wide). Si distintas sucursales
  compran a distinto precio, el costo real diferiría → **costo por sucursal es un tema más profundo, fuera de alcance**
  por ahora (anotado como posible futuro).

## Por qué importa para Artículos/pricing
- El EAN-13 de la Fase B va **compartido** (un código por artículo, escaneable en cualquier local).
- La Fase A puede consolidar el pricing en Artículos **sin** introducir per-sucursal, y sin cerrarse la puerta a
  hacerlo después.
