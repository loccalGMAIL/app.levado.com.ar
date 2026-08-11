import { compatibleUnits, convertUnitPrice, roundPrice } from '../units.js';

// Se conserva '' en vez de 0 para que x-model no pinte un cero en un campo que el
// usuario todavía no completó.
function blankOrNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return Number(value) || 0;
}

/**
 * Estado de un renglón de compra: unidad, cantidad, precio e impuestos.
 * Lo comparten los tres formularios donde se carga un renglón — alta, edición y
 * revisión del escaneo — que antes repetían el cálculo de IVA por separado.
 *
 * El precio unitario se guarda POR `purchase_unit`, así que cambiar la unidad sin
 * tocar el precio deja el renglón mal por el factor de conversión: $2000 el kilo
 * leídos como $2000 el gramo son 1000× de más, y eso se propaga al costo del insumo
 * vía PurchaseLineRecorder::apply().
 *
 * La conversión no se aplica sola a propósito: cambiar la unidad puede significar
 * «me equivoqué de unidad» (el número está bien) o «reexpreso la medida» (el número
 * tiene que cambiar), y adivinar mal rompe una de las dos. Se ofrece y decide el usuario.
 */
window.purchaseLine = function purchaseLine(initial = {}) {
    return {
        unit: initial.unit || '',
        unitBeforeChange: initial.unit || '',
        qty: blankOrNumber(initial.qty),
        price: blankOrNumber(initial.price),
        ivaRate: Number(initial.ivaRate ?? 0.21),
        percepcionRate: Number(initial.percepcionRate ?? 0),
        priceConversion: null,

        /** Sufijo del label del campo de precio: «Precio por kg». */
        get unitLabel() {
            return this.unit || 'unidad';
        },

        get net() {
            return (Number(this.qty) || 0) * (Number(this.price) || 0);
        },

        get ivaAmount() {
            return this.net * this.ivaRate;
        },

        get percepcionAmount() {
            return this.net * (this.percepcionRate / 100);
        },

        get lineTotal() {
            return this.net * (1 + this.ivaRate + this.percepcionRate / 100);
        },

        /**
         * Reposiciona el estado sin ofrecer conversión. Lo usa el modal de edición,
         * que recarga el renglón cada vez que se abre.
         */
        seedLine(line) {
            this.unit = line?.purchase_unit || '';
            this.unitBeforeChange = this.unit;
            this.qty = blankOrNumber(line?.quantity_purchased);
            this.price = blankOrNumber(line?.unit_price);
            this.ivaRate = parseFloat(line?.iva_rate ?? 0.21);
            this.percepcionRate = parseFloat(line?.percepcion_rate ?? 0);
            this.priceConversion = null;
        },

        onUnitChange(newUnit) {
            const from = this.unitBeforeChange;

            this.unit = newUnit;
            this.unitBeforeChange = newUnit;
            this.priceConversion = null;

            if (!from || from === newUnit || !(this.price > 0) || !compatibleUnits(from, newUnit)) {
                return;
            }

            const converted = roundPrice(convertUnitPrice(this.price, from, newUnit));

            if (converted > 0) {
                this.priceConversion = { from, to: newUnit, price: converted };
            }
        },

        applyPriceConversion() {
            this.price = this.priceConversion.price;
            this.priceConversion = null;
        },

        dismissPriceConversion() {
            this.priceConversion = null;
        },

        fmt(n) {
            return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        fmtPrice(n) {
            return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        },
    };
};
