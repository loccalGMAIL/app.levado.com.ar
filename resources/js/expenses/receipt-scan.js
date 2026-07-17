/**
 * Sube un comprobante de gasto al endpoint de scan y devuelve el borrador que
 * la IA leyó, para que el modal de alta prellene sus campos.
 *
 * Tira un Error con el mensaje del backend (422) para que el modal lo muestre
 * tal cual: los mensajes del extractor ya están redactados para el usuario.
 */
async function scanExpenseReceipt(file, url) {
    const body = new FormData();
    body.append('receipt', file);

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body,
    });

    const data = await res.json().catch(() => null);

    if (!res.ok) {
        throw new Error(
            data?.message
                ?? data?.errors?.receipt?.[0]
                ?? 'No se pudo leer el comprobante. Probá de nuevo.',
        );
    }

    return data;
}

window.scanExpenseReceipt = scanExpenseReceipt;
