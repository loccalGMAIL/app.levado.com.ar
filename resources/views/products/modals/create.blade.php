<x-crud-modal name="product-create" title="Nuevo artículo" :show="$errorsInCreate">
    <form method="POST" action="{{ route('products.store') }}" class="space-y-4"
          x-data="{
              type: '{{ old('type') }}',
              showNewCat: false,
              newCatName: '',
              newCatLoading: false,
              newCatError: '',
              async createCategory() {
                  this.newCatLoading = true;
                  this.newCatError = '';
                  try {
                      const res = await fetch('{{ route('product-categories.store') }}', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                              'Accept': 'application/json',
                              'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                          },
                          body: JSON.stringify({ name: this.newCatName }),
                      });
                      const data = await res.json();
                      if (!res.ok) {
                          this.newCatError = data?.errors?.name?.[0] ?? 'Error al crear la categoría.';
                          return;
                      }
                      const sel = document.getElementById('create_product_category');
                      sel.add(new Option(data.name, data.id, true, true));
                      this.showNewCat = false;
                      this.newCatName = '';
                  } catch {
                      this.newCatError = 'Error al crear la categoría.';
                  } finally {
                      this.newCatLoading = false;
                  }
              }
          }">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_product_name" value="Nombre" />
            <x-text-input id="create_product_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label value="Tipo de artículo" />
            <div class="mt-1 grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="type === 'manufactured' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="manufactured" x-model="type" class="text-corteza focus:ring-horno" required>
                    <span>Elaborado <span class="text-xs text-masa-madre">(desde receta)</span></span>
                </label>
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="type === 'resale' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="resale" x-model="type" class="text-corteza focus:ring-horno">
                    <span>Reventa <span class="text-xs text-masa-madre">(se compra)</span></span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        {{-- Elaborado: se elige la receta que lo produce. --}}
        <div x-show="type === 'manufactured'" x-cloak>
            <x-input-label for="create_product_recipe" value="Receta" />
            <select id="create_product_recipe" name="recipe_id" x-bind:required="type === 'manufactured'"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná una receta —</option>
                @foreach($recipes->where('active', true) as $recipe)
                    <option value="{{ $recipe->id }}" @selected(old('recipe_id') == $recipe->id)>
                        {{ $recipe->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-masa-madre">El costo del elaborado se toma de la receta.</p>
            <x-input-error :messages="$errors->get('recipe_id')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <x-input-label for="create_product_category" value="Categoría (opcional)" />
                <button type="button" @click="showNewCat = !showNewCat"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    <span x-text="showNewCat ? 'Cancelar' : '+ Nueva categoría'"></span>
                </button>
            </div>

            <div x-show="showNewCat" x-cloak class="mb-2">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="newCatName"
                        placeholder="Nombre de la categoría"
                        @keydown.enter.prevent="createCategory()"
                        class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm" />
                    <button type="button" @click="createCategory()"
                        :disabled="newCatLoading || !newCatName.trim()"
                        class="px-3 py-1.5 text-xs bg-corteza text-white rounded-md hover:bg-horno transition-colors disabled:opacity-50 whitespace-nowrap">
                        <span x-text="newCatLoading ? 'Creando…' : 'Crear'"></span>
                    </button>
                </div>
                <p x-show="newCatError" x-text="newCatError" class="mt-1 text-xs text-red-500"></p>
                <p class="mt-1 text-xs text-masa-madre">La categoría nueva se crea con «se produce» activado; ajustalo en Categorías.</p>
            </div>

            <select id="create_product_category" name="product_category_id"
                class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Sin categoría —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('product_category_id') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_product_unit" value="Unidad de venta" />
                <select id="create_product_unit" name="unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Seleccioná —</option>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            {{-- Reventa: el costo lo carga el usuario (luego lo mantendrá Compras). --}}
            <div x-show="type === 'resale'" x-cloak>
                <x-input-label for="create_product_cost" value="Costo por unidad" />
                <x-text-input id="create_product_cost" name="cost_per_unit" type="number"
                    step="0.01" min="0"
                    class="mt-1 block w-full"
                    :value="old('cost_per_unit')"
                    x-bind:required="type === 'resale'" />
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div x-show="type === 'resale'" x-cloak>
            <x-input-label for="create_product_costing" value="Método de costeo (opcional)" />
            <select id="create_product_costing" name="costing_method"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Usar el del negocio —</option>
                @foreach(\App\Enums\CostingMethod::cases() as $method)
                    <option value="{{ $method->value }}" @selected(old('costing_method') === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('costing_method')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_product_sku" value="SKU (opcional)" />
                <x-text-input id="create_product_sku" name="sku" type="text"
                    class="mt-1 block w-full"
                    :value="old('sku')" />
                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_product_barcode" value="Código de barras (opcional)" />
                <x-text-input id="create_product_barcode" name="barcode" type="text"
                    class="mt-1 block w-full"
                    :value="old('barcode')" />
                <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear artículo</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'product-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
