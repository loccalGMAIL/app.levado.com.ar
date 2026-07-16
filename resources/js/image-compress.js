// Client-side image downscaling/compression for the invoice scanner.
//
// Phone cameras produce 12–48 MP photos. Rendering one full-resolution in an
// <img> preview (or uploading it as-is) can exhaust memory on low-end devices,
// surfacing "Memoria insuficiente para completar la operación anterior". We
// shrink the photo to a sane size and re-encode it as JPEG before it is ever
// previewed or uploaded.

const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.8;

// Phone photos carry their orientation in EXIF rather than in the pixels, and
// canvas.toBlob() drops EXIF on re-encode. Decoding with the orientation
// applied bakes it into the pixels, so the upright photo survives the
// re-encode. The spec default changed over time, so we state it explicitly
// instead of depending on the browser version.
const BITMAP_OPTS = { imageOrientation: 'from-image' };

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
 * Returns the file rotated clockwise by `degrees` (0/90/180/270), re-encoded as
 * JPEG. Returns the file untouched for 0°, for non-raster files (PDF) or when
 * anything goes wrong, so the feature never breaks.
 *
 * Callers pass the ORIGINAL compressed file and the cumulative angle rather
 * than rotating the previous result: re-encoding a JPEG on every click would
 * stack a generation of lossy artefacts per turn.
 *
 * @param {File} file
 * @param {number} degrees
 * @param {{ quality?: number }} [opts]
 * @returns {Promise<File>}
 */
export async function rotateInvoiceImage(file, degrees, opts = {}) {
    const angle = ((degrees % 360) + 360) % 360;

    if (!file || !file.type || !file.type.startsWith('image/') || angle === 0) {
        return file;
    }

    try {
        const blob = await drawRotatedJpeg(file, angle, opts.quality || JPEG_QUALITY);

        return blob
            ? new File([blob], file.name || 'factura.jpg', { type: 'image/jpeg', lastModified: Date.now() })
            : file;
    } catch (e) {
        return file;
    }
}

/**
 * @param {File} file
 * @param {number} angle Normalised to 90, 180 or 270.
 * @param {number} quality
 * @returns {Promise<Blob|null>}
 */
async function drawRotatedJpeg(file, angle, quality) {
    const source = await decodeToDrawable(file);
    const { width, height } = intrinsicSize(source);
    const swapped = angle === 90 || angle === 270;

    const canvas = document.createElement('canvas');
    canvas.width = swapped ? height : width;
    canvas.height = swapped ? width : height;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Rotate about the centre of the (already swapped) canvas, then draw the
    // source centred on that origin.
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate((angle * Math.PI) / 180);
    ctx.drawImage(source, -width / 2, -height / 2, width, height);

    if (typeof source.close === 'function') {
        source.close();
    }

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', quality);
    });

    canvas.width = 0;
    canvas.height = 0;

    return blob;
}

/**
 * @param {File} file
 * @returns {Promise<ImageBitmap|HTMLImageElement>}
 */
async function decodeToDrawable(file) {
    if (typeof createImageBitmap === 'function') {
        return await createImageBitmap(file, BITMAP_OPTS);
    }

    return await new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('decode failed'));
        };
        img.src = url;
    });
}

/**
 * @param {ImageBitmap|HTMLImageElement} source
 * @returns {{ width: number, height: number }}
 */
function intrinsicSize(source) {
    return {
        width: source.width || source.naturalWidth,
        height: source.height || source.naturalHeight,
    };
}

/**
 * Reads the intrinsic pixel size without holding a full bitmap around.
 *
 * @param {File} file
 * @returns {Promise<{ width: number, height: number }>}
 */
async function readDimensions(file) {
    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(file, BITMAP_OPTS);
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
            ...BITMAP_OPTS,
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
window.rotateInvoiceImage = rotateInvoiceImage;
