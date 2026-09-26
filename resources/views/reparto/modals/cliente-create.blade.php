<x-crud-modal name="customer-create" title="Nuevo cliente" max-width="lg" :show="$errorsInCreate">
    <form method="POST" action="{{ route('reparto.clientes.store') }}" class="space-y-4"
        x-data="{ fiscalOpen: {{ $errors->hasAny(['legal_name', 'tax_id', 'condicion_iva']) ? 'true' : 'false' }} }">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_name" value="Nombre" />
            <x-text-input id="create_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_phone" value="Teléfono" />
                <x-text-input id="create_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    :value="old('phone')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_email" value="Email" />
                <x-text-input id="create_email" name="email" type="email"
                    class="mt-1 block w-full"
                    :value="old('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_address" value="Dirección" />
                <x-text-input id="create_address" name="address" type="text"
                    class="mt-1 block w-full"
                    :value="old('address')" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_city" value="Ciudad" />
                <x-text-input id="create_city" name="city" type="text"
                    class="mt-1 block w-full"
                    :value="old('city')" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_delivery_person_id" value="Repartidor a cargo (opcional)" />
            <select id="create_delivery_person_id" name="delivery_person_id"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                <option value="">— Sin asignar —</option>
                @foreach($deliveryPeople as $deliveryPerson)
                    <option value="{{ $deliveryPerson->id }}" @selected(old('delivery_person_id') == $deliveryPerson->id)>
                        {{ $deliveryPerson->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('delivery_person_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_notes" value="Notas" />
            <textarea id="create_notes" name="notes" rows="2"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-horno focus:ring-horno text-sm">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div>
            <button type="button" x-show="! fiscalOpen" @click="fiscalOpen = true" class="text-sm text-horno hover:underline">
                + Agregar datos fiscales
            </button>

            <template x-if="fiscalOpen">
                <div class="mt-3 p-3 bg-miga rounded-lg space-y-4">
                    <div>
                        <x-input-label for="create_legal_name" value="Razón social" />
                        <x-text-input id="create_legal_name" name="legal_name" type="text"
                            class="mt-1 block w-full"
                            :value="old('legal_name')" />
                        <x-input-error :messages="$errors->get('legal_name')" class="mt-2" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="create_tax_id" value="CUIT" />
                            <x-text-input id="create_tax_id" name="tax_id" type="text"
                                class="mt-1 block w-full" placeholder="XX-XXXXXXXX-X"
                                :value="old('tax_id')" />
                            <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="create_condicion_iva" value="Condición de IVA" />
                            <select id="create_condicion_iva" name="condicion_iva"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="">— Sin especificar —</option>
                                @foreach(\App\Enums\CondicionIva::cases() as $case)
                                    <option value="{{ $case->value }}" @selected(old('condicion_iva') === $case->value)>
                                        {{ $case->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condicion_iva')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear cliente</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'customer-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
