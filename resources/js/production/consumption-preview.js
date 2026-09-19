/**
 * Estado compartido del preview de consumo de insumos (orden y orden
 * instantánea): ambas pantallas muestran la misma tabla insumo/necesario/
 * disponible/costo, pero difieren en CÓMO piden el preview (GET sin body vs
 * POST con `items` y debounce) — por eso esto es sólo el estado + formato, no
 * un Alpine.data() completo con loadPreview() incluido. Cada pantalla hace:
 *
 *   x-data="{ ...consumptionPreviewState(), async loadPreview() { ... } }"
 *
 * Expuesta en window (no Alpine.data): el spread `...fn()` dentro de un
 * x-data de objeto literal necesita `fn` como función JS accesible en el
 * scope de la expresión, no como data-provider registrado por nombre.
 *
 * `previewLines`, no `lines`: la orden instantánea combina este spread con
 * `...productionOrderLines(...)` en el MISMO x-data (destino + artículos +
 * preview, todo en un solo <form>, sin scope anidado como en show.blade.php).
 * Ambos factories usaban "lines" — resetPreview()/applyPreview() pisaban los
 * artículos recién agregados en cuanto corría el debounce del preview. Bug
 * real encontrado a mano: el artículo se agregaba a la grilla y desaparecía
 * solo ~400ms después.
 */
export function consumptionPreviewState() {
    return {
        loading: false,
        error: '',
        previewLines: [],
        materialCost: 0,
        laborCost: 0,
        totalCost: 0,
        get hasShortfall() {
            return this.previewLines.some(l => l.shortfall > 0);
        },
        /** Pisa el estado con la respuesta JSON del endpoint de preview. */
        applyPreview(data) {
            this.previewLines = data.lines;
            this.materialCost = data.material_cost;
            this.laborCost = data.labor_cost;
            this.totalCost = data.total_cost;
        },
        /** Vacía el preview (sin selección, error de red, etc.). */
        resetPreview() {
            this.previewLines = [];
            this.materialCost = 0;
            this.laborCost = 0;
            this.totalCost = 0;
        },
        fmt(n) {
            return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);
        },
        fmtQty(n) {
            return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 3 }).format(n || 0);
        },
    };
}

window.consumptionPreviewState = consumptionPreviewState;
