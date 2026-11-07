import Alpine from 'alpinejs';

/**
 * Selector de tema: lo usan la sección «Apariencia» de Perfil y el menú de usuario
 * (x-theme-switcher). Aplica el tema al instante con window.levadoTheme (el script
 * de components/theme-head) y lo guarda con PATCH /profile/theme.
 *
 * Cada selector escucha `theme-changed` y relee la preferencia, así dos selectores
 * en la misma página (Perfil y el menú) quedan sincronizados.
 *
 *   theme  '' (Automático), 'claro', 'oscuro' o 'centeno'
 *   url    route('profile.theme')
 */
Alpine.data('themePicker', (cfg) => ({
    theme: cfg.theme ?? '',
    status: null,

    init() {
        window.addEventListener('theme-changed', () => {
            this.theme = window.levadoTheme?.preference() ?? '';
        });
    },

    async choose(value = this.theme) {
        this.theme = value;
        window.levadoTheme?.set(value);
        this.status = null;

        const response = await fetch(cfg.url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ theme: value || null }),
        }).catch(() => null);

        this.status = response?.ok ? 'saved' : 'error';

        if (this.status === 'saved') {
            setTimeout(() => { this.status = null; }, 2000);
        }
    },
}));
