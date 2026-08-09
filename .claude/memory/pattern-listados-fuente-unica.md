---
name: pattern-listados-fuente-unica
description: Los listados renderizan la fila una sola vez y el CSS decide si se ve como fila o como card — x-data-table, roles de celda y por qué no se usa @aware (v0.12.10)
metadata:
  type: project
---
# Listados con una sola fuente de render — `x-data-table` (v0.12.10)

**Regla del proyecto:** un listado no puede escribir el mismo dato dos veces. Antes de crear una
pantalla nueva, verificar si la propuesta duplica HTML; si lo hace, resolverlo con una sola fuente de
render. El layout general (menú, barras, encabezados) sí se adapta con CSS. La paginación siempre en
el servidor.

## Lo que había

`x-responsive-table` factorizaba los divs envoltorio y el toggle, pero pedía **dos slots**: uno de
cards y otro de tabla. Nueve índices recorrían la colección dos veces. Consecuencias reales:

- El payload `Js::from([...])` de `openEdit()` aparecía **3 veces por archivo** (card, celda del
  nombre, celda de acciones).
- La card y la tabla **divergían**: en ingredientes la tabla tenía 8 columnas y la card omitía «Por
  envase» y fusionaba Marca+Proveedor.
- Las dos copias viajaban siempre en el HTML.

## El mecanismo

Se renderiza **sólo el `<tr>`**. Cada `<td>` declara su rol y el CSS decide la presentación:

```blade
<x-data-table :paginator="$ingredients" total-label="ingrediente">
    <x-slot:head> …los <th>, con x-sortable-th… </x-slot:head>
    @foreach($ingredients as $ingredient)
        <x-data-table.row :dimmed="! $ingredient->active">
            <x-data-table.cell role="title">…</x-data-table.cell>
            <x-data-table.cell role="meta" cards="hide">…</x-data-table.cell>
        </x-data-table.row>
    @endforeach
</x-data-table>
```

- **Roles:** `title` · `subtitle` · `figure` · `badge` · `meta` · `actions`. En la card el orden lo da
  el rol (`order`), no el orden de las columnas.
- **`cards="hide"`** saca una columna de baja prioridad del modo card sin sacarla de la tabla.
- **`label="Stock:"`** es el prefijo que la card muestra con `::before` (la tabla ya tiene su `<th>`).
- **`.dt-card-only`** es la fuente única para el texto que sólo tiene sentido en la card: el label de
  un botón de acción (en la tabla alcanza el ícono) o la unidad de un importe.
- **`.dt-actions`** va adentro del `<td>`, nunca en el `<td>`: `display:flex` sobre una celda la saca
  del layout de tabla y el navegador genera una celda anónima, desalineando la columna.

## Cuatro trampas

1. **`flex-wrap` + `order`, no `grid-template-areas`.** Con áreas nombradas dos celdas del mismo rol
   (dos `meta`) caen en la misma área y se **pisan**. Con `order` cada rol ocupa su renglón y las
   celdas del mismo rol conservan el orden del documento.
2. **`:empty` de CSS no sirve**: el whitespace de Blade hace que la celda nunca esté vacía. La celda
   se recorta en PHP (`trim($slot->toHtml())`) y se marca `data-empty`; la tabla le pone «—» y la
   card la oculta.
3. **`min-width: 0` en todos los `td`** y `overflow-wrap: anywhere`. Sin eso `min-width:auto` impide
   que la celda ceda y un importe de 9 dígitos empuja contenido fuera de la card — es el desborde de
   [[pattern-cifras-responsive]].
4. **Nada de `container-type` acá.** `contain: inline-size` prohíbe que el ancho dependa del
   contenido y rompe la tabla en desktop.

## Por qué no se usa `@aware`

Se evaluó pasar la definición de columnas desde `x-data-table` a cada celda con `@aware` + un
contador por referencia, para que el rol saliera automáticamente del `<thead>`. Se descartó: los
bloques `@can('manage-costs')` que envuelven columnas enteras desincronizan el contador y **el fallo
es silencioso**. El rol se declara en la celda: una palabra, en el lugar donde importa.

## Cómo se verifica

- **`DataTableComponentsTest`** cuenta celdas de título, verifica que el nombre aparezca una sola vez
  como texto y que el editor de stock no se duplique. **Las anclas se probaron contra un revert**: un
  `assertSee` del nombre pasa igual con y sin el arreglo, porque el payload de `openEdit` lo repite
  en JSON. El test viejo (`assertSeeInOrder(['X','X']) // card + fila`) anclaba la duplicación.
- **Playwright** sobre el CSS compilado real: `documentElement.scrollWidth - clientWidth === 0` en
  320/375/390/414/640/768/1024/1280/1440/1920px, **en los dos modos del toggle**.

---

# La otra duplicación: el Alpine por fila (v0.12.11)

`x-data-table` sacó la **segunda copia de la fila**. Pero la copia que queda seguía siendo enorme por
otro motivo: un objeto Alpine escrito **adentro del `@foreach`** se serializa una vez por fila.

**Regla del proyecto: más de ~15 líneas de Alpine adentro de un `@foreach` van a `Alpine.data()`.**
El Blade queda con la llamada al factory y los valores iniciales; el código vive en
`resources/js/rows/`.

