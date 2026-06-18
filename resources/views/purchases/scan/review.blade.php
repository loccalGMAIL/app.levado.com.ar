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

            @if(isset($possibleDuplicate) && $possibleDuplicate)
                <div class="p-4 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg text-sm flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 text-yellow-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <span>
                        Ya existe un comprobante con este número para este proveedor
                        (cargado el {{ $possibleDuplicate->invoice_date->format('d/m/Y') }}).
                        <a href="{{ route('purchases.show', $possibleDuplicate) }}"
                            class="font-medium underline hover:text-yellow-900">
                            Ver comprobante →
                        </a>
                        Podés continuar igual si es una compra diferente.
                    </span>
                </div>
            @endif

            <div>
                <h2 class="text-base font-semibold text-corteza">Revisá lo que se leyó de la factura</h2>
                <p class="text-sm text-masa-madre mt-0.5">
                    Corregí lo que haga falta y guardá la compra. Después vas a asociar cada renglón con tus insumos para actualizar los costos.
                </p>
            </div>

            <form method="POST" action="{{ route('purchases.scan.store') }}" class="space-y-6"
                x-data="{
                    globalIva: '0.21',
                    globalPercepcion: '',
                    applyToAll() {
                        $dispatch('apply-tax-defaults', { ivaRate: this.globalIva, percepcionRate: this.globalPercepcion });
                    }
                }">
                @csrf
                <input type="hidden" name="invoice_image_path" value="{{ $imagePath }}">
                <input type="hidden" name="invoice_total" value="{{ $header['total'] }}">
                <input type="hidden" name="default_iva_rate" :value="globalIva">
                <input type="hidden" name="default_percepcion_rate" :value="globalPercepcion">

                {{-- Cabecera --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2"
                                x-data="{ supplierTs: null }"
                                @supplier-created.window="
                                    if (supplierTs) {
                                        supplierTs.addOption({ value: String($event.detail.id), text: $event.detail.name });
                                        supplierTs.setValue(String($event.detail.id));
                                    }
                                ">
                                <x-input-label value="Proveedor" />
                                <select name="supplier_id" required
                                    x-init="$nextTick(() => {
                                        supplierTs = new TomSelect($el, { maxOptions: null });
                                        const initialId = '{{ old('supplier_id', $matchedSupplierId ?? '') }}';
                                        if (initialId) supplierTs.setValue(initialId);
                                    })"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                    <option value="">— Seleccioná un proveedor —</option>
                                    @foreach($suppliers as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
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

                {{-- Control global IVA y percepciones --}}
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm font-semibold text-corteza mb-3">IVA y percepciones de la factura</p>
                    <div class="flex flex-wrap items-end gap-5">
                        <div class="space-y-1.5">
                            <p class="text-xs font-medium text-masa-madre">Alícuota IVA</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" x-model="globalIva" value="0"
                                        class="text-corteza focus:ring-horno border-gray-300">
                                    Sin IVA
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" x-model="globalIva" value="0.105"
                                        class="text-corteza focus:ring-horno border-gray-300">
                                    10,5%
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" x-model="globalIva" value="0.21"
                                        class="text-corteza focus:ring-horno border-gray-300">
                                    21%
                                </label>
                            </div>
                        </div>

                        <div x-show="globalIva !== '0'" x-cloak class="space-y-1.5">
                            <p class="text-xs font-medium text-masa-madre">Percepción</p>
                            <div class="flex items-center gap-1.5">
                                <input type="number" x-model="globalPercepcion"
                                    step="0.01" min="0" max="100"
                                    placeholder="0"
                                    class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-24 text-right">
                                <span class="text-sm text-masa-madre">%</span>
                            </div>
                        </div>

                        <button type="button" @click="applyToAll()"
                            class="px-4 py-2 text-sm font-medium bg-corteza text-white rounded-md hover:bg-horno transition-colors shrink-0">
                            Aplicar a todos los renglones
                        </button>
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
                                        <th class="px-3 py-3 font-medium text-center">IVA</th>
                                        <th class="px-3 py-3 font-medium text-center">Perc. %</th>
                                        <th class="px-3 py-3 font-medium text-right">IVA $</th>
                                        <th class="px-3 py-3 font-medium text-right">Perc. $</th>
                                        <th class="px-3 py-3 font-medium text-right">Total</th>
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
                                            qty:             {{ $initQty }},
                                            price:           {{ $initPrice }},
                                            ivaRate:         0.21,
                                            percepcionRate:  0,
                                            get net()              { return this.qty * this.price; },
                                            get ivaAmount()        { return this.net * this.ivaRate; },
                                            get percepcionAmount() { return this.net * (this.percepcionRate / 100); },
                                            get lineTotal()        { return this.net * (1 + this.ivaRate + this.percepcionRate / 100); },
                                            fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
                                        }"
                                        @apply-tax-defaults.window="
                                            ivaRate = parseFloat($event.detail.ivaRate) || 0;
                                            percepcionRate = parseFloat($event.detail.percepcionRate) || 0;
                                        ">
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
                                                    data-maxdecimals="4"
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
                                                        step="0.0001" min="0"
                                                        data-maxdecimals="4"
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

                                            <td class="px-3 py-3 align-top text-center">
                                                <div class="flex items-center gap-1 justify-center">
                                                    <input type="number" name="lines[{{ $i }}][percepcion_rate]"
                                                        x-model.number="percepcionRate"
                                                        step="0.01" min="0" max="100"
                                                        placeholder="0"
                                                        class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-16 text-right">
                                                    <span class="text-xs text-masa-madre">%</span>
                                                </div>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <span class="text-sm font-mono text-masa-madre"
                                                    x-text="'$ ' + fmt(ivaAmount)"></span>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <span class="text-sm font-mono text-masa-madre"
                                                    x-text="percepcionRate > 0 ? '$ ' + fmt(percepcionAmount) : '—'"></span>
                                            </td>

                                            <td class="px-3 py-3 align-top text-right">
                                                <span class="text-sm font-mono font-medium text-corteza"
                                                    x-text="'$ ' + fmt(lineTotal)"></span>
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
