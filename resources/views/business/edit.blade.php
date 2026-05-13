<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi negocio
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Datos del negocio</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Esta información se usa para personalizar el sistema y calcular costos correctamente.
                        </p>
                    </header>

                    <form method="POST" action="{{ route('business.update') }}"
                          enctype="multipart/form-data" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        {{-- Logo --}}
                        <div>
                            <x-input-label for="logo" value="Logo del negocio" />

                            @if ($tenant->logo_path)
                                <div class="mt-2 mb-3">
                                    <img src="{{ Storage::url($tenant->logo_path) }}"
                                         alt="Logo actual"
                                         class="h-16 w-auto rounded object-contain border border-gray-200 p-1">
                                </div>
                            @endif

                            <input id="logo" name="logo" type="file" accept="image/*"
                                   class="mt-1 block w-full text-sm text-gray-600
                                          file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                          file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                          hover:file:bg-gray-200" />
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG o SVG. Máximo 2 MB.</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        {{-- Nombre --}}
                        <div>
                            <x-input-label for="name" value="Nombre del negocio" />
                            <x-text-input id="name" name="name" type="text"
                                class="mt-1 block w-full"
                                :value="old('name', $tenant->name)"
                                required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        {{-- País --}}
                        <div>
                            <x-input-label for="country" value="País" />
                            <select id="country" name="country"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500
                                       focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach([
                                    'AR' => 'Argentina',
                                    'UY' => 'Uruguay',
                                    'CL' => 'Chile',
                                    'CO' => 'Colombia',
                                    'MX' => 'México',
                                    'PE' => 'Perú',
                                    'EC' => 'Ecuador',
                                    'BO' => 'Bolivia',
                                    'PY' => 'Paraguay',
                                ] as $code => $label)
                                    <option value="{{ $code }}"
                                        @selected(old('country', $tenant->country) === $code)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>

                        {{-- Moneda --}}
                        <div>
                            <x-input-label for="currency" value="Moneda" />
                            <select id="currency" name="currency"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500
                                       focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach([
                                    'ARS' => 'Peso argentino (ARS)',
                                    'UYU' => 'Peso uruguayo (UYU)',
                                    'CLP' => 'Peso chileno (CLP)',
                                    'COP' => 'Peso colombiano (COP)',
                                    'MXN' => 'Peso mexicano (MXN)',
                                    'PEN' => 'Sol peruano (PEN)',
                                    'USD' => 'Dólar estadounidense (USD)',
                                ] as $code => $label)
                                    <option value="{{ $code }}"
                                        @selected(old('currency', $tenant->currency) === $code)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>

                        {{-- Horas productivas mensuales --}}
                        <div>
                            <x-input-label for="productive_hours_month" value="Horas productivas por mes" />
                            <x-text-input id="productive_hours_month" name="productive_hours_month"
                                type="number" min="1" max="744"
                                class="mt-1 block w-40"
                                :value="old('productive_hours_month', $tenant->productive_hours_month)"
                                required />
                            <p class="mt-1 text-xs text-gray-500">
                                Se usa para prorratear los gastos fijos en el cálculo de costos.
                            </p>
                            <x-input-error :messages="$errors->get('productive_hours_month')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>Guardar cambios</x-primary-button>

                            @if (session('status') === 'business-updated')
                                <p x-data="{ show: true }"
                                   x-show="show"
                                   x-transition
                                   x-init="setTimeout(() => show = false, 2000)"
                                   class="text-sm text-gray-600">
                                    Guardado.
                                </p>
                            @endif
                        </div>
                    </form>
                </section>
            </div>

        </div>
    </div>
</x-app-layout>
