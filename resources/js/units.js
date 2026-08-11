// Espejo en JS de app/Services/UnitConverter.php.
// UNIT_CONV[from][to] = cuántas unidades `to` entran en 1 unidad `from`.
export const UNIT_CONV = {
    'gr': { 'gr': 1, 'kg': 0.001 },
    'kg': { 'kg': 1, 'gr': 1000 },
    'ml': { 'ml': 1, 'L': 0.001, 'cc': 1 },
    'L':  { 'L': 1,  'ml': 1000, 'cc': 1000 },
    'cc': { 'cc': 1, 'ml': 1,    'L': 0.001 },
    'u':  { 'u': 1 },
};

// Normaliza unidades escritas en las descripciones de los productos a los
// valores del enum Unit.
export const UNIT_ALIASES = {
    'kg': 'kg', 'kgs': 'kg', 'kilo': 'kg', 'kilos': 'kg', 'kilogramo': 'kg', 'kilogramos': 'kg',
    'gr': 'gr', 'grs': 'gr', 'g': 'gr', 'gramo': 'gr', 'gramos': 'gr', 'gram': 'gr', 'grams': 'gr',
    'l': 'L', 'lt': 'L', 'lts': 'L', 'litro': 'L', 'litros': 'L', 'litre': 'L', 'liter': 'L',
    'ml': 'ml', 'mls': 'ml', 'mililitro': 'ml', 'mililitros': 'ml',
    'cc': 'cc', 'cm3': 'cc',
};

export function compatibleUnits(from, to) {
    return UNIT_CONV[from]?.[to] !== undefined;
}

/**
 * Reexpresa una cantidad: 0,8 kg → 800 gr.
 * Devuelve null si las unidades son de dimensiones distintas.
 */
export function convertAmount(amount, from, to) {
    const factor = UNIT_CONV[from]?.[to];

    return factor === undefined ? null : amount * factor;
}

/**
 * Reexpresa un precio POR unidad, que se mueve al revés que la cantidad:
 * $2000 por kg → $2 por gr.
 */
export function convertUnitPrice(price, from, to) {
    const factor = UNIT_CONV[from]?.[to];

    return factor === undefined ? null : price / factor;
}

// La columna unit_price es decimal(14,4); redondear a menos perdería plata en
// precios por gramo o mililitro.
export function roundPrice(value) {
    return Math.round(value * 10000) / 10000;
}
