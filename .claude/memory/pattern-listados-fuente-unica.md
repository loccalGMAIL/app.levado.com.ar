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

## Estado

Migradas `ingredients/index` y `labor-types/index`. **Pendientes** (siguen en `x-responsive-table`,
que se mantiene funcionando): `packaging`, `recipes`, `variable-expenses`, `price-lists`, `purchases`,
`fixed-costs`, `stock`. Fuera del patrón y con el doble marcado escrito a mano: `team/index`
(2 bloques), `recipes/show` (4 bloques) y `purchases/show` (1 bloque).
