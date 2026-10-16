# Rendimiento de `/recipes`

Medición local con un tenant con datos reales (373 recetas, 96 sub-recetas), accedido como super admin,
`APP_DEBUG=true` con Debugbar. «Nodos» = elementos del DOM de la app (sin Debugbar, `<script>` ni `<style>`).
«Bytes» = HTML crudo que devuelve el servidor.

## Antes (master @ 832167e)

| Métrica                              | per_page=20 | per_page=200 |
|--------------------------------------|------------:|-------------:|
| Nodos DOM (app, ya inicializado)     |       2.398 |       19.311 |
| Nodos DOM (HTML crudo)               |       1.673 |       12.203 |
| HTML crudo (bytes)                   |     625.478 |    4.528.892 |
| Líneas de HTML                       |       6.164 |       44.619 |
| `<template x-teleport>` (popovers)   |          30 |          306 |
| Popovers vivos tras Alpine           |          34 |          310 |
| Íconos `<svg>`                       |         153 |            — |
| Queries                              |          12 |           13 |
| Queries duplicadas reales            |           0 |            0 |
| Vistas Blade renderizadas            |         177 |        1.228 |
| Tiempo de respuesta (Debugbar)       |       729 ms |       1,64 s |
| Memoria pico                         |        29 MB |        44 MB |
| Lighthouse mobile (Performance / DOM)|   _pendiente_ |   _pendiente_ |

Lighthouse requiere sesión en el navegador del usuario: lo corre él en DevTools y se completa acá.

## Queries duplicadas

Ninguna real. Debugbar agrupa por prefijo de SQL y marca falsos duplicados:

- `recipes` x2: el listado paginado y la lista completa de sub-recetas del modal «Reemplazar»
  (`RecipeController::index`, `$semiElaborateRecipes`). La segunda desaparece en el commit 4.
- No hay N+1: pasar de 20 a 200 filas suma 1 query (12 → 13).

Observación (no se corrige acá): el scope de tenant se agrega dos veces a cada query
(`tenant_id = N ... and tenant_id = N`). Inofensivo, solo ruido en el SQL.

## Segunda pasada (mismo patrón, fuera de este alcance original)

Modales «Reemplazar» con el `<select>` completo precargado en cada carga del índice:

- `resources/views/ingredients/modals/replace.blade.php` (lista completa de ingredientes en `IngredientController::index`).
- `resources/views/packaging/modals/replace.blade.php` (lista completa de envases en `PackagingController::index`).

## Después

_Se completa al cerrar los commits._
