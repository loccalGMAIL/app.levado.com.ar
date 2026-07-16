<x-crud-modal name="variable-expense-edit" title="Editar gasto variable" :show="$errorsInEdit">
    <form method="POST"
        :action="`/variable-expenses/${editing.id}`"
        class="space-y-4"
        enctype="multipart/form-data"
        x-data="{
            showNewCat: false,
            newCatName: '',
            newCatLoading: false,
            newCatError: '',
            receiptName: '',
            receiptPreviewUrl: '',
            receiptPreparing: false,
            receiptPickError: '',
            receiptBaseFile: null,
            receiptRotation: 0,
            async onPickReceipt(e) {
                const input = e.target;
                const original = input.files[0];
                this.receiptPickError = '';
                this.receiptBaseFile = null;
                this.receiptRotation = 0;
                if (this.receiptPreviewUrl) { URL.revokeObjectURL(this.receiptPreviewUrl); }
                this.receiptPreviewUrl = '';
                this.receiptName = '';
                if (!original) { return; }

                this.receiptPreparing = true;
                try {
                    const file = await (window.compressInvoiceImage ? window.compressInvoiceImage(original) : Promise.resolve(original));

                    if (file.size > 10 * 1024 * 1024) {
                        this.receiptPickError = 'El archivo supera los 10 MB. Probá con una foto o un PDF más liviano.';
                        input.value = '';
                        return;
                    }

                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;

                    this.receiptBaseFile = file;
                    this.receiptName = file.name;
                    this.receiptPreviewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : '';
                } finally {
                    this.receiptPreparing = false;
                }
            },
            async rotateReceipt(direction) {
                if (!this.receiptBaseFile || this.receiptPreparing) { return; }

                this.receiptRotation = (this.receiptRotation + direction * 90 + 360) % 360;
                this.receiptPreparing = true;
                try {
                    const rotated = await window.rotateInvoiceImage(this.receiptBaseFile, this.receiptRotation);

                    const input = document.getElementById('edit_ve_receipt');
                    const dt = new DataTransfer();
                    dt.items.add(rotated);
                    input.files = dt.files;

                    if (this.receiptPreviewUrl) { URL.revokeObjectURL(this.receiptPreviewUrl); }
                    this.receiptPreviewUrl = URL.createObjectURL(rotated);
                } finally {
                    this.receiptPreparing = false;
                }
            },
            async createCategory() {
                this.newCatLoading = true;
                this.newCatError = '';
                try {
                    const res = await fetch('{{ route('variable-expense-categories.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ name: this.newCatName }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.newCatError = data?.errors?.name?.[0] ?? 'Error al crear la categoría.';
                        return;
                    }
                    const sel = document.getElementById('edit_ve_category');
                    sel.add(new Option(data.name, data.id, true, true));
                    editing.variable_expense_category_id = String(data.id);
                    this.showNewCat = false;
                    this.newCatName = '';
                } catch {
                    this.newCatError = 'Error al crear la categoría.';
                } finally {
                    this.newCatLoading = false;
                }
            }
        }"
        x-on:supplier-created.window="
            const sel = document.getElementById('edit_ve_supplier');
            sel.add(new Option($event.detail.name, $event.detail.id, true, true));
            editing.supplier_id = String($event.detail.id);
        ">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="ve-edit">
        <input type="hidden" name="variable_expense_id" x-bind:value="editing.id">
        {{-- Sólo para restaurar el link «Ver comprobante» si la validación falla:
             el controller no lo lee, el path vive en la fila. --}}
        <input type="hidden" name="receipt_image_path" x-bind:value="editing.receipt_image_path">

        <div>
            <x-input-label for="edit_ve_name" value="Nombre" />
            <x-text-input id="edit_ve_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <x-input-label for="edit_ve_category" value="Categoría" />
                    <button type="button" @click="showNewCat = !showNewCat"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                        <span x-text="showNewCat ? 'Cancelar' : '+ Nueva categoría'"></span>
                    </button>
                </div>

                <div x-show="showNewCat" x-cloak class="mb-2">
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="newCatName"
                            placeholder="Nombre de la categoría"
                            @keydown.enter.prevent="createCategory()"
                            class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm" />
                        <button type="button"
                            @click="createCategory()"
                            :disabled="newCatLoading || !newCatName.trim()"
                            class="px-3 py-1.5 text-xs bg-corteza text-white rounded-md hover:bg-horno transition-colors disabled:opacity-50 whitespace-nowrap">
                            <span x-text="newCatLoading ? 'Creando…' : 'Crear'"></span>
                        </button>
                    </div>
                    <p x-show="newCatError" x-text="newCatError" class="mt-1 text-xs text-red-500"></p>
                </div>

                <select id="edit_ve_category" name="variable_expense_category_id"
                    x-model="editing.variable_expense_category_id"
                    class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                    required>
                    <option value="">— Seleccioná —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('variable_expense_category_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_ve_date" value="Fecha del gasto" />
                <x-text-input id="edit_ve_date" name="expense_date" type="date"
                    class="mt-1 block w-full"
                    x-model="editing.expense_date"
                    required />
                <x-input-error :messages="$errors->get('expense_date')" class="mt-2" />
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <x-input-label for="edit_ve_supplier" value="Proveedor" />
                <button type="button"
                    @click="$dispatch('open-modal', 'supplier-quick-create')"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    + Nuevo proveedor
                </button>
            </div>
            {{-- Lista todos los proveedores, no sólo los activos: si el gasto apunta a uno dado
                 de baja, su opción tiene que existir o el select caería en «Ninguno» y guardar
                 borraría el proveedor en silencio. --}}
            <select id="edit_ve_supplier" name="supplier_id"
                x-model="editing.supplier_id"
                class="block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Ninguno —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">
                        {{ $supplier->name }}{{ $supplier->active ? '' : ' (inactivo)' }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_ve_amount" value="Monto" />
            <x-text-input id="edit_ve_amount" name="amount" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                x-model="editing.amount"
                required />
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>

        {{-- Sólo adjuntar o reemplazar, sin lectura con IA: prellenar sobre un gasto
             ya cargado pisaría datos que el usuario confirmó al crearlo. --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <x-input-label for="edit_ve_receipt" value="Comprobante" />
                <a x-show="editing.receipt_image_path"
                    x-cloak
                    :href="`/variable-expenses/${editing.id}/receipt`"
                    target="_blank"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    Ver comprobante
                </a>
            </div>

            <label class="block">
                <div class="border-2 border-dashed border-miga rounded-md p-4 text-center cursor-pointer hover:border-horno transition-colors"
                    :class="receiptName ? 'border-horno bg-miga/40' : ''">
                    <template x-if="!receiptPreviewUrl">
                        <p class="text-sm text-masa-madre"
                            x-text="editing.receipt_image_path ? 'Tocá para reemplazar el comprobante' : 'Tocá para sacar una foto o subir un archivo'"></p>
                    </template>
                    <template x-if="receiptPreviewUrl">
                        <img :src="receiptPreviewUrl" alt="Vista previa" class="max-h-40 mx-auto rounded-md">
                    </template>
                </div>
                <input type="file" id="edit_ve_receipt" name="receipt" accept="image/*,application/pdf" capture="environment"
                    class="hidden"
                    @change="onPickReceipt($event)">
            </label>

            <p class="mt-1 text-xs text-masa-madre" x-show="receiptPreparing">Preparando imagen…</p>
            <p class="mt-1 text-xs text-masa-madre" x-show="receiptName && !receiptPreparing" x-text="'Archivo: ' + receiptName"></p>

            {{-- Sólo para imágenes: un PDF no se gira en el navegador. --}}
            <div class="mt-2 flex items-center gap-2" x-show="receiptPreviewUrl && !receiptPreparing" x-cloak>
                <x-rotate-button direction="-1" />
                <x-rotate-button direction="1" />
                <span class="text-xs text-masa-madre">Girá el comprobante si quedó de costado.</span>
            </div>
            <p class="mt-1 text-xs text-masa-madre" x-show="receiptName && !receiptPreparing && editing.receipt_image_path">
                Al guardar, este archivo reemplaza al comprobante anterior.
            </p>
            <p class="mt-1 text-xs text-red-600" x-show="receiptPickError" x-text="receiptPickError"></p>
            <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'variable-expense-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
