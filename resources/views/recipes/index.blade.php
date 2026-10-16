<x-app-layout>
    <x-slot name="title">Recetas</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit']) && old('_form') === 'create';
        $showUrl = fn ($recipe) => route('recipes.show', array_filter(['recipe' => $recipe->id, 'volver' => request()->getQueryString()]));
    @endphp

    <div class="py-8 px-6 lg:px-8" x-data="{
        replacing: null,
        openReplace(item) {
            this.replacing = item;
            $dispatch('open-modal', 'recipe-replace');
        }
    }">
        <div class="space-y-6">

            <x-list-header title="Recetas" subtitle="Cada receta calcula el costo de producción a partir de ingredientes, envases y mano de obra.">
                @can('manage-costs')
                    <button type="button" id="btn-nueva-receta"
                        @click="$dispatch('open-modal', 'recipe-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva receta
                    </button>
                @endcan
            </x-list-header>

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
                <x-data-table :paginator="$recipes" total-label="receta" :toggle="false">
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
                            $initPriceFormatted = $price !== null ? number_format($price, 2, ',', '.') : '';
                            $policy = $policies[$recipe->id] ?? ['type' => 'manual', 'value' => null];
                        @endphp
                        <x-data-table.row :dimmed="! $recipe->active">
                            <x-data-table.cell role="title">
                                <a href="{{ $showUrl($recipe) }}" class="text-corteza hover:underline">
                                    {{ $recipe->name }}
                                    <x-semi-badge :is-semi-elaborate="$recipe->is_semi_elaborate" />
                                </a>
                            </x-data-table.cell>

                            <x-data-table.cell role="subtitle" align="right">
                                <span class="font-mono text-corteza">{{ number_format((float) $recipe->yield_quantity, 0, ',', '.') }} {{ $recipe->yield_unit->short() }}</span><span class="dt-card-only"> rendimiento</span>
                            </x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" label="{{ $priceList->name }}:" class="text-corteza">
                                @can('manage-costs')
                                    @if($recipe->manufacturedProduct)
                                        <div class="inline-block"
                                            x-data="priceCell({
                                                url: @js(route('products.prices.update', [$recipe->manufacturedProduct, $priceList])),
                                                price: {{ $price ?? 'null' }},
                                                priceFormatted: '{{ $initPriceFormatted }}',
                                                policyType: '{{ $policy['type'] }}',
                                                policyValue: {{ $policy['value'] ?? 'null' }},
                                            })">
                                            <div @click="startEdit($event)"
                                                class="font-mono cursor-pointer hover:text-horno select-none inline-flex items-center gap-1 md:flex-col md:items-end md:gap-0.5">
                                                <span x-show="!saving">
                                                    <span x-show="price !== null" x-text="'$ ' + priceFormatted"></span>
                                                    <span x-show="price === null" class="text-xs text-masa-madre hover:text-corteza">Agregar →</span>
                                                </span>
                                                <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                                <span x-show="hasPolicy" x-text="policyBadge"
                                                    class="text-[10px] px-1.5 py-0.5 rounded bg-miga text-masa-madre font-sans leading-none"></span>
                                                <x-icon name="pencil" class="w-3 h-3 text-masa-madre md:hidden" x-show="!hasPolicy" />
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-masa-madre" title="Creá el artículo elaborado para ponerle precio">—</span>
                                    @endif
                                @else
                                    @if($price !== null)
                                        <span class="font-mono">$ {{ $initPriceFormatted }}</span>
                                    @else
                                        <span class="text-masa-madre">—</span>
                                    @endif
                                @endcan
                            </x-data-table.cell>

                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$recipe->active" label-active="activa" label-inactive="inactiva" />
                            </x-data-table.cell>

                            <x-data-table.cell role="actions">
                                {{-- En la card los botones llevan solo texto y van de a dos por renglón
                                     (una semi tiene 4; con ícono + texto se partían las palabras). --}}
                                <div class="dt-actions max-md:flex-wrap max-md:[&_svg]:hidden max-md:[&>*]:!basis-[calc(50%-0.25rem)] max-md:[&_.dt-action]:whitespace-nowrap">
                                    <a href="{{ $showUrl($recipe) }}"
                                        aria-label="Ver detalle de receta" title="Ver detalle"
                                        class="dt-action">
                                        <x-icon name="pencil" />
                                        <span class="dt-card-only">Ver receta</span>
                                    </a>
                                    <a href="{{ route('recipes.print', $recipe) }}"
                                        aria-label="Imprimir receta" title="Imprimir receta"
                                        class="dt-action max-md:hidden">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.056 48.056 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                                        </svg>
                                    </a>
                                    @can('manage-costs')
                                        <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                                            @csrf
                                            <button type="submit"
                                                aria-label="Copiar receta" title="Copiar receta"
                                                class="dt-action">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.878a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.375" />
                                                </svg>
                                                <span class="dt-card-only">Copiar</span>
                                            </button>
                                        </form>
                                        @if($recipe->is_semi_elaborate)
                                            <button type="button"
                                                @click="openReplace({{ Js::from(['id' => $recipe->id, 'name' => $recipe->name]) }})"
                                                aria-label="Reemplazar en recetas" title="Reemplazar en recetas"
                                                class="dt-action">
                                                <x-icon name="arrow-path" />
                                                <span class="dt-card-only">Reemplazar</span>
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $recipe->active ? 'Desactivar' : 'Activar' }}" title="{{ $recipe->active ? 'Desactivar' : 'Activar' }}"
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
            @include('recipes.modals.replace')
        @endcan

    </div>
</x-app-layout>
