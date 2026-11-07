@php
    use App\Enums\Theme;

    $themeOptions = [
        ['value' => '', 'label' => 'Automático', 'description' => 'Sigue la configuración de tu dispositivo.', 'previews' => [Theme::Claro, Theme::Oscuro]],
        ...array_map(fn (Theme $theme) => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'description' => $theme->description(),
            'previews' => [$theme],
        ], Theme::cases()),
    ];
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Apariencia</h2>
        <p class="mt-1 text-sm text-gray-600">
            Elegí cómo se ve Levado para vos. El cambio se aplica al instante.
        </p>
    </header>

    {{-- Con JS el cambio se aplica en la página y se guarda por fetch; sin JS
         queda el botón Guardar del <noscript>. --}}
    <form method="post" action="{{ route('profile.theme') }}" class="mt-6"
          x-data="themePicker({ theme: @js($user->theme?->value ?? ''), url: @js(route('profile.theme')) })"
          @change="choose()">
        @csrf
        @method('patch')

        <fieldset>
            <legend class="sr-only">Tema</legend>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($themeOptions as $option)
                    <label class="block cursor-pointer rounded-lg border border-miga p-2 transition-colors hover:border-horno/50 has-[:checked]:border-horno has-[:checked]:ring-1 has-[:checked]:ring-horno has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-horno">
                        <input type="radio" name="theme" value="{{ $option['value'] }}" class="sr-only"
                               x-model="theme" @checked(($user->theme?->value ?? '') === $option['value'])>

                        {{-- Miniatura: cada mitad pone data-theme y toma los colores
                             de ese tema, así no hay colores escritos a mano. --}}
                        <span class="flex h-16 overflow-hidden rounded-md border border-miga" aria-hidden="true">
                            @foreach ($option['previews'] as $preview)
                                <span data-theme="{{ $preview->value }}" class="flex flex-1 bg-harina">
                                    <span class="w-3 shrink-0 bg-barra"></span>
                                    <span class="flex flex-1 flex-col justify-between p-1.5">
                                        <span class="font-serif text-sm font-semibold leading-none text-corteza">Aa</span>
                                        <span class="h-1.5 w-3/4 rounded-full bg-miga"></span>
                                        <span class="h-2.5 w-1/2 rounded bg-horno"></span>
                                    </span>
                                </span>
                            @endforeach
                        </span>

                        <span class="mt-2 block text-sm font-medium text-corteza">{{ $option['label'] }}</span>
                        <span class="block text-xs text-masa-madre">{{ $option['description'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mt-4 flex items-center gap-4 min-h-[1.25rem]">
            <noscript>
                <x-primary-button>Guardar</x-primary-button>
            </noscript>

            <p x-show="status === 'saved'" x-transition style="display: none" class="text-sm text-gray-600">Guardado.</p>
            <p x-show="status === 'error'" style="display: none" class="text-sm text-red-600">No se pudo guardar. Probá de nuevo.</p>

            @if (session('status') === 'theme-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-gray-600">
                    Guardado.
                </p>
            @endif
        </div>
    </form>
</section>
