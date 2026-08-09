<x-app-layout>
    <x-slot name="title">Recetas</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit']) && old('_form') === 'create';
        $showUrl = fn ($recipe) => route('recipes.show', array_filter(['recipe' => $recipe->id, 'volver' => request()->getQueryString()]));
    @endphp

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <x-list-header title="Recetas"
                subtitle="Cada receta calcula el costo de producción a partir de ingredientes, envases y mano de obra.">
                @can('manage-costs')
                    <button type="button" id="btn-nueva-receta"
                        @click="$dispatch('open-modal', 'recipe-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva receta
                    </button>
                @endcan
            </x-list-header>

            <x-list-filters :reset-route="route('recipes.index')" status-active-label="Activas" status-inactive-label="Inactivas">
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
            </x-list-filters>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
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
                <x-data-table :paginator="$recipes" total-label="receta">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="yield_quantity" :sort="$sort" :dir="$dir" align="right">Rendimiento</x-sortable-th>
                        <x-sortable-th column="selling_price" :sort="$sort" :dir="$dir" align="right">Precio ({{ $priceList->name }}) / u</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3"></th>
                    </x-slot:head>

                    @foreach($recipes as $recipe)
                        @php
                            $price = isset($prices[$recipe->id]) ? (float) $prices[$recipe->id] : null;
                            $priceFormatted = $price !== null ? number_format($price, 2, ',', '.') : '';
                        @endphp
                        <x-data-table.row :dimmed="! $recipe->active">
                            <x-data-table.cell role="title" class="font-medium text-corteza">
                                <a href="{{ $showUrl($recipe) }}" class="hover:underline">
                                    {{ $recipe->name }}
                                    <x-semi-badge :is-semi-elaborate="$recipe->is_semi_elaborate" />
                                </a>
                            </x-data-table.cell>

                            <x-data-table.cell role="subtitle" align="right" class="text-corteza font-mono">
                                {{ number_format((float) $recipe->yield_quantity, 0, ',', '.') }} {{ $recipe->yield_unit->short() }}
                                <span class="dt-card-only font-sans">rendimiento</span>
                            </x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" label="{{ $priceList->name }}:" class="font-mono text-corteza"
                                x-data="priceCell({{ Js::from([
                                    'price' => $price,
                                    'priceFormatted' => $priceFormatted,
                                    'updateUrl' => route('recipes.prices.update', [$recipe, $priceList]),
                                ]) }})">
                                @can('manage-costs')
                                    <span x-show="!editing && !saving"
                                        @click="startEdit()"
                                        class="cursor-pointer hover:text-horno select-none inline-flex items-center gap-1">
                                        <span x-show="price !== null" x-text="'$ ' + priceFormatted"></span>
                                        <span x-show="price === null" class="text-xs text-masa-madre hover:text-corteza">Agregar →</span>
                                    </span>
                                    <input x-show="editing" x-ref="priceInput"
                                        type="number" step="0.01" min="0"
                                        @input="isDirty = true"
                                        @keydown.enter.prevent="savePrice()"
                                        @keydown.escape="editing = false; isDirty = false"
                                        @blur="savePrice()"
                                        class="w-28 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                    <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                @else
                                    @if($price !== null)
                                        $ {{ $priceFormatted }}
                                    @else
                                        <span class="text-masa-madre">—</span>
                                    @endif
                                @endcan
                            </x-data-table.cell>

                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$recipe->active" label-active="activa" label-inactive="inactiva" />
                            </x-data-table.cell>

                            <x-data-table.cell role="actions">
                                <div class="dt-actions">
                                    <a href="{{ $showUrl($recipe) }}"
                                        aria-label="Ver detalle de receta" title="Ver detalle" class="dt-action">
                                        <x-icon name="pencil" />
                                        <span class="dt-card-only">Ver receta</span>
                                    </a>
                                    @can('manage-costs')
                                        <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                                            @csrf
                                            <button type="submit" aria-label="Copiar receta" title="Copiar receta" class="dt-action">
                                                <x-icon name="copy" />
                                                <span class="dt-card-only">Copiar</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $recipe->active ? 'Desactivar' : 'Activar' }}"
                                                title="{{ $recipe->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $recipe->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <x-icon :name="$recipe->active ? 'eye-off' : 'eye'" />
                                                <span class="dt-card-only">{{ $recipe->active ? 'Desactivar' : 'Activar' }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('recipes.modals.create')
        @endcan

    </div>
</x-app-layout>
