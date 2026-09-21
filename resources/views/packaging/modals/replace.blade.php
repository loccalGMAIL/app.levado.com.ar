<x-crud-modal name="packaging-replace" title="Reemplazar en recetas" max-width="lg">
    <form method="POST"
        :action="replacing ? '{{ route('packaging.replace', ['packaging' => '__id__']) }}'.replace('__id__', replacing.id) : '#'"
        class="space-y-4"
        x-data="{
            toId: '',
            preview: null,
            loading: false,
            async loadPreview() {
                this.preview = null;
                if (!this.toId || !replacing) return;
                this.loading = true;
                try {
                    const url = new URL('{{ route('catalog.replacement-preview') }}', window.location.origin);
                    url.searchParams.set('type', 'packaging');
                    url.searchParams.set('from_id', replacing.id);
                    url.searchParams.set('to_id', this.toId);
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    });
                    this.preview = res.ok ? await res.json() : null;
                } finally {
                    this.loading = false;
                }
            }
        }"
        x-init="$watch('replacing', () => {
            toId = '';
            preview = null;
            const ts = $refs.toSelect._ts;
            if (ts) { ts.clear(true); }
        })">
        @csrf

        <p class="text-sm text-masa-madre">
            Reemplazando <span class="font-medium text-corteza" x-text="replacing?.name"></span>
            por otro descartable en todas las recetas que lo usan. El descartable viejo no se borra.
        </p>

        <div>
            <x-input-label value="Reemplazar por" />
            <select x-ref="toSelect" name="to_id" required
                data-searchable
                x-model="toId" @change="loadPreview()"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná el descartable sustituto —</option>
                @foreach($allPackagings as $pkg)
                    <option value="{{ $pkg->id }}">
                        {{ $pkg->name }}@if(! $pkg->active) (inactivo)@endif
                    </option>
                @endforeach
            </select>
            <p x-show="toId && replacing && Number(toId) === replacing.id" x-cloak class="mt-1 text-xs text-red-600">
                Elegí un descartable distinto del que estás reemplazando.
            </p>
        </div>

        <div x-show="loading" x-cloak class="text-sm text-masa-madre">Calculando…</div>

        <template x-if="preview && !loading">
            <div class="bg-miga/50 rounded-md p-3 space-y-2 text-sm">
                <template x-if="preview.recipes.length === 0">
                    <p class="text-masa-madre">Este descartable no se usa en ninguna receta. Igual se puede reemplazar (por si vas a desactivarlo).</p>
                </template>
                <template x-if="preview.recipes.length > 0">
                    <div>
                        <p class="font-medium text-corteza" x-text="'Se reemplazará en ' + preview.recipes.length + ' receta(s):'"></p>
                        <p class="text-masa-madre" x-text="preview.recipes.join(', ')"></p>
                    </div>
                </template>
                <template x-if="preview.merges > 0">
                    <p class="text-masa-madre" x-text="preview.merges + ' línea(s) ya tenían el descartable destino y se fusionarán.'"></p>
                </template>
            </div>
        </template>

        <label class="flex items-start gap-2 cursor-pointer select-none">
            <input type="hidden" name="deactivate_source" value="0">
            <input type="checkbox" name="deactivate_source" value="1" checked
                class="mt-0.5 rounded border-gray-300 text-corteza focus:ring-horno">
            <span class="text-sm text-masa-madre">
                <span class="font-medium text-corteza">Desactivar el descartable viejo</span>
                <span class="block text-xs">Ya no aparecerá para nuevas compras ni recetas.</span>
            </span>
        </label>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                :disabled="!toId || (replacing && Number(toId) === replacing.id)"
                class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Reemplazar
            </button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'packaging-replace')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>
