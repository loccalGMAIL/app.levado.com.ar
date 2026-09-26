<x-crud-modal name="ingredient-convert-to-product" title="Convertir a producto de reventa" max-width="lg">
    <form method="POST"
        :action="converting ? '{{ route('ingredients.convert-to-product', ['ingredient' => '__id__']) }}'.replace('__id__', converting.id) : '#'"
        class="space-y-4">
        @csrf

        <p class="text-sm text-masa-madre">
            <span class="font-medium text-corteza" x-text="converting?.name"></span> deja de ser un insumo y pasa a
            ser un artículo de reventa, con su costo, stock e historial de compras migrados. El insumo queda
            desactivado (no se borra).
        </p>

        <div class="bg-miga/50 rounded-md p-3 grid grid-cols-2 gap-2 text-sm">
            <div>
                <div class="text-masa-madre text-xs">Costo actual</div>
                <div class="font-mono text-corteza">$ <span x-text="converting?.cost_per_unit"></span> / <span x-text="converting?.unit"></span></div>
            </div>
            <div>
                <div class="text-masa-madre text-xs">Stock actual</div>
                <div class="font-mono text-corteza"><span x-text="converting?.stock"></span> <span x-text="converting?.unit"></span></div>
            </div>
        </div>

        <div>
            <x-input-label for="convert_product_category" value="Categoría del artículo (opcional)" />
            <select id="convert_product_category" name="product_category_id"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Sin categoría —</option>
                @foreach($productCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>

        <p class="text-xs text-masa-madre">
            Si este insumo se usa en alguna receta, la conversión se va a rechazar: reemplazalo ahí primero.
        </p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                Convertir
            </button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'ingredient-convert-to-product')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
