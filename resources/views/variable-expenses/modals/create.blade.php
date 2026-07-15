<x-crud-modal name="variable-expense-create" title="Nuevo gasto variable" :show="$errorsInCreate">
    <form method="POST" action="{{ route('variable-expenses.store') }}" class="space-y-4"
          x-data="{
              showNewCat: false,
              newCatName: '',
              newCatLoading: false,
              newCatError: '',
              async createCategory() {
                  this.newCatLoading = true;
                  this.newCatError = '';
                  try {
                      const res = await fetch('{{ route('variable-expense-categories.store') }}', {
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
                      const sel = document.getElementById('create_ve_category');
                      sel.add(new Option(data.name, data.id, true, true));
                      this.showNewCat = false;
                      this.newCatName = '';
                  } catch {
                      this.newCatError = 'Error al crear la categoría.';
                  } finally {
                      this.newCatLoading = false;
                  }
              }
          }"
          x-on:supplier-created.window="
              const sel = document.getElementById('create_ve_supplier');
              sel.add(new Option($event.detail.name, $event.detail.id, true, true));
          ">
        @csrf
        <input type="hidden" name="_form" value="ve-create">

        <div>
            <x-input-label for="create_ve_name" value="Nombre" />
            <x-text-input id="create_ve_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <x-input-label for="create_ve_category" value="Categoría" />
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
                        <button type="button"
                            @click="createCategory()"
                            :disabled="newCatLoading || !newCatName.trim()"
                            class="px-3 py-1.5 text-xs bg-corteza text-white rounded-md hover:bg-horno transition-colors disabled:opacity-50 whitespace-nowrap">
                            <span x-text="newCatLoading ? 'Creando…' : 'Crear'"></span>
                        </button>
                    </div>
                    <p x-show="newCatError" x-text="newCatError" class="mt-1 text-xs text-red-500"></p>
                </div>

                <select id="create_ve_category" name="variable_expense_category_id"
                    class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                    required>
                    <option value="">— Seleccioná —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            @selected(old('variable_expense_category_id') == $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('variable_expense_category_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_ve_date" value="Fecha del gasto" />
                <x-text-input id="create_ve_date" name="expense_date" type="date"
                    class="mt-1 block w-full"
                    :value="old('expense_date', date('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('expense_date')" class="mt-2" />
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <x-input-label for="create_ve_supplier" value="Proveedor" />
                <button type="button"
                    @click="$dispatch('open-modal', 'supplier-quick-create')"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    + Nuevo proveedor
                </button>
            </div>
            <select id="create_ve_supplier" name="supplier_id"
                class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Ninguno —</option>
                @foreach($suppliers->where('active', true) as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_ve_amount" value="Monto" />
            <x-text-input id="create_ve_amount" name="amount" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('amount')"
                required />
            <p class="mt-1 text-xs text-masa-madre">En la moneda del negocio. No se incluye en el costo de las recetas.</p>
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear gasto</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'variable-expense-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
