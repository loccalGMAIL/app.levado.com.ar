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
                                                <svg class="ico w-3 h-3 text-masa-madre md:hidden" x-show="!hasPolicy" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
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
                                        <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                        <span class="dt-card-only">Ver receta</span>
                                    </a>
                                    <a href="{{ route('recipes.print', $recipe) }}"
                                        aria-label="Imprimir receta" title="Imprimir receta"
                                        class="dt-action max-md:hidden">
                                        <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-print"></use></svg>
                                    </a>
                                    @can('manage-costs')
                                        <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                                            @csrf
                                            <button type="submit"
                                                aria-label="Copiar receta" title="Copiar receta"
                                                class="dt-action">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-copy"></use></svg>
                                                <span class="dt-card-only">Copiar</span>
                                            </button>
                                        </form>
                                        @if($recipe->is_semi_elaborate)
                                            <button type="button"
                                                @click="openReplace({{ Js::from(['id' => $recipe->id, 'name' => $recipe->name]) }})"
                                                aria-label="Reemplazar en recetas" title="Reemplazar en recetas"
                                                class="dt-action">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-arrow-path"></use></svg>
                                                <span class="dt-card-only">Reemplazar</span>
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $recipe->active ? 'Desactivar' : 'Activar' }}" title="{{ $recipe->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $recipe->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-{{ $recipe->active ? 'eye-off' : 'eye' }}"></use></svg>
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
