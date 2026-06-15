<x-app-layout>
    <x-slot name="title">Recetas</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit']) && old('_form') === 'create';
    @endphp

    <div class="py-8 px-6 lg:px-8" x-data="{}">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Recetas</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Cada receta calcula el costo de producción a partir de ingredientes, envases y mano de obra.</p>
                </div>
                @can('manage-costs')
                    <button type="button" id="btn-nueva-receta"
                        @click="$dispatch('open-modal', 'recipe-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva receta
                    </button>
                @endcan
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <select name="status"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activas</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option>
                </select>
                @if($priceLists->count() > 1)
                    <select name="price_list" onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        @foreach($priceLists as $list)
                            <option value="{{ $list->id }}" @selected($priceList->id === $list->id)>
                                Lista: {{ $list->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('recipes.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
                $sortUrl = fn (string $col): string => request()->url() . '?' . http_build_query(
                    array_merge(request()->except(['sort', 'dir', 'page']), [
                        'sort' => $col,
                        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                    ])
                );
                $sortIcon = fn (string $col): string => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';
            @endphp

            @if($recipes->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron recetas con esos filtros.
                    @else
                        Todavía no hay recetas. Creá la primera para empezar a calcular costos.
                    @endif
                </x-empty-state>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl('name') }}" class="hover:text-corteza inline-flex items-center gap-1">
                                        Nombre <span class="text-xs">{{ $sortIcon('name') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <a href="{{ $sortUrl('yield_quantity') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1">
                                        Rendimiento <span class="text-xs">{{ $sortIcon('yield_quantity') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <a href="{{ $sortUrl('selling_price') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1">
                                        Precio ({{ $priceList->name }}) / u <span class="text-xs">{{ $sortIcon('selling_price') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recipes as $recipe)
                                @php
                                    $price = isset($prices[$recipe->id]) ? (float) $prices[$recipe->id] : null;
                                    $initPriceInput     = $price !== null ? number_format($price, 2, '.', '') : '';
                                    $initPriceFormatted = $price !== null ? number_format($price, 2, ',', '.') : '';
                                @endphp
                                <tr class="{{ $recipe->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <a href="{{ route('recipes.show', $recipe) }}" class="hover:underline">
                                            {{ $recipe->name }}
                                            @if($recipe->is_semi_elaborate)
                                                <span class="ml-1 text-[10px] font-medium bg-amber-100 text-amber-700 rounded px-1 py-0.5">semi</span>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        {{ number_format((float)$recipe->yield_quantity, 0, ',', '.') }} {{ $recipe->yield_unit->short() }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza"
                                        x-data="{
                                            editing: false,
                                            saving: false,
                                            isDirty: false,
                                            price: {{ $price ?? 'null' }},
                                            priceFormatted: '{{ $initPriceFormatted }}',
                                            startEdit() {
                                                this.isDirty = false;
                                                this.$refs.priceInput.value = this.price !== null ? parseFloat(this.price).toFixed(2) : '';
                                                this.editing = true;
                                                this.$nextTick(() => this.$refs.priceInput.select());
                                            },
                                            async savePrice() {
                                                if (this.saving) return;
                                                if (!this.isDirty) { this.editing = false; return; }
                                                const raw = this.$refs.priceInput.value.trim();
                                                const payload = raw !== '' ? raw : null;
                                                this.saving = true;
                                                this.editing = false;
                                                try {
                                                    const res = await fetch('{{ route('recipes.prices.update', [$recipe, $priceList]) }}', {
                                                        method: 'PATCH',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                            'Accept': 'application/json',
                                                        },
                                                        body: JSON.stringify({ price: payload })
                                                    });
                                                    const data = await res.json();
                                                    this.price = data.selling_price;
                                                    this.priceFormatted = data.selling_price_formatted ?? '';
                                                } finally {
                                                    this.saving = false;
                                                }
                                            }
                                        }">
                                        @can('manage-costs')
                                            <div x-show="!editing && !saving"
                                                @click="startEdit()"
                                                class="cursor-pointer hover:text-horno select-none">
                                                <span x-show="price !== null"
                                                    x-text="'$ ' + priceFormatted"></span>
                                                <span x-show="price === null"
                                                    class="text-xs text-masa-madre hover:text-corteza">
                                                    Agregar →
                                                </span>
                                            </div>
                                            <input
                                                x-show="editing"
                                                x-ref="priceInput"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                @input="isDirty = true"
                                                @keydown.enter.prevent="savePrice()"
                                                @keydown.escape="editing = false; isDirty = false"
                                                @blur="savePrice()"
                                                class="w-28 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                            <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                        @else
                                            @if($price !== null)
                                                $ {{ $initPriceFormatted }}
                                            @else
                                                <span class="text-masa-madre">—</span>
                                            @endif
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :active="$recipe->active" label-active="activa" label-inactive="inactiva" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('recipes.show', $recipe) }}"
                                                aria-label="Ver detalle de receta"
                                                class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </a>
                                            @can('manage-costs')
                                                <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                                                    @csrf
                                                    <button type="submit"
                                                        aria-label="Copiar receta"
                                                        class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.878a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.375" />
                                                        </svg>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        aria-label="{{ $recipe->active ? 'Desactivar' : 'Activar' }}"
                                                        class="p-1.5 rounded transition-colors {{ $recipe->active ? 'text-masa-madre hover:text-red-600 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                        @if($recipe->active)
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                        @endif
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($recipes->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $recipes->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $recipes->total() }} receta(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('recipes.modals.create')
        @endcan

    </div>
</x-app-layout>
