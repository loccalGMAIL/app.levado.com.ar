<x-crud-modal name="recipe-add-subrecipe" title="Agregar sub-receta" :show="$errorsInAddSubrecipe">
    <form method="POST" action="{{ route('recipes.subrecipe-lines.store', $recipe) }}" class="space-y-4"
        x-data="{
            groups: { gr: 'weight', kg: 'weight', ml: 'volume', L: 'volume', cc: 'volume', u: 'unit' },
            selectedGroup: null,
            init() {
                const preselected = this.$el.querySelector('#add_sub_id option[selected]');
                if (preselected) {
                    this.selectedGroup = this.groups[preselected.dataset.yieldUnit] ?? null;
                }
            },
            onChildChange(event) {
                const option = event.target.selectedOptions[0];
                this.selectedGroup = option?.dataset?.yieldUnit
                    ? (this.groups[option.dataset.yieldUnit] ?? null)
                    : null;
            },
            isCompatible(unit) {
                if (!this.selectedGroup) return true;
                return this.groups[unit] === this.selectedGroup;
            }
        }">
        @csrf
        <input type="hidden" name="_form" value="add-subrecipe">

        <div>
            <x-input-label for="add_sub_id" value="Sub-receta" />
            <select id="add_sub_id" name="child_recipe_id"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                @change="onChildChange($event)"
                required>
                <option value="">— seleccionar —</option>
                @foreach($semiElaborates as $semi)
                    <option value="{{ $semi->id }}"
                        data-yield-unit="{{ $semi->yield_unit->value }}"
                        {{ old('child_recipe_id') == $semi->id ? 'selected' : '' }}>
                        {{ $semi->name }} ({{ $semi->yield_unit->short() }})
                    </option>
                @endforeach
            </select>
            @if($semiElaborates->isEmpty())
                <p class="mt-1 text-xs text-masa-madre">No hay semi-elaboraciones disponibles. Creá una receta y marcala como semi-elaboración.</p>
            @endif
            <x-input-error :messages="$errors->get('child_recipe_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label for="add_sub_qty" value="Cantidad" />
                <x-text-input id="add_sub_qty" name="quantity_used" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('quantity_used')"
                    required />
                <x-input-error :messages="$errors->get('quantity_used')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="add_sub_unit" value="Unidad" />
                <select id="add_sub_unit" name="unit"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                    required>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}"
                            x-show="isCompatible('{{ $unit->value }}')"
                            {{ old('unit') === $unit->value ? 'selected' : '' }}>
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Agregar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-add-subrecipe')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
