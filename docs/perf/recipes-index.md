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

## Después (v0.17.1-listado-recetas-perf)

| Métrica                              | per_page=20 | Δ vs antes | per_page=200 | Δ vs antes |
|--------------------------------------|------------:|-----------:|-------------:|-----------:|
| Nodos DOM (app, ya inicializado)     |       1.386 |       −42% |        8.417 |       −56% |
| Nodos DOM (HTML crudo)               |       1.220 |       −27% |        8.347 |       −32% |
| HTML crudo (bytes)                   |     350.178 |       −44% |    1.890.497 |       −58% |
| `<template x-teleport>` (popovers)   |           0 |        −30 |            0 |       −306 |
| Queries                              |          11 |         −1 |           12 |         −1 |
| Vistas Blade renderizadas            |         221 |       +25% |        1.661 |       +35% |
| Tiempo de respuesta (Debugbar, mediana de 3) | 730 ms |      ≈ |  1,55–1,77 s |          ≈ |

**Meta no alcanzada:** con 20 filas el DOM bajó 42%, no más de la mitad. Quedan ~600 nodos de estructura fija de la
página (sidebar, barra inferior mobile, barra superior, modales de crear/reemplazar, TomSelect) que no dependen de las
filas y están fuera de este alcance. El costo por fila pasó de ~94 a ~39 nodos: con 200 filas la baja es de 56%.

**Costo del lado del servidor:** la primera versión usaba `<x-icon sprite>` (una vista Blade por ícono) y con 200 filas
el render subía ~29% (1,64 s → 2,12 s). Pasar los íconos a `<svg class="ico"><use></svg>` plano lo devolvió al nivel
de antes (1,55–1,77 s) y bajó las vistas de 2.676 a 1.661. Lo que queda de más son los componentes `x-data-table.row` y
`.cell` (un componente por celda).

**El sprite reduce bytes pero no nodos:** cada `<use>` es un nodo hijo del `<svg>` (+20 nodos con 20 filas). El
beneficio es el HTML más chico y el path definido una sola vez en un archivo cacheable.

Lo que cambió: un solo editor de precio en el layout (−30/−306 popovers), una sola fila por receta (antes card + fila),
íconos desde `public/icons/sprite.svg`, y las sub-recetas del modal «Reemplazar» se piden bajo demanda
(`recipes.semi-options`, 50 como máximo) en vez de ir en cada carga.
