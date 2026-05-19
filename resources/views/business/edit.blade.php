<x-app-layout>
    <x-slot name="title">Mi negocio</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            @if(session('status') === 'business-updated')
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    Cambios guardados.
                </div>
            @endif

            <form method="POST" action="{{ route('business.update') }}"
                  enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-2 gap-6 items-start">

                    {{-- Datos del negocio --}}
                    <div class="bg-white rounded-lg shadow p-6 space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-corteza mb-1">Datos del negocio</h2>
                            <p class="text-sm text-masa-madre">
                                Información para personalizar el sistema y calcular costos correctamente.
                            </p>
                        </div>

                        {{-- Logo --}}
                        <div>
                            <x-input-label for="logo" value="Logo del negocio" />

                            <div class="mt-2 mb-3">
                                @if ($tenant->logo_path)
                                    <img src="{{ Storage::url($tenant->logo_path) }}"
                                         alt="Logo actual"
                                         class="h-16 w-auto rounded object-contain border border-miga p-1">
                                @else
                                    <x-application-logo class="h-12 w-auto text-corteza opacity-40" />
                                @endif
                            </div>

                            <input id="logo" name="logo" type="file" accept="image/*"
                                   class="mt-1 block w-full text-sm text-masa-madre
                                          file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                          file:text-sm file:font-medium file:bg-miga file:text-corteza
                                          hover:file:bg-harina" />
                            <p class="mt-1 text-xs text-masa-madre">PNG, JPG o SVG. Máximo 2 MB.</p>
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

                        {{-- País y moneda --}}
                        <div class="hidden grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="country" value="País" />
                                <select id="country" name="country"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
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
                            <div>
                                <x-input-label for="currency" value="Moneda" />
                                <select id="currency" name="currency"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                                    @foreach([
                                        'ARS' => 'ARS — Peso argentino',
                                        'UYU' => 'UYU — Peso uruguayo',
                                        'CLP' => 'CLP — Peso chileno',
                                        'COP' => 'COP — Peso colombiano',
                                        'MXN' => 'MXN — Peso mexicano',
                                        'PEN' => 'PEN — Sol peruano',
                                        'USD' => 'USD — Dólar',
                                    ] as $code => $label)
                                        <option value="{{ $code }}"
                                            @selected(old('currency', $tenant->currency) === $code)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                            </div>
                        </div>

                    </div>

                    {{-- Datos fiscales --}}
                    <div class="bg-white rounded-lg shadow p-6 space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-corteza mb-1">Datos fiscales</h2>
                            <p class="text-sm text-masa-madre">
                                Se usan en documentos y comprobantes. Opcionales.
                            </p>
                        </div>

                        <div>
                            <x-input-label for="razon_social" value="Razón Social" />
                            <x-text-input id="razon_social" name="razon_social" type="text"
                                class="mt-1 block w-full"
                                :value="old('razon_social', $tenant->razon_social)" />
                            <x-input-error :messages="$errors->get('razon_social')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="cuit" value="CUIT / CUIL" />
                            <x-text-input id="cuit" name="cuit" type="text"
                                class="mt-1 block w-full"
                                placeholder="XX-XXXXXXXX-X"
                                :value="old('cuit', $tenant->cuit)" />
                            <x-input-error :messages="$errors->get('cuit')" class="mt-2" />
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
                            <x-input-error :messages="$errors->get('condicion_iva')" class="mt-2" />
                        </div>
                    </div>

                </div>

                {{-- Capacidad productiva --}}
                <div class="bg-white rounded-lg shadow p-6" id="field-horas-productivas">
                    <div class="flex items-start justify-between gap-8">
                        <div class="flex-1 space-y-1">
                            <h2 class="text-base font-semibold text-corteza">Capacidad productiva</h2>
                            <p class="text-sm text-masa-madre">
                                ¿Cuántas horas por mes trabaja tu panadería? Se usa para distribuir los gastos fijos y calcular el costo por hora de producción.
                            </p>
                            <div class="flex items-center gap-4 pt-2">
                                <div>
                                    <x-input-label for="productive_hours_month" value="Horas productivas / mes" />
                                    <x-text-input id="productive_hours_month" name="productive_hours_month"
                                        type="number" min="1" max="744"
                                        class="mt-1 block w-32"
                                        :value="old('productive_hours_month', $tenant->productive_hours_month ?: '')"
                                        placeholder="ej. 160" />
                                    <x-input-error :messages="$errors->get('productive_hours_month')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        @if($overheadPerHour !== null)
                        <div class="bg-miga rounded-lg px-5 py-4 text-right shrink-0">
                            <p class="text-xs text-masa-madre">Overhead / hora</p>
                            <p class="text-xl font-semibold text-corteza font-mono mt-0.5">
                                $ {{ number_format($overheadPerHour, 2, ',', '.') }}
                            </p>
                            <p class="text-[11px] text-masa-madre mt-1">
                                Gastos fijos: $ {{ number_format((float)$totalFixedCosts, 2, ',', '.') }} / mes
                            </p>
                        </div>
                        @elseif($tenant->productive_hours_month > 0)
                        <div class="bg-miga rounded-lg px-5 py-4 text-right shrink-0">
                            <p class="text-xs text-masa-madre">Overhead / hora</p>
                            <p class="text-sm text-masa-madre mt-1">Sin gastos fijos activos</p>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="pb-2">
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>

            </form>

        </div>
    </div>
</x-app-layout>
