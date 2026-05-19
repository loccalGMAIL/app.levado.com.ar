<x-crud-modal name="fixed-cost-create" title="Nuevo gasto fijo" :show="$errorsInCreate">
    <form method="POST" action="{{ route('fixed-costs.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_fc_name" value="Nombre" />
            <x-text-input id="create_fc_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_fc_category" value="Categoría" />
                @if($categories->isEmpty())
                    <p class="mt-1 text-xs text-red-500">Primero creá una categoría usando el botón "Categorías".</p>
                @else
                    <select id="create_fc_category" name="fixed_cost_category_id"
                        class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                        required>
                        <option value="">— Seleccioná —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                @selected(old('fixed_cost_category_id') == $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <x-input-error :messages="$errors->get('fixed_cost_category_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_fc_valid_from" value="Vigente desde" />
                <x-text-input id="create_fc_valid_from" name="valid_from" type="date"
                    class="mt-1 block w-full"
                    :value="old('valid_from', date('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('valid_from')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_fc_amount" value="Monto mensual" />
            <x-text-input id="create_fc_amount" name="monthly_amount" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('monthly_amount')"
                required />
            <p class="mt-1 text-xs text-masa-madre">En la moneda del negocio.</p>
            <x-input-error :messages="$errors->get('monthly_amount')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Crear gasto</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'fixed-cost-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
