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
 *   { saveUrl, previousUrl, products: [{id, name, unit, step, production_unit}], lines: [{id, product_id, name, unit, measure, quantity}], deferred }
 * `step` = salto de los botones −/+ por medida ({unit, production_unit}), viene de LineMeasure::step().
 * `measure` es 'unit' o 'production_unit' (lata/tanda de la receta del artículo).
 * `products` viaja UNA sola vez por página (en el x-data raíz) y cada grilla
 * lo recibe acá por referencia — no lo dupliques por pedido.
 *
 * `deferred: true` (el modal "Nuevo pedido"): no hay saveUrl ni botón
 * "Guardar pedido" propio — las líneas viajan como inputs ocultos dentro del
 * <form> del modal, que se manda entero de una. En ese modo, fromPrevious()
 * necesita destinationType/destinationId, que NO son de este componente:
 * el Blade que arma el modal los agrega al mismo x-data por spread
 * (`{ ...productionOrderLines(...), destinationType: '...', destinationId: '' }`),
 * así que `this.destinationType` ya está en el mismo objeto reactivo.
 */
Alpine.data('productionOrderLines', (init = {}) => ({
    deferred: init.deferred ?? false,
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

    /** Unidad de producción de la receta del artículo de la línea (o null). */
    productionUnitOf(line) {
        return this.productById(line.product_id)?.production_unit ?? null;
    },

    /**
     * Ayuda bajo la cantidad cuando la línea está en unidades de producción:
     * "= 33 u", o "≈ 17 u" si el redondeo cambió el valor. Espejo de
     * Product::roundForProduction(): las unidades ('u') suben al entero y el
     * resto (kg, L...) conserva sus decimales.
     */
    baseHint(line) {
        const pu = this.productionUnitOf(line);
        const qty = Number(line.quantity);
        if (line.measure !== 'production_unit' || !pu || !(qty > 0)) {
            return '';
        }
        const exact = qty * pu.size;
        const rounded = line.unit === 'u' ? Math.ceil(Math.round(exact * 1e6) / 1e6) : Math.round(exact * 1000) / 1000;
        const approx = Math.abs(rounded - exact) > 0.0001;
        return (approx ? '≈ ' : '= ') + rounded.toLocaleString('es-AR', { maximumFractionDigits: 3 }) + ' ' + line.unit;
    },

    /** Salto de los botones −/+ y de las flechas para la medida actual de la línea. */
    stepFor(line) {
        return this.productById(line.product_id)?.step?.[line.measure ?? 'unit'] ?? 1;
    },

    /**
     * Sube o baja la cantidad al múltiplo siguiente o anterior del salto (desde
     * un valor fuera de grilla, como 0,3, va a 0,25 o 0,5), sin bajar de un salto.
     */
    stepQuantity(line, direction) {
        const step = this.stepFor(line);
        const ratio = (Number(line.quantity) || 0) / step;
        const multiple = direction > 0 ? Math.floor(ratio + 1e-9) + 1 : Math.ceil(ratio - 1e-9) - 1;
        line.quantity = Math.round(Math.max(step, multiple * step) * 10000) / 10000;
    },

    /** Cantidad y medida de una línea tal como viajan al servidor. */
    payload(line) {
        return { quantity: line.quantity, measure: line.measure ?? 'unit' };
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
        // Arranca en la unidad de producción (lata) cuando el artículo la tiene;
        // las unidades sueltas quedan como opción del selector.
        const measure = product.production_unit ? 'production_unit' : 'unit';
        this.lines.push({ id: null, product_id: product.id, name: product.name, unit: product.unit, measure, quantity: '' });
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

    /** Vuelve al picker para cargar otro artículo sin soltar el teclado —
     *  enganchado al @keydown.enter.prevent de la cantidad (ver lines-grid). */
    focusPicker() {
        const el = this.$refs.picker;
        if (!el) {
            return;
        }
        // El picker del modal de recurrentes vive dentro de un x-if, así que
        // el init global de TomSelect en app.js (corre una sola vez, al
        // cargar la página) no llega a envolverlo — cae al <select> pelado.
        (el._ts ?? el).focus();
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
                    lines: this.lines.map(l => ({ id: l.id, product_id: l.product_id, ...this.payload(l) })),
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
        if (this.deferred && !this.destinationId) {
            alert('Elegí primero un destino.');
            return;
        }
        if (this.lines.length > 0 && !confirm('¿Reemplazar los artículos actuales por los del pedido anterior?')) {
            return;
        }
        this.error = '';
        try {
            const url = this.deferred
                ? `${this.previousUrl}?destination_type=${encodeURIComponent(this.destinationType)}&destination_id=${encodeURIComponent(this.destinationId)}`
                : this.previousUrl;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                this.error = 'No se pudo traer el pedido anterior.';
                return;
            }
            const data = await res.json();
            if (!data.found) {
                alert('Todavía no hay un pedido anterior a este destino.');
                return;
            }
            this.lines = data.lines.map(l => ({ id: null, product_id: l.product_id, name: l.name, unit: l.unit, measure: l.measure ?? 'unit', quantity: l.quantity }));
            if (data.skipped.length > 0) {
                alert('Artículos del pedido anterior que ya no se producen y no se trajeron: ' + data.skipped.join(', '));
            }
        } catch (e) {
            this.error = 'No se pudo traer el pedido anterior.';
        }
    },
}));
