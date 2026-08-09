// Edición inline de un número en una celda de listado: mostrar, abrir el input,
// mandar el PATCH y absorber la respuesta. Vivía adentro del @foreach, así que
// viajaba una vez por fila (37 líneas × 20 en ingredientes; en envases, dos
// copias por fila —costo y stock— más una tercera por el doble render).
//
// El stock y el costo son el mismo objeto con otros nombres, así que comparten
// implementación y sólo cambian la clave que espera el endpoint y la que trae
// la respuesta. La URL llega como argumento: la conoce Laravel.

/**
 * @param {{payloadKey: string, valueKey: string}} endpoint
 */
function inlineNumber({ payloadKey, valueKey }) {
    return {
        editing: false,
        saving: false,
        isDirty: false,
        value: 0,
        valueFormatted: '',
        updateUrl: '',

        startEdit() {
            this.isDirty = false;
            this.$refs.input.value = parseFloat(this.value).toFixed(2);
            this.editing = true;
            this.$nextTick(() => this.$refs.input.select());
        },

        async save() {
            if (this.saving) return;
            if (!this.isDirty) { this.editing = false; return; }

            const raw = this.$refs.input.value.trim();
            if (raw === '') { this.editing = false; return; }

            this.saving = true;
            this.editing = false;

            try {
                const res = await fetch(this.updateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ [payloadKey]: raw }),
                });
                const data = await res.json();
                this.value = data[valueKey];
                this.valueFormatted = data[`${valueKey}_formatted`];
            } finally {
                this.saving = false;
            }
        },
    };
}

/** Stock contado de un ingrediente o envase (PATCH a stock.level.update). */
export function stockCell(init = {}) {
    return { ...inlineNumber({ payloadKey: 'counted_quantity', valueKey: 'quantity' }), ...init };
}

/** Costo por sub-unidad de un envase (PATCH a packaging.cost.update). */
export function costCell(init = {}) {
    return { ...inlineNumber({ payloadKey: 'cost_per_unit', valueKey: 'cost_per_unit' }), ...init };
}
