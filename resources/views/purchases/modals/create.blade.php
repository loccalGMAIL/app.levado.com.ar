<x-crud-modal name="purchase-create" title="Nueva compra" :show="$errorsInCreate">
    <form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data" class="space-y-4"
          x-data="{
              supplierId: '{{ old('supplier_id', '') }}',
              invoiceNumber: '{{ old('invoice_number', '') }}',
              ivaRate: '{{ old('default_iva_rate', '0.21') }}',
              hasPercepcion: {{ old('default_percepcion_rate') ? 'true' : 'false' }},
              percepcionRate: '{{ old('default_percepcion_rate', '') }}',
              duplicateWarning: null,
              _dupTimer: null,
              checkDuplicate() {
                  clearTimeout(this._dupTimer);
                  this.duplicateWarning = null;
                  if (!this.invoiceNumber.trim() || !this.supplierId) return;
                  this._dupTimer = setTimeout(async () => {
                      const url = new URL('{{ route('purchases.check-duplicate') }}', window.location.origin);
                      url.searchParams.set('supplier_id', this.supplierId);
                      url.searchParams.set('invoice_number', this.invoiceNumber.trim());
                      const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                      const data = await res.json();
                      this.duplicateWarning = data.duplicate ? data : null;
                  }, 400);
              },
              invoiceFileName: '',
              invoicePreviewUrl: '',
              invoicePreparing: false,
              invoicePickError: '',
              invoiceBaseFile: null,
              invoiceRotation: 0,
              async onPickInvoice(e) {
                  const input = e.target;
                  const original = input.files[0];
                  this.invoicePickError = '';
                  this.invoiceBaseFile = null;
                  this.invoiceRotation = 0;
                  if (this.invoicePreviewUrl) { URL.revokeObjectURL(this.invoicePreviewUrl); }
                  this.invoicePreviewUrl = '';
                  this.invoiceFileName = '';
                  if (!original) { return; }

                  this.invoicePreparing = true;
                  try {
                      const file = await (window.compressInvoiceImage ? window.compressInvoiceImage(original) : Promise.resolve(original));

                      if (file.size > 10 * 1024 * 1024) {
                          this.invoicePickError = 'El archivo supera los 10 MB. Probá con una foto o un PDF más liviano.';
                          input.value = '';
                          return;
                      }

                      const dt = new DataTransfer();
                      dt.items.add(file);
                      input.files = dt.files;

                      this.invoiceBaseFile = file;
                      this.invoiceFileName = file.name;
                      this.invoicePreviewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : '';
                  } finally {
                      this.invoicePreparing = false;
                  }
              },
              async rotateInvoice(direction) {
                  if (!this.invoiceBaseFile || this.invoicePreparing) { return; }

                  this.invoiceRotation = (this.invoiceRotation + direction * 90 + 360) % 360;
                  this.invoicePreparing = true;
                  try {
                      // Siempre desde el archivo original: re-encodear el resultado
                      // anterior apilaría una generación de pérdida por cada giro.
                      const rotated = await window.rotateInvoiceImage(this.invoiceBaseFile, this.invoiceRotation);

                      const input = document.getElementById('create_purchase_invoice_file');
                      const dt = new DataTransfer();
                      dt.items.add(rotated);
                      input.files = dt.files;

                      if (this.invoicePreviewUrl) { URL.revokeObjectURL(this.invoicePreviewUrl); }
                      this.invoicePreviewUrl = URL.createObjectURL(rotated);
                  } finally {
                      this.invoicePreparing = false;
                  }
              }
          }"
          x-on:supplier-created.window="
              const sel = document.getElementById('create_purchase_supplier');
              if (sel && sel._ts) {
                  sel._ts.addOption({ value: String($event.detail.id), text: $event.detail.name });
                  sel._ts.setValue(String($event.detail.id));
              }
          ">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="create_purchase_supplier" value="Proveedor" />
                <button type="button"
                    @click="$dispatch('open-modal', 'supplier-quick-create')"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    + Nuevo proveedor
                </button>
            </div>
            <select id="create_purchase_supplier" name="supplier_id" required
                data-searchable
                @change="supplierId = $event.target.value; checkDuplicate()"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná un proveedor —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}"
                        @selected(old('supplier_id') == $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_purchase_date" value="Fecha de compra" />
                <x-text-input id="create_purchase_date" name="invoice_date" type="date"
                    class="mt-1 block w-full"
                    :value="old('invoice_date', date('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_purchase_invoice" value="N° de factura" />
                <x-text-input id="create_purchase_invoice" name="invoice_number" type="text"
                    class="mt-1 block w-full"
                    :value="old('invoice_number')"
                    @input="invoiceNumber = $event.target.value; checkDuplicate()"
                    placeholder="Opcional" />
                <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
                <p x-show="duplicateWarning"
                    x-cloak
                    class="mt-1 text-xs text-yellow-700 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Ya existe un comprobante con este número para este proveedor
                    (cargado el <span x-text="duplicateWarning?.date"></span>).
                </p>
            </div>
        </div>

        <div>
            <x-input-label for="create_purchase_notes" value="Notas" />
            <textarea id="create_purchase_notes" name="notes"
                rows="2"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm"
                placeholder="Opcional">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_purchase_invoice_file" value="Comprobante" />
            <label class="block">
                <div class="border-2 border-dashed border-miga rounded-md p-4 text-center cursor-pointer hover:border-horno transition-colors"
                    :class="invoiceFileName ? 'border-horno bg-miga/40' : ''">
                    <template x-if="!invoicePreviewUrl">
                        <p class="text-sm text-masa-madre">Tocá para sacar una foto o subir un archivo</p>
                    </template>
                    <template x-if="invoicePreviewUrl">
                        <img :src="invoicePreviewUrl" alt="Vista previa" class="max-h-40 mx-auto rounded-md">
                    </template>
                </div>
                <input type="file" id="create_purchase_invoice_file" name="invoice" accept="image/*,application/pdf" capture="environment"
                    class="hidden"
                    @change="onPickInvoice($event)">
            </label>
            <p class="mt-1 text-xs text-masa-madre" x-show="invoicePreparing">Preparando imagen…</p>
            <p class="mt-1 text-xs text-masa-madre" x-show="invoiceFileName && !invoicePreparing" x-text="'Archivo: ' + invoiceFileName"></p>
            <p class="mt-1 text-xs text-red-600" x-show="invoicePickError" x-text="invoicePickError"></p>

            {{-- Sólo para imágenes: un PDF no se gira en el navegador. --}}
            <div class="mt-2 flex items-center gap-2" x-show="invoicePreviewUrl && !invoicePreparing" x-cloak>
                <x-rotate-button direction="-1" method="rotateInvoice" />
                <x-rotate-button direction="1" method="rotateInvoice" />
                <span class="text-xs text-masa-madre">Girá el comprobante si quedó de costado.</span>
            </div>
            <x-input-error :messages="$errors->get('invoice')" class="mt-2" />
        </div>

        {{-- IVA y percepciones --}}
        <div class="bg-miga/50 rounded-md p-4 space-y-3">
            <p class="text-xs font-semibold text-corteza">IVA de la factura</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="default_iva_rate" value="0" x-model="ivaRate"
                        class="text-corteza focus:ring-horno border-gray-300">
                    Sin IVA
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="default_iva_rate" value="0.105" x-model="ivaRate"
                        class="text-corteza focus:ring-horno border-gray-300">
                    10,5%
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="default_iva_rate" value="0.21" x-model="ivaRate"
                        class="text-corteza focus:ring-horno border-gray-300">
                    21%
                </label>
            </div>

            <div x-show="ivaRate !== '0'" x-cloak class="flex items-center gap-3 pt-1">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" x-model="hasPercepcion"
                        @change="if (!hasPercepcion) percepcionRate = ''"
                        class="rounded text-corteza focus:ring-horno border-gray-300">
                    Percepción
                </label>
                <div x-show="hasPercepcion" x-cloak class="flex items-center gap-1.5">
                    <input type="number" name="default_percepcion_rate"
                        step="0.01" min="0" max="100"
                        x-model="percepcionRate"
                        :disabled="!hasPercepcion"
                        placeholder="0"
                        class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-24 text-right">
                    <span class="text-sm text-masa-madre">%</span>
                </div>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Registrar compra</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'purchase-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
