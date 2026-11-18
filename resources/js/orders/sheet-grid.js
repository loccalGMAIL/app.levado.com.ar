import Alpine from 'alpinejs';

/**
 * Grilla semanal de una planilla de pedidos (lunes a domingo, una fila por
 * artículo). Todo viaja en un <form> normal: cada fila genera sus inputs
 * (`lines[i][product_id]`, `lines[i][quantities][d]`) y Guardar los manda de
 * una, reemplazando la semana entera.
 *
 * El <select data-searchable> del picker lo envuelve TomSelect desde app.js;
 * Alpine solo escucha su `change` (mismo patrón que Compras). Las opciones
 * viajan ya renderizadas en el HTML, así que acá `products` solo se usa para
 * resolver nombre y unidad del artículo elegido.
 *
 * Init esperado:
 *   { products: [{id, name, unit}], lines: [{product_id, name, unit, q: [7 números]}] }
 */
Alpine.data('orderSheetGrid', (init = {}) => ({
    products: init.products ?? [],
    lines: (init.lines ?? []).map(line => ({
        ...line,
        q: line.q.map(value => (Number(value) === 0 ? '' : value)),
    })),
    baseline: '',
    submitting: false,
    notice: '',

    init() {
        this.baseline = this.snapshot();
        window.addEventListener('beforeunload', (event) => {
            if (this.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    },

    snapshot() {
        return JSON.stringify(this.lines.map(line => [line.product_id, line.q.map(value => Number(value) || 0)]));
    },

    get dirty() {
        return !this.submitting && this.snapshot() !== this.baseline;
    },

    /** Total de la semana de una fila, en la unidad del artículo. */
    rowTotal(line) {
        const total = line.q.reduce((sum, value) => sum + (Number(value) || 0), 0);
        return Number(total.toFixed(3)).toLocaleString('es-AR', { maximumFractionDigits: 3 });
    },

    /** Enganchado al @change del <select data-searchable> del picker. */
    addProduct(productId, pickerEl) {
        if (!productId) {
            return;
        }

        if (this.lines.some(line => String(line.product_id) === String(productId))) {
            this.notify('Ese artículo ya está en la planilla.');
            this.resetPicker(pickerEl);
            return;
        }

        const product = this.products.find(p => String(p.id) === String(productId));
        if (!product) {
            return;
        }

        this.lines.push({ product_id: product.id, name: product.name, unit: product.unit, q: ['', '', '', '', '', '', ''] });
        this.resetPicker(pickerEl);
        this.$nextTick(() => {
            const rows = this.$refs.rows?.querySelectorAll('tr');
            rows?.[rows.length - 1]?.querySelector('input[type="number"]')?.focus();
        });
    },

    resetPicker(pickerEl) {
        if (pickerEl?._ts) {
            pickerEl._ts.clear(true);
        } else if (pickerEl) {
            pickerEl.value = '';
        }
    },

    removeLine(index) {
        this.lines.splice(index, 1);
    },

    notify(message) {
        this.notice = message;
        setTimeout(() => { this.notice = ''; }, 3000);
    },
}));
