import Alpine from 'alpinejs';

/**
 * Edición inline de un número en una celda de listado (costo, stock): click en el
 * valor → input → Enter/blur guarda con PATCH, Escape cancela. Reemplaza el x-data
 * de ~30 líneas que cada fila repetía en el HTML.
 *
 * La celda trae (todo requerido):
 *   url           endpoint PATCH
 *   field         clave del body ({ [field]: valor })
 *   valueKey      clave del número en la respuesta JSON
 *   formattedKey  clave del texto formateado en la respuesta JSON
 *   value         número actual
 *   formatted     texto formateado actual
 *
 * El estado se llama `isEditing` y no `editing` a propósito: el índice tiene su
 * propio `editing` (el registro del modal) y un x-data anidado con el mismo nombre
 * lo taparía para los botones que vivan dentro de la celda.
 *
 * Markup esperado: un elemento con x-ref="input" (el <input type="number">) y los
 * estados `isEditing`, `saving`, `value`, `formatted`, `startEdit()`, `save()`.
 */
Alpine.data('inlineEdit', (cfg) => ({
    isEditing: false,
    saving: false,
    isDirty: false,
    value: cfg.value,
    formatted: cfg.formatted,

    startEdit() {
        this.isDirty = false;
        this.$refs.input.value = parseFloat(this.value).toFixed(2);
        this.isEditing = true;
        this.$nextTick(() => this.$refs.input.select());
    },

    cancel() {
        this.isEditing = false;
        this.isDirty = false;
    },

    async save() {
        if (this.saving) {
            return;
        }
        if (!this.isDirty) {
            this.isEditing = false;
            return;
        }
        const raw = this.$refs.input.value.trim();
        if (raw === '') {
            this.isEditing = false;
            return;
        }

        this.saving = true;
        this.isEditing = false;
        try {
            const res = await fetch(cfg.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ [cfg.field]: raw }),
            });
            const data = await res.json();
            this.value = data[cfg.valueKey];
            this.formatted = data[cfg.formattedKey];
        } finally {
            this.saving = false;
        }
    },
}));
