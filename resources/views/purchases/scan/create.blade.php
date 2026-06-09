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
                    fileName: '',
                    previewUrl: '',
                    onPick(e) {
                        const file = e.target.files[0];
                        this.fileName = file ? file.name : '';
                        this.previewUrl = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : '';
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
                        <input type="file" name="invoice" accept="image/*,application/pdf" capture="environment"
                            class="hidden" required
                            @change="onPick($event)">
                    </label>
                    <p class="mt-1 text-xs text-masa-madre" x-show="fileName" x-text="'Archivo: ' + fileName"></p>
                    <x-input-error :messages="$errors->get('invoice')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-masa-madre">
                        La lectura puede tardar unos segundos. No cierres la página.
                    </p>
                    <button type="submit"
                        x-bind:disabled="submitting || !fileName"
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
