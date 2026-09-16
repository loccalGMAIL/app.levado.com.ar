<x-crud-modal name="product-bulk-category" title="Asignar categoría">
    <form method="POST" action="{{ route('products.bulk-category') }}" class="space-y-4">
        @csrf
        @method('PATCH')

        <template x-for="id in selectedIds" :key="id">
            <input type="hidden" name="product_ids[]" :value="id">
        </template>

        <p class="text-sm text-masa-madre">
            Se le va a asignar la categoría a <span class="font-medium text-corteza" x-text="selectedIds.length"></span> artículo(s).
        </p>

        <div>
            <x-input-label for="bulk_product_category_id" value="Categoría" />
            <select id="bulk_product_category_id" name="product_category_id"
                class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                <option value="">— Sin categoría (quitar) —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}{{ $cat->producible ? '' : ' (no se produce)' }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" x-on:click="$dispatch('close-modal', 'product-bulk-category')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
            <button type="submit" :disabled="selectedIds.length === 0"
                class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Asignar
            </button>
        </div>
    </form>
</x-crud-modal>
