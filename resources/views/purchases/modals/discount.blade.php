{{-- Alta y edición de un descuento. editingDiscount vive en el x-data de la página (null = alta). --}}
<x-crud-modal name="discount-form" title="Descuento" max-width="xl" :show="$errorsInDiscount">
    <form method="POST"
        x-data="{
            form: { id: null, description: '', kind: 'percent', rate: '', amount: '', purchase_line_id: '' },
            lineBases: @js($purchase->lines->mapWithKeys(fn ($l) => [$l->id => (float) $l->subtotal])),
            get isEditing() { return this.form.id !== null; },
            get base() {
                return this.form.purchase_line_id === ''
                    ? tfootSubtotal
                    : (this.lineBases[this.form.purchase_line_id] ?? 0);
            },
            get computedAmount() {
                return this.form.kind === 'percent'
                    ? Math.round(this.base * (parseFloat(this.form.rate) || 0)) / 100
                    : (parseFloat(this.form.amount) || 0);
            },
            seed(data) {
                this.form = data
                    ? { ...data, id: data.id ?? null }
                    : { id: null, description: '', kind: 'percent', rate: '', amount: '', purchase_line_id: '' };
            },
            init() {
                this.seed($data.editingDiscount);
                window.addEventListener('open-modal', (e) => {
                    if (e.detail === 'discount-form') { this.seed($data.editingDiscount); }
                });
            },
        }"
        :action="isEditing
            ? '{{ route('purchases.discounts.update', [$purchase, '__ID__']) }}'.replace('__ID__', form.id)
            : '{{ route('purchases.discounts.store', $purchase) }}'"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="discount">
        <input type="hidden" name="discount_id" :value="form.id">
        <template x-if="isEditing"><input type="hidden" name="_method" value="PATCH"></template>

        <div>
            <x-input-label value="Descripción" />
            <x-text-input name="description" type="text" class="mt-1 block w-full"
                x-model="form.description" placeholder="Ej.: Bonificación 10%" />
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Se aplica a" />
            <select name="purchase_line_id" x-model="form.purchase_line_id"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                <option value="">Toda la factura</option>
                @foreach($purchase->lines as $line)
                    <option value="{{ $line->id }}">{{ $line->raw_name ?: 'Renglón #'.$line->id }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('purchase_line_id')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label value="Tipo" />
                <select name="kind" x-model="form.kind"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach(\App\Enums\DiscountKind::cases() as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kind')" class="mt-1" />
            </div>
            <div>
                <template x-if="form.kind === 'percent'">
                    <div>
                        <x-input-label value="Porcentaje %" />
                        <x-text-input name="rate" type="number" step="0.01" min="0" max="100"
                            class="mt-1 block w-full text-right" x-model="form.rate" />
                        <x-input-error :messages="$errors->get('rate')" class="mt-1" />
                    </div>
                </template>
                <template x-if="form.kind === 'amount'">
                    <div>
                        <x-input-label value="Importe $" />
                        <x-text-input name="amount" type="number" step="0.01" min="0"
                            class="mt-1 block w-full text-right" x-model="form.amount" />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>
                </template>
            </div>
        </div>

        <div class="bg-miga/50 rounded-md p-3 flex items-center justify-between text-sm">
            <span class="font-medium text-corteza">Descuento a registrar</span>
            <span class="font-mono font-medium text-corteza" x-text="'− $ ' + fmt(computedAmount)"></span>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar</x-primary-button>
            <button type="button" x-on:click="$dispatch('close-modal', 'discount-form')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</button>
        </div>
    </form>
</x-crud-modal>
