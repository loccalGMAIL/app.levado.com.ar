import Alpine from 'alpinejs';

/**
 * Celda de precio de artículo reutilizable: catálogo, Dashboard, /recipes y matriz.
 * Encapsula el estado de precio + política (manual / margen / recargo) y el guardado
 * contra `products.prices.update`. La UI del editor la aporta <x-price-cell-editor>,
 * que se monta dentro del mismo scope (lee draftType/draftPrice/draftValue y llama a save()).
 *
 * Init esperado (todo opcional salvo url para poder editar):
 *   { url, price, priceFormatted, marginPct, marginPctFormatted, marginColor, policyType, policyValue }
 */
Alpine.data('priceCell', (init = {}) => ({
    url: init.url ?? '',
    canEdit: (init.url ?? '') !== '',

    editing: false,
    saving: false,

    // Estado mostrado (lo pisa la respuesta del endpoint tras guardar).
    price: init.price ?? null,
    priceFormatted: init.priceFormatted ?? '',
    marginPct: init.marginPct ?? null,
    marginPctFormatted: init.marginPctFormatted ?? '',
    marginColor: init.marginColor ?? 'text-masa-madre',
    policyType: init.policyType ?? 'manual',
    policyValue: init.policyValue ?? null,

    // Solo la matriz: precio sugerido por el % de la lista cuando la celda no
    // tiene precio propio. Al editar en manual sin precio, se siembra con esto.
    suggested: init.suggested ?? null,
    suggestedFormatted: init.suggestedFormatted ?? '',

    // Borrador del editor (se siembra en startEdit).
    draftType: 'manual',
    draftPrice: '',
    draftValue: '',

    // Posición del popover (se teletransporta a <body> con position: fixed para
    // no quedar recortado por el overflow-x-auto del contenedor de la tabla).
    popTop: 0,
    popLeft: 0,
    get popStyle() {
        return `position: fixed; top: ${this.popTop}px; left: ${this.popLeft}px;`;
    },

    get hasPolicy() {
        return this.policyType === 'margin' || this.policyType === 'markup';
    },

    /** Etiqueta corta del badge: "Margen 40%" / "Recargo 25%". Vacío si es manual. */
    get policyBadge() {
        if (this.policyType === 'margin') {
            return 'Margen ' + this.fmtPct(this.policyValue);
        }
        if (this.policyType === 'markup') {
            return 'Recargo ' + this.fmtPct(this.policyValue);
        }
        return '';
    },

    fmtPct(v) {
        if (v === null || v === undefined || isNaN(v)) {
            return '';
        }
        return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 }).format(v) + '%';
    },

    startEdit(e) {
        if (!this.canEdit || this.saving) {
            return;
        }

        // Ubica el popover debajo del trigger, alineado a su borde derecho; si no
        // entra abajo, lo sube. Coordenadas fixed (viewport) porque va en <body>.
        const trigger = e?.currentTarget ?? this.$el;
        const rect = trigger.getBoundingClientRect();
        const POP_W = 240;
        const POP_H = 170;
        let left = rect.right - POP_W;
        if (left < 8) {
            left = 8;
        }
        let top = rect.bottom + 4;
        if (top + POP_H > window.innerHeight && rect.top - POP_H > 0) {
            top = rect.top - POP_H - 4;
        }
        this.popLeft = left;
        this.popTop = top;

        this.draftType = this.policyType ?? 'manual';
        // En manual sin precio, arrancamos del sugerido (matriz) si lo hay.
        this.draftPrice = this.price !== null
            ? parseFloat(this.price).toFixed(2)
            : (this.suggested !== null ? parseFloat(this.suggested).toFixed(2) : '');
        this.draftValue = this.policyValue !== null ? String(this.policyValue) : '';
        this.editing = true;
        this.$nextTick(() => {
            const el = this.draftType === 'manual' ? this.$refs.priceInput : this.$refs.valueInput;
            if (el) {
                el.focus();
                el.select?.();
            }
        });
    },

    cancel() {
        this.editing = false;
    },

    async save() {
        if (this.saving) {
            return;
        }

        let payload;
        if (this.draftType === 'manual') {
            const raw = String(this.draftPrice).trim();
            payload = { policy_type: 'manual', price: raw !== '' ? raw : null };
        } else {
            const raw = String(this.draftValue).trim();
            if (raw === '') {
                // Margen/recargo exigen un valor; no guardamos vacío.
                return;
            }
            payload = { policy_type: this.draftType, policy_value: raw };
        }

        this.saving = true;
        this.editing = false;
        try {
            const res = await fetch(this.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                const data = await res.json();
                this.price = data.selling_price;
                this.priceFormatted = data.selling_price_formatted ?? '';
                this.marginPct = data.margin_pct;
                this.marginPctFormatted = data.margin_pct_formatted ?? '';
                this.marginColor = data.margin_color ?? 'text-masa-madre';
                this.policyType = data.policy_type ?? 'manual';
                this.policyValue = data.policy_value ?? null;
            }
        } finally {
            this.saving = false;
        }
    },
}));
