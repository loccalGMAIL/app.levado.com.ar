<x-crud-modal name="price-list-create" title="Nueva lista de precios" :show="$errorsInCreate">
    <form method="POST" action="{{ route('price-lists.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_pl_name" value="Nombre de la lista" />
            <x-text-input id="create_pl_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                placeholder="Ej: Mayorista, Cafeterías, Reventa"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_pl_pct" value="% de ajuste sobre la lista base (opcional)" />
            <x-text-input id="create_pl_pct" name="adjustment_pct" type="number"
                step="0.01" min="-99.99" max="999.99"
                class="mt-1 block w-full"
                :value="old('adjustment_pct')"
                placeholder="Ej: -15 para 15% menos" />
            <p class="mt-1 text-xs text-masa-madre">Solo sugiere precios: en cada receta podés cargar el monto que quieras.</p>
            <x-input-error :messages="$errors->get('adjustment_pct')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear lista</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'price-list-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
