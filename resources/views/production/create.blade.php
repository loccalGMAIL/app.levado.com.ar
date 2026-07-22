<x-app-layout>
    <x-slot name="title">Producir</x-slot>

    <div class="py-8 px-6 lg:px-8 max-w-3xl mx-auto"
        x-data="{
            productId: '',
            quantity: '',
            unit: '',
            lines: [],
            totalCost: 0,
            loading: false,
            submitting: false,
            error: '',
            get canSubmit() { return this.productId !== '' && Number(this.quantity) > 0; },
            get hasShortfall() { return this.lines.some(l => l.shortfall > 0); },
            onProductChange(e) {
                this.unit = e.target.selectedOptions[0]?.dataset.unit || '';
                this.loadPreview();
            },
            async loadPreview() {
                this.error = '';
                if (! this.canSubmit) { this.lines = []; this.totalCost = 0; return; }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('production.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ product_id: this.productId, quantity: this.quantity }),
                    });
                    if (! res.ok) { this.lines = []; this.totalCost = 0; this.error = 'No se pudo calcular el consumo de insumos.'; return; }
                    const data = await res.json();
                    this.lines = data.lines;
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
            <a href="{{ route('production.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Producción</a>
            <h2 class="text-base font-semibold text-corteza mt-2">Producir un elaborado</h2>
            <p class="text-sm text-masa-madre mt-0.5">Elegí el producto y la cantidad; se descuentan los insumos de la receta y se suma el stock del elaborado.</p>
        </div>

        @if($products->isEmpty())
            <x-empty-state>
                No hay elaborados en una categoría que se produzca. Asigná a tus artículos una
                <a href="{{ route('products.index') }}" class="text-horno hover:underline">categoría marcada «se produce»</a>.
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('production.store') }}" @submit="submitting = true" class="space-y-6">
                @csrf
                <input type="hidden" name="product_id" :value="productId">
                <input type="hidden" name="quantity" :value="quantity">

                <div class="bg-white border border-miga rounded-lg p-5 shadow-sm space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="product" value="Producto elaborado" />
                            <select id="product" x-model="productId" @change="onProductChange($event)"
                                class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                <option value="">Elegí un elaborado…</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-unit="{{ $p->unit->short() }}">
                                        {{ $p->name }} — {{ $p->recipe->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('product_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="quantity" value="Cantidad a producir" />
                            <div class="mt-1 flex items-center gap-2">
                                <input id="quantity" type="number" step="0.01" min="0" x-model="quantity"
                                    @input.debounce.400ms="loadPreview()"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                <span class="text-sm text-masa-madre" x-text="unit"></span>
                            </div>
                            <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notas (opcional)" />
                        <textarea id="notes" name="notes" rows="2" maxlength="1000"
                            class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>

                {{-- Vista previa del consumo de insumos --}}
                <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-miga flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-corteza">Insumos a consumir</h3>
                        <span x-show="loading" class="text-xs text-masa-madre">Calculando…</span>
                    </div>

                    <template x-if="error">
                        <p class="px-5 py-4 text-sm text-red-600" x-text="error"></p>
                    </template>

                    <template x-if="! error && lines.length === 0 && ! loading">
                        <p class="px-5 py-4 text-sm text-masa-madre">Elegí un producto y una cantidad para ver el consumo.</p>
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
                                        <td colspan="3" class="px-5 py-2.5 text-right text-sm text-masa-madre">Costo total de insumos</td>
                                        <td class="px-5 py-2.5 text-right font-mono text-corteza font-semibold">$ <span x-text="fmt(totalCost)"></span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('production.index') }}" class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</a>
                    <button type="submit" :disabled="! canSubmit || submitting"
                        class="px-5 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="! submitting">Producir</span>
                        <span x-show="submitting">Registrando…</span>
                    </button>
                </div>
            </form>
        @endif

    </div>
</x-app-layout>
