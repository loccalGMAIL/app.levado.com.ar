// Restaura la posición de scroll al volver a una URL (misma ruta + query string).
// Complementa al navegador: tras un POST → redirect (guardar un modal, volver de
// un detalle) el navegador no restaura el scroll porque es una navegación nueva.
const SCROLL_KEY_PREFIX = 'levado:scroll:';
const SCROLL_TTL_MS = 5 * 60 * 1000;

const scrollKey = () => SCROLL_KEY_PREFIX + window.location.pathname + window.location.search;

window.addEventListener('pagehide', () => {
    if (window.scrollY > 0) {
        sessionStorage.setItem(scrollKey(), JSON.stringify({ y: window.scrollY, at: Date.now() }));
    } else {
        sessionStorage.removeItem(scrollKey());
    }
});

window.addEventListener('load', () => {
    const raw = sessionStorage.getItem(scrollKey());
    if (!raw) return;
    sessionStorage.removeItem(scrollKey());
    try {
        const { y, at } = JSON.parse(raw);
        if (Date.now() - at <= SCROLL_TTL_MS && y > 0) {
            window.scrollTo(0, y);
        }
    } catch {
        // valor corrupto: se ignora
    }
});
