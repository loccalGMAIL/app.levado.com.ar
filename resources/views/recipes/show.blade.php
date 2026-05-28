<x-app-layout>
    <x-slot name="title">{{ $recipe->name }}</x-slot>

    @php
        $errorsInEdit          = $errors->hasAny(['name', 'description', 'yield_quantity', 'yield_unit', 'selling_price']) && old('_form') === 'edit';
        $errorsInAddIngredient = $errors->hasAny(['ingredient_id', 'quantity', 'unit']) && old('_form') === 'add-ingredient';
        $errorsInAddPackaging  = $errors->hasAny(['packaging_id', 'quantity']) && old('_form') === 'add-packaging';
        $errorsInAddLabor      = $errors->hasAny(['labor_type_id', 'hours']) && old('_form') === 'add-labor';
        $errorsInAddSubrecipe  = $errors->hasAny(['child_recipe_id', 'quantity_used', 'unit']) && old('_form') === 'add-subrecipe';
        $recipeCode = 'REC-' . str_pad($recipe->id, 3, '0', STR_PAD_LEFT);
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            ingredientLines: @js($ingredientLinesData),
            laborLines:      @js($laborLinesData),
            packagingLines:  @js($packagingLinesData),
            subrecipeLines:  @js($subrecipeLinesData),
            overheadPerHour: @js($overheadPerHour),
            yieldQty:        @js((float) $recipe->yield_quantity),
            sellingPrice:    @js((float) ($recipe->selling_price ?? 0)),
            targetMargin:    30,

            get ingredientCost() {
                return this.ingredientLines.reduce((s, l) => s + l.quantity * l.costPerLineUnit, 0);
            },
            get laborCost() {
                return this.laborLines.reduce((s, l) => s + l.hours * l.hourlyRate, 0);
            },
            get packagingCost() {
                return this.packagingLines.reduce((s, l) => s + l.quantity * l.costPerUnit, 0);
            },
            get subrecipeCost() {
                return this.subrecipeLines.reduce((s, l) => s + l.quantity * l.costPerLineUnit, 0);
            },
            get totalLaborHours() {
                return this.laborLines.reduce((s, l) => s + l.hours, 0);
            },
            get fixedCost() {
                return this.totalLaborHours * this.overheadPerHour;
            },
            get totalCost() {
                return this.ingredientCost + this.laborCost + this.packagingCost + this.subrecipeCost + this.fixedCost;
            },
            get costPerUnit() {
                return this.yieldQty > 0 ? this.totalCost / this.yieldQty : null;
            },
            get suggestedPrice() {
                if (!this.costPerUnit || this.targetMargin >= 100) return null;
                return this.costPerUnit / (1 - this.targetMargin / 100);
            },
            get currentMarginPct() {
                if (!this.sellingPrice || !this.costPerUnit || this.sellingPrice <= 0) return null;
                return (this.sellingPrice - this.costPerUnit) / this.sellingPrice * 100;
            },
            get isBelowCost() {
                return this.sellingPrice > 0 && this.costPerUnit !== null && this.sellingPrice < this.costPerUnit;
            },
            fmt(n) {
                if (n === null || n === undefined || isNaN(n)) return '—';
                return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
            },
            async saveIngredientLine(line) {
                await fetch('/recipes/{{ $recipe->id }}/ingredient-lines/' + line.id, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ quantity: line.quantity }),
                });
            },
            async savePackagingLine(line) {
                await fetch('/recipes/{{ $recipe->id }}/packaging-lines/' + line.id, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ quantity: line.quantity }),
                });
            },
            async saveLaborLine(line) {
                await fetch('/recipes/{{ $recipe->id }}/labor-lines/' + line.id, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ hours: line.hours }),
                });
            },
            async saveSubrecipeLine(line) {
                await fetch('/recipes/{{ $recipe->id }}/subrecipe-lines/' + line.id, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ quantity_used: line.quantity }),
                });
            },
        }">

        @if(session('status'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Header sticky --}}
        <div class="sticky top-0 z-20 -mx-6 lg:-mx-8 px-6 lg:px-8 py-3 mb-6 bg-harina border-b border-miga flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-corteza leading-tight">{{ $recipe->name }}
                    @if(!$recipe->active)
                        <span class="text-sm font-normal text-gray-400 ml-1">(inactiva)</span>
                    @endif
                </h1>
                <p class="text-xs text-masa-madre mt-0.5">
                    {{ $recipeCode }} · Última edición: {{ $recipe->updated_at->diffForHumans() }}
                </p>
            </div>
            @can('manage-costs')
                <div class="flex items-center gap-2 shrink-0 flex-wrap">
                    <button type="submit" form="form-precio-venta"
                        class="px-3 py-1.5 text-sm bg-corteza text-white rounded-md hover:bg-horno transition-colors">
                        Guardar precio
                    </button>
                    <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors">
                            {{ $recipe->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                        @csrf
                        <button type="submit"
                            class="px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors">
                            Copiar
                        </button>
                    </form>
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-edit-info')"
                        class="px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors">
                        Editar info
                    </button>
                </div>
            @endcan
        </div>

        {{-- Two-column layout --}}
        <div class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- Left: line sections --}}
            <div class="flex-1 min-w-0 space-y-4">

                {{-- Ingredientes --}}
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-miga">
                        <h3 class="text-sm font-semibold text-corteza">Ingredientes</h3>
                        @can('manage-costs')
                            <button type="button"
                                @click="$dispatch('open-modal', 'recipe-add-ingredient')"
                                class="text-xs text-corteza hover:underline">
                                + Agregar ingrediente
                            </button>
                        @endcan
                    </div>
                    @if($recipe->ingredientLines->isEmpty())
                        <p class="px-4 py-4 text-sm text-masa-madre">Sin ingredientes todavía.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-miga text-masa-madre">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Ingrediente</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28">Cantidad</th>
                                    <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-wide w-20">Unidad</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24">Costo</th>
                                    @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                <template x-for="line in ingredientLines" :key="line.id">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium text-corteza" x-text="line.name"></div>
                                            <div class="text-[11px] text-masa-madre mt-0.5">
                                                <span x-text="line.code"></span>
                                                <span class="mx-1">·</span>
                                                <span x-text="'$' + fmt(line.refCost) + '/' + line.refUnit"></span>
                                                <template x-if="line.supplier">
                                                    <span> — <span x-text="line.supplier"></span></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number"
                                                x-model.number="line.quantity"
                                                @change="saveIngredientLine(line)"
                                                min="0.001" step="any"
                                                class="w-24 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-block px-2 py-0.5 bg-miga rounded text-xs text-masa-madre font-mono" x-text="line.unitLabel"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                            <span x-text="'$ ' + fmt(line.quantity * line.costPerLineUnit)"></span>
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-2.5 text-center">
                                                <form method="POST" action="{{ route('recipes.ingredient-lines.destroy', [$recipe, ':id']) }}"
                                                    :action="'{{ route('recipes.ingredient-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- Mano de obra --}}
                <div class="bg-white rounded-lg shadow overflow-x-auto">
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
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Rol</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28">Horas</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24">Costo</th>
                                    @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                <template x-for="line in laborLines" :key="line.id">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium text-corteza" x-text="line.name"></div>
                                            <div class="text-[11px] text-masa-madre mt-0.5">
                                                $<span x-text="fmt(line.hourlyRate)"></span>/h
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number"
                                                x-model.number="line.hours"
                                                @change="saveLaborLine(line)"
                                                min="0.01" step="any"
                                                class="w-24 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                            <span x-text="'$ ' + fmt(line.hours * line.hourlyRate)"></span>
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-2.5 text-center">
                                                <form method="POST"
                                                    :action="'{{ route('recipes.labor-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- Envases --}}
                <div class="bg-white rounded-lg shadow overflow-x-auto">
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
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Envase</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28">Cantidad</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24">Costo</th>
                                    @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                <template x-for="line in packagingLines" :key="line.id">
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium text-corteza" x-text="line.name"></td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number"
                                                x-model.number="line.quantity"
                                                @change="savePackagingLine(line)"
                                                min="0.001" step="any"
                                                class="w-24 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                            <span x-text="'$ ' + fmt(line.quantity * line.costPerUnit)"></span>
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-2.5 text-center">
                                                <form method="POST"
                                                    :action="'{{ route('recipes.packaging-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- Sub-recetas --}}
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-miga">
                        <h3 class="text-sm font-semibold text-corteza">Sub-recetas</h3>
                        @can('manage-costs')
                            <button type="button"
                                @click="$dispatch('open-modal', 'recipe-add-subrecipe')"
                                class="text-xs text-corteza hover:underline">
                                + Agregar
                            </button>
                        @endcan
                    </div>
                    <template x-if="subrecipeLines.length === 0">
                        <p class="px-4 py-4 text-sm text-masa-madre">Sin sub-recetas todavía.</p>
                    </template>
                    <template x-if="subrecipeLines.length > 0">
                        <table class="w-full text-sm">
                            <thead class="bg-miga text-masa-madre">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide">Sub-receta</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28">Cantidad</th>
                                    <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-wide w-20">Unidad</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24">Costo</th>
                                    @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                <template x-for="line in subrecipeLines" :key="line.id">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium text-corteza" x-text="line.name"></div>
                                            <div class="text-[11px] text-masa-madre mt-0.5">
                                                <span x-text="'$' + fmt(line.unitCost) + '/' + line.childYieldUnit"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number"
                                                x-model.number="line.quantity"
                                                @change="saveSubrecipeLine(line)"
                                                min="0.001" step="any"
                                                class="w-24 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-block px-2 py-0.5 bg-miga rounded text-xs text-masa-madre font-mono" x-text="line.unitLabel"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                            <span x-text="'$ ' + fmt(line.quantity * line.costPerLineUnit)"></span>
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-2.5 text-center">
                                                <form method="POST"
                                                    :action="'{{ route('recipes.subrecipe-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </div>

            </div>{{-- /left --}}

            {{-- Right: sticky sidebar --}}
            <div class="w-full lg:w-64 lg:shrink-0 lg:sticky lg:top-20 space-y-3">

                {{-- Resumen --}}
                <div class="bg-white rounded-lg shadow p-4 space-y-2">
                    <p class="text-xs font-semibold text-masa-madre uppercase tracking-wide">Resumen en tiempo real</p>

                    <div class="space-y-1.5 text-sm pt-1">
                        <div class="flex justify-between">
                            <span class="text-masa-madre">Ingredientes</span>
                            <span class="font-mono text-corteza" x-text="'$ ' + fmt(ingredientCost)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-masa-madre">Mano de obra</span>
                            <span class="font-mono text-corteza" x-text="'$ ' + fmt(laborCost)"></span>
                        </div>
                        <template x-if="packagingLines.length > 0">
                            <div class="flex justify-between">
                                <span class="text-masa-madre">Envases</span>
                                <span class="font-mono text-corteza" x-text="'$ ' + fmt(packagingCost)"></span>
                            </div>
                        </template>
                        <template x-if="subrecipeLines.length > 0">
                            <div class="flex justify-between">
                                <span class="text-masa-madre">Sub-recetas</span>
                                <span class="font-mono text-corteza" x-text="'$ ' + fmt(subrecipeCost)"></span>
                            </div>
                        </template>
                        <template x-if="overheadPerHour > 0">
                            <div class="flex justify-between">
                                <span class="text-masa-madre">Gastos fijos</span>
                                <span class="font-mono text-corteza" x-text="'$ ' + fmt(fixedCost)"></span>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-miga pt-2 flex justify-between items-baseline">
                        <span class="text-sm font-semibold text-corteza">Costo unitario</span>
                        <span class="text-lg font-bold text-corteza font-mono"
                            x-text="costPerUnit ? '$ ' + fmt(costPerUnit) : '—'"></span>
                    </div>
                </div>

                {{-- Precio de venta --}}
                <div class="bg-white rounded-lg shadow p-4 space-y-3">
                    <p class="text-xs font-semibold text-masa-madre uppercase tracking-wide">Precio de venta</p>

                    <form id="form-precio-venta" method="POST" action="{{ route('recipes.update', $recipe) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" value="{{ $recipe->name }}">
                        <input type="hidden" name="yield_quantity" value="{{ $recipe->yield_quantity }}">
                        <input type="hidden" name="yield_unit" value="{{ $recipe->yield_unit->value }}">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-masa-madre shrink-0">$</span>
                            <input type="number" name="selling_price"
                                x-model.number="sellingPrice"
                                step="0.01" min="0"
                                placeholder="0,00"
                                class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono text-right" />
                        </div>
                    </form>

                    {{-- Margin bar --}}
                    <template x-if="sellingPrice > 0 && costPerUnit !== null">
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs">
                                <span class="text-masa-madre">Margen actual</span>
                                <span class="font-mono font-medium"
                                    :class="currentMarginPct >= 30 ? 'text-green-600' : currentMarginPct >= 15 ? 'text-amber-600' : 'text-red-500'"
                                    x-text="currentMarginPct !== null ? fmt(currentMarginPct) + ' %' : '—'"></span>
                            </div>
                            <div class="w-full bg-miga rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full transition-all"
                                    :class="currentMarginPct >= 30 ? 'bg-green-500' : currentMarginPct >= 15 ? 'bg-amber-400' : 'bg-red-500'"
                                    :style="'width:' + Math.max(0, Math.min(100, currentMarginPct ?? 0)) + '%'">
                                </div>
                            </div>
                            <template x-if="isBelowCost">
                                <div class="bg-red-50 border border-red-200 rounded-md p-2 text-xs text-red-700 mt-1">
                                    ⚠ Vendés por debajo del costo. Subí el precio a al menos
                                    <strong x-text="'$ ' + fmt(costPerUnit)"></strong>.
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Simular margen --}}
                <div class="bg-white rounded-lg shadow p-4 space-y-2">
                    <p class="text-xs font-semibold text-masa-madre uppercase tracking-wide">Simular margen</p>
                    <div class="flex items-center gap-3">
                        <input type="range" x-model.number="targetMargin" min="0" max="80" step="1"
                            class="flex-1 accent-horno h-1.5" />
                        <span class="text-sm font-mono font-medium text-corteza w-10 text-right"
                            x-text="targetMargin + ' %'"></span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span class="text-xs text-masa-madre">Precio sugerido</span>
                        <span class="text-base font-bold font-mono text-corteza"
                            x-text="suggestedPrice ? '$ ' + fmt(suggestedPrice) : '—'"></span>
                    </div>
                </div>

            </div>{{-- /sidebar --}}

        </div>{{-- /two-column --}}

    </div>

    @can('manage-costs')
        @include('recipes.modals.edit-info')
        @include('recipes.modals.add-ingredient')
        @include('recipes.modals.add-packaging')
        @include('recipes.modals.add-labor')
        @include('recipes.modals.add-subrecipe')
    @endcan

</x-app-layout>
