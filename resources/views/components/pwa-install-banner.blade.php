{{-- Banner de instalación de la PWA: prompt nativo en Android/Chrome e instrucciones en iOS. --}}
<div
    x-data="{
        visible: false,
        mode: null,
        dismissKey: 'levado:pwa-banner-dismissed',
        dismissDays: 30,
        init() {
            if (this.isStandalone() || this.isDismissed()) {
                return;
            }
            if (window.deferredInstallPrompt) {
                this.mode = 'android';
                this.visible = true;
            }
            window.addEventListener('pwa-installable', () => {
                if (! this.isStandalone() && ! this.isDismissed()) {
                    this.mode = 'android';
                    this.visible = true;
                }
            });
            if (! this.visible && this.isIos()) {
                this.mode = 'ios';
                this.visible = true;
            }
        },
        isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        },
        isIos() {
            return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        },
        isDismissed() {
            const at = parseInt(localStorage.getItem(this.dismissKey) || '0', 10);
            return at > 0 && (Date.now() - at) < this.dismissDays * 24 * 60 * 60 * 1000;
        },
        dismiss() {
            localStorage.setItem(this.dismissKey, String(Date.now()));
            this.visible = false;
        },
        async install() {
            const prompt = window.deferredInstallPrompt;
            if (! prompt) {
                return;
            }
            prompt.prompt();
            const { outcome } = await prompt.userChoice;
            window.deferredInstallPrompt = null;
            this.visible = false;
            if (outcome === 'dismissed') {
                this.dismiss();
            }
        },
    }"
    x-show="visible"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-full opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-full opacity-0"
    style="display: none"
    class="sm:hidden fixed bottom-16 inset-x-0 z-40 px-3 pb-2"
>
    <div class="bg-white border border-miga rounded-xl shadow-lg p-3 flex items-start gap-3">
        <img src="/icons/icon-192.png" alt="{{ config('app.name') }}" class="w-11 h-11 rounded-lg border border-miga shrink-0">

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-corteza">Instalá {{ config('app.name') }} en tu teléfono</p>

            <template x-if="mode === 'android'">
                <div class="mt-1.5">
                    <p class="text-xs text-masa-madre">Accedé más rápido, a pantalla completa y desde tu inicio.</p>
                    <button type="button" @click="install()"
                        class="mt-2 px-4 py-1.5 bg-corteza text-white text-xs font-medium rounded-md hover:bg-horno transition-colors">
                        Instalar
                    </button>
                </div>
            </template>

            <template x-if="mode === 'ios'">
                <p class="text-xs text-masa-madre mt-1.5 leading-relaxed">
                    Tocá
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 text-horno" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12l-4 4m4-4l4 4M6 12H5a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2h-1" />
                    </svg>
                    <strong class="text-corteza">Compartir</strong> y elegí
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 text-horno" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="4" y="4" width="16" height="16" rx="3" />
                        <path stroke-linecap="round" d="M12 9v6m-3-3h6" />
                    </svg>
                    <strong class="text-corteza">Agregar a inicio</strong>.
                </p>
            </template>
        </div>

        <button type="button" @click="dismiss()" aria-label="Cerrar"
            class="shrink-0 p-1 text-masa-madre hover:text-corteza transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
