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
- Tokens nuevos (fase 2): `superficie` (← `bg-white`) y los **inversos** `tinta` (← `bg-corteza`:
  botones primarios, chips activos), `barra` (← `bg-masa-madre`: sidebar y barra mobile) y
  `sobre-barra` (← `text-harina`, sólo usado sobre la barra). Mismo valor que corteza/masa-madre/
  harina en claro, pero en oscuro no se pueden invertir con el texto: un botón `bg-corteza`
  quedaría claro con letra blanca. `text-white` sobre tinta/horno se queda literal.
- **Cambio respecto del plan original:** NO se consolidaron los estados en `exito/peligro/…` ni
  `border-gray-300` en `linea`. Las escalas de Tailwind que usa la app (`gray/red/green/amber/
  yellow/orange/blue/indigo/violet/sky`) son variables, limitadas a los tonos en uso
  (`scale()` en `tailwind.config.js`; un tono fuera de la lista no genera clase). El tema oscuro
  invierte las escalas (50 = fondo oscuro teñido, 700 = texto claro) y centeno, que es claro,
  hereda las de claro. **Why:** consolidar eran ~600 decisiones a mano con cambio visual para
  ahorrar ~1 KB gzip; con variables el cambio fue cero visual y cero codemod de estados.
- `white`/`black` quedan literales (overlays `bg-black/40`, `border-white/10` sobre la barra).
- `theme.colors` reemplaza la paleta completa (no `extend`): los colores de Tailwind que no se
  usan no existen.
- `@tailwindcss/forms` detecta `var(` y usa el gris por defecto para el chevron del select
  (data-URI); el fondo de los inputs que pone el plugin es `#fff` literal → pisarlo en fase 3.
- La vista de paginación de Laravel (vendor) usa `bg-white` y clases `dark:` con estrategia
  `media`: hoy ya se oscurece con el SO en modo oscuro. En fase 3: `darkMode` por selector
  `[data-theme="oscuro"]` o publicar la vista con tokens.
- Test de prevención: `tests/Unit/ThemeTokensTest.php` (sin `bg-white`, sin inversos con nombre
  de texto, sin hex en vistas/JS/app.css, todo token usado definido en `:root`).
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
2. ✅ Escalas como variables, codemod de `bg-white`/inversos (248 clases), hex de `app.css`,
   gráficos (`themeColor()` lee el token y devuelve hex: ApexCharts no resuelve `var()`),
   leyenda del donut y logo; `storage/framework/views` fuera del `content`. Verificado: CSS
   completo normalizado regla por regla (vars resueltas, colores a RGBA) contra el de fase 1
   = sólo diferencias equivalentes; único cambio real `#fafafa` → gray-50 en tom-select.
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
Se sacó esa entrada del `content` en la fase 2.

## Track aparte (peso web, no hecho)
`app.js` (44 KB gzip) carga en todas las páginas módulos de una sola (compras, escaneo,
compresión de imágenes) → `import()` dinámico; Google Fonts self-hosted; recortar el CSS
default de tom-select (~9,5 KB).
