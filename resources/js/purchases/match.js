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

window.matchRow = function matchRow(selected, unitPrice, purchaseUnit, description) {
    return {
        selected,
        unitPrice,
        purchaseUnit,
        description,

        catalogUnit: '',
        pkgQty: 1,
        pkgQtyFromDesc: false,
        unitCost: unitPrice,
        needsPkgQty: false,
        incompatiblePkg: false,

        init() {
            if (this.selected) this.recalc();
        },

        onSelect() {
            this.pkgQty = 1;
            this.pkgQtyFromDesc = false;
            this.recalc();
        },

        recalc() {
            this.needsPkgQty = false;
            this.incompatiblePkg = false;
            this.catalogUnit = '';

            if (!this.selected) {
                this.unitCost = this.unitPrice;
                return;
            }

            const [type, id] = this.selected.split(':');

            if (type === 'packaging') {
                if (this.purchaseUnit !== 'u') {
                    this.incompatiblePkg = true;
                    this.unitCost = this.unitPrice;
                } else {
                    this.catalogUnit = 'u';
                    this.unitCost = this.unitPrice;
                }
                return;
            }

            const catalog = window.MATCH_CATALOG || {};
            const item = catalog[id];
            if (!item) return;

            this.catalogUnit = item.unit;
            const directFactor = UNIT_CONV[this.purchaseUnit]?.[item.unit];

            if (directFactor !== undefined) {
                this.needsPkgQty = false;
                this.unitCost = Math.round((this.unitPrice / directFactor) * 100) / 100;
            } else {
                this.needsPkgQty = true;
                const parsed = this.parseDesc(this.description);

                if (parsed) {
                    const parsedFactor = UNIT_CONV[parsed.unit]?.[item.unit];
                    if (parsedFactor !== undefined) {
                        this.pkgQty = parsed.qty * parsedFactor;
                        this.pkgQtyFromDesc = true;
                    }
                }

                this.unitCost = this.pkgQty > 0 ? Math.round((this.unitPrice / this.pkgQty) * 100) / 100 : this.unitPrice;
            }
        },

        onPkgQtyChange() {
            this.pkgQtyFromDesc = false;
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