```blade
<tr x-data="priceRow({{ Js::from([
        'price' => $row['selling_price'],
        'tier'  => \App\Enums\MarginTier::fromPercent($pct)->value,
        'updateUrl' => route('recipes.prices.update', [$recipe, $priceList]),
    ]) }})">
```

Medido con 20 registros: dashboard **4266 → 2926 líneas (−31%)**, `price-lists/matrix`
**7388 → 3708 (−50%**, porque multiplica recetas × listas**)**, `ingredients/index` 3798 → 3078 (−19%).

## Cuatro trampas

1. **`Alpine.start()` engaña.** En `app.js` está en la línea 9 y los `import` debajo, pero ESM
   hoistea los imports: en runtime corren **antes**. `window.matchRow` funciona por ese accidente. El
   registro va colgado de **`alpine:init`**, que corre durante `start()`, y así el orden deja de
   importar.
2. **Nada de `import()` dinámico para estos módulos.** Un `Alpine.data()` que llega después de
   `start()` no se aplica a lo ya montado: el `x-data` queda sin definir **en silencio**, sin error
   en consola. Para lazy-loading, el patrón es el de `dashboard-charts.js`: módulo estático,
   `import()` dinámico sólo del vendor pesado adentro.
3. **El spread aplana los getters.** `{...base}` no copia un getter: lo **ejecuta** y guarda el valor.
   Un `get marginColor()` de la base quedaría congelado y no volvería a reaccionar. Por eso
   `price-editor.js` compone con `Object.getOwnPropertyDescriptors`.
4. **La URL se pasa como argumento, no se arma en JS.** La conoce Laravel, y además
   `DataTableComponentsTest` ancla que aparezca una sola vez en el HTML. Ojo: `Js::from()` escapa las
   barras (`http:\/\/…`) y las comillas (`"`), así que un test que busque la URL cruda tiene que
   aplanar los backslashes primero.

## Los umbrales de margen — `MarginTier`

Encontrado midiendo esto: los cortes vivían en **siete lugares con dos escalas**. El dashboard usaba
60/40/20 y `RecipePriceController` + la matriz usaban 30/15, así que **editar un precio inline movía
el color del número a la otra escala**: una receta al 35% pasaba de naranja a verde sola mientras la
badge de al lado seguía diciendo «Regular».

`App\Enums\MarginTier` es ahora el único dueño (`HIGH=60`, `MEDIUM=40`, `LOW=20`). El controller
devuelve **`margin_tier`, no `margin_color`**: el servidor manda el tramo y el cliente lo traduce a
clases con tablas de lookup. El JS no conoce los cortes.

## Anclas

- `AlpineRowComponentsTest`: el **cuerpo** del objeto no está en el HTML (`assertDontSee('async
  savePrice')`) y el factory aparece una vez por fila. Las 4 fallan contra un revert.
- `PriceListMatrixTest` tenía `assertDontSee('savePrice')` como gate del rol Viewer. Al mudar el
  objeto a un módulo ese string desaparece **para todos los roles**: el test pasaba siempre sin
  proteger nada. Reapuntado, y verificado que falla si se saca el `@can`.
- **Un test de marcado no alcanza acá**: si un factory queda mal registrado, la edición inline muere
  en silencio. Hay que ejercitarla en navegador (abrir, escribir, guardar, ver el valor y el tramo
  actualizados sin recargar).

## Los dos frentes se atacan juntos

`packaging/index` y `recipes/index` tenían **las dos duplicaciones a la vez**: el doble render y el
Alpine inline, así que el objeto viajaba dos y hasta tres veces por registro (en envases: costo en la
card, costo otra vez en la fila, y stock). Migrarlos a `x-data-table` **y** extraer el Alpine en la
misma pasada dio la mayor caída de todas: envases **6209 → 3109 líneas (−50%)**, recetas
**4855 → 2296 (−53%)**.

`stockCell` y `costCell` son el mismo editor con otra clave de payload y de respuesta: viven juntos
en `rows/inline-number.js` con estado uniforme (`value` / `valueFormatted` / `save()`), no en dos
módulos casi iguales.

**Detalle de layout:** con tres acciones en la card («Ver receta / Copiar / Desactivar») repartir el
ancho en partes iguales corta las palabras al medio. `.dt-actions` en modo card usa
`flex: 1 1 8rem` + `flex-wrap`, así el botón que no entra baja a un renglón nuevo.

## Pendiente de este frente

`purchases/show` (45 líneas, sin paginar) y `purchases/scan/review` (11). En listados quedan 5 vistas
todavía en `x-responsive-table`: `variable-expenses`, `price-lists/index`, `purchases/index`,
`fixed-costs` y `stock`.

---

## Estado

Migradas `ingredients/index` y `labor-types/index`. **Pendientes** (siguen en `x-responsive-table`,
que se mantiene funcionando): `packaging`, `recipes`, `variable-expenses`, `price-lists`, `purchases`,
`fixed-costs`, `stock`. Fuera del patrón y con el doble marcado escrito a mano: `team/index`
(2 bloques), `recipes/show` (4 bloques) y `purchases/show` (1 bloque).
