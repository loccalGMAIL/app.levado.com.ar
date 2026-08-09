// Edición inline del stock en los listados de catálogo. Vivía adentro del
// @foreach de ingredients/index: 37 líneas × 20 filas por página.
//
// La URL llega como argumento y no se arma acá: la conoce Laravel, y además hay
// un test que ancla que aparezca una sola vez en el HTML renderizado.

/**
 * @param {{updateUrl: string, qty: number, qtyFormatted: string}} init
 */
export function stockCell(init = {}) {
    return {
        editing: false,
        saving: false,
        isDirty: false,
        qty: 0,
        qtyFormatted: '',
        updateUrl: '',
        ...init,

        startEdit() {
            this.isDirty = false;
            this.$refs.stockInput.value = parseFloat(this.qty).toFixed(2);
            this.editing = true;
            this.$nextTick(() => this.$refs.stockInput.select());
        },

        async saveStock() {
            if (this.saving) return;
            if (!this.isDirty) { this.editing = false; return; }

            const raw = this.$refs.stockInput.value.trim();
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
                    body: JSON.stringify({ counted_quantity: raw }),
                });
                const data = await res.json();
                this.qty = data.quantity;
                this.qtyFormatted = data.quantity_formatted;
            } finally {
                this.saving = false;
            }
        },
    };
}
