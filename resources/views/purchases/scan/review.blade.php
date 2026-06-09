<x-app-layout>
    <x-slot name="title">Revisar factura</x-slot>

    @php
        $header = $draft['header'];
    @endphp

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div>
                <a href="{{ route('purchases.scan.create') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Leer otra factura
                </a>
            </div>

            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div>
                <h2 class="text-base font-semibold text-corteza">Revisá lo que se leyó de la factura</h2>
                <p class="text-sm text-masa-madre mt-0.5">
                    Corregí lo que haga falta y guardá la compra. Después vas a asociar cada renglón con tus insumos para actualizar los costos.
                </p>
            </div>

            <form method="POST" action="{{ route('purchases.scan.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="invoice_image_path" value="{{ $imagePath }}">
                <input type="hidden" name="invoice_total" value="{{ $header['total'] }}">

                {{-- Cabecera --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2"
                                x-data="{
                                    suppliers: {{ Js::from($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()) }},
                                    selectedSupplierId: '{{ old('supplier_id', $matchedSupplierId ?? '') }}',
                                }"
                                @supplier-created.window="
                                    suppliers.push({ id: $event.detail.id, name: $event.detail.name });
                                    selectedSupplierId = String($event.detail.id);
                                ">
                                <x-input-label value="Proveedor" />
                                <select name="supplier_id" required
                                    x-model="selectedSupplierId"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                    <option value="">— Seleccioná un proveedor —</option>
                                    <template x-for="s in suppliers" :key="s.id">
                                        <option :value="String(s.id)" x-text="s.name"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('supplier_id')" class="mt-1" />
                                @if(! $matchedSupplierId && $header['supplier_name'])
                                    <p class="mt-1 text-xs text-horno">
                                        En la factura figura «{{ $header['supplier_name'] }}»{{ $header['cuit'] ? ' — CUIT '.$header['cuit'] : '' }}.
                                        No coincide con ningún proveedor. Elegí uno o
                                        <button type="button"
                                            @click="$dispatch('open-modal', 'supplier-quick-create')"
                                            class="underline hover:text-corteza transition-colors">
                                            creálo acá
                                        </button>.
                                    </p>
                                @endif
                            </div>

                            <div>
                                <x-input-label value="N° de factura" />
                                <x-text-input name="invoice_number" type="text"
                                    class="mt-1 block w-full"
                                    :value="old('invoice_number', $header['invoice_number'])" />
                                <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Fecha" />
                                <x-text-input name="invoice_date" type="date"
                                    class="mt-1 block w-full"
                                    :value="old('invoice_date', $header['invoice_date'])"
                                    required />
                                <x-input-error :messages="$errors->get('invoice_date')" class="mt-1" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label value="Notas" />
                                <textarea name="notes" rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-medium text-masa-madre">Factura</p>
                            <a href="{{ Storage::url($imagePath) }}" target="_blank" class="block">
                                @if(str_ends_with(strtolower($imagePath), '.pdf'))
                                    <div class="border border-miga rounded-md p-6 text-center text-sm text-masa-madre hover:border-horno transition-colors">
                                        Ver PDF de la factura →
                                    </div>
                                @else
                                    <img src="{{ Storage::url($imagePath) }}" alt="Factura"
                                        class="rounded-md border border-miga max-h-56 w-full object-contain bg-miga/30">
                                @endif
                            </a>
                            @if($header['total'])
                                <p class="text-xs text-masa-madre">
                                    Total leído en la factura:
                                    <span class="font-mono text-corteza">${{ number_format($header['total'], 2, ',', '.') }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Renglones detectados --}}
                <div>
                    <h3 class="text-sm font-semibold text-corteza mb-3">Renglones detectados ({{ count($draft['lines']) }})</h3>

                    @if(empty($draft['lines']))
                        <div class="bg-white rounded-lg shadow p-6 text-center text-masa-madre text-sm">
                            No se detectaron renglones en la factura. Podés cargarlos manualmente desde la compra.
                        </div>
                    @else
                        <div class="bg-white rounded-lg shadow overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-miga text-masa-madre border-b border-miga">
                                    <tr>
                                        <th class="px-3 py-3 font-medium text-center">Guardar</th>
                                        <th class="px-3 py-3 font-medium">Descripción detectada</th>
                                        <th class="px-3 py-3 font-medium text-right">Cantidad</th>
                                        <th class="px-3 py-3 font-medium">Unidad</th>
                                        <th class="px-3 py-3 font-medium text-right">Precio unit.</th>
                                        <th class="px-3 py-3 font-medium text-center">Alíc. IVA</th>
                                        <th class="px-3 py-3 font-medium text-right">IVA $</th>
                                        <th class="px-3 py-3 font-medium text-right">Subtotal c/IVA</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    @foreach($draft['lines'] as $i => $line)
                                        @php
                                            $suggLabel = null;
                                            if (($line['matched_type'] ?? null) === 'ingredient') {
                                                $suggLabel = $ingredientNames[$line['matched_id']] ?? null;
                                            } elseif (($line['matched_type'] ?? null) === 'packaging') {
                                                $suggLabel = $packagingNames[$line['matched_id']] ?? null;
                                            }
                                            $initQty   = is_numeric($line['quantity'])   ? (float) $line['quantity']   : 0;
                                            $initPrice = is_numeric($line['unit_price']) ? (float) $line['unit_price'] : 0;
                                        @endphp
                                        <tr x-data="{
                                            qty:     {{ $initQty }},
                                            price:   {{ $initPrice }},
                                            ivaRate: 0.21,
                                            get ivaAmount()   { return this.qty * this.price * this.ivaRate; },
                                            get subtotalIva() { return this.qty * this.price * (1 + this.ivaRate); },
                                            fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
                                        }">
                                            <td class="px-3 py-3 text-center align-top">
                                                <input type="checkbox" name="lines[{{ $i }}][include]" value="1"
                                                    @checked(old("lines.$i.include", true))
                                                    class="rounded border-gray-300 text-corteza focus:ring-horno mt-2">
                                            </td>

                                            <td class="px-3 py-3 align-top min-w-[14rem]">
                                                <input type="text" name="lines[{{ $i }}][raw_name]"
                                                    value="{{ old("lines.$i.raw_name", $line['raw_name']) }}"
                                                    class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                                <input type="hidden" name="lines[{{ $i }}][matched_type]" value="{{ old("lines.$i.matched_type", $line['matched_type']) }}">
                                                <input type="hidden" name="lines[{{ $i }}][matched_id]" value="{{ old("lines.$i.matched_id", $line['matched_id']) }}">
                                                @if($suggLabel)
                                                    <p class="mt-1 text-[11px] text-masa-madre">Se sugerirá: <span class="text-corteza">{{ $suggLabel }}</span></p>
                                                @else
                                                    <p class="mt-1 text-[11px] text-amber-700">Sin sugerencia — lo asociás después</p>
                                                @endif
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <input type="number" name="lines[{{ $i }}][quantity_purchased]"
                                                    x-model.number="qty"
                                                    step="0.0001" min="0.0001"
                                                    value="{{ old("lines.$i.quantity_purchased", $line['quantity'] !== null ? rtrim(rtrim(number_format($line['quantity'], 4, '.', ''), '0'), '.') : '') }}"
                                                    class="block w-24 border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm text-right">
                                            </td>

                                            <td class="px-3 py-3 align-top">
                                                <select name="lines[{{ $i }}][purchase_unit]"
                                                    class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                                    @foreach($units as $unit)
                                                        <option value="{{ $unit->value }}"
                                                            @selected(old("lines.$i.purchase_unit", $line['unit']) === $unit->value)>
                                                            {{ $unit->short() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-2 flex items-center text-masa-madre text-xs">$</span>
                                                    <input type="number" name="lines[{{ $i }}][unit_price]"
                                                        x-model.number="price"
                                                        step="0.01" min="0"
                                                        value="{{ old("lines.$i.unit_price", $line['unit_price']) }}"
                                                        class="block w-28 pl-5 border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm text-right">
                                                </div>
                                            </td>

                                            <td class="px-3 py-3 align-top text-center">
                                                <select name="lines[{{ $i }}][iva_rate]" x-model.number="ivaRate"
                                                    class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm text-center">
                                                    <option value="0.21">21%</option>
                                                    <option value="0.105">10,5%</option>
                                                    <option value="0">0%</option>
                                                </select>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <span class="text-sm font-mono text-masa-madre"
                                                    x-text="'$ ' + fmt(ivaAmount)"></span>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <span class="text-sm font-mono font-medium text-corteza"
                                                    x-text="'$ ' + fmt(subtotalIva)"></span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-masa-madre">
                            El precio es por la unidad indicada (ej. $/kg). Todavía no se toca ningún costo: eso pasa cuando asociás los renglones en el detalle de la compra.
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('purchases.index') }}" class="text-sm text-masa-madre hover:underline">Cancelar</a>
                    <x-primary-button>Guardar compra</x-primary-button>
                </div>
            </form>

        </div>
    </div>

    @include('suppliers.modals.quick-create')
</x-app-layout>
