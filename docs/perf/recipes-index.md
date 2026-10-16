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
| HTML crudo (bytes)                   |     389.738 |       −38% |    2.261.059 |       −50% |
| Líneas de HTML                       |       3.345 |       −46% |       18.287 |       −59% |
| `<template x-teleport>` (popovers)   |           0 |        −30 |            0 |       −306 |
| Queries                              |          11 |         −1 |           12 |         −1 |
| Vistas Blade renderizadas            |         323 |       +82% |        2.676 |      +118% |
| Tiempo de respuesta (Debugbar)       |      737 ms |         ≈ |       2,12 s |       +29% |
| Memoria pico                         |       28 MB |         ≈ |        39 MB |       −11% |

**Meta no alcanzada:** con 20 filas el DOM bajó 42%, no más de la mitad. Quedan ~600 nodos de estructura fija de la
página (sidebar, barra inferior mobile, barra superior, modales de crear/reemplazar, TomSelect) que no dependen de las
filas y están fuera de este alcance. El costo por fila pasó de ~94 a ~39 nodos: con 200 filas la baja es de 56%.

**Costo del lado del servidor:** cada fila ahora son 6 componentes de celda/fila y 6 `<x-icon>`, por eso las vistas
Blade se duplican. Con 20 filas el tiempo no cambia; con 200 sube ~29% (1,64 s → 2,12 s con Debugbar activo). Si
`per_page=200` es un caso real, el siguiente paso es renderizar los íconos como `<svg><use>` plano (sin componente)
en vez de `<x-icon sprite>`.

**El sprite reduce bytes pero no nodos:** cada `<use>` es un nodo hijo del `<svg>` (+20 nodos con 20 filas). El
beneficio es el HTML más chico y el path definido una sola vez en un archivo cacheable.

Lo que cambió: un solo editor de precio en el layout (−30/−306 popovers), una sola fila por receta (antes card + fila),
íconos desde `public/icons/sprite.svg`, y las sub-recetas del modal «Reemplazar» se piden bajo demanda
(`recipes.semi-options`, 50 como máximo) en vez de ir en cada carga.
