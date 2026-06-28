// Client-side image downscaling/compression for the invoice scanner.
//
// Phone cameras produce 12–48 MP photos. Rendering one full-resolution in an
// <img> preview (or uploading it as-is) can exhaust memory on low-end devices,
// surfacing "Memoria insuficiente para completar la operación anterior". We
// shrink the photo to a sane size and re-encode it as JPEG before it is ever
// previewed or uploaded.

const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.8;

/**
 * Returns a downscaled JPEG File, or the original file when it is not a raster
 * image (e.g. PDF) or when anything goes wrong (so the feature never breaks).
 *
 * @param {File} file
 * @param {{ maxEdge?: number, quality?: number }} [opts]
 * @returns {Promise<File>}
 */
export async function compressInvoiceImage(file, opts = {}) {
    if (!file || !file.type || !file.type.startsWith('image/')) {
        return file;
    }

    const maxEdge = opts.maxEdge || MAX_EDGE;
    const quality = opts.quality || JPEG_QUALITY;

    try {
        const { width, height } = await readDimensions(file);
        if (!width || !height) {
            return file;
        }

        const scale = Math.min(1, maxEdge / Math.max(width, height));
        const targetWidth = Math.max(1, Math.round(width * scale));
        const targetHeight = Math.max(1, Math.round(height * scale));

        const blob = await drawToJpeg(file, targetWidth, targetHeight, quality);
        if (!blob) {
            return file;
        }

        const name = (file.name || 'factura').replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    } catch (e) {
        return file;
    }
}

/**
 * Reads the intrinsic pixel size without holding a full bitmap around.
 *
 * @param {File} file
 * @returns {Promise<{ width: number, height: number }>}
 */
async function readDimensions(file) {
    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(file);
        const dims = { width: bitmap.width, height: bitmap.height };
        bitmap.close();

        return dims;
    }

    return await new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const dims = { width: img.naturalWidth, height: img.naturalHeight };
            URL.revokeObjectURL(url);
            resolve(dims);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('decode failed'));
        };
        img.src = url;
    });
}

/**
 * Decodes the image (downscaling during decode when supported, which keeps
 * peak memory low), paints it onto a small canvas, and exports a JPEG blob.
 *
 * @param {File} file
 * @param {number} targetWidth
 * @param {number} targetHeight
 * @param {number} quality
 * @returns {Promise<Blob|null>}
 */
async function drawToJpeg(file, targetWidth, targetHeight, quality) {
    const canvas = document.createElement('canvas');
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const ctx = canvas.getContext('2d');
    // Invoices are documents on a white background; flatten transparency to white
    // so PNGs/GIFs don't turn black when re-encoded as JPEG.
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, targetWidth, targetHeight);

    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(file, {
            resizeWidth: targetWidth,
            resizeHeight: targetHeight,
            resizeQuality: 'high',
        });
        ctx.drawImage(bitmap, 0, 0, targetWidth, targetHeight);
        bitmap.close();
    } else {
        await new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                URL.revokeObjectURL(url);
                resolve();
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('decode failed'));
            };
            img.src = url;
        });
    }

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', quality);
    });

    // Release the canvas backing store promptly.
    canvas.width = 0;
    canvas.height = 0;

    return blob;
}

window.compressInvoiceImage = compressInvoiceImage;
