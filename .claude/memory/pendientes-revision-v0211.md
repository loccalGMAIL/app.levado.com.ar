---
name: pendientes-revision-v0211
description: Decisiones de producto que quedaron abiertas tras la revisión integral de código (v0.21.1) y que no se implementaron
metadata:
  type: project
---

# Pendientes de la revisión integral (v0.21.1, 15/11/2026)

La revisión corrigió lo que tenía una respuesta técnica única. Quedó **sin tocar**, a la espera de una decisión del usuario:

- **Horas de sub-recetas en el overhead**: `recipes.labor_hours` guarda sólo las horas directas de la receta; `Product::fullCost()` prorratea gastos fijos con ese valor. `RecipeExploder` sí suma las horas de las sub-recetas. Incluirlas sube el costo total (y los precios con política) de toda receta armada con semielaborados → cambia números que el usuario ve; no se cambió sin su OK.
- **`User::isSuperAdmin()`** toma el rol de cualquier membresía activa; no hay marca de "tenant HQ" en la base. Hoy el rol sólo se asigna desde el backoffice (cerrado en v0.21.1); atarlo a un tenant HQ requiere una columna o config.
- **Revertir el costo** al reasociar o borrar un renglón de compra mal imputado (hoy sólo se revierte el stock, por diseño documentado).
- Menores: IVA `0.21` literal en 3 lugares; `is_template` dormido; imágenes de escaneo huérfanas si se abandona la revisión; `verified` en rutas sin `MustVerifyEmail` (no-op); policies que sólo comparan tenant (el rol vive en el grupo de rutas).

**How to apply:** no implementar estos puntos por iniciativa propia; preguntar primero.
