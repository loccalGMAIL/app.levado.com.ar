<x-app-layout>
    <x-slot name="title">Amasado #{{ $batch->number }}</x-slot>

    @php
        $canEdit = auth()->user()->can('manage-costs');
        $oldLines = old('lines');
        $initialLines = is_array($oldLines)
            ? collect($oldLines)->map(function ($line) use ($lines, $productOptions) {
                $base = collect($lines)->firstWhere('product_id', (int) ($line['product_id'] ?? 0))
                    ?? collect($productOptions)->firstWhere('product_id', (int) ($line['product_id'] ?? 0))
                    ?? [];

                return array_merge(['planned' => 0], $base, [
                    'actual' => $line['actual_units'] ?? 0,
                    'waste' => $line['waste_units'] ?? 0,
                    'reason' => $line['waste_reason'] ?? '',
                    'note' => $line['waste_note'] ?? '',
                ]);
            })->values()->all()
            : $lines;
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8"
        x-data="{
            lines: @js($initialLines),
            options: @js($productOptions),
            reasons: @js($wasteReasons),
            adding: '',
            fmt(value) {
                return Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 3 });
            },
            containers(line, units) {
                if (! line.container || ! line.container.per) return '';
                return '≈ ' + this.fmt(Math.round(units / line.container.per * 100) / 100) + ' ' + line.container.short;
            },
            needsNote(line) {
                const reason = this.reasons.find(r => r.value === line.reason);
                return reason ? reason.note : false;
            },
            available() {
                return this.options.filter(o => ! this.lines.some(l => l.product_id === o.product_id));
            },
            add() {
                const option = this.options.find(o => o.product_id === Number(this.adding));
                if (! option) return;
                this.lines.push({ ...option, planned: 0, actual: '', waste: 0, reason: '', note: '' });
                this.adding = '';
            },
        }">
        <div class="max-w-3xl space-y-5">

            <div class="space-y-1">
                <a href="{{ route('produccion.amasado.index', ['fecha' => $batch->dough_date->toDateString()]) }}"
                    class="text-sm text-masa-madre hover:text-corteza">← Amasado del {{ $batch->dough_date->format('d/m') }}</a>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-corteza">Amasado #{{ $batch->number }}</h2>
                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $batch->status->badgeClasses() }}">{{ $batch->status->label() }}</span>
                </div>
                <p class="text-sm text-masa-madre">Cargá lo que realmente salió y la merma. Al confirmar se descuentan los insumos y entra lo amasado.</p>
            </div>

            @if($errors->any())
                <div class="text-sm text-red-700 bg-red-50 rounded-md px-4 py-3">
                    @foreach($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <form id="dough-batch-form" method="POST" action="{{ route('produccion.amasados.update', $batch) }}" class="space-y-5">
                @csrf
                @method('PUT')

                {{-- Renglones --}}
                <section class="space-y-3">
                    <template x-if="lines.length === 0">
                        <p class="text-sm text-masa-madre bg-superficie rounded-lg shadow px-4 py-4">Sin artículos. Agregá lo que se amasó.</p>
                    </template>

                    <template x-for="(line, index) in lines" :key="line.product_id">
                        <div class="bg-superficie rounded-lg shadow px-4 py-3 space-y-3">
                            <input type="hidden" :name="`lines[${index}][product_id]`" :value="line.product_id">

                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-corteza" x-text="line.name"></p>
                                    <p class="text-xs text-masa-madre">
                                        <span x-text="line.goes_to_chamber ? 'Va a cámara' : 'Directo'"></span>
                                        <template x-if="Number(line.planned) > 0">
                                            <span>· planeado <span class="font-mono" x-text="fmt(line.planned) + ' ' + line.unit"></span> <span x-text="containers(line, line.planned)"></span></span>
                                        </template>
                                    </p>
                                </div>
                                @if($canEdit)
                                    <button type="button" @click="lines.splice(index, 1)"
                                        class="text-xs text-masa-madre hover:text-red-600" aria-label="Quitar">Quitar</button>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-xs text-masa-madre">Real (<span x-text="line.unit"></span>)</span>
                                    <input type="number" inputmode="decimal" step="any" min="0"
                                        :name="`lines[${index}][actual_units]`" x-model="line.actual"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-base font-mono focus:border-horno focus:ring-horno">
                                    <span class="text-[11px] text-masa-madre" x-text="containers(line, line.actual)"></span>
                                </label>
                                <label class="block">
                                    <span class="text-xs text-masa-madre">Merma (<span x-text="line.unit"></span>)</span>
                                    <input type="number" inputmode="decimal" step="any" min="0"
                                        :name="`lines[${index}][waste_units]`" x-model="line.waste"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-base font-mono focus:border-horno focus:ring-horno">
                                </label>
                            </div>

                            <div x-show="Number(line.waste) > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-xs text-masa-madre">Motivo de la merma</span>
                                    <select :name="`lines[${index}][waste_reason]`" x-model="line.reason"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                        <option value="">Elegí…</option>
                                        <template x-for="reason in reasons" :key="reason.value">
                                            <option :value="reason.value" x-text="reason.label" :selected="reason.value === line.reason"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="block" x-show="needsNote(line)">
                                    <span class="text-xs text-masa-madre">¿Qué pasó?</span>
                                    <input type="text" maxlength="255" :name="`lines[${index}][waste_note]`" x-model="line.note"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                </label>
                            </div>
                        </div>
                    </template>

                    @if($canEdit)
                        <div class="flex items-center gap-2" x-show="available().length > 0">
                            <label for="add_product" class="sr-only">Agregar artículo</label>
                            <select id="add_product" x-model="adding"
                                class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                <option value="">Agregar artículo…</option>
                                <template x-for="option in available()" :key="option.product_id">
                                    <option :value="option.product_id" x-text="option.name"></option>
                                </template>
                            </select>
                            <button type="button" @click="add()" :disabled="adding === ''"
                                class="px-3 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga disabled:opacity-50">Agregar</button>
                        </div>
                    @endif
                </section>

                {{-- Masas --}}
                @if($batch->masses->isNotEmpty())
                    <section class="bg-superficie rounded-lg shadow">
                        <header class="px-4 py-3 border-b border-miga">
                            <h3 class="text-sm font-semibold text-corteza">Masas</h3>
                            <p class="text-xs text-masa-madre">Lo que sobró (+) o faltó (−). Es solo registro: la masa no tiene stock.</p>
                        </header>
                        <ul class="divide-y divide-miga">
                            @foreach($batch->masses as $index => $mass)
                                <li class="px-4 py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm text-corteza">{{ $mass->recipe->name }}</p>
                                        <p class="text-xs text-masa-madre">Planeado {{ \App\Services\QuantityFormatter::format($mass->planned_quantity) }} {{ $mass->recipe->yield_unit->short() }}</p>
                                    </div>
                                    <input type="hidden" name="masses[{{ $index }}][recipe_id]" value="{{ $mass->recipe_id }}">
                                    <label class="flex items-center gap-2">
                                        <span class="sr-only">Sobrante o faltante de {{ $mass->recipe->name }}</span>
                                        <input type="number" inputmode="decimal" step="any"
                                            name="masses[{{ $index }}][leftover_quantity]"
                                            value="{{ old("masses.{$index}.leftover_quantity", $mass->leftover_quantity != 0 ? $mass->leftover_quantity : '') }}"
                                            placeholder="0"
                                            class="w-24 border-gray-300 rounded-md shadow-sm text-base font-mono text-right focus:border-horno focus:ring-horno">
                                        <span class="text-xs text-masa-madre">{{ $mass->recipe->yield_unit->short() }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <div>
                    <label for="notes" class="text-xs text-masa-madre">Notas</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="1000"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes', $batch->notes) }}</textarea>
                </div>

                @if($canEdit)
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                        <button type="submit" form="dough-batch-delete"
                            class="text-sm text-red-600 hover:text-red-700"
                            onclick="return window.confirm('¿Descartar este borrador?')">Descartar borrador</button>
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <button type="submit"
                                class="px-4 py-2.5 border border-miga text-sm text-corteza rounded-md hover:bg-miga transition-colors">Guardar</button>
                            <button type="submit" name="confirm" value="1"
                                onclick="return window.confirm('Se van a descontar los insumos y entra lo amasado. ¿Confirmar?')"
                                class="px-4 py-2.5 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">Confirmar amasado</button>
                        </div>
                    </div>
                @endif
            </form>

            @if($canEdit)
                <form id="dough-batch-delete" method="POST" action="{{ route('produccion.amasados.destroy', $batch) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

        </div>
    </div>
</x-app-layout>
