<x-admin-layout>
    <x-slot name="title">Nuevo tenant</x-slot>

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tenants.index') }}" class="text-masa-madre hover:text-corteza text-sm">← Tenants</a>
            <span class="text-miga">/</span>
            <h2 class="font-semibold text-xl text-corteza leading-tight">Nuevo tenant</h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                <form method="POST" action="{{ route('admin.tenants.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nombre del negocio" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="country" value="País (código ISO 2)" />
                            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full"
                                :value="old('country', 'AR')" maxlength="2" style="text-transform:uppercase" required />
                            <x-input-error :messages="$errors->get('country')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="currency" value="Moneda (código ISO 3)" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                                :value="old('currency', 'ARS')" maxlength="3" style="text-transform:uppercase" required />
                            <x-input-error :messages="$errors->get('currency')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="productive_hours_month" value="Horas productivas por mes" />
                        <x-text-input id="productive_hours_month" name="productive_hours_month" type="number"
                            class="mt-1 block w-full" :value="old('productive_hours_month', 160)" min="1" max="744" required />
                        <x-input-error :messages="$errors->get('productive_hours_month')" class="mt-1" />
                    </div>

                    <hr class="border-miga">

                    <div>
                        <x-input-label for="owner_email" value="Email del propietario" />
                        <p class="text-xs text-masa-madre mb-1">Se enviará una invitación para que configure su cuenta.</p>
                        <x-text-input id="owner_email" name="owner_email" type="email" class="mt-1 block w-full"
                            :value="old('owner_email')" required />
                        <x-input-error :messages="$errors->get('owner_email')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.tenants.index') }}"
                            class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</a>
                        <x-primary-button type="submit">Crear tenant y enviar invitación</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
