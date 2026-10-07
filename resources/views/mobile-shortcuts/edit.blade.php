<x-app-layout>
    <x-slot name="title">Accesos rápidos</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6 max-w-3xl">

            @if(session('status') === 'mobile-shortcuts-updated')
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    Cambios guardados.
                </div>
            @endif

            <div>
                <h1 class="text-xl font-semibold text-corteza">Accesos rápidos</h1>
                <p class="text-sm text-masa-madre mt-1">
                    Elegí qué secciones aparecen en la barra inferior del celular. Vale para todo el equipo del negocio.
                    Inicio y Más siempre están; las secciones que no elijas quedan dentro de Más.
                </p>
            </div>

            <form method="POST" action="{{ route('mobile-shortcuts.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    @foreach($selected as $index => $current)
                        <div>
                            <x-input-label for="shortcut_{{ $index }}" value="Acceso {{ $index + 1 }}" />
                            <select id="shortcut_{{ $index }}" name="shortcuts[]"
                                    class="mt-1 block w-full sm:w-72 rounded-md border-gray-300 shadow-sm focus:border-horno focus:ring-horno text-sm">
                                @foreach($optionsByGroup as $group => $options)
                                    <optgroup label="{{ $group }}">
                                        @foreach($options as $option)
                                            <option value="{{ $option->value }}"
                                                @selected(old("shortcuts.{$index}", $current->value) === $option->value)>
                                                {{ $option->label() }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('shortcuts.'.$index)" class="mt-2" />
                        </div>
                    @endforeach

                    <x-input-error :messages="$errors->get('shortcuts')" />

                    <p class="text-xs text-masa-madre">
                        Reparto sólo lo ven los roles que administran costos.
                    </p>
                </div>

                <div class="pb-2">
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
