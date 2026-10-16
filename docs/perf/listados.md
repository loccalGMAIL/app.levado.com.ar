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

## Después (v0.17.1-listado-recetas-perf)

Mismas vistas y metodología, `?per_page=20`. Tiempo = mediana de 3 pedidos (Debugbar). Entre paréntesis, el cambio
contra «Antes». Los nodos incluyen ~23 del editor de precio que ahora está en el layout de todas las páginas.

| Vista                | Nodos        | HTML (bytes)         | Queries | Vistas Blade | Respuesta  | Teleports |
|----------------------|-------------:|---------------------:|--------:|-------------:|-----------:|----------:|
| `/recipes`           | 1.220 (−27%) | 350.178 (−44%)       | 11 (−1) |   221 (+25%) |     730 ms |   0 (−30) |
| `/products`          | 2.388 (−16%) | 644.448 (−18%)       | 17 (=)  |   363 (+80%) |     934 ms |   0 (−20) |
| `/packaging`         | 1.542 (−19%) | 484.694 (−36%)       | 12 (−1) |   295 (+92%) |     831 ms |         0 |
| `/ingredients`       | 1.529 (−14%) | 471.786 (−22%)       | 13 (−1) |   284 (−26%) |     769 ms |         0 |
| `/variable-expenses` | 1.571 (−14%) | 460.727 (−12%)       | 13 (=)  |   260 (+98%) |     803 ms |         0 |
| `/fixed-costs`       | 1.312 (−19%) | 370.861 (−23%)       | 11 (=)  |   222 (+23%) |     754 ms |         0 |
| `/purchases`         | 1.317 (−11%) | 369.900 (−16%)       | 11 (=)  |   225 (+116%)|     790 ms |         0 |
| `/stock`             |   783 (−28%) | 288.739 (−12%)       | 11 (=)  |   208 (+210%)|     674 ms |         0 |
| `/price-lists`       |    493 (−5%) | 186.541 (−7%)        | 9 (=)   |    94 (+38%) |     570 ms |         0 |
| `/labor-types`       |   514 (+5%)  | 191.326 (−2%)        | 8 (=)   |    108 (−12%)|     625 ms |         0 |
| `/production-orders` |   965 (+2%)  | 240.586 (−2%)        | 10 (=)  |    65 (+2%)  |     687 ms |         0 |
| `/products/history`  |   650 (+4%)  | 170.310 (+3%)        | 8 (=)   |    36 (+3%)  |     612 ms |         0 |

- `production-orders` y `products/history` no tenían filas en esta base: el cambio ahí es solo estructura fija.
- **El tiempo de respuesta no mejora:** con 20 filas queda igual en recetas y sube entre 4% y 18% en varias vistas
  (envases 703 → 831 ms, gastos variables 725 → 803, compras 709 → 790), con ruido de medición de unos ±50 ms. La
  cantidad de vistas Blade sube porque cada fila/celda de `x-data-table` es un componente. Con 200 filas en
  recetas el tiempo quedó igual que antes (con `<x-icon>` por ícono llegó a subir 29%). Lo que mejora es el HTML
  (bytes y nodos), no el tiempo de servidor.
- Ingredientes y envases: el modal «Reemplazar» ya no consulta ni renderiza la lista completa (1 query menos).
- Envases e ingredientes: la edición inline de costo/stock se define una vez (`inlineEdit`) en lugar de repetir ~30
  líneas de JS por celda, que era buena parte de los bytes.

## Cambios aplicados

- Un solo editor de precio compartido (`priceEditor`, en el layout): recetas, artículos, matriz y dashboard.
- `x-data-table` (una fila por registro, el CSS decide fila o card) en: recetas, artículos, envases, compras, stock,
  listas de precios, gastos fijos, gastos variables, órdenes de producción e historial de producción
  (ya estaban: ingredientes, mano de obra, notas de crédito). Se eliminó `x-responsive-table`.
- Íconos de las acciones por fila desde `public/icons/sprite.svg` (`<svg class="ico"><use href="{{ $iconSprite }}#i-…">`).
- Modales «Reemplazar» de recetas, ingredientes y envases: opciones bajo demanda.

## Fuera de este alcance

- `suppliers`, `locations`, `reparto/clientes`, `reparto/repartidores`, `admin/users` y `notifications`: no usan
  `x-responsive-table` (son grillas de cards o listas propias) y no se tocaron.
- La barra lateral y la barra inferior mobile siguen duplicando navegación (~190 nodos).
