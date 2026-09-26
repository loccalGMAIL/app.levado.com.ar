---
name: feedback-crud-modals
description: "CRUD create/edit deben ser modales, no páginas separadas. Estructura de carpetas y patrón Alpine/Blade."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: cf76d912-9bf5-4925-b760-f5537b71a250
---

Los formularios de crear y editar van siempre en modales, no en páginas separadas (`/create`, `/edit`).

**Why:** El usuario lo decidió explícitamente al ver las vistas de ingredients y locations. Mejora la UX (no abandona la lista) y reduce la cantidad de rutas y vistas.

**How to apply:**

## Estructura de archivos
Cada módulo con CRUD tiene esta estructura:
```
resources/views/{module}/
    index.blade.php          ← lista + x-data Alpine + @include de modales
    modals/
        create.blade.php     ← <x-crud-modal name="{module}-create" ...>
        edit.blade.php       ← <x-crud-modal name="{module}-edit" ...>
```

## Componente reutilizable
`<x-crud-modal name="X" title="Y" :show="$phpCondition">` — wrapper de `<x-modal>` de Breeze con header estándar (título + X de cierre).

## Patrón Alpine en index
```js
x-data="{
    editing: {{ Js::from($editingOnError) }},   // objeto con defaults vacíos, nunca null
    openEdit(record) {
        this.editing = record;
        $dispatch('open-modal', '{module}-edit');
    }
}"
```
- `editing` siempre es un objeto (nunca `null`) para evitar `null.field` en Alpine.
- Botón editar: `@click="openEdit({{ Js::from([...campos...]) }})"`
- Formulario editar: `:action="\`/{route}/${editing.id}\`"`, campos con `x-model="editing.field"`

## Re-apertura tras error de validación
- Hidden `<input type="hidden" name="_form" value="create|edit">` en cada form
- PHP en index detecta `old('_form')` y pasa `:show="$errorsInCreate"` o `:show="$errorsInEdit"` al modal
- `$editingOnError` reconstruye el objeto editing desde `old()` para re-popular el formulario de edición

## Gotchas (aprendidos en Artículos, sesión 01/09/2026)
- **Un solo `$editPayload($product)`, no arrays inline duplicados.** Si hay varios disparadores de edición (card, nombre,
  ícono), el payload del `@click` debe salir de UNA closure `$editPayload = fn ($x) => [...]` reusada por todos. En
  Artículos el botón ícono había quedado con menos campos (le faltaban `product_category_id` y `costing_method`): al
  editar por ahí y guardar, esos campos se mandaban vacíos y **se borraban** (los `<select>` sin valor caen en "" y el
  controller los nulea). Un solo payload elimina la deriva.
- **Cuidado con colisiones de nombre entre `editing` y x-data anidados.** Si un x-data hijo dentro de la fila (ej.
  `priceCell` en `<tr x-data="priceCell(...)">`) define una propiedad llamada `editing`, los botones de editar que viven
  DENTRO de esa fila hacen `this.editing = record` sobre el scope del hijo, **no** sobre el del modal → el modal queda
  vacío y los `x-show` por tipo no ocultan nada. Se resolvió renombrando la propiedad del hijo (`editing` → `popoverOpen`).
  Regla: el `editing` del modal debe tener nombre único respecto de cualquier x-data anidado en la lista.

## Gotchas (aprendidos en Producción — orden instantánea como modal, sesión 18-19/09/2026)
- **TomSelect (`<select data-searchable>`) dentro de un modal necesita `dropdownParent: 'body'`.**
  Por default el desplegable se renderiza como hermano del `<select>`, dentro del flujo normal del
  DOM — cualquier ancestro con `overflow-hidden` (muy común: bordes redondeados de una grilla o
  tabla) lo recorta y lo deja invisible o casi. Se corrigió una vez, en el init global de app.js
  (`new TomSelect(el, { maxOptions: null, dropdownParent: 'body' })`), no por-modal — afecta a
  cualquier picker searchable de la app. Con `dropdownParent: 'body'` hace falta además que
  `.ts-dropdown` tenga un `z-index` mayor al de cualquier modal (incluido uno anidado) para que no
  quede tapado por el overlay — ver `resources/css/app.css`.
- **No combinar dos factories Alpine que usan el mismo nombre de propiedad en el mismo `x-data`
  plano.** `consumptionPreviewState()` y `productionOrderLines()` (ambos en `resources/js/production/`)
  usan/usaban `lines` con significados distintos. Spreadeados juntos en un solo `<form x-data="...">`
  (sin scope anidado), los métodos del uno (`resetPreview()`) pisan silenciosamente el estado del
  otro — sin ningún error en consola, el síntoma es datos que aparecen y desaparecen solos un rato
  después (ej. con un `$watch` debounced de por medio). Antes de spreadear dos factories en el mismo
  objeto literal, listar las propiedades que cada uno define y confirmar que no colisionan — o
  namespacearlos / mantenerlos en scopes Alpine anidados distintos (como ya hace `show.blade.php`,
  que por eso nunca pisó nada).

## Gotchas (aprendidos al reusar modales de Recetas/Compras en el Dashboard, sesión 19/09/2026)
- **Dos convenciones distintas para `$errorsInCreate` conviven en el proyecto.** Los modales de
  producción (`production-orders/modals/*.blade.php`) calculan su propio `$errorsInXxx` en un `@php`
  al tope del archivo — son autocontenidos. Los modales más viejos (`recipes/modals/create.blade.php`,
  `purchases/modals/create.blade.php`, etc.) NO lo hacen: esperan que la página que los `@include`
  ya haya definido `$errorsInCreate` (así lo hacen sus índices originales). Antes de reusar un modal
  ajeno desde otra página, revisar si es de la primera o la segunda familia.
- **Si una página incluye dos modales de la segunda familia, no puede pasarles la misma
  `$errorsInCreate`** — cada uno la pisaría. Solución: calcular una variable con nombre propio por
  módulo (`$errorsInRecipeCreate`, `$errorsInPurchaseCreate`) y pasarla con la sintaxis de
  `@include('modulo.modals.create', ['errorsInCreate' => $errorsInModuloCreate])`. Ver
  [[feature-dashboard]] (quick actions del saludo, v0.13.2).
- Si el modal reusado depende de otro modal auxiliar (ej. `purchases/modals/create.blade.php` abre
  `supplier-quick-create` con "+ Nuevo proveedor"), ese auxiliar también hay que incluirlo en la
  página nueva — no es automático por estar en el mismo `@can`.

## Rutas
Solo `index` (GET), `store` (POST), `update` (PUT), `toggleActive` (PATCH). Sin rutas GET para `/create` o `/{id}/edit`.

## Controllers
Sin métodos `create()` ni `edit()`. Solo `index`, `store`, `update`, `toggleActive` (y `destroy` si aplica).
