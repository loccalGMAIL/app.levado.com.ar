import Alpine from 'alpinejs';

/**
 * Editor de política de precio (popover) único por página. Se monta una vez en el
 * layout (<x-price-cell-editor>) y escucha `edit-price`, que despachan las celdas
 * `priceCell`. Guarda contra `products.prices.update` y avisa con `price-saving` /
 * `price-saved` para que la celda de esa `url` se actualice.
 */
Alpine.data('priceEditor', () => ({
    open: false,
    saving: false,
    url: '',

    // Borrador del editor (se siembra en openFor).
    draftType: 'manual',
    draftPrice: '',
    draftValue: '',

    // Posición del popover: position fixed, para no quedar recortado por el
    // overflow-x-auto del contenedor de la tabla.
    popTop: 0,
    popLeft: 0,
    get popStyle() {
        return `position: fixed; top: ${this.popTop}px; left: ${this.popLeft}px;`;
    },

    openFor(detail) {
        if (this.saving) {
            return;
        }

        // Ubica el popover debajo del ancla, alineado a su borde derecho; si no
        // entra abajo, lo sube. Coordenadas fixed (viewport).
        const rect = detail.anchor.getBoundingClientRect();
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

        this.url = detail.url;
        this.draftType = detail.policyType ?? 'manual';
        // En manual sin precio, arrancamos del sugerido (matriz) si lo hay.
        const price = detail.price ?? detail.suggested ?? null;
        this.draftPrice = price !== null ? parseFloat(price).toFixed(2) : '';
        this.draftValue = detail.policyValue !== null && detail.policyValue !== undefined ? String(detail.policyValue) : '';
        this.open = true;
        this.$nextTick(() => {
            const el = this.draftType === 'manual' ? this.$refs.priceInput : this.$refs.valueInput;
            if (el) {
                el.focus();
                el.select?.();
            }
        });
    },

    cancel() {
        this.open = false;
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

        const url = this.url;
        this.saving = true;
        this.open = false;
        window.dispatchEvent(new CustomEvent('price-saving', { detail: { url, saving: true } }));
        try {
            const res = await fetch(url, {
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
                window.dispatchEvent(new CustomEvent('price-saved', {
                    detail: {
                        url,
                        price: data.selling_price,
                        priceFormatted: data.selling_price_formatted ?? '',
                        marginPct: data.margin_pct,
                        marginPctFormatted: data.margin_pct_formatted ?? '',
                        marginColor: data.margin_color ?? 'text-masa-madre',
                        policyType: data.policy_type ?? 'manual',
                        policyValue: data.policy_value ?? null,
                    },
                }));
            }
        } finally {
            this.saving = false;
            window.dispatchEvent(new CustomEvent('price-saving', { detail: { url, saving: false } }));
        }
    },
}));
