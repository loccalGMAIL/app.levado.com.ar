import Alpine from 'alpinejs';

const formatNumber = (value) => Number(Number(value).toFixed(3)).toLocaleString('es-AR', { maximumFractionDigits: 3 });

/**
 * Grilla semanal de una planilla de pedidos (lunes a domingo, una fila por
 * artículo). Todo viaja en un <form> normal: cada fila genera sus inputs
 * (`lines[i][product_id]`, `lines[i][quantities][d]`) y Guardar los manda de
 * una, reemplazando la semana entera.
 *
 * Un artículo con bandeja/lata (`container`) se carga en recipientes, de a
 * cuartos, y debajo de cada celda se ven las unidades equivalentes. Sin
 * `container` se carga directo en la unidad del artículo.
 *
 * El <select data-searchable> del picker lo envuelve TomSelect desde app.js;
 * Alpine solo escucha su `change` (mismo patrón que Compras). Las opciones
 * viajan ya renderizadas en el HTML, así que acá `products` solo se usa para
 * resolver nombre, unidad y recipiente del artículo elegido.
 *
 * Init esperado:
 *   { products: [{id, name, unit, container}],
 *     lines: [{product_id, name, unit, container, q: [7 números], warning}] }
 *   container = null | {type, label, short, per}
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

    sum(line) {
        return line.q.reduce((total, value) => total + (Number(value) || 0), 0);
    },

    /** «Bandeja de 30 u» bajo el nombre del artículo. */
    containerLabel(line) {
        return `${line.container.label} de ${formatNumber(line.container.per)} ${line.unit}`;
    },

    /** Unidades equivalentes a lo cargado en un día (solo filas con recipiente). */
    equivalent(line, day) {
        const value = Number(line.q[day]) || 0;
        return value > 0 ? `${formatNumber(value * line.container.per)} ${line.unit}` : '';
    },

    /** Total de la semana: en recipientes y unidades, o solo unidades. */
    rowTotal(line) {
        const total = this.sum(line);
        if (!line.container) {
            return formatNumber(total);
        }
        return `${formatNumber(total)} ${line.container.short} · ${formatNumber(total * line.container.per)} ${line.unit}`;
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

        this.lines.push({
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            container: product.container,
            warning: null,
            q: ['', '', '', '', '', '', ''],
        });
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
