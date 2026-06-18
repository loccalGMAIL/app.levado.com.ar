<x-crud-modal name="recipe-add-ingredient" title="Agregar ingrediente" :show="$errorsInAddIngredient">
    <form method="POST" action="{{ route('recipes.ingredient-lines.store', $recipe) }}" class="space-y-4"
        x-data="{
            groups: { gr: 'weight', kg: 'weight', ml: 'volume', L: 'volume', cc: 'volume', u: 'unit' },
            selectedGroup: null,
            selectedSubdivisions: null,
            selectedSubdivisionLabel: null,
            init() {
                const preselected = this.$el.querySelector('#add_ing_id option[selected]');
                if (preselected) {
                    this.selectedGroup = this.groups[preselected.dataset.unit] ?? null;
                    this.selectedSubdivisions = preselected.dataset.subdivisions ? parseInt(preselected.dataset.subdivisions) : null;
                    this.selectedSubdivisionLabel = preselected.dataset.subdivisionLabel || null;
                }
            },
            onIngredientChange(event) {
                const option = event.target.selectedOptions[0];
                this.selectedGroup = option?.dataset?.unit ? (this.groups[option.dataset.unit] ?? null) : null;
                this.selectedSubdivisions = option?.dataset?.subdivisions ? parseInt(option.dataset.subdivisions) : null;
                this.selectedSubdivisionLabel = option?.dataset?.subdivisionLabel || null;
            },
            isCompatible(unit) {
                if (!this.selectedGroup) return true;
                return this.groups[unit] === this.selectedGroup;
            },
            get quantityLabel() {
                if (this.selectedSubdivisions && this.selectedSubdivisionLabel) {
                    return 'Cantidad (en ' + this.selectedSubdivisionLabel + 's)';
                }
                if (this.selectedSubdivisions) {
                    return 'Cantidad (en unidades)';
                }
                return 'Cantidad';
            }
        }">
        @csrf
        <input type="hidden" name="_form" value="add-ingredient">

        <div>
            <x-input-label for="add_ing_id" value="Ingrediente" />
            <select id="add_ing_id" name="ingredient_id"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                @change="onIngredientChange($event)"
                required>
                <option value="">— seleccionar —</option>
                @foreach($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}"
                        data-unit="{{ $ingredient->unit->value }}"
                        @if($ingredient->subdivisions)
                            data-subdivisions="{{ $ingredient->subdivisions }}"
                            data-subdivision-label="{{ $ingredient->subdivision_label ?? '' }}"
                        @endif
                        {{ old('ingredient_id') == $ingredient->id ? 'selected' : '' }}>
                        {{ $ingredient->name }}
                        @if($ingredient->subdivisions)
                            ({{ $ingredient->subdivision_label ?? 'u' }})
                        @else
                            ({{ $ingredient->unit->short() }})
                        @endif
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('ingredient_id')" class="mt-2" />
        </div>

        <template x-if="selectedSubdivisions">
            <div class="bg-amber-50 border border-amber-200 rounded-md px-3 py-2 text-xs text-amber-800">
                1 envase = <span x-text="selectedSubdivisions"></span>
                <span x-text="selectedSubdivisionLabel || 'unidades'"></span>.
                Ingresá la cantidad en <span x-text="selectedSubdivisionLabel ? selectedSubdivisionLabel + 's' : 'unidades'"></span>.
            </div>
        </template>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="add_ing_qty"
                    class="block font-medium text-sm text-gray-700"
                    x-text="quantityLabel"></label>
                <x-text-input id="add_ing_qty" name="quantity" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('quantity')"
                    required />
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="add_ing_unit" value="Unidad" />
                <select id="add_ing_unit" name="unit"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                    required>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}"
                            x-show="isCompatible('{{ $unit->value }}')"
                            {{ old('unit') === $unit->value ? 'selected' : '' }}>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Agregar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-add-ingredient')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
