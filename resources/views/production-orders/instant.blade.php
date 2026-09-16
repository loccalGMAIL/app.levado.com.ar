<x-app-layout>
    <x-slot name="title">Orden instantánea</x-slot>

    <div class="py-8 px-6 lg:px-8 max-w-3xl mx-auto"
        x-data="{
            destinationType: 'location',
            destinationId: '',
            rows: [{ product_id: '', quantity: '' }],
            lines: [],
            materialCost: 0,
            laborCost: 0,
            totalCost: 0,
            loading: false,
            submitting: false,
            error: '',
            get validRows() { return this.rows.filter(r => r.product_id !== '' && Number(r.quantity) > 0); },
            get canSubmit() { return this.destinationId !== '' && this.validRows.length > 0; },
            get hasShortfall() { return this.lines.some(l => l.shortfall > 0); },
            addRow() { this.rows.push({ product_id: '', quantity: '' }); },
            removeRow(i) {
                this.rows.splice(i, 1);
                if (this.rows.length === 0) { this.addRow(); }
                this.loadPreview();
            },
            async loadPreview() {
                this.error = '';
                if (this.validRows.length === 0) { this.lines = []; this.materialCost = 0; this.laborCost = 0; this.totalCost = 0; return; }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('production-orders.instant.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ items: this.validRows.map(r => ({ product_id: r.product_id, quantity: r.quantity })) }),
                    });
                    if (! res.ok) { this.lines = []; this.materialCost = 0; this.laborCost = 0; this.totalCost = 0; this.error = 'No se pudo calcular el consumo de insumos.'; return; }
                    const data = await res.json();
                    this.lines = data.lines;
                    this.materialCost = data.material_cost;
                    this.laborCost = data.labor_cost;
                    this.totalCost = data.total_cost;
                } catch (e) {
                    this.error = 'No se pudo calcular el consumo de insumos.';
                } finally {
                    this.loading = false;
                }
            },
            fmt(n) { return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0); },
            fmtQty(n) { return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 3 }).format(n || 0); },
        }">

        <div class="mb-5">
            <a href="{{ route('production-orders.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Órdenes de producción</a>
            <h2 class="text-base font-semibold text-corteza mt-2">Orden instantánea</h2>
            <p class="text-sm text-masa-madre mt-0.5">Un solo destino, uno o más artículos: se crea la orden y se produce ya.</p>
            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-3 py-2 mt-2">
                El destino es informativo, para la planilla de reparto — el stock producido entra igual al obrador, no se mueve al destino elegido.
            </p>
        </div>

        @if($products->isEmpty())
            <x-empty-state>
                No hay elaborados en una categoría que se produzca. Asigná a tus artículos una
                <a href="{{ route('products.index') }}" class="text-horno hover:underline">categoría marcada «se produce»</a>.
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('production-orders.instant.store') }}" @submit="submitting = true" class="space-y-6">
                @csrf
                <input type="hidden" name="destination_type" :value="destinationType">
                <input type="hidden" name="destination_id" :value="destinationId">

                <div class="bg-white border border-miga rounded-lg p-5 shadow-sm space-y-4">
                    <div>
                        <x-input-label value="Destino" />
                        <div class="mt-1 flex gap-4">
                            <label class="flex items-center gap-2 text-sm text-corteza">
                                <input type="radio" x-model="destinationType" value="location" @change="destinationId = ''"
                                    class="border-gray-300 text-horno focus:ring-horno">
                                Sucursal
                            </label>
                            <label class="flex items-center gap-2 text-sm text-corteza">
                                <input type="radio" x-model="destinationType" value="delivery_person" @change="destinationId = ''"
                                    class="border-gray-300 text-horno focus:ring-horno">
                                Repartidor
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
                    </div>

                    <div x-show="destinationType === 'location'">
                        <x-input-label value="Sucursal" />
                        <select x-model="destinationId" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            <option value="">Elegí una sucursal…</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="destinationType === 'delivery_person'">
                        <x-input-label value="Repartidor" />
                        <select x-model="destinationId" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            <option value="">Elegí un repartidor…</option>
                            @foreach($deliveryPeople as $deliveryPerson)
                                <option value="{{ $deliveryPerson->id }}">{{ $deliveryPerson->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />

                    <div>
                        <x-input-label value="Artículos" />
                        <div class="space-y-2 mt-1">
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="flex items-center gap-2">
                                    <select :name="'items['+i+'][product_id]'" x-model="row.product_id" @change="loadPreview()"
                                        class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                        <option value="">Elegí un elaborado…</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} — {{ $p->recipe->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0" :name="'items['+i+'][quantity]'" x-model="row.quantity"
                                        @input.debounce.400ms="loadPreview()" placeholder="Cantidad"
                                        class="w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                    <button type="button" @click="removeRow(i)" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Quitar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addRow()" class="text-sm text-horno hover:underline mt-2">+ Agregar artículo</button>
                        <x-input-error :messages="$errors->get('items')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notas (opcional)" />
                        <textarea id="notes" name="notes" rows="2" maxlength="1000"
                            class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>

                {{-- Vista previa del consumo combinado --}}
                <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-miga flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-corteza">Insumos a consumir</h3>
                        <span x-show="loading" class="text-xs text-masa-madre">Calculando…</span>
                    </div>

                    <template x-if="error">
                        <p class="px-5 py-4 text-sm text-red-600" x-text="error"></p>
                    </template>

                    <template x-if="! error && lines.length === 0 && ! loading">
                        <p class="px-5 py-4 text-sm text-masa-madre">Elegí al menos un artículo y una cantidad para ver el consumo.</p>
                    </template>

                    <template x-if="lines.length > 0">
                        <div>
                            <div x-show="hasShortfall" class="px-5 py-2.5 bg-amber-50 border-b border-amber-100 text-xs text-amber-700">
                                Algún insumo no alcanza: la producción igual se registra y el stock quedará en negativo.
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-miga text-masa-madre">
                                    <tr>
                                        <th class="px-5 py-2 text-left font-medium">Insumo</th>
                                        <th class="px-5 py-2 text-right font-medium">Necesario</th>
                                        <th class="px-5 py-2 text-right font-medium">Disponible</th>
                                        <th class="px-5 py-2 text-right font-medium">Costo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    <template x-for="line in lines" :key="line.type + '-' + line.id">
                                        <tr :class="line.shortfall > 0 ? 'bg-amber-50/50' : ''">
                                            <td class="px-5 py-2 text-corteza" x-text="line.name"></td>
                                            <td class="px-5 py-2 text-right font-mono text-corteza">
                                                <span x-text="fmtQty(line.quantity)"></span> <span class="text-masa-madre" x-text="line.unit"></span>
                                            </td>
                                            <td class="px-5 py-2 text-right font-mono" :class="line.shortfall > 0 ? 'text-amber-600' : 'text-masa-madre'">
                                                <span x-text="fmtQty(line.available)"></span>
                                            </td>
                                            <td class="px-5 py-2 text-right font-mono text-masa-madre">
                                                <span x-text="fmt(line.line_cost)"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t border-miga">
                                    <tr>
                                        <td colspan="3" class="px-5 py-1.5 text-right text-sm text-masa-madre">Costo de insumos</td>
                                        <td class="px-5 py-1.5 text-right font-mono text-corteza">$ <span x-text="fmt(materialCost)"></span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-5 py-1.5 text-right text-sm text-masa-madre">Mano de obra</td>
                                        <td class="px-5 py-1.5 text-right font-mono text-corteza">$ <span x-text="fmt(laborCost)"></span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-5 py-2.5 text-right text-sm text-masa-madre">Costo total</td>
                                        <td class="px-5 py-2.5 text-right font-mono text-corteza font-semibold">$ <span x-text="fmt(totalCost)"></span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('production-orders.index') }}" class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</a>
                    <button type="submit" :disabled="! canSubmit || submitting"
                        class="px-5 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="! submitting">Producir ahora</span>
                        <span x-show="submitting">Produciendo…</span>
                    </button>
                </div>
            </form>
        @endif

    </div>
</x-app-layout>
