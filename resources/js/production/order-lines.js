import Alpine from 'alpinejs';

/**
 * Grilla editable de las líneas de un pedido (artículo + cantidad): agregar,
 * editar cantidad y quitar sin recargar la página, guardado en lote contra
 * ProductionOrderLineController::sync() (PUT, reemplaza el set completo).
 *
 * El picker de artículo es un <select data-searchable> común (mismo patrón
 * que los modales de Compras) — TomSelect lo envuelve por fuera de Alpine
 * (ver app.js) y sigue disparando el evento `change` nativo del <select>
 * subyacente, que es lo que engancha addProduct().
 *
 * Init esperado:
 *   { saveUrl, previousUrl, products: [{id, name, unit}], lines: [{id, product_id, name, unit, quantity}] }
 * `products` viaja UNA sola vez por página (en el x-data raíz) y cada grilla
 * lo recibe acá por referencia — no lo dupliques por pedido.
 */
Alpine.data('productionOrderLines', (init = {}) => ({
    saveUrl: init.saveUrl ?? '',
    previousUrl: init.previousUrl ?? '',
    products: init.products ?? [],
    lines: (init.lines ?? []).map(line => ({ ...line })),
    saved: '',
    saving: false,
    error: '',
    duplicateNotice: '',

    init() {
        this.saved = JSON.stringify(this.lines);
    },

    get dirty() {
        return JSON.stringify(this.lines) !== this.saved;
    },

    productById(id) {
        return this.products.find(p => String(p.id) === String(id));
    },

    /** Enganchado al @change del <select data-searchable> del picker. */
    addProduct(productId, pickerEl) {
        if (!productId) {
            return;
        }
        if (this.lines.some(l => String(l.product_id) === String(productId))) {
            this.duplicateNotice = 'Ese artículo ya está en el pedido — editá su cantidad abajo.';
            setTimeout(() => { this.duplicateNotice = ''; }, 3000);
            this.resetPicker(pickerEl);
            return;
        }
        const product = this.productById(productId);
        if (!product) {
            return;
        }
        this.lines.push({ id: null, product_id: product.id, name: product.name, unit: product.unit, quantity: '' });
        this.resetPicker(pickerEl);
        this.$nextTick(() => {
            const inputs = this.$refs.rows?.querySelectorAll('input[type="number"]');
            inputs?.[inputs.length - 1]?.focus();
        });
    },

    resetPicker(pickerEl) {
        if (pickerEl?._ts) {
            pickerEl._ts.clear();
        }
    },

    removeLine(i) {
        this.lines.splice(i, 1);
    },

    async save() {
        this.saving = true;
        this.error = '';
        try {
            const res = await fetch(this.saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    lines: this.lines.map(l => ({ id: l.id, product_id: l.product_id, quantity: l.quantity })),
                }),
            });
            if (!res.ok) {
                this.error = 'No se pudo guardar el pedido.';
                return;
            }
            const data = await res.json();
            this.lines = data.lines.map(l => ({ ...l }));
            this.saved = JSON.stringify(this.lines);
            window.dispatchEvent(new CustomEvent('production-lines-saved', {
                detail: { aggregated: data.aggregated, preview: data.preview },
            }));
        } catch (e) {
            this.error = 'No se pudo guardar el pedido.';
        } finally {
            this.saving = false;
        }
    },

    /** Trae (sin persistir) los renglones del último pedido al mismo destino. */
    async fromPrevious() {
        if (this.lines.length > 0 && !confirm('¿Reemplazar los artículos actuales por los del pedido anterior?')) {
            return;
        }
        this.error = '';
        try {
            const res = await fetch(this.previousUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                this.error = 'No se pudo traer el pedido anterior.';
                return;
            }
            const data = await res.json();
            if (!data.found) {
                alert('Todavía no hay un pedido anterior a este destino.');
                return;
            }
            this.lines = data.lines.map(l => ({ id: null, product_id: l.product_id, name: l.name, unit: l.unit, quantity: l.quantity }));
            if (data.skipped.length > 0) {
                alert('Artículos del pedido anterior que ya no se producen y no se trajeron: ' + data.skipped.join(', '));
            }
        } catch (e) {
            this.error = 'No se pudo traer el pedido anterior.';
        }
    },
}));
