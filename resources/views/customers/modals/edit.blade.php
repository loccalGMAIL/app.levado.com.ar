<x-crud-modal name="customer-edit" title="Editar cliente" max-width="lg" :show="$errorsInEdit">
    <form method="POST"
        :action="`/customers/${editing.id}`"
        class="space-y-4"
        x-data="{ fiscalOpen: !!(editing.legal_name || editing.tax_id || editing.condicion_iva) }">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="customer_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_name" value="Nombre" />
            <x-text-input id="edit_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_phone" value="Teléfono" />
                <x-text-input id="edit_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.phone" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_email" value="Email" />
                <x-text-input id="edit_email" name="email" type="email"
                    class="mt-1 block w-full"
                    x-model="editing.email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_address" value="Dirección" />
                <x-text-input id="edit_address" name="address" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.address" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_city" value="Ciudad" />
                <x-text-input id="edit_city" name="city" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.city" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="edit_delivery_person_id" value="Repartidor a cargo (opcional)" />
            <select id="edit_delivery_person_id" name="delivery_person_id" x-model="editing.delivery_person_id"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                <option value="">— Sin asignar —</option>
                @foreach($deliveryPeople as $deliveryPerson)
                    <option value="{{ $deliveryPerson->id }}">{{ $deliveryPerson->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('delivery_person_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_notes" value="Notas" />
            <textarea id="edit_notes" name="notes" rows="2"
                x-model="editing.notes"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-horno focus:ring-horno text-sm"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div>
            <button type="button" x-show="! fiscalOpen" @click="fiscalOpen = true" class="text-sm text-horno hover:underline">
                + Agregar datos fiscales
            </button>

            <template x-if="fiscalOpen">
                <div class="mt-3 p-3 bg-miga rounded-lg space-y-4">
                    <div>
                        <x-input-label for="edit_legal_name" value="Razón social" />
                        <x-text-input id="edit_legal_name" name="legal_name" type="text"
                            class="mt-1 block w-full"
                            x-model="editing.legal_name" />
                        <x-input-error :messages="$errors->get('legal_name')" class="mt-2" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="edit_tax_id" value="CUIT" />
                            <x-text-input id="edit_tax_id" name="tax_id" type="text"
                                class="mt-1 block w-full" placeholder="XX-XXXXXXXX-X"
                                x-model="editing.tax_id" />
                            <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="edit_condicion_iva" value="Condición de IVA" />
                            <select id="edit_condicion_iva" name="condicion_iva" x-model="editing.condicion_iva"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="">— Sin especificar —</option>
                                @foreach(\App\Enums\CondicionIva::cases() as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condicion_iva')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'customer-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
