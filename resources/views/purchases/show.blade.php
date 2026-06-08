<x-app-layout>
    <x-slot name="title">
        {{ $purchase->invoice_number ? 'Factura #' . $purchase->invoice_number : 'Compra #' . $purchase->id }}
    </x-slot>

    @php
        $errorsInAddLine = $errors->hasAny(['purchaseable_type', 'purchaseable_id', 'quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'add-line';
        $editingLine = null;
        $errorsInEditLine = $errors->hasAny(['quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'edit-line';
        if ($errorsInEditLine) {
            $editingLine = ['id' => old('line_id'), 'quantity_purchased' => old('quantity_purchased'), 'purchase_unit' => old('purchase_unit'), 'unit_price' => old('unit_price')];
        }
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editingLine: {{ Js::from($editingLine) }},
            openEdit(line) {
                this.editingLine = line;
                $dispatch('open-modal', 'line-edit');
            }
        }">

        {{-- Botón volver (solo móvil) --}}
        <div class="sm:hidden px-6 pt-4 pb-0">
            <a href="{{ route('purchases.index') }}"
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
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold text-corteza">
                            {{ $purchase->invoice_number ? 'Factura N° ' . $purchase->invoice_number : 'Compra sin número de factura' }}
                        </h2>
                        <p class="text-sm text-masa-madre">
                            <span class="font-medium text-corteza">{{ $purchase->supplier->name }}</span>
                            &mdash; {{ $purchase->invoice_date->format('d/m/Y') }}
                        </p>
                        @if($purchase->notes)
                            <p class="text-sm text-masa-madre italic">{{ $purchase->notes }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs text-masa-madre">Total compra</p>
                        <p class="text-xl font-mono font-semibold text-corteza">
                            ${{ number_format($purchase->totalAmount(), 2, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Líneas existentes --}}
            <div>
                <h3 class="text-sm font-semibold text-corteza mb-3">Ítems de la compra</h3>

                @if($purchase->lines->isEmpty())
                    <div class="bg-white rounded-lg shadow p-6 text-center text-masa-madre text-sm">
                        No hay ítems cargados. Agregá el primero abajo.
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-miga text-masa-madre border-b border-miga">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Tipo</th>
                                    <th class="px-4 py-3 font-medium">Insumo</th>
                                    <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                                    <th class="px-4 py-3 font-medium">Unidad</th>
                                    <th class="px-4 py-3 font-medium text-right">Precio unitario</th>
                                    <th class="px-4 py-3 font-medium text-right">Subtotal</th>
                                    @can('manage-costs')
                                        <th class="px-4 py-3"></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($purchase->lines as $line)
                                    @php
                                        $itemName = $line->isIngredient()
                                            ? ($line->ingredient?->name ?? 'Ingrediente eliminado')
                                            : ($line->packaging?->name ?? 'Envase eliminado');
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $line->isIngredient() ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                                {{ $line->isIngredient() ? 'Ingrediente' : 'Envase' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-corteza">{{ $itemName }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-corteza">
                                            {{ number_format($line->quantity_purchased, 4, ',', '.') + 0 }}
                                        </td>
                                        <td class="px-4 py-3 text-masa-madre">{{ $line->purchase_unit->short() }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-corteza">
                                            ${{ number_format($line->unit_price, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold text-corteza">
                                            ${{ number_format($line->subtotal, 2, ',', '.') }}
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-3">
                                                    <button type="button"
                                                        @click="openEdit({{ Js::from([
                                                            'id' => $line->id,
                                                            'quantity_purchased' => $line->quantity_purchased,
                                                            'purchase_unit' => $line->purchase_unit->value,
                                                            'unit_price' => $line->unit_price,
                                                            'item_name' => $itemName,
                                                        ]) }})"
                                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                        Editar
                                                    </button>
                                                    <form method="POST"
                                                        action="{{ route('purchases.lines.destroy', [$purchase, $line]) }}"
                                                        onsubmit="return confirm('¿Eliminar este ítem?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="text-xs text-red-400 hover:text-red-600 hover:underline">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Formulario para agregar ítem --}}
            @can('manage-costs')
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-corteza mb-4">Agregar ítem</h3>

                <form method="POST" action="{{ route('purchases.lines.store', $purchase) }}"
                    class="space-y-4"
                    x-data="{
                        type: '{{ old('purchaseable_type', 'ingredient') }}',
                        itemId: '{{ old('purchaseable_id', '') }}',
                        isPackaging() { return this.type === 'packaging'; }
                    }">
                    @csrf
                    <input type="hidden" name="_form" value="add-line">

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                        <div>
                            <x-input-label value="Tipo" />
                            <select name="purchaseable_type" required
                                x-model="type"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="ingredient">Ingrediente</option>
                                <option value="packaging">Envase</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label value="Insumo" />

                            {{-- Ingredientes --}}
                            <select name="purchaseable_id" required
                                x-show="!isPackaging()"
                                x-bind:disabled="isPackaging()"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="">— Seleccioná un ingrediente —</option>
                                @foreach($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}"
                                        @selected(old('purchaseable_id') == $ingredient->id && old('purchaseable_type', 'ingredient') === 'ingredient')>
                                        {{ $ingredient->name }} ({{ $ingredient->unit->short() }})
                                    </option>
                                @endforeach
                            </select>

                            {{-- Envases --}}
                            <select name="purchaseable_id" required
                                x-show="isPackaging()"
                                x-bind:disabled="!isPackaging()"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="">— Seleccioná un envase —</option>
                                @foreach($packagings as $packaging)
                                    <option value="{{ $packaging->id }}"
                                        @selected(old('purchaseable_id') == $packaging->id && old('purchaseable_type') === 'packaging')>
                                        {{ $packaging->name }}
                                    </option>
                                @endforeach
                            </select>

                            <x-input-error :messages="$errors->get('purchaseable_id')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Cantidad comprada" />
                            <x-text-input name="quantity_purchased" type="number"
                                step="0.0001" min="0.0001"
                                class="mt-1 block w-full"
                                :value="old('quantity_purchased')"
                                required />
                            <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Unidad de compra" />

                            {{-- Ingredientes: elegir unidad --}}
                            <select name="purchase_unit" required
                                x-show="!isPackaging()"
                                x-bind:disabled="isPackaging()"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                @foreach($units as $unit)
                                    <option value="{{ $unit->value }}"
                                        @selected(old('purchase_unit') === $unit->value)>
                                        {{ $unit->label() }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Packaging: siempre unidad --}}
                            <div x-show="isPackaging()" class="mt-1">
                                <input type="hidden" name="purchase_unit" value="u" x-bind:disabled="!isPackaging()">
                                <p class="text-sm text-masa-madre bg-miga px-3 py-2 rounded-md">
                                    Unidad (u) — fijo para envases
                                </p>
                            </div>

                            <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
                        </div>

                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <div>
                            <x-input-label value="Precio por unidad de compra" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                                <x-text-input name="unit_price" type="number"
                                    step="0.01" min="0"
                                    class="block w-full pl-7"
                                    :value="old('unit_price')"
                                    required />
                            </div>
                            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
                        </div>

                        <div class="lg:col-span-3 flex items-end">
                            <x-primary-button>Agregar ítem</x-primary-button>
                        </div>
                    </div>

                    <p class="text-xs text-masa-madre">
                        Al agregar el ítem, el costo del insumo se actualizará automáticamente y las recetas que lo usan se recalcularán.
                    </p>

                    @if($errorsInAddLine)
                        <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                            Revisá los campos marcados arriba.
                        </div>
                    @endif
                </form>
            </div>
            @endcan

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
        @endcan

    </div>
</x-app-layout>
