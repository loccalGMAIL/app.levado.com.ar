import './bootstrap';

import Alpine from 'alpinejs';
import TomSelect from 'tom-select';

window.Alpine = Alpine;
window.TomSelect = TomSelect;

Alpine.start();

import './onboarding-tour';
import './purchases/match';
import './image-compress';

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
