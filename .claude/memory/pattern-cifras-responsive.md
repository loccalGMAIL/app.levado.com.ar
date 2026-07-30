---
name: pattern-cifras-responsive
description: Cómo se evita que un monto de 8-9 dígitos se salga de una card — utilitarios .kpi-card / .kpi-figure, container queries y las trampas de contain (v0.12.6)
metadata:
  type: project
---
# Cifras grandes en cards — `.kpi-card` / `.kpi-figure` (v0.12.6)

Los montos en pesos de un cliente real llegan a **8 o 9 dígitos** (`$ 123.456.789,00`). A la
tipografía de un KPI eso no entra en una card, y el número se salía del borde arrastrando **scroll
horizontal a toda la página**. Arreglado en v0.12.6 en dashboard, kardex de existencias, detalle de
compra, Mi negocio y las cards de mobile de los listados.

## La medida que importa
Cada glifo de **JetBrains Mono avanza 0,6em**. De ahí sale todo el cálculo:

| cadena | glifos | ancho |
|---|---|---|
| `$ 123.456.789,00` | 16 | **9,6em** (230px a 24px) |
| `$ 123.456.789` (sin centavos) | 13 | **7,8em** (187px a 24px) |

Y el interior disponible por card, con la geometría real (sidebar `w-52` desde `sm`, `px-6 lg:px-8`):

| viewport | cols | interior |
|---|---|---|
| 375px | 2 | 113px |
| 640px | 2 | 144px |
| **1024px** | **4** | **136px** |
| 1280px | 4 | 200px |
| 1440px | 4 | 240px |

**El interior no crece con el viewport.** A 1024px, con el 4-up y el sidebar, la card es más angosta
que en un celular de 390px. Por eso una escala por breakpoints no sirve: tendría que ir
`2xl → lg → 2xl → base → 2xl` de mobile a desktop, que además de ilegible se rompe con cualquier
cambio de grid.

## El mecanismo
El tamaño se mide **contra el ancho de la card**, con un container query (`resources/css/app.css`):

```css
.kpi-card  { container-type: inline-size; }
.kpi-figure {
    --kpi-figure-min: 1rem;
    --kpi-figure-max: 1.5rem;
    font-size: var(--kpi-figure-max);   /* fallback sin soporte de cqi */
    font-size: clamp(var(--kpi-figure-min), 10cqi, var(--kpi-figure-max));
    overflow-wrap: anywhere;
}
```

`10cqi` = ancho de la card ÷ 10, que alcanza para los 9,6em del monto con centavos. Los límites se
ajustan por card con `[--kpi-figure-max:…]` / `[--kpi-figure-min:…]`.

## Tres trampas
1. **`@layer components`, no CSS suelto al final.** Fuera de la capa, Tailwind emite el override
   `[--kpi-figure-max:1.125rem]` **antes** que `.kpi-figure` y, con la misma especificidad (0,1,0),
   gana el valor por defecto y el override no hace nada. En `components` el orden de capas lo
   resuelve y los utilities siguen ganando.
2. **`.kpi-card` sólo en items de grid.** `container-type: inline-size` implica `contain: inline-size`,
   que prohíbe que el ancho del elemento dependa de su contenido: **un flex item con `shrink-0`
   colapsa a 0**. Donde el ancho viene del contenido, el arreglo va por breakpoints
   (`text-lg sm:text-xl`) y no por container query.
3. **Ancho, no tamaño de letra.** Bajar la tipografía no alcanza sola: a 113px de interior, 16 glifos
   necesitarían 12px de font. La card de importe tiene que **ganar ancho** (`col-span-2` mientras el
   grid sea de 2 columnas, o bajar a su propia fila con `flex-col sm:flex-row`); el clamp después
   ajusta lo que queda.

## Redes de seguridad
- `overflow-wrap: anywhere` en la cifra: si aparece un monto más largo que lo previsto, parte en dos
  líneas en vez de salirse.
- `flex-wrap` en las filas de ícono + píldora: la píldora baja en vez de desbordar, y así el arreglo
  no depende de las métricas exactas de Inter.
- `min-w-0` en la columna flexible de las cards de mobile de los listados: sin eso, `min-width: auto`
  impide que la columna ceda y un nombre o un importe largo empuja la badge fuera de la card.

## Cómo se verifica
No hay runner de JS en el proyecto, y esto es geometría: se mide con **Playwright sobre el marcado y
el CSS compilado reales**, comparando el ancho que necesita cada cifra contra el borde interior de su
card, en **320 / 375 / 390 / 414 / 640 / 768 / 1024 / 1280 / 1440 / 1920px** y replicando el sidebar
y los paddings de producción. El chequeo de que no quede desborde es
`documentElement.scrollWidth - clientWidth === 0` **en los 10 anchos**, no sólo en mobile.

Desde Pest sólo se puede fijar el marcado del que depende el arreglo (el utilitario en la cifra, el
`col-span`, el `title` con el valor exacto) — ver `MontosGrandesEnMobileTest`. Al escribir esas
anclas, **verificar que fallen contra un revert de la corrección**: un `assertSee` de un monto pasa
igual con y sin el arreglo.
