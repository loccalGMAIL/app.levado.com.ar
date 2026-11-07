@php
    $theme = auth()->user()?->theme;
@endphp
{{--
    Tema visual. Va en el <head> de los layouts, antes del CSS. El layout ya pone
    data-theme en <html> si el usuario eligió uno (funciona sin JS); este script
    resuelve «Automático» según el sistema operativo antes del primer pintado,
    sigue sus cambios y expone window.levadoTheme.set() para el selector de
    Perfil. Avisa con el evento `theme-changed` (los gráficos se redibujan).
--}}
<meta name="theme-color" content="{{ ($theme ?? \App\Enums\Theme::Claro)->themeColor() }}">
<script>
    (() => {
        const root = document.documentElement;
        const colors = @json(\App\Enums\Theme::themeColors());
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
        let preference = @json($theme?->value);

        const apply = () => {
            const theme = preference ?? (systemDark.matches ? 'oscuro' : 'claro');
            root.dataset.theme = theme;
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', colors[theme]);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme } }));
        };

        systemDark.addEventListener('change', () => preference === null && apply());

        window.levadoTheme = {
            preference: () => preference,
            set(value) {
                preference = value || null;
                apply();
            },
        };

        apply();
    })();
</script>
