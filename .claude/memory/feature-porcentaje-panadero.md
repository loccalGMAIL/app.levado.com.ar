---
name: feature-porcentaje-panadero
description: Porcentaje panadero en recetas (v0.20.0) - base harina marcada por línea, vista dedicada, escalado y columna % en la hoja imprimible
metadata:
  type: project
---

Harina total = 100%; cada componente se expresa como % sobre ella. Rama `v0.20.0-porcentaje-panadero`.

- **Dato**: `recipe_ingredient_lines.is_flour` (bool, default false). Sólo líneas de `Ingredient`; reventa-como-insumo y sub-recetas nunca son harina. `RecipeController::copy` lo copia.
- **Servicio** `App\Services\BakerPercentages::for($recipe, ?$flourGrams)`: devuelve `rows` (kind ingredient|product|subrecipe, grams, percent, scaled_quantity), `flour_grams`, `dough_grams`, `total_percent`, `has_flour`, `scale`. Sólo arrays/escalares (apto dompdf). Gramos vía `UnitConverter::toGrams()` (1 ml = 1 gr; `u` y insumos con `subdivisions` → null, fuera de la masa). Sub-recetas = un ingrediente más, no se explotan.
- **Vista** `recipes.baker` (`RecipeBakerController`), botón "% panadero" en el header de `recipes/show`. Alpine recalcula % en JS desde `grams`; el checkbox de harina hace `PATCH recipes/{recipe}/ingredient-lines/{line}/flour` (no propaga costos). Calculadora de escalado client-side (no persiste), "Imprimir escalada" → `recipes.print?flour=<gr>`.
- **Hoja imprimible**: `RecipePrintSheet::for($recipe, ?$flourGrams)` suma `percent`, cantidades escaladas y `baker{has_flour,scaled,flour_grams}`; columna % sólo si `has_flour`; `?flour=` (numeric > 0) en pantalla y PDF.

**Why:** leer la fórmula en %/escalar por harina o masa es la forma de trabajo del panadero.
**How to apply:** si se agregan tipos de línea a la receta, decidir si entran en `BakerPercentages`; no usar `json_encode` de `@js` en tests con `"` (se serializa como `"`).
- **v0.20.2**: `ingredients.grams_per_unit` (nullable); las líneas en `u` de un insumo con peso por unidad pesan `quantity * grams_per_unit` y entran en la masa. Sin él (o con `subdivisions`) siguen en null. El escalado muestra `u` con 1 decimal (`fmtScaled`).
- **Huevo sin peso cargado**: si el nombre del insumo contiene "huevo", en `u` y sin `grams_per_unit`, `BakerPercentages` asume 60 gr (`DEFAULT_EGG_GRAMS`).
