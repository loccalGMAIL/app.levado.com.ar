@php
    $errorsInTemplate = $errors->has('name');
@endphp

<x-crud-modal name="production-order-save-template" title="Guardar como plantilla" :show="$errorsInTemplate">
    <form method="POST" action="{{ route('production-orders.save-as-template', $productionOrder) }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="template_name" value="Nombre de la plantilla" />
            <x-text-input id="template_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <p class="mt-1 text-xs text-masa-madre">Copia los pedidos y artículos de esta orden. La orden original no se modifica.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar plantilla</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-order-save-template')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
