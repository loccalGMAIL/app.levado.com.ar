<x-app-layout>
    <x-slot name="title">Cierre de cámara #{{ $closing->number }}</x-slot>

    @php
        $canEdit = auth()->user()->can('manage-costs');
        $oldLines = collect(old('lines', []))->keyBy(fn ($line) => (int) ($line['product_id'] ?? 0));
        $initialLines = collect($lines)->map(function (array $line) use ($oldLines) {
            $old = $oldLines->get($line['product_id']);

            return $old === null ? $line : array_merge($line, [
                'counted' => $old['counted'] ?? '',
                'waste' => $old['waste_units'] ?? 0,
                'reason' => $old['waste_reason'] ?? '',
                'note' => $old['waste_note'] ?? '',
            ]);
        })->values()->all();
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8"
        x-data="{
            lines: @js($initialLines),
            reasons: @js($wasteReasons),
            fmt(value) {
                return Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 3 });
            },
            countedUnits(line) {
                const counted = Number(line.counted || 0);
                return line.container ? counted * line.container.per : counted;
            },
            baked(line) {
                return Math.round((Number(line.expected) - this.countedUnits(line) - Number(line.waste || 0)) * 1000) / 1000;
            },
            needsNote(line) {
                const reason = this.reasons.find(r => r.value === line.reason);
                return reason ? reason.note : false;
            },
        }">
        <div class="max-w-3xl space-y-5">

            <div class="space-y-1">
                <a href="{{ route('produccion.camara.index') }}" class="text-sm text-masa-madre hover:text-corteza">← Cierres de cámara</a>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-corteza">Cierre de cámara #{{ $closing->number }}</h2>
                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $closing->status->badgeClasses() }}">{{ $closing->status->label() }}</span>
                </div>
                <p class="text-sm text-masa-madre">Esperado = lo que había en cámara al abrir ({{ $closing->opened_at->format('d/m H:i') }}). Cargá lo que <strong>quedó</strong>: el resto se horneó.</p>
            </div>

            @if($errors->any())
                <div class="text-sm text-red-700 bg-red-50 rounded-md px-4 py-3">
                    @foreach($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <form id="chamber-closing-form" method="POST" action="{{ route('produccion.camara.update', $closing) }}" class="space-y-3">
                @csrf
                @method('PUT')

                <template x-if="lines.length === 0">
                    <p class="text-sm text-masa-madre bg-superficie rounded-lg shadow px-4 py-4">La cámara está vacía: no hay nada para contar.</p>
                </template>

                <template x-for="(line, index) in lines" :key="line.product_id">
                    <div class="bg-superficie rounded-lg shadow px-4 py-3 space-y-3">
                        <input type="hidden" :name="`lines[${index}][product_id]`" :value="line.product_id">

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-corteza" x-text="line.name"></p>
                                <p class="text-xs text-masa-madre">
                                    Esperado
                                    <template x-if="line.container">
                                        <span class="font-mono" x-text="fmt(Math.round(line.expected / line.container.per * 100) / 100) + ' ' + line.container.short + ' · '"></span>
                                    </template>
                                    <span class="font-mono" x-text="fmt(line.expected) + ' ' + line.unit"></span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[11px] text-masa-madre">Horneado</p>
                                <p class="font-mono font-semibold" :class="baked(line) < 0 ? 'text-red-600' : 'text-corteza'" x-text="fmt(baked(line)) + ' ' + line.unit"></p>
                            </div>
                        </div>

                        <label class="block">
                            <span class="text-xs text-masa-madre" x-text="line.container ? 'Quedaron (' + line.container.short + ', de a cuartos)' : 'Quedaron (' + line.unit + ')'"></span>
                            <input type="number" inputmode="decimal" min="0" :step="line.container ? 0.25 : 'any'"
                                :name="`lines[${index}][counted]`" x-model="line.counted" placeholder="0"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-base font-mono focus:border-horno focus:ring-horno">
                        </label>
                        <p x-show="baked(line) < 0" x-cloak class="text-xs text-red-600">Contaste más de lo esperado: ¿faltó registrar un amasado?</p>

                        <details class="text-sm" :open="Number(line.waste) > 0">
                            <summary class="text-xs text-masa-madre cursor-pointer select-none">Merma de horno</summary>
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="block">
                                    <span class="text-xs text-masa-madre">Cantidad (<span x-text="line.unit"></span>)</span>
                                    <input type="number" inputmode="decimal" step="any" min="0"
                                        :name="`lines[${index}][waste_units]`" x-model="line.waste"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-base font-mono focus:border-horno focus:ring-horno">
                                </label>
                                <label class="block">
                                    <span class="text-xs text-masa-madre">Motivo</span>
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
                        </details>
                    </div>
                </template>

                @if($canEdit)
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                        <button type="submit" form="chamber-closing-delete"
                            class="text-sm text-red-600 hover:text-red-700"
                            onclick="return confirm('¿Descartar este cierre?')">Descartar cierre</button>
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <button type="submit"
                                class="px-4 py-2.5 border border-miga text-sm text-corteza rounded-md hover:bg-miga transition-colors">Guardar</button>
                            <button type="submit" name="confirm" value="1"
                                onclick="return confirm('Lo horneado pasa a terminado y se descuentan sus descartables. ¿Confirmar?')"
                                class="px-4 py-2.5 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">Confirmar cierre</button>
                        </div>
                    </div>
                @endif
            </form>

            @if($canEdit)
                <form id="chamber-closing-delete" method="POST" action="{{ route('produccion.camara.destroy', $closing) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

        </div>
    </div>
</x-app-layout>
