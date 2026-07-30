// ingredient_units per 1 purchase_unit (for auto-conversion between compatible units).
const UNIT_CONV = {
    'gr': { 'gr': 1, 'kg': 0.001 },
    'kg': { 'kg': 1, 'gr': 1000 },
    'ml': { 'ml': 1, 'L': 0.001, 'cc': 1 },
    'L':  { 'L': 1,  'ml': 1000, 'cc': 1000 },
    'cc': { 'cc': 1, 'ml': 1,    'L': 0.001 },
    'u':  { 'u': 1 },
};

// Normalize text units from product descriptions to Unit enum values.
const UNIT_ALIASES = {
    'kg': 'kg', 'kgs': 'kg', 'kilo': 'kg', 'kilos': 'kg', 'kilogramo': 'kg', 'kilogramos': 'kg',
    'gr': 'gr', 'grs': 'gr', 'g': 'gr', 'gramo': 'gr', 'gramos': 'gr', 'gram': 'gr', 'grams': 'gr',
    'l': 'L', 'lt': 'L', 'lts': 'L', 'litro': 'L', 'litros': 'L', 'litre': 'L', 'liter': 'L',
    'ml': 'ml', 'mls': 'ml', 'mililitro': 'ml', 'mililitros': 'ml',
    'cc': 'cc', 'cm3': 'cc',
};

// `remembered` viene de ProductLinkMemory: { selection: 'ingredient:42', pkgQty: 25 }.
// Va atado a la selección porque el divisor es del ítem, no del renglón.
window.matchRow = function matchRow(selected, unitPrice, purchaseUnit, description, remembered = null) {
    return {
        selected,
        unitPrice,
        purchaseUnit,
        description,
        remembered,

        catalogUnit: '',
        displayUnit: '',
        pkgQty: 1,
        pkgQtyFromDesc: false,
        pkgQtyFromMemory: false,
        unitCost: unitPrice,
        needsPkgQty: false,
        incompatiblePkg: false,
        subdivisions: null,
        subdivisionLabel: null,

        // Centinela del select: el renglón no es del negocio (consumo personal).
        get isExcluded() {
            return this.selected === 'excluded';
        },

        init() {
            if (this.selected) this.recalc();
        },

        onSelect() {
            this.pkgQty = 1;
            this.pkgQtyFromDesc = false;
            this.pkgQtyFromMemory = false;
            this.subdivisions = null;
            this.subdivisionLabel = null;
            this.recalc();
        },

        recalc() {
            this.needsPkgQty = false;
            this.incompatiblePkg = false;
            this.pkgQtyFromMemory = false;
            this.catalogUnit = '';

            if (!this.selected) {
                this.unitCost = this.unitPrice;
                return;
            }

            // Consumo personal: no hay costo que calcular. unitCost en 0 deja el hidden
            // unit_cost vacío, y catalogUnit vacío mantiene oculto el bloque de cálculo.
            if (this.isExcluded) {
                this.unitCost = 0;
                return;
            }

            // El catálogo está indexado por el mismo "tipo:id" que manda el select.
            const item = (window.MATCH_CATALOG || {})[this.selected];
            if (!item) return;

            // Único caso especial de los descartables: sólo se compran por unidad.
            if (this.selected.startsWith('packaging:') && this.purchaseUnit !== 'u') {
                this.incompatiblePkg = true;
                this.unitCost = this.unitPrice;
                return;
            }

            this.catalogUnit = item.unit;
            this.subdivisions = item.subdivisions || null;
            this.subdivisionLabel = item.subdivisionLabel || null;
            this.displayUnit = (this.subdivisions && item.unit === 'u')
                ? (item.subdivisionLabel || 'u')
                : item.unit;
            const directFactor = UNIT_CONV[this.purchaseUnit]?.[item.unit];

            if (directFactor !== undefined) {
                this.needsPkgQty = false;
                // When the item tracks sub-units, divide by subdivisions so unitCost
                // represents cost per sub-unit (what gets stored in cost_per_unit).
                const subdivisionFactor = (this.subdivisions && this.purchaseUnit === 'u' && item.unit === 'u')
                    ? this.subdivisions : 1;
                this.unitCost = Math.round((this.unitPrice / directFactor / subdivisionFactor) * 10000) / 10000;
            } else {
                this.needsPkgQty = true;

                // El divisor recordado gana sobre el que se adivina de la
                // descripción, pero sólo si el renglón sigue apuntando al mismo
                // ítem con el que se guardó.
                const memoryHit = this.remembered?.selection === this.selected
                    ? this.remembered.pkgQty
                    : null;

                if (memoryHit > 0) {
                    this.pkgQty = memoryHit;
                    this.pkgQtyFromMemory = true;
                } else {
                    const parsed = this.parseDesc(this.description);

                    if (parsed) {
                        const parsedFactor = UNIT_CONV[parsed.unit]?.[item.unit];
                        if (parsedFactor !== undefined) {
                            this.pkgQty = parsed.qty * parsedFactor;
                            this.pkgQtyFromDesc = true;
                        }
                    }
                }

                this.unitCost = this.pkgQty > 0 ? Math.round((this.unitPrice / this.pkgQty) * 100) / 100 : this.unitPrice;
            }
        },

        onPkgQtyChange() {
            this.pkgQtyFromDesc = false;
            this.pkgQtyFromMemory = false;
            if (this.pkgQty > 0) {
                this.unitCost = Math.round((this.unitPrice / this.pkgQty) * 100) / 100;
            }
        },

        // Matches patterns like: "X 25 Kg", "x5lts", "× 200 ml", "X 1.5 L", "X5KG"
        parseDesc(desc) {
            if (!desc) return null;
            const re = /[xX×]\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs?|kilo[s]?|kilogramo[s]?|gr[s]?|gramo[s]?|g|l(?:t[s]?|itro[s]?)?|ml[s]?|cc)/i;
            const m = desc.match(re);
            if (!m) return null;
            const qty = parseFloat(m[1].replace(',', '.'));
            const normalized = UNIT_ALIASES[m[2].toLowerCase()];
            if (!normalized || qty <= 0) return null;
            return { qty, unit: normalized };
        },
    };
};
