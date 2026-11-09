@php
    use App\Enums\Theme;

    $current = auth()->user()?->theme?->value ?? '';

    $options = [
        ['value' => '', 'label' => 'Automático', 'short' => 'Auto', 'icon' => 'computer-desktop'],
        ...array_map(fn (Theme $theme) => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'short' => $theme->label(),
            'icon' => $theme->icon(),
        ], Theme::cases()),
    ];
@endphp
{{--
    Selector de tema compacto para el menú de usuario (desktop) y el panel «Más»
    (mobile). Misma lógica que la sección Apariencia de Perfil (themePicker en
    resources/js/theme-picker.js). @click.stop: elegir un tema no cierra el menú,
    así se pueden probar varios.
--}}
<div x-data="themePicker({ theme: @js($current), url: @js(route('profile.theme')) })" @click.stop
     {{ $attributes }}>
    <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40">Tema</p>

    <div role="radiogroup" aria-label="Tema" class="mt-1.5 grid grid-cols-4 gap-1 rounded-lg bg-miga p-1">
        @foreach ($options as $option)
            <button type="button" role="radio" title="{{ $option['label'] }}"
                    aria-checked="{{ $current === $option['value'] ? 'true' : 'false' }}"
                    :aria-checked="(theme === @js($option['value'])).toString()"
                    @click="choose(@js($option['value']))"
                    class="flex flex-col items-center gap-0.5 rounded-md px-1 py-1.5 text-[10px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-horno"
                    :class="theme === @js($option['value'])
                        ? 'bg-superficie text-corteza shadow-sm'
                        : 'text-masa-madre hover:text-corteza'">
                <x-icon :name="$option['icon']" class="w-4 h-4" />
                <span>{{ $option['short'] }}</span>
            </button>
        @endforeach
    </div>

    <p x-show="status === 'error'" style="display: none" class="mt-1 text-xs text-red-600">No se pudo guardar.</p>
</div>
