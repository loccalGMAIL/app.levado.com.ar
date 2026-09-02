<x-crud-modal name="product-cost-history" title="Historial de costo">
    <div class="space-y-3">
        <p class="text-sm text-corteza font-medium" x-text="costHistory.name"></p>

        <template x-if="costHistory.loading">
            <p class="text-sm text-masa-madre">Cargando…</p>
        </template>

        <template x-if="costHistory.failed">
            <p class="text-sm text-red-600">No se pudo cargar el historial.</p>
        </template>

        <template x-if="!costHistory.loading && !costHistory.failed && costHistory.rows.length === 0">
            <p class="text-sm text-masa-madre">
                Todavía no hay movimientos de costo. Se registra uno cada vez que se imputa
                una compra o se edita el costo a mano.
            </p>
        </template>

        <template x-if="!costHistory.loading && costHistory.rows.length > 0">
            <div class="border border-miga rounded-md divide-y divide-miga max-h-80 overflow-y-auto">
                <template x-for="row in costHistory.rows" :key="row.recorded_at + row.cost">
                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <div class="min-w-0">
                            <div class="text-corteza">
                                <span class="text-[10px] font-medium rounded px-1 py-0.5"
                                    :class="row.source === 'manual' ? 'bg-gray-100 text-gray-600' : 'bg-sky-50 text-sky-700'"
                                    x-text="row.source_label"></span>
                                <span class="text-masa-madre text-xs ml-1" x-text="row.recorded_at"></span>
                            </div>
                            <template x-if="row.purchase_url">
                                <a :href="row.purchase_url" class="text-xs text-horno hover:underline truncate block"
                                    x-text="row.supplier ? `Factura de ${row.supplier}` : 'Ver factura'"></a>
                            </template>
                        </div>
                        <span class="font-mono text-corteza whitespace-nowrap" x-text="'$ ' + row.cost_formatted"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>
</x-crud-modal>
