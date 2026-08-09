// Edición inline de precios. Antes este objeto se escribía adentro del @foreach,
// así que las 68 líneas del dashboard (y las 47 de la matriz) viajaban una vez
// por fila: con 20 recetas eran ~1360 líneas de JavaScript idéntico en el HTML.
//
// El dashboard y la matriz comparten el 80% (editar / guardar / PATCH), así que
// la base vive una sola vez y cada pantalla agrega lo suyo.
//
// Los cortes de margen NO están acá: el servidor manda el tramo (MarginTier) y
// esto sólo lo traduce a clases. Un solo dueño de la regla.

const BAR_COLOR = {
    alta: 'bg-green-500',
    media: 'bg-amber-500',
    regular: 'bg-orange-400',
    baja: 'bg-red-500',
    indefinido: 'bg-miga',
};

const BADGE_CLASS = {
    alta: 'bg-green-100 text-green-700',
    media: 'bg-amber-100 text-amber-700',
    regular: 'bg-orange-100 text-orange-700',
    baja: 'bg-red-100 text-red-700',
    indefinido: 'bg-miga text-masa-madre',
};

const BADGE_LABEL = {
    alta: 'Alta',
    media: 'Media',
    regular: 'Regular',
    baja: 'Baja',
    indefinido: '—',
};

const TEXT_COLOR = {
    alta: 'text-green-600',
    media: 'text-amber-600',
    regular: 'text-orange-600',
    baja: 'text-red-500',
    indefinido: 'text-masa-madre',
};

const tierClass = (table, tier) => table[tier] ?? table.indefinido;

/**
 * Mezcla objetos conservando los getters.
 *
 * Ojo: el spread (`{...base, ...extra}`) NO copia getters, los EJECUTA y guarda
 * el valor resultante. Un `get marginColor()` de la base quedaría congelado en
 * lo que valiera al momento de la mezcla y nunca volvería a reaccionar al
 * cambiar `tier`. Por eso se copian los descriptores.
 */
const merge = (...objects) => Object.defineProperties(
    {},
    Object.assign({}, ...objects.map(Object.getOwnPropertyDescriptors)),
);

/** Lo común a las dos pantallas: abrir el input, mandar el PATCH, absorber la respuesta. */
const priceEditorBase = {
    editing: false,
    saving: false,
    isDirty: false,
    price: null,
    priceFormatted: '',
    marginPct: null,
    marginPctFormatted: '',
    tier: 'indefinido',
    updateUrl: '',

    get marginColor() {
        return tierClass(TEXT_COLOR, this.tier);
    },

    /** Valor con el que se precarga el input al abrir la edición. */
    editableValue() {
        return this.price !== null ? parseFloat(this.price).toFixed(2) : '';
    },

    startEdit() {
        this.isDirty = false;
        this.$refs.priceInput.value = this.editableValue();
        this.editing = true;
        this.$nextTick(() => this.$refs.priceInput.select());
    },

    async savePrice() {
        if (this.saving) return;
        if (!this.isDirty) { this.editing = false; return; }

        const raw = this.$refs.priceInput.value.trim();
        const payload = raw !== '' ? raw : null;
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
                body: JSON.stringify({ price: payload }),
            });
            this.applyResponse(await res.json());
        } finally {
            this.saving = false;
        }
    },

    applyResponse(data) {
        this.price = data.selling_price;
        this.priceFormatted = data.selling_price_formatted ?? '';
        this.marginPct = data.margin_pct;
        this.marginPctFormatted = data.margin_pct_formatted ?? '';
        this.tier = data.margin_tier ?? 'indefinido';
    },
};

/** Fila del dashboard: suma el importe del margen y la presentación del tramo. */
export function priceRow(init = {}) {
    return merge(priceEditorBase, {
        margin: null,
        marginFormatted: '',

        get barColor() {
            return tierClass(BAR_COLOR, this.tier);
        },
        get badgeClass() {
            return tierClass(BADGE_CLASS, this.tier);
        },
        get badgeLabel() {
            return tierClass(BADGE_LABEL, this.tier);
        },

        applyResponse(data) {
            priceEditorBase.applyResponse.call(this, data);
            this.margin = data.margin;
            this.marginFormatted = data.margin_formatted ?? '';
        },
    }, init);
}

/**
 * Celda de la matriz de precios: una lista no-default puede sugerir un precio
 * derivado del de la lista base, y esa sugerencia precarga el input.
 */
export function priceCell(init = {}) {
    return merge(priceEditorBase, {
        suggested: null,
        suggestedFormatted: '',

        editableValue() {
            if (this.price !== null) return parseFloat(this.price).toFixed(2);

            return this.suggested !== null ? parseFloat(this.suggested).toFixed(2) : '';
        },

        startEdit() {
            priceEditorBase.startEdit.call(this);
            // Sobre una celda vacía con sugerencia, aceptar el valor propuesto
            // sin tocarlo tiene que guardarlo: arranca marcada como sucia.
            this.isDirty = this.price === null && this.suggested !== null;
        },
    }, init);
}
