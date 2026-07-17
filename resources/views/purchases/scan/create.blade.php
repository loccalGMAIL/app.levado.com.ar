<x-app-layout>
    <x-slot name="title">Leer factura</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="max-w-2xl mx-auto space-y-6">

            <div>
                <a href="{{ route('purchases.index') }}"
                    class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Volver a compras
                </a>
            </div>

            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div>
                <h2 class="text-base font-semibold text-corteza">Leer factura con la cámara</h2>
                <p class="text-sm text-masa-madre mt-0.5">
                    Sacá una foto o subí la factura. La leemos automáticamente y vos revisás los datos antes de guardar.
                </p>
            </div>

            <form method="POST" action="{{ route('purchases.scan') }}" enctype="multipart/form-data"
                class="bg-white rounded-lg shadow p-6 space-y-5"
                x-data="{
                    submitting: false,
                    preparing: false,
                    fileName: '',
                    previewUrl: '',
                    pickError: '',
                    baseFile: null,
                    rotation: 0,
                    async onPick(e) {
                        const input = e.target;
                        const original = input.files[0];
                        this.pickError = '';
                        this.baseFile = null;
                        this.rotation = 0;
                        if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); }
                        this.previewUrl = '';
                        this.fileName = '';
                        if (!original) { return; }

                        this.preparing = true;
                        try {
                            const file = await (window.compressInvoiceImage ? window.compressInvoiceImage(original) : Promise.resolve(original));

                            if (file.size > 10 * 1024 * 1024) {
                                this.pickError = 'El archivo supera los 10 MB. Probá con una foto o un PDF más liviano.';
                                input.value = '';
                                return;
                            }

                            // Upload the (compressed) file instead of the original.
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            input.files = dt.files;

                            this.baseFile = file;
                            this.fileName = file.name;
                            this.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : '';
                        } finally {
                            this.preparing = false;
                        }
                    },
                    async rotateInvoice(direction) {
                        if (!this.baseFile || this.preparing) { return; }

                        this.rotation = (this.rotation + direction * 90 + 360) % 360;
                        this.preparing = true;
                        try {
                            // Siempre desde el archivo original: re-encodear el resultado
                            // anterior apilaría una generación de pérdida por cada giro.
                            const rotated = await window.rotateInvoiceImage(this.baseFile, this.rotation);

                            const input = document.getElementById('scan_invoice_file');
                            const dt = new DataTransfer();
                            dt.items.add(rotated);
                            input.files = dt.files;

                            if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); }
                            this.previewUrl = URL.createObjectURL(rotated);
                        } finally {
                            this.preparing = false;
                        }
                    }
                }"
                @submit="submitting = true">
                @csrf

                <div>
                    <label class="block">
                        <div class="border-2 border-dashed border-miga rounded-lg p-6 text-center cursor-pointer hover:border-horno transition-colors"
                            :class="fileName ? 'border-horno bg-miga/40' : ''">
                            <template x-if="!previewUrl">
                                <div class="space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-masa-madre" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <p class="text-sm text-corteza font-medium">Tocá para sacar una foto o subir un archivo</p>
                                    <p class="text-xs text-masa-madre">JPG, PNG o PDF — hasta 10 MB</p>
                                </div>
                            </template>
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Vista previa" class="max-h-64 mx-auto rounded-md">
                            </template>
                        </div>
                        <input type="file" id="scan_invoice_file" name="invoice" accept="image/*,application/pdf" capture="environment"
                            class="hidden" required
                            @change="onPick($event)">
                    </label>
                    <p class="mt-1 text-xs text-masa-madre" x-show="preparing">Preparando imagen…</p>
                    <p class="mt-1 text-xs text-masa-madre" x-show="fileName && !preparing" x-text="'Archivo: ' + fileName"></p>
                    <p class="mt-1 text-xs text-red-600" x-show="pickError" x-text="pickError"></p>

                    {{-- Sólo para imágenes: un PDF no se gira en el navegador. --}}
                    <div class="mt-2 flex items-center gap-2" x-show="previewUrl && !preparing" x-cloak>
                        <x-rotate-button direction="-1" method="rotateInvoice" />
                        <x-rotate-button direction="1" method="rotateInvoice" />
                        <span class="text-xs text-masa-madre">Girá la factura si quedó de costado: se lee mejor derecha.</span>
                    </div>
                    <x-input-error :messages="$errors->get('invoice')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-masa-madre">
                        La lectura puede tardar unos segundos. No cierres la página.
                    </p>
                    <button type="submit"
                        x-bind:disabled="submitting || preparing || !fileName"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="submitting">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        </template>
                        <span x-text="submitting ? 'Leyendo la factura…' : 'Leer factura'"></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
