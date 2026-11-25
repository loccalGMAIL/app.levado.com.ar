---
name: feature-produccion-dos-etapas
description: Producción en dos etapas (amasado → cámara de leudado → horneado por cierre de cámara), v0.26.0–v0.28.0. Decisiones del usuario, modelo, servicios y gotchas.
metadata:
  type: project
---

Rama de prueba `ccr-0878ce74-hhg8xe` (sesión cloud, 2026-11-25) con las tres etapas juntas para que el usuario
las pruebe en local; el plan pedía ramas `v0.26.0-planilla-amasado` etc. Retoma producción tras el retiro de v0.23.0
(ver [[feature-planillas-pedidos]]).

## Decisiones del usuario (no reabrir)
- Pedidos = lo que sale ese día. Planilla de amasado del día D: con cámara → pedidos de D+1 − stock en cámara (piso 0);
  directos → pedidos de D. Orden del día: cierre de cámara → planilla → confirmar amasado.
- La cámara es una `Location` (`kind = proofing_chamber`), nunca default, una por negocio; el crudo es el mismo artículo base.
- Insumos SOLO en `DoughBatchService::confirm()` (por real + merma). Descartables al hornear (cierre); en renglones
  directos, al confirmar el amasado. La merma de amasado no mueve producto; la de horno es `Waste` en la cámara.
- Horneado por **cierre de cámara** (no por tanda): horneado = esperado (snapshot al abrir) − contado − merma.
- Registrar: owner/admin (`manage-costs` + middleware `role:super_admin,owner,admin`); ver: todos. Sin roles nuevos.
- Stock negativo permitido: nunca bloquear, solo avisar.

## Piezas
- `recipes.uses_proofing` / `max_proofing_hours` (guardado, sin uso hasta que haya lotes); `Product::usesProofing()`.
- `Tenant::proofingChamber()` (crea) / `existingProofingChamber()` (solo lee); `defaultLocation()` solo mira sucursales.
- `DailyDemand` (por artículo base, presentaciones × `recipeUnitsPerItem()`, no crea semanas vía `previewWeek()` en
  `OrderSheetWeekService` y `CustomerOrderWeekService`) → `DoughPlanner` (masas por sub-receta de primer nivel) →
  `DoughBatchService` → `ChamberClosingService`.
- `StockMovementType::Dough|Bake|Waste`; `WasteReason` con `stage()`; `RecipeExploder::explodeByStage()`.
- `reference_type` = `dough_batch` / `chamber_closing` (strings, no morph map). Anular = `reverseMovementsFor()`.
- Costo del traspaso cámara → terminado: el de la última entrada vigente del amasado a cámara.

## Gotchas
- Un botón `name="confirm"` dentro de un form tapa `confirm()` en los `onclick` inline: usar `window.confirm(...)`.
- Las vistas de impresión no pueden llevar hex (ThemeTokensTest): usar clases de `fixed-costs/report/_styles`.
  Ese test ya fallaba en master por `pedidos/print` y `reparto/print`.
- Alertas de stock bajo: la dedupe key lleva `:location:{id}` para ubicaciones que no son la default.

## Fuera de alcance / pendiente
Lotes/FIFO y alerta de horas en cámara, horneado por tanda, varias cámaras, ventas que descuenten terminado, reportes
de merma/rendimiento, rol de encargado, descartables por presentación, etapa de terminación para insumos. Antes de
usarlo en serio: correr la consulta de descartables con compras por tenant (riesgo de negativos en descartables).
