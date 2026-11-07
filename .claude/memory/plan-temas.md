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
  (data-URI). El fondo `#fff` literal que pone a inputs/selects/textareas se pisa en el
  `@layer base` de app.css con `superficie`.
- **Paginación publicada** (`resources/views/vendor/pagination/{tailwind,simple-tailwind}`) sin
  clases `dark:` y con `bg-superficie`: la de vendor se oscurecía con el SO (estrategia `media`)
  aunque la app estuviera en claro. Ya no hay `vendor/` en el `content` de Tailwind.
- Token `velo` (fondo de modales y del menú «Más» mobile, con opacidad): gris en claro, negro en
  oscuro. El menú mobile pasó de `bg-corteza/60` (marrón) a `bg-velo/60` (gris): único cambio
  visible en claro, a propósito.
- Test de prevención: `tests/Unit/ThemeTokensTest.php` (sin `bg-white`, sin inversos con nombre
  de texto, sin `dark:`, sin hex en vistas/JS/app.css, todo token usado definido, oscuro redefine
  TODAS las variables, centeno sólo existentes, `Theme::themeColor()` = `--c-harina` de cada
  tema, piso de contraste AA de los pares de texto principales en los tres temas).
- Tres temas en el mismo `app.css` (sin archivos por tema: un request más y parpadeo por <1 KB).
  `:root, [data-theme="claro"]` + `@media screen { [data-theme="oscuro"] {…} [data-theme="centeno"] {…} }`:
  al imprimir siempre claro sin duplicar nada. `[data-theme="claro"]` explícito para que las
  miniaturas del selector muestren cada tema con `data-theme` en un elemento (sin hex).
- `users.theme` nullable (`null` = Automático). Enum `App\Enums\Theme` (Claro, Oscuro,
  Centeno) con `label()`, `description()`, `themeColor()` (hex del `<meta name="theme-color">`).
  `PATCH /profile/theme` (`profile.theme`, `ProfileController@updateTheme`, `ThemeUpdateRequest`):
  204 por fetch, redirect sin JS. Selector en Perfil → «Apariencia» (Alpine inline).
- El layout pone `data-theme` en `<html>` si hay tema elegido (sin JS). `<x-theme-head />` (en
  los 3 layouts) emite el meta y un script inline (~600 B) que resuelve Automático según
  `prefers-color-scheme` antes de pintar, sigue los cambios del SO, expone
  `window.levadoTheme.set()` y dispara `theme-changed`. **Cambio respecto del plan:** Automático
  era CSS puro con `@media (prefers-color-scheme)`, pero duplicaba el bloque oscuro entero en
  el CSS; el script es más chico y deja una sola fuente de valores.
- `color-scheme: dark` sólo en oscuro. **No** declarar `color-scheme: light`: el navegador ya lo
  usa y, declarado, Chromium cambia el suavizado del texto en mobile (detectado comparando
  píxeles).
- `dashboard-charts.js` guarda las instancias y las rehace en `theme-changed`.
- Tom Select y Shepherd (tour) tienen overrides en app.css; los que cambiarían claro van con
  prefijo `[data-theme="oscuro"]`.
- Botón de peligro en oscuro: blanco sobre `red-600` = 3,7:1 (se priorizó `text-red-600` sobre
  fondo oscuro, ~150 usos, en 4,7:1).
- Mails, PDFs (`*/_styles.blade.php` de dompdf) y `errors/minimal` quedan en claro.

## Paletas (valores finales en app.css)
| token | claro | oscuro | centeno |
|---|---|---|---|
| harina | #FAF7F2 | #16100B | #F2F3EF |
| superficie | #FFFFFF | #211912 | #FFFFFF |
| miga | #F2EAD8 | #2D231A | #E4E7DF |
| corteza | #3D2B1F | #F2EAD8 | #262C2A |
| masa-madre | #6B5B45 | #C4B39C | #56605B |
| horno | #C8622A | #C8622A | #55702F |
| membrillo | #E8A820 | #F0B83A | #A07A22 |
| tinta | #3D2B1F | #5A4636 | #262C2A |
| barra | #6B5B45 | #2A2018 | #4A5550 |

## Fases
1. ✅ Variables en config + `:root` con los valores actuales. Verificado sin cambio visual:
   se compila una página con todas las utilidades de marca usadas (old vs new config) y se
   comparan los estilos computados en Chromium (51 clases, 0 diferencias).
2. ✅ Escalas como variables, codemod de `bg-white`/inversos (248 clases), hex de `app.css`,
   gráficos (`themeColor()` lee el token y devuelve hex: ApexCharts no resuelve `var()`),
   leyenda del donut y logo; `storage/framework/views` fuera del `content`. Verificado: CSS
   completo normalizado regla por regla (vars resueltas, colores a RGBA) contra el de fase 1
   = sólo diferencias equivalentes; único cambio real `#fafafa` → gray-50 en tom-select.
3. ✅ Temas oscuro y centeno, preferencia por usuario, selector, charts, theme-color, print,
   paginación publicada. QA: capturas de 8 pantallas × 3 temas (desktop y mobile), chequeos de
   comportamiento con Playwright (Automático sigue al SO, selector instantáneo + persistencia,
   print en claro, inputs/velo en oscuro, gauge se redibuja) y regresión píxel a píxel de claro
   contra el CSS de fase 2 (18 pantallas, sólo ruido de render que también aparece comparando
   el CSS nuevo consigo mismo). Para QA local: SQLite descartable con `DB_CONNECTION=sqlite
   DB_DATABASE=…` + `db:seed` + `DemoCatalogSeeder`, login `owner@demo.com` / `password`.
   Para comparar CSS hay que usar contextos de navegador separados: en la misma página la
   segunda carga sale de la caché en memoria aunque haya `route()`.

## Build
`public/build` está versionado. Desde la fase 3 el `content` de Tailwind es sólo
`resources/views`, así que el CSS compilado no depende de `vendor/` ni de la caché de vistas.
(En la nube `composer install` fallaba porque api.github.com corta las descargas: se resolvió
con `--prefer-source` y, para `phpstan/phpstan`, que sólo publica zip, sembrando la caché de
composer con un zip armado desde un clone por git del commit del lock.)

## Track aparte (peso web, no hecho)
`app.js` (44 KB gzip) carga en todas las páginas módulos de una sola (compras, escaneo,
compresión de imágenes) → `import()` dinámico; Google Fonts self-hosted; recortar el CSS
default de tom-select (~9,5 KB).
