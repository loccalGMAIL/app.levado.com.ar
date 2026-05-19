<x-crud-modal name="recipe-add-labor" title="Agregar mano de obra" :show="$errorsInAddLabor">
    <form method="POST" action="{{ route('recipes.labor-lines.store', $recipe) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="add-labor">

        <div>
            <x-input-label for="add_labor_id" value="Rol / tipo" />
            <select id="add_labor_id" name="labor_type_id"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                required>
                <option value="">— seleccionar —</option>
                @foreach($laborTypes as $laborType)
                    <option value="{{ $laborType->id }}" {{ old('labor_type_id') == $laborType->id ? 'selected' : '' }}>
                        {{ $laborType->name }} ($ {{ number_format($laborType->hourly_rate, 2, ',', '.') }}/h)
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('labor_type_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="add_labor_hours" value="Horas" />
            <x-text-input id="add_labor_hours" name="hours" type="number"
                step="0.01" min="0.01"
                class="mt-1 block w-full"
                :value="old('hours')"
                placeholder="Ej: 1.5"
                required />
            <x-input-error :messages="$errors->get('hours')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Agregar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-add-labor')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
