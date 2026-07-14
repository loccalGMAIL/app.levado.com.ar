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
            sellingPrice:    @js((float) ($defaultPrice ?? 0)),
            targetMargin:    30,
            priceLists:      @js($priceLists->map(fn($l) => ['id' => $l->id, 'name' => $l->name])),
            allPrices:       @js($allPrices),
            selectedListId:  @js($defaultPriceList->id),
            savingPrice:     false,
            ingSort: { by: 'name', dir: 1 },
            labSort: { by: 'name', dir: 1 },
            pkgSort: { by: 'name', dir: 1 },
            subSort: { by: 'name', dir: 1 },

            unitGroups: { gr: 'weight', kg: 'weight', ml: 'volume', L: 'volume', cc: 'volume', u: 'unit' },
            allUnits: @js(collect(\App\Enums\Unit::cases())->map(fn ($u) => ['value' => $u->value, 'label' => $u->short()])->values()),
            compatibleUnits(line) {
                const group = this.unitGroups[line.ingredientUnit];
                return this.allUnits.filter(u => this.unitGroups[u.value] === group);
            },
            convertUnit(amount, from, to) {
                if (from === to) return amount;
                const toBase = (n, u) => (u === 'kg' || u === 'L') ? n * 1000 : n;
                const fromBase = (n, u) => (u === 'kg' || u === 'L') ? n / 1000 : n;
                return fromBase(toBase(amount, from), to);
            },
            changeIngredientUnit(line, newUnit) {
                const converted = this.convertUnit(line.quantity, line.unit, newUnit);
                line.quantity = Math.round(converted * 1000) / 1000;
                line.unit = newUnit;
                this.saveIngredientLine(line);
            },

            get sortedIngredientLines() {
                const { by, dir } = this.ingSort;
                return [...this.ingredientLines].sort((a, b) => {
                    const av = by === 'cost' ? a.quantity * a.costPerLineUnit : a[by];
                    const bv = by === 'cost' ? b.quantity * b.costPerLineUnit : b[by];
                    return typeof av === 'string' ? dir * av.localeCompare(bv, 'es') : dir * (av - bv);
                });
            },
            get sortedLaborLines() {
                const { by, dir } = this.labSort;
                return [...this.laborLines].sort((a, b) => {
                    const av = by === 'cost' ? a.hours * a.hourlyRate : a[by];
                    const bv = by === 'cost' ? b.hours * b.hourlyRate : b[by];
                    return typeof av === 'string' ? dir * av.localeCompare(bv, 'es') : dir * (av - bv);
                });
            },
            get sortedPackagingLines() {
                const { by, dir } = this.pkgSort;
                return [...this.packagingLines].sort((a, b) => {
                    const av = by === 'cost' ? a.quantity * a.costPerUnit : a[by];
                    const bv = by === 'cost' ? b.quantity * b.costPerUnit : b[by];
                    return typeof av === 'string' ? dir * av.localeCompare(bv, 'es') : dir * (av - bv);
                });
            },
            get sortedSubrecipeLines() {
                const { by, dir } = this.subSort;
                return [...this.subrecipeLines].sort((a, b) => {
                    const av = by === 'cost' ? a.quantity * a.costPerLineUnit : a[by];
                    const bv = by === 'cost' ? b.quantity * b.costPerLineUnit : b[by];
                    return typeof av === 'string' ? dir * av.localeCompare(bv, 'es') : dir * (av - bv);
                });
            },
            sortCol(section, col) {
                const key = section + 'Sort';
                if (this[key].by === col) {
                    this[key].dir *= -1;
                } else {
                    this[key] = { by: col, dir: 1 };
                }
            },
            sortIcon(section, col) {
                const s = this[section + 'Sort'];
                if (s.by !== col) return '↕';
                return s.dir === 1 ? '↑' : '↓';
            },

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
            changeList(id) {
                this.selectedListId = id;
                this.sellingPrice = this.allPrices[id] ?? 0;
            },
            async savePrice() {
                if (this.savingPrice) return;
                this.savingPrice = true;
                try {
                    const res = await fetch('/recipes/{{ $recipe->id }}/prices/' + this.selectedListId, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ price: this.sellingPrice > 0 ? this.sellingPrice : null }),
                    });
                    if (res.ok) {
                        const data = await res.json();
                        this.allPrices[this.selectedListId] = data.selling_price;
                    }
                } finally {
                    this.savingPrice = false;
                }
            },
            async saveIngredientLine(line) {
                const res = await fetch('/recipes/{{ $recipe->id }}/ingredient-lines/' + line.id, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ quantity: line.quantity, unit: line.unit }),
                });
                if (res.ok) {
                    const data = await res.json();
                    line.unitLabel = data.unitLabel;
                    line.costPerLineUnit = data.costPerLineUnit;
                }
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
        <div class="sticky top-0 z-20 -mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 mb-6 bg-harina border-b border-miga flex items-center justify-between gap-2 sm:gap-4">
            <a href="{{ route('recipes.index') . (request()->filled('volver') ? '?' . request('volver') : '') }}"
               class="shrink-0 inline-flex items-center gap-1 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span class="hidden sm:inline">Recetas</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg sm:text-xl font-semibold text-corteza leading-tight truncate">{{ $recipe->name }}
                    @if(!$recipe->active)
                        <span class="text-sm font-normal text-gray-400 ml-1">(inactiva)</span>
                    @endif
                </h1>
                <p class="text-xs text-masa-madre mt-0.5 hidden sm:block">
                    {{ $recipeCode }} · Última edición: {{ $recipe->updated_at->diffForHumans() }}
                </p>
            </div>
            @can('manage-costs')
                <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                    <button type="button" @click="savePrice()" :disabled="savingPrice"
                        class="px-2 sm:px-3 py-1.5 text-sm bg-corteza text-white rounded-md hover:bg-horno transition-colors whitespace-nowrap disabled:opacity-50">
                        <span x-show="!savingPrice"><span class="hidden sm:inline">Guardar </span>precio</span>
                        <span x-show="savingPrice" class="text-xs">Guardando…</span>
                    </button>
                    <form method="POST" action="{{ route('recipes.toggle-active', $recipe) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-2 sm:px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors whitespace-nowrap">
                            <span class="sm:hidden">{{ $recipe->active ? 'Desact.' : 'Activar' }}</span>
                            <span class="hidden sm:inline">{{ $recipe->active ? 'Desactivar' : 'Activar' }}</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('recipes.copy', $recipe) }}">
                        @csrf
                        <button type="submit"
                            class="px-2 sm:px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors whitespace-nowrap">
                            Copiar
                        </button>
                    </form>
                    <button type="button"
                        @click="$dispatch('open-modal', 'recipe-edit-info')"
                        class="px-2 sm:px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors whitespace-nowrap">
                        Editar<span class="hidden sm:inline"> info</span>
                    </button>
                </div>
            @endcan
        </div>

        {{-- Two-column layout --}}
        <div class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- Left: line sections --}}
            <div class="flex-1 min-w-0 space-y-4">

                {{-- Ingredientes --}}
                <div class="bg-white rounded-lg shadow" x-data="{ mobileExpanded: false }">
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
                        {{-- Cards (mobile) --}}
                        <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="divide-y divide-miga">
                            <template x-for="line in ingredientLines" :key="line.id">
                                <div class="p-3 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium text-corteza text-sm" x-text="line.name"></div>
                                            <div class="text-[11px] text-masa-madre mt-0.5" x-text="'$' + fmt(line.refCost) + '/' + line.refUnit"></div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-[11px] text-masa-madre">Costo</div>
                                            <div class="font-mono text-sm text-corteza" x-text="'$ ' + fmt(line.quantity * line.costPerLineUnit)"></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-masa-madre shrink-0">Cantidad:</span>
                                        <input type="number"
                                            x-model.number="line.quantity"
                                            @change="saveIngredientLine(line)"
                                            min="0.001" step="any"
                                            class="flex-1 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        <template x-if="compatibleUnits(line).length > 1">
                                            <select @change="changeIngredientUnit(line, $event.target.value)"
                                                class="text-xs border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono shrink-0">
                                                <template x-for="u in compatibleUnits(line)" :key="u.value">
                                                    <option :value="u.value" :selected="u.value === line.unit" x-text="u.label"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="compatibleUnits(line).length <= 1">
                                            <span class="inline-block px-2 py-0.5 bg-miga rounded text-xs text-masa-madre font-mono shrink-0" x-text="line.unitLabel"></span>
                                        </template>
                                        @can('manage-costs')
                                            <form method="POST" action="{{ route('recipes.ingredient-lines.destroy', [$recipe, ':id']) }}"
                                                :action="'{{ route('recipes.ingredient-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Quitar">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </template>
                            <div class="p-3">
                                <button type="button" @click="mobileExpanded = true"
                                    class="w-full py-1.5 text-sm text-masa-madre hover:text-corteza text-center">
                                    Ver tabla completa ↓
                                </button>
                            </div>
                        </div>
                        {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                        <div :class="mobileExpanded ? '' : 'hidden md:block'" class="overflow-x-auto">
                            <div class="md:hidden px-4 py-2 border-b border-miga">
                                <button type="button" @click="mobileExpanded = false"
                                    class="text-sm text-masa-madre hover:text-corteza">
                                    ← Volver a cards
                                </button>
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-miga text-masa-madre">
                                    <tr>
                                        <th @click="sortCol('ing', 'name')" class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide cursor-pointer select-none hover:text-corteza">
                                            Ingrediente <span x-text="sortIcon('ing', 'name')" class="font-normal"></span>
                                        </th>
                                        <th @click="sortCol('ing', 'quantity')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28 cursor-pointer select-none hover:text-corteza">
                                            Cantidad <span x-text="sortIcon('ing', 'quantity')" class="font-normal"></span>
                                        </th>
                                        <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-wide w-20">Unidad</th>
                                        <th @click="sortCol('ing', 'cost')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24 cursor-pointer select-none hover:text-corteza">
                                            Costo <span x-text="sortIcon('ing', 'cost')" class="font-normal"></span>
                                        </th>
                                        @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    <template x-for="line in sortedIngredientLines" :key="line.id">
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
                                                <template x-if="compatibleUnits(line).length > 1">
                                                    <select @change="changeIngredientUnit(line, $event.target.value)"
                                                        class="text-xs border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono">
                                                        <template x-for="u in compatibleUnits(line)" :key="u.value">
                                                            <option :value="u.value" :selected="u.value === line.unit" x-text="u.label"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="compatibleUnits(line).length <= 1">
                                                    <span class="inline-block px-2 py-0.5 bg-miga rounded text-xs text-masa-madre font-mono" x-text="line.unitLabel"></span>
                                                </template>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono text-corteza">
                                                <span x-text="'$ ' + fmt(line.quantity * line.costPerLineUnit)"></span>
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
                        </div>
                    @endif
                </div>

                {{-- Mano de obra --}}
                <div class="bg-white rounded-lg shadow" x-data="{ mobileExpanded: false }">
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
                        {{-- Cards (mobile) --}}
                        <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="divide-y divide-miga">
                            <template x-for="line in laborLines" :key="line.id">
                                <div class="p-3 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium text-corteza text-sm" x-text="line.name"></div>
                                            <div class="text-[11px] text-masa-madre mt-0.5">
                                                $<span x-text="fmt(line.hourlyRate)"></span>/h
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-[11px] text-masa-madre">Costo</div>
                                            <div class="font-mono text-sm text-corteza" x-text="'$ ' + fmt(line.hours * line.hourlyRate)"></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-masa-madre shrink-0">Horas:</span>
                                        <input type="number"
                                            x-model.number="line.hours"
                                            @change="saveLaborLine(line)"
                                            min="0.01" step="any"
                                            class="flex-1 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        @can('manage-costs')
                                            <form method="POST"
                                                :action="'{{ route('recipes.labor-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Quitar">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </template>
                            <div class="p-3">
                                <button type="button" @click="mobileExpanded = true"
                                    class="w-full py-1.5 text-sm text-masa-madre hover:text-corteza text-center">
                                    Ver tabla completa ↓
                                </button>
                            </div>
                        </div>
                        {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                        <div :class="mobileExpanded ? '' : 'hidden md:block'" class="overflow-x-auto">
                            <div class="md:hidden px-4 py-2 border-b border-miga">
                                <button type="button" @click="mobileExpanded = false"
                                    class="text-sm text-masa-madre hover:text-corteza">
                                    ← Volver a cards
                                </button>
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-miga text-masa-madre">
                                    <tr>
                                        <th @click="sortCol('lab', 'name')" class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide cursor-pointer select-none hover:text-corteza">
                                            Rol <span x-text="sortIcon('lab', 'name')" class="font-normal"></span>
                                        </th>
                                        <th @click="sortCol('lab', 'hours')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28 cursor-pointer select-none hover:text-corteza">
                                            Horas <span x-text="sortIcon('lab', 'hours')" class="font-normal"></span>
                                        </th>
                                        <th @click="sortCol('lab', 'cost')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24 cursor-pointer select-none hover:text-corteza">
                                            Costo <span x-text="sortIcon('lab', 'cost')" class="font-normal"></span>
                                        </th>
                                        @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    <template x-for="line in sortedLaborLines" :key="line.id">
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
                                                <span x-text="'$ ' + fmt(line.hours * line.hourlyRate)"></span>
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
                        </div>
                    @endif
                </div>

                {{-- Envases --}}
                <div class="bg-white rounded-lg shadow" x-data="{ mobileExpanded: false }">
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
                        {{-- Cards (mobile) --}}
                        <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="divide-y divide-miga">
                            <template x-for="line in packagingLines" :key="line.id">
                                <div class="p-3 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-medium text-corteza text-sm" x-text="line.name"></div>
                                        <div class="text-right shrink-0">
                                            <div class="text-[11px] text-masa-madre">Costo</div>
                                            <div class="font-mono text-sm text-corteza" x-text="'$ ' + fmt(line.quantity * line.costPerUnit)"></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-masa-madre shrink-0">Cantidad:</span>
                                        <input type="number"
                                            x-model.number="line.quantity"
                                            @change="savePackagingLine(line)"
                                            min="0.001" step="any"
                                            class="flex-1 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                        @can('manage-costs')
                                            <form method="POST"
                                                :action="'{{ route('recipes.packaging-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Quitar">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </template>
                            <div class="p-3">
                                <button type="button" @click="mobileExpanded = true"
                                    class="w-full py-1.5 text-sm text-masa-madre hover:text-corteza text-center">
                                    Ver tabla completa ↓
                                </button>
                            </div>
                        </div>
                        {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                        <div :class="mobileExpanded ? '' : 'hidden md:block'" class="overflow-x-auto">
                            <div class="md:hidden px-4 py-2 border-b border-miga">
                                <button type="button" @click="mobileExpanded = false"
                                    class="text-sm text-masa-madre hover:text-corteza">
                                    ← Volver a cards
                                </button>
                            </div>
                            <table class="w-full text-sm">
                                <thead class="bg-miga text-masa-madre">
                                    <tr>
                                        <th @click="sortCol('pkg', 'name')" class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide cursor-pointer select-none hover:text-corteza">
                                            Envase <span x-text="sortIcon('pkg', 'name')" class="font-normal"></span>
                                        </th>
                                        <th @click="sortCol('pkg', 'quantity')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28 cursor-pointer select-none hover:text-corteza">
                                            Cantidad <span x-text="sortIcon('pkg', 'quantity')" class="font-normal"></span>
                                        </th>
                                        <th @click="sortCol('pkg', 'cost')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24 cursor-pointer select-none hover:text-corteza">
                                            Costo <span x-text="sortIcon('pkg', 'cost')" class="font-normal"></span>
                                        </th>
                                        @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    <template x-for="line in sortedPackagingLines" :key="line.id">
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
                                                <span x-text="'$ ' + fmt(line.quantity * line.costPerUnit)"></span>
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
                        </div>
                    @endif
                </div>

                {{-- Sub-recetas --}}
                <div class="bg-white rounded-lg shadow" x-data="{ mobileExpanded: false }">
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
                        <div>
                            {{-- Cards (mobile) --}}
                            <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="divide-y divide-miga">
                                <template x-for="line in subrecipeLines" :key="line.id">
                                    <div class="p-3 space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="font-medium text-corteza text-sm" x-text="line.name"></div>
                                                <div class="text-[11px] text-masa-madre mt-0.5" x-text="'$' + fmt(line.unitCost) + '/' + line.childYieldUnit"></div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-[11px] text-masa-madre">Costo</div>
                                                <div class="font-mono text-sm text-corteza" x-text="'$ ' + fmt(line.quantity * line.costPerLineUnit)"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-masa-madre shrink-0">Cantidad:</span>
                                            <input type="number"
                                                x-model.number="line.quantity"
                                                @change="saveSubrecipeLine(line)"
                                                min="0.001" step="any"
                                                class="flex-1 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                                            <span class="inline-block px-2 py-0.5 bg-miga rounded text-xs text-masa-madre font-mono shrink-0" x-text="line.unitLabel"></span>
                                            @can('manage-costs')
                                                <form method="POST"
                                                    :action="'{{ route('recipes.subrecipe-lines.destroy', [$recipe, ':id']) }}'.replace(':id', line.id)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Quitar">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>
                                </template>
                                <div class="p-3">
                                    <button type="button" @click="mobileExpanded = true"
                                        class="w-full py-1.5 text-sm text-masa-madre hover:text-corteza text-center">
                                        Ver tabla completa ↓
                                    </button>
                                </div>
                            </div>
                            {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                            <div :class="mobileExpanded ? '' : 'hidden md:block'" class="overflow-x-auto">
                                <div class="md:hidden px-4 py-2 border-b border-miga">
                                    <button type="button" @click="mobileExpanded = false"
                                        class="text-sm text-masa-madre hover:text-corteza">
                                        ← Volver a cards
                                    </button>
                                </div>
                                <table class="w-full text-sm">
                                    <thead class="bg-miga text-masa-madre">
                                        <tr>
                                            <th @click="sortCol('sub', 'name')" class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide cursor-pointer select-none hover:text-corteza">
                                                Sub-receta <span x-text="sortIcon('sub', 'name')" class="font-normal"></span>
                                            </th>
                                            <th @click="sortCol('sub', 'quantity')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-28 cursor-pointer select-none hover:text-corteza">
                                                Cantidad <span x-text="sortIcon('sub', 'quantity')" class="font-normal"></span>
                                            </th>
                                            <th class="px-4 py-2 text-center text-[11px] font-semibold uppercase tracking-wide w-20">Unidad</th>
                                            <th @click="sortCol('sub', 'cost')" class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide w-24 cursor-pointer select-none hover:text-corteza">
                                                Costo <span x-text="sortIcon('sub', 'cost')" class="font-normal"></span>
                                            </th>
                                            @can('manage-costs')<th class="px-4 py-2 w-8"></th>@endcan
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-miga">
                                        <template x-for="line in sortedSubrecipeLines" :key="line.id">
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
                            </div>
                        </div>
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
                            <span class="font-mono text-corteza" x-text="'$ ' + fmt(ingredientCost)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-masa-madre">Mano de obra</span>
                            <span class="font-mono text-corteza" x-text="'$ ' + fmt(laborCost)"></span>
                        </div>
                        <template x-if="packagingLines.length > 0">
                            <div class="flex justify-between">
                                <span class="text-masa-madre">Envases</span>
                                <span class="font-mono text-corteza" x-text="'$ ' + fmt(packagingCost)"></span>
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
                                <span class="font-mono text-corteza" x-text="'$ ' + fmt(fixedCost)"></span>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-miga pt-2 flex justify-between items-baseline">
                        <span class="text-sm font-semibold text-corteza">Costo unitario</span>
                        <span class="text-lg font-bold text-corteza font-mono"
                            x-text="costPerUnit ? '$ ' + fmt(costPerUnit) : '—'"></span>
                    </div>
                </div>

                {{-- Precio de venta --}}
                <div class="bg-white rounded-lg shadow p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-masa-madre uppercase tracking-wide">Precio de venta</p>
                        @if($priceLists->count() > 1)
                            <select @change="changeList(parseInt($event.target.value))"
                                class="text-xs border-gray-300 rounded-md shadow-sm focus:border-horno focus:ring-horno text-corteza py-0.5">
                                @foreach($priceLists as $list)
                                    <option value="{{ $list->id }}" {{ $list->id === $defaultPriceList->id ? 'selected' : '' }}>
                                        {{ $list->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <span class="text-xs text-masa-madre">{{ $defaultPriceList->name }}</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-sm text-masa-madre shrink-0">$</span>
                        <input type="number"
                            x-model.number="sellingPrice"
                            step="0.01" min="0"
                            placeholder="0,00"
                            class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono text-right" />
                    </div>

                    {{-- Margin bar --}}
                    <template x-if="sellingPrice > 0 && costPerUnit !== null">
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs">
                                <span class="text-masa-madre">Margen actual</span>
                                <span class="font-mono font-medium"
                                    :class="currentMarginPct >= 30 ? 'text-green-600' : currentMarginPct >= 15 ? 'text-amber-600' : 'text-red-500'"
                                    x-text="currentMarginPct !== null ? fmt(currentMarginPct) + ' %' : '—'"></span>
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
                                    <strong x-text="'$ ' + fmt(costPerUnit)"></strong>.
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
                            x-text="targetMargin + ' %'"></span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span class="text-xs text-masa-madre">Precio sugerido</span>
                        <span class="text-base font-bold font-mono text-corteza"
                            x-text="suggestedPrice ? '$ ' + fmt(suggestedPrice) : '—'"></span>
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
