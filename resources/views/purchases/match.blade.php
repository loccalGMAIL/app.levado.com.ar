<x-app-layout>
    <x-slot name="title">
        Vincular — {{ $purchase->invoice_number ? 'Factura #' . $purchase->invoice_number : 'Compra #' . $purchase->id }}
    </x-slot>

    @php
        $appliedCount   = $purchase->lines->filter(fn ($l) => $l->isApplied())->count();
        $suggestedCount = $purchase->lines->filter(fn ($l) => $l->isMatched() && ! $l->isApplied())->count();
        $total          = $purchase->lines->count();
    @endphp

    <script>window.MATCH_CATALOG = @json($ingredientCatalog);</script>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Header --}}
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="space-y-1">
                        <div class="flex items-center gap-3 text-sm text-masa-madre">
                            <a href="{{ route('purchases.index') }}" class="hover:text-corteza transition-colors">← Compras</a>
                            <span class="text-masa-madre/30">|</span>
                            <a href="{{ route('purchases.show', $purchase) }}" class="hover:text-corteza transition-colors">Ver factura</a>
                        </div>
                        <h2 class="text-base font-semibold text-corteza mt-2">Vincular renglones con catálogo</h2>
                        <p class="text-sm text-masa-madre">
                            <span class="font-medium text-corteza">{{ $purchase->supplier->name }}</span>
                            &mdash;
                            {{ $purchase->invoice_number ? 'Factura N° ' . $purchase->invoice_number : 'Sin N° de factura' }}
                            &mdash; {{ $purchase->invoice_date->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4 shrink-0">
                        <div class="text-right">
                            <p class="text-2xl font-mono font-semibold {{ $appliedCount === $total ? 'text-green-600' : 'text-corteza' }}">
                                {{ $appliedCount }}<span class="text-masa-madre font-normal text-base">/{{ $total }}</span>
                            </p>
                            <p class="text-xs text-masa-madre">con costo aplicado</p>
                        </div>
                        @if($suggestedCount > 0)
                            <form method="POST" action="{{ route('purchases.apply-suggestions', $purchase) }}">
                                @csrf
                                <button type="submit"
                                    class="px-4 py-2 bg-horno text-white text-sm rounded-md hover:opacity-90 transition-opacity whitespace-nowrap">
                                    Aplicar {{ $suggestedCount }} sugerencia{{ $suggestedCount > 1 ? 's' : '' }} de la IA
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if($appliedCount === $total && $total > 0)
                    <p class="mt-4 flex items-center gap-1.5 text-green-700 text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Todos los renglones tienen costo aplicado.
                    </p>
                @endif
            </div>

            {{-- Tabla --}}
            @if($purchase->lines->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    Esta compra no tiene renglones. Agregalos desde
                    <a href="{{ route('purchases.show', $purchase) }}" class="underline hover:text-corteza">Ver factura</a>.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium w-[32%]">Descripción (factura)</th>
                                <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Precio unit.</th>
                                <th class="px-4 py-3 font-medium">Insumo o descartable</th>
                                <th class="px-4 py-3 font-medium">Costo unitario</th>
                                <th class="px-4 py-3 font-medium w-[8%]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($purchase->lines as $line)
                                @php
                                    $currentValue = $line->isMatched()
                                        ? "{$line->purchaseable_type}:{$line->purchaseable_id}"
                                        : '';
                                    $matchedName = null;
                                    if ($line->purchaseable_type === 'ingredient') {
                                        $matchedName = $ingredients->firstWhere('id', $line->purchaseable_id)?->name;
                                    } elseif ($line->purchaseable_type === 'packaging') {
                                        $matchedName = $packagings->firstWhere('id', $line->purchaseable_id)?->name;
                                    }
                                @endphp

                                @if($line->isApplied())
                                    {{-- Renglón ya aplicado — estático --}}
                                    @php
                                        $appliedIngredient = $line->isIngredient()
                                            ? $ingredients->firstWhere('id', $line->purchaseable_id)
                                            : null;
                                    @endphp
                                    <tr class="bg-green-50/40">
                                        <td class="px-4 py-3 text-corteza text-xs" title="{{ $line->raw_name }}">
                                            {{ \Illuminate\Support\Str::limit($line->raw_name ?? '—', 65) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-xs text-masa-madre whitespace-nowrap">
                                            ${{ number_format($line->unit_price, 2, ',', '.') }}
                                            <span class="text-masa-madre/60">/{{ $line->purchase_unit->short() }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-corteza text-sm">
                                            {{ $matchedName ?? '—' }}
                                            @if($appliedIngredient?->subdivisions)
                                                <span class="block text-[11px] font-normal text-masa-madre">
                                                    {{ $appliedIngredient->subdivisions }} {{ $appliedIngredient->subdivision_label ?? 'u' }} / envase
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                                Aplicado {{ $line->cost_applied_at->format('d/m H:i') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <form method="POST"
                                                action="{{ route('purchases.lines.match', [$purchase, $line]) }}">
                                                @csrf
                                                <input type="hidden" name="match" value="">
                                                <button type="submit"
                                                    class="text-xs text-masa-madre hover:text-red-500 transition-colors"
                                                    title="Desasocia el renglón (el costo ya aplicado no se revierte).">
                                                    Desasociar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @else
                                    {{-- Renglón pendiente o sugerido — interactivo --}}
                                    <tr x-data="matchRow(
                                        {{ Js::from($currentValue) }},
                                        {{ (float) $line->unit_price }},
                                        {{ Js::from($line->purchase_unit->value) }},
                                        {{ Js::from($line->raw_name ?? '') }}
                                    )">
                                        <td class="px-4 py-3 align-top">
                                            <span class="text-xs text-corteza" title="{{ $line->raw_name }}">
                                                {{ \Illuminate\Support\Str::limit($line->raw_name ?? '—', 65) }}
                                            </span>
                                            @if($line->isMatched())
                                                <span class="block text-xs text-amber-600 mt-0.5">Sugerido por IA</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-xs text-corteza align-top whitespace-nowrap">
                                            ${{ number_format($line->unit_price, 2, ',', '.') }}
                                            <span class="text-masa-madre">/{{ $line->purchase_unit->short() }}</span>
                                        </td>

                                        {{-- Columnas interactivas --}}
                                        <td class="px-4 py-3 align-top" colspan="3">
                                            <form method="POST"
                                                action="{{ route('purchases.lines.match', [$purchase, $line]) }}"
                                                class="space-y-2">
                                                @csrf
                                                <input type="hidden" name="match" x-bind:value="selected">
                                                <input type="hidden" name="unit_cost" x-bind:value="unitCost > 0 ? unitCost.toFixed(4) : ''">

                                                <div class="flex items-start gap-3 flex-wrap">
                                                    {{-- Select de insumo/descartable --}}
                                                    <select x-model="selected" @change="onSelect()"
                                                        x-init="$nextTick(() => { new TomSelect($el, { maxOptions: null, dropdownParent: 'body' }); })"
                                                        class="text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm py-1.5 min-w-[220px]">
                                                        <option value="">— sin asociar —</option>
                                                        @if($ingredients->isNotEmpty())
                                                            <optgroup label="Insumos">
                                                                @foreach($ingredients as $ing)
                                                                    <option value="ingredient:{{ $ing->id }}"
                                                                        @selected($currentValue === "ingredient:{$ing->id}")>
                                                                        {{ $ing->name }}
                                                                        @if($ing->subdivisions)
                                                                            ({{ $ing->subdivisions }} {{ $ing->subdivision_label ?? 'u' }} / envase)
                                                                        @else
                                                                            ({{ $ing->unit->short() }})
                                                                        @endif
                                                                    </option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                        @if($packagings->isNotEmpty())
                                                            <optgroup label="Descartables">
                                                                @foreach($packagings as $pkg)
                                                                    <option value="packaging:{{ $pkg->id }}"
                                                                        @selected($currentValue === "packaging:{$pkg->id}")>
                                                                        {{ $pkg->name }}
                                                                    </option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                    </select>

                                                    {{-- Error: descartable + unidad incompatible --}}
                                                    <p x-show="incompatiblePkg" x-cloak class="text-xs text-red-500 self-center">
                                                        Los descartables solo se compran por unidad (u).
                                                    </p>

                                                    {{-- Cálculo del costo unitario --}}
                                                    <div x-show="catalogUnit && !incompatiblePkg" x-cloak
                                                        class="flex items-center gap-2 flex-wrap">

                                                        {{-- Divisor (solo cuando unidades incompatibles: u → kg/gr/etc) --}}
                                                        <template x-if="needsPkgQty">
                                                            <div class="flex items-center gap-1 text-xs text-masa-madre">
                                                                <span>÷</span>
                                                                <input type="number"
                                                                    x-model.number="pkgQty"
                                                                    @input="onPkgQtyChange()"
                                                                    min="0.001" step="0.001"
                                                                    class="w-20 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm py-1 text-center font-mono"
                                                                    :class="pkgQtyFromDesc ? 'border-amber-300 bg-amber-50' : ''">
                                                                <span x-text="catalogUnit + '/u'"></span>
                                                                <span x-show="pkgQtyFromDesc" x-cloak
                                                                    class="text-amber-600"
                                                                    title="Cantidad detectada automáticamente en la descripción del producto">
                                                                    ✦
                                                                </span>
                                                            </div>
                                                        </template>

                                                        {{-- Costo por unidad del catálogo (siempre editable) --}}
                                                        <div class="flex items-center gap-1 text-xs text-masa-madre">
                                                            <span>=&nbsp;$</span>
                                                            <input type="number"
                                                                x-model.number="unitCost"
                                                                min="0" step="0.01"
                                                                data-maxdecimals="4"
                                                                class="w-28 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm py-1 text-right font-mono">
                                                            <span>/</span>
                                                            <span x-text="displayUnit || catalogUnit" class="font-medium text-corteza"></span>
                                                            <template x-if="subdivisions">
                                                                <span class="text-masa-madre/60 ml-1">
                                                                    (1 envase = <span x-text="subdivisions"></span> <span x-text="subdivisionLabel || 'u'"></span>)
                                                                </span>
                                                            </template>
                                                        </div>

                                                        {{-- Botón --}}
                                                        <button type="submit"
                                                            :disabled="!selected || incompatiblePkg || unitCost <= 0"
                                                            class="px-3 py-1.5 bg-corteza text-white text-sm rounded hover:bg-horno transition-colors disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                                                            {{ $line->isMatched() ? 'Aplicar sugerencia' : 'Asociar' }}
                                                        </button>
                                                    </div>

                                                    {{-- Botón cuando aún no hay catálogUnit (selección recién elegida sin calcular) --}}
                                                    <div x-show="selected && !catalogUnit && !incompatiblePkg" x-cloak>
                                                        <button type="submit"
                                                            class="px-3 py-1.5 bg-corteza text-white text-sm rounded hover:bg-horno transition-colors">
                                                            Asociar
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($appliedCount < $total)
                    <p class="text-xs text-masa-madre">
                        ✦ cantidad detectada en la descripción del producto — verificá antes de aplicar.
                        Los renglones pendientes no actualizan ningún costo hasta asociarlos.
                    </p>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
