import Alpine from 'alpinejs';

/**
 * Celda de precio de artículo reutilizable: catálogo, Dashboard, /recipes y matriz.
 * Solo muestra: precio formateado, badge de política, margen y el estado «guardando…».
 * Al hacer click despacha `edit-price`; el editor (popover) es uno solo para toda la
 * página y vive en el layout (ver price-editor.js y <x-price-cell-editor>). Cuando
 * el editor guarda despacha `price-saving` / `price-saved`, y la celda cuya `url`
 * coincide se actualiza.
 *
 * Init esperado (todo opcional salvo url para poder editar):
 *   { url, price, priceFormatted, marginPct, marginPctFormatted, marginColor, policyType, policyValue }
 */
Alpine.data('priceCell', (init = {}) => ({
    url: init.url ?? '',
    canEdit: (init.url ?? '') !== '',

    saving: false,

    // Estado mostrado (lo pisa el evento price-saved tras guardar).
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

    init() {
        if (!this.canEdit) {
            return;
        }

        this.onSaving = (e) => {
            if (e.detail.url === this.url) {
                this.saving = e.detail.saving;
            }
        };
        this.onSaved = (e) => {
            const d = e.detail;
            if (d.url !== this.url) {
                return;
            }
            this.price = d.price;
            this.priceFormatted = d.priceFormatted;
            this.marginPct = d.marginPct;
            this.marginPctFormatted = d.marginPctFormatted;
            this.marginColor = d.marginColor;
            this.policyType = d.policyType;
            this.policyValue = d.policyValue;
        };
        window.addEventListener('price-saving', this.onSaving);
        window.addEventListener('price-saved', this.onSaved);
    },

    destroy() {
        window.removeEventListener('price-saving', this.onSaving);
        window.removeEventListener('price-saved', this.onSaved);
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

        this.$dispatch('edit-price', {
            url: this.url,
            price: this.price,
            policyType: this.policyType,
            policyValue: this.policyValue,
            suggested: this.suggested,
            anchor: e?.currentTarget ?? this.$el,
        });
    },
}));
