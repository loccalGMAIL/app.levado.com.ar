# Rendimiento de los listados

Misma metodología y datos que [recipes-index.md](recipes-index.md), con `?per_page=20`.
Nodos = elementos del HTML crudo (sin Debugbar, `<script>` ni `<style>`).

## Antes (master @ 832167e)

| Vista                | Filas | Nodos | HTML (bytes) | Queries | Vistas Blade | Respuesta | Teleports |
|----------------------|------:|------:|-------------:|--------:|-------------:|----------:|----------:|
| `/recipes`           |    20 | 1.673 |      625.478 |      12 |          177 |    729 ms |        30 |
| `/products`          |    20 | 2.845 |      785.493 |      17 |          202 |    887 ms |        20 |
| `/packaging`         |    20 | 1.899 |      751.855 |      13 |          154 |    703 ms |         0 |
| `/ingredients`       |    20 | 1.770 |      605.856 |      14 |          383 |    969 ms |         0 |
| `/variable-expenses` |    20 | 1.829 |      524.030 |      13 |          131 |    725 ms |         0 |
| `/fixed-costs`       |    20 | 1.613 |      483.485 |      11 |          181 |    693 ms |         0 |
| `/purchases`         |    20 | 1.480 |      439.201 |      11 |          104 |    709 ms |         0 |
| `/stock`             |    20 | 1.080 |      327.832 |      11 |           67 |    649 ms |         0 |
| `/price-lists`       |     5 |   521 |      199.876 |       9 |           68 |    523 ms |         0 |
| `/labor-types`       |     8 |   491 |      196.143 |       8 |          123 |    637 ms |         0 |
| `/production-orders` |     0 |   942 |      245.025 |      10 |           64 |    699 ms |         0 |
| `/products/history`  |     0 |   627 |      165.743 |       8 |           35 |    622 ms |         0 |

## N+1

No se encontró ninguno: todas las vistas hacen entre 8 y 17 queries, constantes respecto de la cantidad de filas.
Los «duplicados» que marca Debugbar por prefijo de SQL son queries distintas:

- `ingredients`, `packaging`: listado paginado + lista completa sin paginar para el modal «Reemplazar».
- `products`: lista de precios por defecto + listas de precios activas.
- `stock`: ubicación por defecto + ubicaciones activas.

Lo único que sobra son las listas completas de los modales «Reemplazar» (ingredientes, envases, recetas), que se quitan
con los endpoints bajo demanda.

## Después

_Se completa al cerrar los commits._
