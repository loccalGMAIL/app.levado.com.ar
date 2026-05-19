<x-admin-layout>
    <x-slot name="title">Editar {{ $tenant->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-masa-madre hover:text-corteza text-sm">
                ← {{ $tenant->name }}
            </a>
            <span class="text-miga">/</span>
            <h2 class="font-semibold text-xl text-corteza leading-tight">Editar configuración</h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Nombre del negocio" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name', $tenant->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="country" value="País (código ISO 2)" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full"
                                :value="old('country', $tenant->country)" maxlength="2" style="text-transform:uppercase" required />
                            <x-input-error :messages="$errors->get('country')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="currency" value="Moneda (código ISO 3)" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                                :value="old('currency', $tenant->currency)" maxlength="3" style="text-transform:uppercase" required />
                            <x-input-error :messages="$errors->get('currency')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="productive_hours_month" value="Horas productivas por mes" />
                        <x-text-input id="productive_hours_month" name="productive_hours_month" type="number"
                            class="mt-1 block w-full" :value="old('productive_hours_month', $tenant->productive_hours_month)"
                            min="1" max="744" required />
                        <x-input-error :messages="$errors->get('productive_hours_month')" class="mt-1" />
                    </div>

                    <hr class="border-miga">

                    <div>
                        <x-input-label for="razon_social" value="Razón Social" />
                        <x-text-input id="razon_social" name="razon_social" type="text"
                            class="mt-1 block w-full" :value="old('razon_social', $tenant->razon_social)" />
                        <x-input-error :messages="$errors->get('razon_social')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cuit" value="CUIT / CUIL" />
                            <x-text-input id="cuit" name="cuit" type="text"
                                class="mt-1 block w-full" placeholder="XX-XXXXXXXX-X"
                                :value="old('cuit', $tenant->cuit)" />
                            <x-input-error :messages="$errors->get('cuit')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="condicion_iva" value="Condición de IVA" />
                            <select id="condicion_iva" name="condicion_iva"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                                <option value="">— Sin especificar —</option>
                                @foreach(\App\Enums\CondicionIva::cases() as $case)
                                    <option value="{{ $case->value }}"
                                        @selected(old('condicion_iva', $tenant->condicion_iva?->value) === $case->value)>
                                        {{ $case->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condicion_iva')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.tenants.show', $tenant) }}"
                            class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</a>
                        <x-primary-button type="submit">Guardar cambios</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
