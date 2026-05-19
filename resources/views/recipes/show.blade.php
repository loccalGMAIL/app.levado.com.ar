<x-app-layout>
    <x-slot name="title">{{ $recipe->name }}</x-slot>

    @php
        $errorsInEdit           = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit']) && old('_form') === 'edit';
        $errorsInAddIngredient  = $errors->hasAny(['ingredient_id', 'quantity', 'unit']) && old('_form') === 'add-ingredient';
        $errorsInAddPackaging   = $errors->hasAny(['packaging_id', 'quantity']) && old('_form') === 'add-packaging';
        $errorsInAddLabor       = $errors->hasAny(['labor_type_id', 'hours']) && old('_form') === 'add-labor';
    @endphp

    <div class="py-8 px-6 lg:px-8 space-y-6" x-data="{}">

        @if(session('status'))
            <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('recipes.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">Recetas</a>
                    <span class="text-masa-madre text-sm">/</span>
                    <h2 class="text-base font-semibold text-corteza">{{ $recipe->name }}</h2>
                    @if(!$recipe->active)
                        <span class="text-xs text-gray-400 font-medium">(inactiva)</span>
                    @endif
                </div>
                @if($recipe->description)
                    <p class="mt-1 text-sm text-masa-madre">{{ $recipe->description }}</p>
                @endif
                <p class="mt-1 text-sm text-masa-madre">
                    Rinde <span class="font-medium text-corteza">{{ number_format((float)$recipe->yield_quantity, 2, ',', '.') }} {{ $recipe->yield_unit->label() }}</span>
                </p>
            </div>
            @can('manage-costs')
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-edit-info')"
                        class="px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-harina transition-colors">
                        Editar info
                    </button>
                    <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-harina transition-colors">
                            {{ $recipe->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                </div>
            @endcan
        </div>

        {{-- Resumen de costos --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Ingredientes</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    $ {{ number_format($ingredientCost, 2, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Envases</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    $ {{ number_format($packagingCost, 2, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Mano de obra</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    $ {{ number_format($laborCost, 2, ',', '.') }}
                </p>
            </div>
            <div class="bg-corteza rounded-lg shadow p-4 text-white">
                <p class="text-xs opacity-75">Costo total</p>
                <p class="mt-1 text-lg font-semibold font-mono">
                    $ {{ number_format($totalCost, 2, ',', '.') }}
                </p>
                @if($costPerUnit !== null)
                    <p class="mt-1 text-xs opacity-75">
                        $ {{ number_format($costPerUnit, 2, ',', '.') }} / {{ $recipe->yield_unit->short() }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Ingredientes --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-miga">
                <h3 class="text-sm font-semibold text-corteza">Ingredientes</h3>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-add-ingredient')"
                        class="text-xs text-corteza hover:underline">
                        + Agregar
                    </button>
                @endcan
            </div>
            @if($recipe->ingredientLines->isEmpty())
                <p class="px-4 py-4 text-sm text-masa-madre">Sin ingredientes todavía.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-miga text-masa-madre">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Ingrediente</th>
                            <th class="px-4 py-2 text-right font-medium">Cantidad</th>
                            <th class="px-4 py-2 text-right font-medium">Costo</th>
                            @can('manage-costs')<th class="px-4 py-2"></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($recipe->ingredientLines as $line)
                            @php
                                $converter = new \App\Services\UnitConverter;
                                $converted = $converter->convert((float)$line->quantity, $line->unit, $line->ingredient->unit);
                                $lineCost = $converted !== null ? $converted * (float)$line->ingredient->cost_per_unit : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-2.5 text-corteza">{{ $line->ingredient->name }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    {{ number_format((float)$line->quantity, 3, ',', '.') }} {{ $line->unit->short() }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    @if($lineCost !== null)
                                        $ {{ number_format($lineCost, 2, ',', '.') }}
                                    @else
                                        <span class="text-red-500 text-xs">unidades incompatibles</span>
                                    @endif
                                </td>
                                @can('manage-costs')
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('recipes.ingredient-lines.destroy', [$recipe, $line]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Envases --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-miga">
                <h3 class="text-sm font-semibold text-corteza">Envases</h3>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-add-packaging')"
                        class="text-xs text-corteza hover:underline">
                        + Agregar
                    </button>
                @endcan
            </div>
            @if($recipe->packagingLines->isEmpty())
                <p class="px-4 py-4 text-sm text-masa-madre">Sin envases todavía.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-miga text-masa-madre">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Envase</th>
                            <th class="px-4 py-2 text-right font-medium">Cantidad</th>
                            <th class="px-4 py-2 text-right font-medium">Costo</th>
                            @can('manage-costs')<th class="px-4 py-2"></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($recipe->packagingLines as $line)
                            <tr>
                                <td class="px-4 py-2.5 text-corteza">{{ $line->packaging->name }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    {{ number_format((float)$line->quantity, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    $ {{ number_format((float)$line->quantity * (float)$line->packaging->cost_per_unit, 2, ',', '.') }}
                                </td>
                                @can('manage-costs')
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('recipes.packaging-lines.destroy', [$recipe, $line]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Mano de obra --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-miga">
                <h3 class="text-sm font-semibold text-corteza">Mano de obra</h3>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-add-labor')"
                        class="text-xs text-corteza hover:underline">
                        + Agregar
                    </button>
                @endcan
            </div>
            @if($recipe->laborLines->isEmpty())
                <p class="px-4 py-4 text-sm text-masa-madre">Sin mano de obra todavía.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-miga text-masa-madre">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Rol</th>
                            <th class="px-4 py-2 text-right font-medium">Horas</th>
                            <th class="px-4 py-2 text-right font-medium">Costo</th>
                            @can('manage-costs')<th class="px-4 py-2"></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($recipe->laborLines as $line)
                            <tr>
                                <td class="px-4 py-2.5 text-corteza">{{ $line->laborType->name }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    {{ number_format((float)$line->hours, 2, ',', '.') }} h
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                    $ {{ number_format((float)$line->hours * (float)$line->laborType->hourly_rate, 2, ',', '.') }}
                                </td>
                                @can('manage-costs')
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('recipes.labor-lines.destroy', [$recipe, $line]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>

    @can('manage-costs')
        @include('recipes.modals.edit-info')
        @include('recipes.modals.add-ingredient')
        @include('recipes.modals.add-packaging')
        @include('recipes.modals.add-labor')
    @endcan

</x-app-layout>
