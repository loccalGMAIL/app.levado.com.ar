@php
    $errorsInCreate = $errors->hasAny(['type', 'scheduled_for', 'notes']);
@endphp

<x-crud-modal name="production-order-create" title="Nueva orden de producción" :show="$errorsInCreate">
    <form method="POST" action="{{ route('production-orders.store') }}" class="space-y-4"
        x-data="{ type: '{{ old('type', 'daily') }}' }">
        @csrf

        <div>
            <x-input-label value="Tipo" />
            <div class="mt-1 flex gap-4">
                @foreach(\App\Enums\ProductionOrderType::selectable() as $option)
                    <label class="flex items-center gap-2 text-sm text-corteza">
                        <input type="radio" name="type" value="{{ $option->value }}" x-model="type"
                            class="border-gray-300 text-horno focus:ring-horno">
                        {{ $option->label() }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div x-show="type === 'daily'">
            <x-input-label for="scheduled_for" value="Fecha" />
            <input id="scheduled_for" name="scheduled_for" type="date"
                value="{{ old('scheduled_for', now()->toDateString()) }}"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
            <x-input-error :messages="$errors->get('scheduled_for')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" value="Notas (opcional)" />
            <textarea id="notes" name="notes" rows="2" maxlength="1000"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Creando…">Crear orden</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-order-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
