import './bootstrap';

import Alpine from 'alpinejs';
import TomSelect from 'tom-select';

import { priceCell, priceRow } from './rows/price-editor';
import { stockCell } from './rows/stock-cell';

window.Alpine = Alpine;
window.TomSelect = TomSelect;

// Componentes de fila: se definen una vez acá en vez de repetirse en el HTML
// dentro de cada @foreach. Van colgados de 'alpine:init' —que corre durante
// Alpine.start()— y no sueltos: así el registro no depende del orden de los
// módulos. Por eso mismo NO se los debe cargar con import() dinámico: un
// Alpine.data() que llega después de start() no se aplica a lo ya montado y el
// x-data queda sin definir, en silencio.
document.addEventListener('alpine:init', () => {
    Alpine.data('priceRow', priceRow);
    Alpine.data('priceCell', priceCell);
    Alpine.data('stockCell', stockCell);
});

Alpine.start();

import './onboarding-tour';
import './purchases/match';
import './expenses/receipt-scan';
import './image-compress';
import './scroll-restore';
import './dashboard-charts';

// Capturar el prompt de instalación de la PWA (Android/Chrome) antes de que
// Alpine monte el banner; el componente escucha el evento 'pwa-installable'.
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredInstallPrompt = e;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

// Registrar el service worker de la PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // sin SW la app sigue funcionando; solo pierde instalación/offline
        });
    });
}

// Initialize Tom Select on [data-searchable] elements (non-Alpine selects)
document.querySelectorAll('[data-searchable]').forEach(function (el) {
    const ts = new TomSelect(el, { maxOptions: null });
    el._ts = ts;
});

// Prevent double-submit and show loading state on forms with data-loading buttons
document.addEventListener('submit', function(e) {
    const btn = e.target.querySelector('[type="submit"][data-loading]');
    if (!btn || btn.disabled) { return; }
    btn.disabled = true;
    btn.textContent = btn.dataset.loading;
}, true);

// Limit decimal inputs to max 4 places in real time
document.addEventListener('input', function (e) {
    const max = e.target.dataset.maxdecimals;
    if (!max) return;
    const decimals = parseInt(max, 10);
    const value = e.target.value;
    const dotIdx = value.indexOf('.');
    if (dotIdx !== -1 && value.length - dotIdx - 1 > decimals) {
        e.target.value = value.slice(0, dotIdx + decimals + 1);
    }
});
