<x-app-layout>
    <x-slot name="title">Recetas</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit']) && old('_form') === 'create';
    @endphp

    <div class="py-8 px-6 lg:px-8" x-data="{}">
        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

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

            @if($recipes->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    Todavía no hay recetas. Creá la primera para empezar a calcular costos.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium text-right">Rendimiento</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recipes as $recipe)
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
                                    <td class="px-4 py-3">
                                        @if($recipe->active)
                                            <span class="text-xs text-green-600 font-medium">activa</span>
                                        @else
                                            <span class="text-xs text-gray-400">inactiva</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('recipes.show', $recipe) }}"
                                                class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                Ver detalle
                                            </a>
                                            @can('manage-costs')
                                                <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                        {{ $recipe->active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-masa-madre">{{ $recipes->count() }} receta(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('recipes.modals.create')
        @endcan

    </div>
</x-app-layout>
