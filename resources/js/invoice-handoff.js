// Traspaso de la foto de una factura entre páginas.
//
// El navegador sólo abre la cámara si el usuario toca el input en esa misma
// página, así que en Compras se captura la foto y acá se deja guardada para que
// la pantalla de lectura la recoja. IndexedDB guarda Blobs sin el tope de ~5 MB
// de sessionStorage. Si algo falla (modo privado, storage bloqueado) las
// funciones resuelven en falso/null y el flujo cae al de siempre.

const DB_NAME = 'levado-handoff';
const STORE = 'files';
const KEY = 'invoice';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function run(db, mode, action) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const req = action(tx.objectStore(STORE));
        tx.oncomplete = () => resolve(req.result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

/**
 * @param {File} file
 * @returns {Promise<boolean>} true si quedó guardado
 */
export async function stashInvoiceFile(file) {
    try {
        const db = await openDb();
        await run(db, 'readwrite', (store) => store.put(file, KEY));
        db.close();

        return true;
    } catch {
        return false;
    }
}

/**
 * Devuelve el archivo guardado y lo borra, o null si no hay.
 *
 * @returns {Promise<File|null>}
 */
export async function takeStashedInvoiceFile() {
    try {
        const db = await openDb();
        const file = await run(db, 'readonly', (store) => store.get(KEY));
        await run(db, 'readwrite', (store) => store.delete(KEY));
        db.close();

        return file instanceof Blob ? file : null;
    } catch {
        return null;
    }
}

window.stashInvoiceFile = stashInvoiceFile;
window.takeStashedInvoiceFile = takeStashedInvoiceFile;
