---
name: plan-temas
description: Temas claro / oscuro / centeno con variables CSS — decisiones de peso, tokens, fases y estado
metadata:
  type: project
---

# Temas: claro, oscuro y centeno

## Decisión: variables CSS, no `dark:`
Cada color de `tailwind.config.js` es `rgb(var(--c-…) / <alpha-value>)` y los valores
(canales RGB) viven en `:root` dentro de `resources/css/app.css`. Un tema = un bloque
`[data-theme="…"]` que redefine las variables.

**Why:** las utilidades de color pesaban ~25,6 KB del `app.css` (80 KB). Con `dark:` +
una variante para centeno se duplicaban por tema (~+50 KB); con variables los tres temas
cuestan ~2 KB sin comprimir. Además no hay que tocar las ~3.600 clases de las vistas.
**How to apply:** nunca agregar colores con hex ni clases `dark:` para tematizar; agregar
el token a la config + la variable en cada tema.

## Decisiones tomadas
- Se mantienen los nombres de marca como **roles**: `harina` = fondo, `miga` = superficie
  alternativa/bordes suaves, `corteza` = texto, `masa-madre` = texto secundario,
  `horno` = acento, `membrillo` = acento 2. No renombrar (no ahorra bytes, diff enorme).
- Tokens nuevos (fase 2): `superficie` (← `bg-white`), `linea` (← `border-gray-300` y
  `gray-*`), `exito`/`peligro`/`aviso`/`info` con `-fondo`/`-borde` (← `red/green/amber/violet/sky-*`).
  `text-white` sobre acentos se queda.
- Tres temas en el mismo `app.css` (sin archivos por tema: un request más y parpadeo por <1 KB).
- Cero JS para aplicar: el servidor renderiza `<html data-theme>` según `users.theme`
  (nullable, `null` = automático vía `@media (prefers-color-scheme: dark)` entre claro/oscuro;
  centeno sólo explícito). Enum `App\Enums\Theme` (Claro, Oscuro, Centeno).
- Cambio sin recarga: componente Alpine que setea `data-theme` y hace `PATCH`.
- ApexCharts lee colores con `getComputedStyle` y escucha `theme-changed`.
- `color-scheme` por tema; `theme-color` meta renderizado por servidor; `@media print` fuerza claro.
- Mails y PDFs (`fixed-costs/report/_styles.blade.php` copia la paleta a mano) quedan en claro.

## Paletas iniciales (validar contraste AA)
| token | claro | oscuro | centeno |
|---|---|---|---|
| harina | #FAF7F2 | #17110C | #F2F3EF |
| superficie | #FFFFFF | #211912 | #FFFFFF |
| miga | #F2EAD8 | #2E241B | #E4E7DF |
| linea | #D1D5DB | #3D3126 | #CDD2C6 |
| corteza | #3D2B1F | #F2EAD8 | #262C2A |
| masa-madre | #6B5B45 | #BFAE96 | #5A6460 |
| horno | #C8622A | #E07A3F | #5E7A3A |
| membrillo | #E8A820 | #F0B83A | #B08A2E |

## Fases
1. ✅ Variables en config + `:root` con los valores actuales. Verificado sin cambio visual:
   se compila una página con todas las utilidades de marca usadas (old vs new config) y se
   comparan los estilos computados en Chromium (51 clases, 0 diferencias).
2. Codemod de colores crudos (~600 clases) y hex de `app.css` / `dashboard-charts.js` a
   tokens + test Pest que prohíba `bg-white`, `gray-*` y hex en vistas.
3. Temas oscuro y centeno, preferencia por usuario, selector, charts, theme-color, print.
4. QA visual (~10 pantallas × 3 temas) y contraste.

## Trampa del build
`public/build` está versionado. Para que el CSS salga igual al del equipo de desarrollo hace
falta `vendor/laravel/framework/src/Illuminate/Pagination/resources/views` (Tailwind las escanea).
En la nube `composer install` falla (api.github.com corta las descargas); alcanza con un sparse
clone de `laravel/framework` en el tag del lock y copiar esa carpeta a `vendor/`.

El content `./storage/framework/views/*.php` mete en el build las vistas compiladas de la
página de errores de Laravel (`dark:bg-[#1a1a1a]`, `neutral-*`, `rose-*`…): ~185 selectores
que la app no usa (~1,7 KB gzip). El build de la fase 1 se hizo sin esa caché y salió de
80 → 68 KB (14,8 → 13,1 KB gzip); si se vuelve a compilar en Windows con la caché, vuelven.
Pendiente decidir si se saca esa entrada del `content`.

## Track aparte (peso web, no hecho)
`app.js` (44 KB gzip) carga en todas las páginas módulos de una sola (compras, escaneo,
compresión de imágenes) → `import()` dinámico; Google Fonts self-hosted; recortar el CSS
default de tom-select (~9,5 KB).
