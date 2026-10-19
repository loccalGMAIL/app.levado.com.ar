<x-crud-modal name="recipe-production" title="Producción" :show="$errorsInProduction" :z="60">
    <form method="POST" action="{{ route('recipes.production.update', $recipe) }}" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_form" value="production">

        <div>
            <x-input-label for="production_unit_name" value="Unidad de producción" />
            <x-text-input id="production_unit_name" name="production_unit_name" type="text" maxlength="30"
                class="mt-1 block w-full" placeholder="Lata"
                :value="old('production_unit_name', $recipe->production_unit_name ?? 'Lata')" />
            <x-input-error :messages="$errors->get('production_unit_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="production_unit_quantity" value="Equivalencia" />
            <div class="mt-1 flex items-center gap-2 text-sm text-masa-madre">
                <span>1 unidad de producción =</span>
                <x-text-input id="production_unit_quantity" name="production_unit_quantity" type="number"
                    step="0.001" min="0.001"
                    class="block w-32"
                    :value="old('production_unit_quantity', $recipe->productionUnitSize())" />
                <span>{{ $recipe->yield_unit->short() }}</span>
            </div>
            <x-input-error :messages="$errors->get('production_unit_quantity')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-production')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
