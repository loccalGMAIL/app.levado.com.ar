<x-app-layout>
    <x-slot name="title">
        {{ $purchase->invoice_number ? 'Factura #' . $purchase->invoice_number : 'Compra #' . $purchase->id }}
    </x-slot>

    @php
        $errorsInAddLine      = $errors->hasAny(['raw_name', 'quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'add-line';
        $errorsInEditLine     = $errors->hasAny(['quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'edit-line';
        $errorsInEditPurchase = $errors->hasAny(['supplier_id', 'invoice_number', 'invoice_date', 'notes', 'default_iva_rate', 'default_percepcion_rate', 'invoice']) && old('_form') === 'edit-purchase';

        $editingLine = null;
        if ($errorsInEditLine) {
            $editingLine = [
                'id'                 => old('line_id'),
                'raw_name'           => old('raw_name'),
                'quantity_purchased' => old('quantity_purchased'),
                'purchase_unit'      => old('purchase_unit'),
                'unit_price'         => old('unit_price'),
                'iva_rate'           => old('iva_rate'),
                'percepcion_rate'    => old('percepcion_rate'),
            ];
        }

        $resolvedCount = $purchase->lines->filter(fn ($l) => $l->isResolved())->count();
        $excludedLines = $purchase->lines->filter(fn ($l) => $l->isExcluded());
        $totalLines    = $purchase->lines->count();
        $allResolved   = $totalLines > 0 && $resolvedCount === $totalLines;
        $personalTotal = $excludedLines->sum(fn ($l) => (float) $l->subtotal);

        $totalSubtotal   = $purchase->lines->sum(fn ($l) => (float) $l->subtotal);
        $totalIva        = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->iva_rate);
        $totalPercepcion = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * ((float) ($l->percepcion_rate ?? 0) / 100));
        $grandTotal      = $totalSubtotal + $totalIva + $totalPercepcion;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editingLine: {{ Js::from($editingLine) }},
            openEdit(line) {
                this.editingLine = line;
                $dispatch('open-modal', 'line-edit');
            },
            mobileExpanded: false,
            tfootSubtotal: {{ $totalSubtotal }},
            tfootIva: {{ $totalIva }},
            tfootPercepcion: {{ $totalPercepcion }},
            tfootGrandTotal: {{ $grandTotal }},
            fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        }"
        @line-price-saved.window="
            tfootSubtotal   = $event.detail.total_subtotal;
            tfootIva        = $event.detail.total_iva;
            tfootPercepcion = $event.detail.total_percepcion;
            tfootGrandTotal = $event.detail.grand_total;
        ">

        {{-- Botón volver --}}
        <div class="px-6 pt-4 pb-0">
            <a href="{{ route('purchases.index') . (request()->filled('volver') ? '?' . request('volver') : '') }}"
                class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a compras
            </a>
        </div>

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

            {{-- Header de la compra --}}
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-corteza">
                                {{ $purchase->invoice_number ? 'Factura N° ' . $purchase->invoice_number : 'Compra sin número de factura' }}
                            </h2>
                            @can('manage-costs')
                                <button type="button"
                                    @click="$dispatch('open-modal', 'purchase-edit')"
                                    class="p-1 text-masa-madre hover:text-corteza transition-colors"
                                    title="Editar compra">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z" />
                                    </svg>
                                </button>
                            @endcan
                        </div>
                        <p class="text-sm text-masa-madre">
                            <span class="font-medium text-corteza">{{ $purchase->supplier->name }}</span>
                            &mdash; {{ $purchase->invoice_date->format('d/m/Y') }}
                        </p>
                        @if($purchase->notes)
                            <p class="text-sm text-masa-madre italic">{{ $purchase->notes }}</p>
                        @endif
                        @if($purchase->invoice_image_path)
                            <button type="button"
                                @click="$dispatch('open-modal', 'invoice-image')"
                                class="inline-flex items-center gap-1.5 text-sm text-corteza hover:text-horno transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Ver factura original
                            </button>
                        @endif
                    </div>
                    {{-- En mobile los totales bajan a su propia fila con el ancho completo:
                         apilados a la derecha con `shrink-0` empujaban el monto fuera de la card. --}}
                    <div class="sm:text-right sm:shrink-0 space-y-2">
                        <div>
                            <p class="text-xs text-masa-madre">Total renglones{{ $totalIva > 0 ? ' (sin IVA)' : '' }}</p>
                            <p class="text-lg sm:text-xl font-mono font-semibold text-corteza [overflow-wrap:anywhere]">
                                ${{ number_format($purchase->totalAmount(), 2, ',', '.') }}
                            </p>
                        </div>
                        @if($purchase->invoice_total && $totalIva > 0)
                            <div>
                                <p class="text-xs text-masa-madre">Total factura (con IVA)</p>
                                <p class="text-sm font-mono text-corteza [overflow-wrap:anywhere]">
                                    ${{ number_format($purchase->invoice_total, 2, ',', '.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Renglones digitalizados (factura tal cual) --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-corteza">Renglones de la factura</h3>
                    @can('manage-costs')
                        <button type="button"
                            @click="$dispatch('open-modal', 'add-line')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-corteza border border-corteza/30 rounded-md hover:bg-miga transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar renglón
                        </button>
                    @endcan
                </div>

                {{-- Banner de vinculación --}}
                @if($totalLines > 0)
                    @if($allResolved)
                        <div class="mb-3 flex items-center gap-2 px-4 py-2.5 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            No queda ningún renglón por resolver
                        </div>
                    @else
                        <div class="mb-3 flex items-center justify-between gap-4 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                                {{ $resolvedCount }} de {{ $totalLines }} {{ $totalLines === 1 ? 'renglón resuelto' : 'renglones resueltos' }}
                            </div>
                            @can('manage-costs')
                                <a href="{{ route('purchases.match', $purchase) }}"
                                    class="shrink-0 font-medium hover:underline">
                                    Vincular renglones →
                                </a>
                            @endcan
                        </div>
                    @endif
                @endif

                @if($purchase->lines->isEmpty())
                    <div class="bg-white rounded-lg shadow p-6 text-center text-masa-madre text-sm">
                        No hay renglones. Agregá uno con el botón de arriba.
                    </div>
                @else
                    {{-- Vista tarjetas: mobile (oculta cuando se expande la tabla) --}}
                    <div :class="mobileExpanded ? 'hidden' : 'md:hidden'"
                         class="bg-white rounded-lg shadow divide-y divide-miga">
                        @foreach($purchase->lines as $line)
                            @php
                                $mSub   = (float) $line->subtotal;
                                $mTotal = $mSub * (1 + (float) $line->iva_rate + ((float) ($line->percepcion_rate ?? 0) / 100));
                            @endphp
                            <div class="px-4 py-3 space-y-1.5">
                                <p class="font-medium text-corteza text-sm">{{ $line->raw_name ?? '—' }}</p>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1 min-w-0">
                                        <p class="text-masa-madre text-xs">
                                            {{ number_format($line->quantity_purchased, 2, ',', '.') }}
                                            {{ $line->purchase_unit->short() }}
                                            &middot; ${{ number_format($line->unit_price, 2, ',', '.') }}/u
                                        </p>
                                        @if($line->isExcluded())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-miga text-masa-madre"
                                                @if($line->exclusion_note) title="{{ $line->exclusion_note }}" @endif>
                                                Sin insumo
                                            </span>
                                        @elseif($line->isApplied() && $line->isBonus())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700"
                                                title="Obsequio o promoción: entró al stock el {{ $line->cost_applied_at->format('d/m/Y') }} sin modificar el costo del insumo.">
                                                Sin cargo
                                            </span>
                                        @elseif($line->isApplied())
                                            <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                                Aplicado
                                            </span>
                                        @elseif($line->isMatched())
                                            <span class="inline-flex items-center gap-1 text-amber-600 text-xs font-medium">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                                                </svg>
                                                Pendiente
                                            </span>
                                        @else
                                            <span class="text-masa-madre/60 text-xs">Sin vincular</span>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="font-mono font-semibold text-corteza whitespace-nowrap text-sm">
                                            ${{ number_format($mTotal, 2, ',', '.') }}
                                        </p>
                                        <p class="text-masa-madre text-xs whitespace-nowrap">
                                            Sub: ${{ number_format($mSub, 2, ',', '.') }}
                                        </p>
                                    </div>
                                </div>
                                @can('manage-costs')
                                    <div class="flex items-center gap-2 pt-0.5">
                                        <button type="button"
                                            @click="openEdit({{ Js::from([
                                                'id' => $line->id,
                                                'raw_name' => $line->raw_name,
                                                'quantity_purchased' => round((float) $line->quantity_purchased, 2),
                                                'purchase_unit' => $line->purchase_unit->value,
                                                'unit_price' => round((float) $line->unit_price, 2),
                                                'iva_rate' => $line->iva_rate,
                                                'percepcion_rate' => $line->percepcion_rate,
                                                'is_bonus' => $line->isBonus(),
                                            ]) }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-corteza border border-corteza/30 rounded-md hover:bg-miga transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z" />
                                            </svg>
                                            Editar
                                        </button>
                                        <form method="POST"
                                            action="{{ route('purchases.lines.destroy', [$purchase, $line]) }}"
                                            onsubmit="return confirm('¿Eliminar este renglón?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-red-600 border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                </svg>
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @endforeach

                        {{-- Total mobile --}}
                        <div class="px-4 py-3 bg-miga/30 flex items-center justify-between gap-4">
                            <span class="text-xs font-semibold text-corteza">{{ $totalIva > 0 ? 'Total con IVA' : 'Total' }}</span>
                            <span class="font-mono font-semibold text-corteza whitespace-nowrap"
                                  x-text="'$ ' + fmt(tfootGrandTotal)"></span>
                        </div>
                        @if($excludedLines->isNotEmpty())
                            <div class="px-4 pb-3 -mt-1 flex items-center justify-between gap-4 bg-miga/30">
                                <span class="text-xs text-masa-madre">Sin insumo (no imputado al catálogo)</span>
                                <span class="font-mono text-xs text-masa-madre whitespace-nowrap">
                                    $ {{ number_format($personalTotal, 2, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        {{-- Expandir a tabla completa --}}
                        <div class="px-4 py-2.5 flex justify-center">
                            <button type="button" @click="mobileExpanded = true"
                                class="inline-flex items-center gap-1.5 text-xs text-masa-madre hover:text-corteza transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                </svg>
                                Ver tabla completa
                            </button>
                        </div>
                    </div>

                    {{-- Tabla completa: siempre visible en desktop; en mobile solo al expandir --}}
                    <div :class="mobileExpanded ? '' : 'hidden md:block'"
                         class="bg-white rounded-lg shadow overflow-x-auto">
                        {{-- Botón colapsar (solo mobile) --}}
                        <div class="md:hidden px-4 py-2.5 border-b border-miga flex items-center justify-between">
                            <span class="text-xs font-medium text-masa-madre">Vista completa</span>
                            <button type="button" @click="mobileExpanded = false"
                                class="inline-flex items-center gap-1 text-xs font-medium text-corteza hover:text-horno transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Vista simple
                            </button>
                        </div>
                        <table class="w-full text-xs text-left">
                            <thead class="bg-miga text-masa-madre border-b border-miga">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Descripción</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Cantidad</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Precio unitario</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Subtotal</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">IVA $</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Percepción $</th>
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">Total</th>
                                    <th class="px-4 py-3 font-medium text-center">Costo</th>
                                    @can('manage-costs')
                                        <th class="px-4 py-3"></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($purchase->lines as $line)
                                    <tr x-data="{
                                            editing: false,
                                            saving: false,
                                            isDirty: false,
                                            price: {{ (float) $line->unit_price }},
                                            qty: {{ (float) $line->quantity_purchased }},
                                            ivaRate: {{ (float) $line->iva_rate }},
                                            percepcionRate: {{ (float) ($line->percepcion_rate ?? 0) }},
                                            get subtotal() { return this.qty * this.price; },
                                            get ivaAmount() { return this.subtotal * this.ivaRate; },
                                            get percepcionAmount() { return this.subtotal * (this.percepcionRate / 100); },
                                            get lineTotal() { return this.subtotal + this.ivaAmount + this.percepcionAmount; },
                                            fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                                            startEdit() {
                                                this.isDirty = false;
                                                this.$refs.priceInput.value = parseFloat(this.price).toFixed(2);
                                                this.editing = true;
                                                this.$nextTick(() => this.$refs.priceInput.select());
                                            },
                                            async savePrice() {
                                                if (this.saving) return;
                                                if (!this.isDirty) { this.editing = false; return; }
                                                const raw = this.$refs.priceInput.value.trim();
                                                if (raw === '') { this.editing = false; return; }
                                                this.saving = true;
                                                this.editing = false;
                                                try {
                                                    const res = await fetch('{{ route('purchases.lines.price.update', [$purchase, $line]) }}', {
                                                        method: 'PATCH',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                            'Accept': 'application/json',
                                                        },
                                                        body: JSON.stringify({ unit_price: raw })
                                                    });
                                                    if (!res.ok) return;
                                                    const data = await res.json();
                                                    this.price = data.unit_price;
                                                    this.$el.dispatchEvent(new CustomEvent('line-price-saved', { detail: data, bubbles: true, composed: true }));
                                                } finally {
                                                    this.saving = false;
                                                }
                                            }
                                        }">
                                        <td class="px-4 py-3 align-top text-corteza min-w-[12rem]">
                                            {{ $line->raw_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-corteza whitespace-nowrap">
                                            {{ number_format($line->quantity_purchased, 2, ',', '.') }}
                                            <span class="text-masa-madre ml-0.5">{{ $line->purchase_unit->short() }}</span>
                                        </td>

                                        {{-- Precio unitario: edición inline --}}
                                        <td class="px-4 py-3 align-top text-right font-mono whitespace-nowrap">
                                            @can('manage-costs')
                                                <div x-show="!editing && !saving"
                                                    @click="startEdit()"
                                                    class="cursor-pointer text-corteza hover:text-horno select-none"
                                                    title="Click para editar"
                                                    x-text="'$ ' + fmt(price)"></div>
                                                <input
                                                    x-show="editing"
                                                    x-cloak
                                                    x-ref="priceInput"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    @input="isDirty = true"
                                                    @keydown.enter.prevent="savePrice()"
                                                    @keydown.escape="editing = false; isDirty = false"
                                                    @blur="savePrice()"
                                                    class="w-28 text-right text-xs border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                                <span x-show="saving" x-cloak class="text-xs text-masa-madre italic">guardando…</span>
                                            @else
                                                <span class="text-corteza" x-text="'$ ' + fmt(price)"></span>
                                            @endcan
                                        </td>

                                        <td class="px-4 py-3 align-top text-right font-mono font-semibold text-corteza whitespace-nowrap"
                                            x-text="'$ ' + fmt(subtotal)"></td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-masa-madre whitespace-nowrap">
                                            <span x-show="ivaAmount > 0" x-text="'$ ' + fmt(ivaAmount)"></span>
                                            <span x-show="ivaAmount <= 0">—</span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-masa-madre whitespace-nowrap">
                                            <span x-show="percepcionAmount > 0" x-text="'$ ' + fmt(percepcionAmount)"></span>
                                            <span x-show="percepcionAmount <= 0">—</span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono font-semibold text-corteza whitespace-nowrap"
                                            x-text="'$ ' + fmt(lineTotal)"></td>
                                        <td class="px-4 py-3 align-top text-center">
                                            @if($line->isExcluded())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-miga text-masa-madre"
                                                    title="{{ $line->exclusion_note ?: 'No es un insumo del catálogo (consumo personal, servicio administrativo, etc.)' }}">
                                                    Sin insumo
                                                </span>
                                            @elseif($line->isApplied() && $line->isBonus())
                                                {{-- Violeta y no verde: el renglón está resuelto pero no imputó ningún
                                                     costo, que es justo lo que el verde significa en esta columna. --}}
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700"
                                                    title="Obsequio o promoción: entró al stock el {{ $line->cost_applied_at->format('d/m/Y') }} sin modificar el costo del insumo.">
                                                    Sin cargo
                                                </span>
                                            @elseif($line->isApplied())
                                                <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium"
                                                    title="Costo imputado el {{ $line->cost_applied_at->format('d/m/Y') }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                    Aplicado
                                                </span>
                                            @elseif($line->isMatched())
                                                <span class="inline-flex items-center gap-1 text-amber-600 text-xs font-medium"
                                                    title="Vinculado, pendiente de imputar">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                                                    </svg>
                                                    Pendiente
                                                </span>
                                            @else
                                                <span class="text-masa-madre/60 text-xs">Sin vincular</span>
                                            @endif
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-3 align-top">
                                                <div class="flex items-center justify-end gap-3">
                                                    <button type="button"
                                                        @click="openEdit({{ Js::from([
                                                            'id' => $line->id,
                                                            'raw_name' => $line->raw_name,
                                                            'quantity_purchased' => round((float) $line->quantity_purchased, 2),
                                                            'purchase_unit' => $line->purchase_unit->value,
                                                            'unit_price' => round((float) $line->unit_price, 2),
                                                            'iva_rate' => $line->iva_rate,
                                                            'percepcion_rate' => $line->percepcion_rate,
                                                'is_bonus' => $line->isBonus(),
                                                        ]) }})"
                                                        class="p-1 text-masa-madre hover:text-corteza transition-colors"
                                                        title="Editar todos los campos">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z" />
                                                        </svg>
                                                    </button>
                                                    <form method="POST"
                                                        action="{{ route('purchases.lines.destroy', [$purchase, $line]) }}"
                                                        onsubmit="return confirm('¿Eliminar este renglón?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="p-1 text-red-400 hover:text-red-600 transition-colors"
                                                            title="Eliminar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-miga bg-miga/30">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-xs font-semibold text-corteza text-right">Totales</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-corteza text-xs whitespace-nowrap"
                                        x-text="'$ ' + fmt(tfootSubtotal)"></td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs whitespace-nowrap">
                                        <span x-show="tfootIva > 0" x-text="'$ ' + fmt(tfootIva)"></span>
                                        <span x-show="tfootIva <= 0">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs whitespace-nowrap">
                                        <span x-show="tfootPercepcion > 0" x-text="'$ ' + fmt(tfootPercepcion)"></span>
                                        <span x-show="tfootPercepcion <= 0">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-corteza text-xs whitespace-nowrap"
                                        x-text="'$ ' + fmt(tfootGrandTotal)"></td>
                                    <td @can('manage-costs') colspan="2" @endcan class="px-4 py-3"></td>
                                </tr>
                                {{-- Informativo: el total de arriba NO lo descuenta, tiene que cerrar contra el papel. --}}
                                @if($excludedLines->isNotEmpty())
                                    <tr>
                                        <td colspan="6" class="px-4 py-2 text-xs text-masa-madre text-right">
                                            Sin insumo (no imputado al catálogo)
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-masa-madre text-xs whitespace-nowrap">
                                            $ {{ number_format($personalTotal, 2, ',', '.') }}
                                        </td>
                                        <td @can('manage-costs') colspan="2" @endcan class="px-4 py-2"></td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-masa-madre">
                        Esta es la factura tal como se leyó. La asociación con insumos y la actualización de costos se hacen en un paso aparte.
                    </p>
                @endif
            </div>

            {{-- Notas de crédito de esta compra --}}
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-corteza">Notas de crédito</h3>
                        <p class="text-xs text-masa-madre mt-0.5">Devoluciones y reconocimientos del proveedor sobre esta factura.</p>
                    </div>
                    @can('manage-costs')
                        <button type="button"
                            @click="$dispatch('open-modal', 'credit-note-create')"
                            class="px-3 py-1.5 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                            + Nueva nota de crédito
                        </button>
                    @endcan
                </div>

                @if($purchase->creditNotes->isEmpty())
                    <p class="text-sm text-masa-madre mt-4">Todavía no hay notas de crédito para esta compra.</p>
                @else
                    @php $creditedTotal = $purchase->creditNotes->sum(fn ($n) => (float) $n->lines->sum('subtotal')); @endphp
                    <div class="mt-4 divide-y divide-miga border-t border-miga">
                        @foreach($purchase->creditNotes as $note)
                            <div class="py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('credit-notes.show', $note) }}" class="font-medium text-corteza hover:underline">
                                        {{ $note->note_number ? 'NC #'.$note->note_number : 'Nota de crédito #'.$note->id }}
                                    </a>
                                    <div class="text-xs text-masa-madre mt-0.5">{{ $note->note_date->format('d/m/Y') }} · {{ $note->lines->count() }} renglón(es)</div>
                                </div>
                                <div class="text-right font-mono text-sm text-corteza shrink-0">
                                    − $ {{ number_format($note->lines->sum('subtotal'), 2, ',', '.') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-miga flex items-center justify-between text-sm">
                        <span class="text-masa-madre">Acreditado</span>
                        <span class="font-mono text-corteza">− $ {{ number_format($creditedTotal, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            {{-- Acción eliminar compra --}}
            @can('manage-costs')
                @if($purchase->lines->isEmpty())
                    <div>
                        <form method="POST" action="{{ route('purchases.destroy', $purchase) }}"
                            onsubmit="return confirm('¿Eliminar esta compra?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-xs text-red-400 hover:text-red-600 hover:underline">
                                Eliminar esta compra
                            </button>
                        </form>
                    </div>
                @endif
            @endcan

        </div>

        @can('manage-costs')
            @include('purchases.modals.edit-line')
            @include('purchases.modals.edit-purchase')
            @include('purchases.modals.add-line')
            @include('credit-notes.modals.create')
        @endcan

        {{-- Modal: factura original --}}
        @if($purchase->invoice_image_path)
            <x-crud-modal name="invoice-image" title="Factura original" max-width="2xl">
                <div class="space-y-4">
                    @if(str_ends_with(strtolower($purchase->invoice_image_path), '.pdf'))
                        <a href="{{ route('purchases.invoice', $purchase) }}" target="_blank"
                            class="block border border-miga rounded-md p-8 text-center text-sm text-masa-madre hover:border-horno transition-colors">
                            Abrir PDF de la factura →
                        </a>
                    @else
                        <img src="{{ route('purchases.invoice', $purchase) }}" alt="Factura"
                            class="rounded-md border border-miga max-h-[70vh] w-full object-contain bg-miga/30">
                    @endif
                    <div class="flex justify-end">
                        <a href="{{ route('purchases.invoice', $purchase) }}" download
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Descargar
                        </a>
                    </div>
                </div>
            </x-crud-modal>
        @endif

    </div>
</x-app-layout>
