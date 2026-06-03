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

## Rutas
Solo `index` (GET), `store` (POST), `update` (PUT), `toggleActive` (PATCH). Sin rutas GET para `/create` o `/{id}/edit`.

## Controllers
Sin métodos `create()` ni `edit()`. Solo `index`, `store`, `update`, `toggleActive` (y `destroy` si aplica).
