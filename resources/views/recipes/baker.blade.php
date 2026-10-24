<x-app-layout>
    <x-slot name="title">% panadero — {{ $recipe->name }}</x-slot>

    @php
        $rows = collect($baker['rows'])->map(fn ($r) => $r + [
            'toggleUrl' => $r['kind'] === 'ingredient' ? route('recipes.ingredient-lines.flour', [$recipe, $r['id']]) : null,
        ])->values();
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            rows: @js($rows),
            canEdit: @js($canEdit),
            mode: 'flour',
            amountKg: '',
            printUrl: @js(route('recipes.print', $recipe)),
            error: null,

            get flourGrams() {
                return this.rows.reduce((s, r) => s + (r.is_flour && r.grams !== null ? r.grams : 0), 0);
            },
            get doughGrams() {
                return this.rows.reduce((s, r) => s + (r.grams !== null ? r.grams : 0), 0);
            },
            get hasFlour() { return this.flourGrams > 0; },
            get totalPercent() { return this.hasFlour ? this.doughGrams / this.flourGrams * 100 : 0; },
            get targetGrams() {
                const kg = parseFloat(String(this.amountKg).replace(',', '.'));
                return kg > 0 ? kg * 1000 : 0;
            },
            get scale() {
                if (!this.hasFlour || !this.targetGrams) return 1;
                const base = this.mode === 'flour' ? this.flourGrams : this.doughGrams;
                return base > 0 ? this.targetGrams / base : 1;
            },
            get scaled() { return this.hasFlour && this.targetGrams > 0; },
            get scaledFlourGrams() { return this.flourGrams * this.scale; },
            get printHref() {
                return this.scaled ? this.printUrl + '?flour=' + Math.round(this.scaledFlourGrams * 1000) / 1000 : this.printUrl;
            },
            percent(r) {
                return this.hasFlour && r.grams !== null ? r.grams / this.flourGrams * 100 : null;
            },
            fmt(n, d = 2) {
                return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: d });
            },
            fmtPercent(r) {
                const p = this.percent(r);
                return p === null ? '—' : this.fmt(p, 1) + ' %';
            },
            get ingredientRows() { return this.rows.filter(r => r.kind !== 'subrecipe'); },
            get subrecipeRows() { return this.rows.filter(r => r.kind === 'subrecipe'); },
            async toggleFlour(r, checked) {
                this.error = null;
                const previous = r.is_flour;
                r.is_flour = checked;
                try {
                    const res = await fetch(r.toggleUrl, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ is_flour: checked }),
                    });
                    if (!res.ok) throw new Error('http');
                } catch (e) {
                    r.is_flour = previous;
                    this.error = 'No se pudo guardar la marca de harina. Probá de nuevo.';
                }
            },
        }">

        {{-- Header --}}
        <div class="sticky top-0 z-20 -mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 mb-6 bg-harina border-b border-miga flex items-center justify-between gap-2 sm:gap-4">
            <a href="{{ route('recipes.show', $recipe) }}"
               class="shrink-0 inline-flex items-center gap-1 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span class="hidden sm:inline">Volver a la receta</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg sm:text-xl font-semibold text-corteza leading-tight truncate">% panadero — {{ $recipe->name }}</h1>
            </div>
            <a :href="printHref"
                class="shrink-0 px-2 sm:px-3 py-1.5 text-sm border border-miga rounded-md text-masa-madre hover:text-corteza hover:bg-white transition-colors whitespace-nowrap">
                <span x-text="scaled ? 'Imprimir escalada' : 'Imprimir'"></span>
            </a>
        </div>

        <div x-show="error" x-cloak x-text="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"></div>

        <div x-show="rows.length === 0" class="bg-white rounded-lg shadow px-4 py-4 text-sm text-masa-madre">
            Esta receta todavía no tiene ingredientes.
        </div>

        <div x-show="rows.length > 0" class="space-y-4">

            <div x-show="!hasFlour" x-cloak class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
                @if($canEdit)
                    No hay ninguna harina marcada. Tildá la columna «Harina» en los ingredientes que cuentan como harina para calcular los porcentajes.
                @else
                    No hay ninguna harina marcada en esta receta, así que no se pueden calcular los porcentajes.
                @endif
            </div>

            {{-- Resumen --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow px-4 py-3">
                    <div class="text-xs text-masa-madre">Harina total</div>
                    <div class="font-mono text-lg text-corteza" x-text="fmt(flourGrams, 1) + ' gr'"></div>
                </div>
                <div class="bg-white rounded-lg shadow px-4 py-3">
                    <div class="text-xs text-masa-madre">Masa total</div>
                    <div class="font-mono text-lg text-corteza" x-text="fmt(doughGrams, 1) + ' gr'"></div>
                </div>
                <div class="bg-white rounded-lg shadow px-4 py-3">
                    <div class="text-xs text-masa-madre">Suma de %</div>
                    <div class="font-mono text-lg text-corteza" x-text="hasFlour ? fmt(totalPercent, 1) + ' %' : '—'"></div>
                </div>
            </div>

            {{-- Calculadora de escalado --}}
            <div class="bg-white rounded-lg shadow px-4 py-3" x-show="hasFlour">
                <h3 class="text-sm font-semibold text-corteza mb-2">Escalar receta</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-md border border-miga overflow-hidden text-sm">
                        <button type="button" @click="mode = 'flour'"
                            :class="mode === 'flour' ? 'bg-corteza text-white' : 'text-masa-madre hover:bg-harina'"
                            class="px-3 py-1.5 transition-colors">Harina (kg)</button>
                        <button type="button" @click="mode = 'dough'"
                            :class="mode === 'dough' ? 'bg-corteza text-white' : 'text-masa-madre hover:bg-harina'"
                            class="px-3 py-1.5 transition-colors">Masa total (kg)</button>
                    </div>
                    <input type="number" x-model="amountKg" min="0" step="any" inputmode="decimal"
                        :placeholder="mode === 'flour' ? 'Kg de harina' : 'Kg de masa'"
                        class="w-40 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm font-mono" />
                    <button type="button" x-show="amountKg !== ''" @click="amountKg = ''"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">Limpiar</button>
                    <span x-show="scaled" class="text-xs text-masa-madre" x-text="'Harina escalada: ' + fmt(scaledFlourGrams / 1000, 3) + ' kg'"></span>
                </div>
            </div>

            {{-- Ingredientes --}}
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-3 border-b border-miga">
                    <h3 class="text-sm font-semibold text-corteza">Ingredientes</h3>
                </div>

                {{-- Cards (mobile) --}}
                <div class="md:hidden divide-y divide-miga">
                    <template x-for="r in ingredientRows" :key="r.kind + r.id">
                        <div class="p-3 space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-medium text-corteza text-sm">
                                    <span x-text="r.name"></span>
                                    <template x-if="r.kind === 'product'">
                                        <span class="ml-1 px-1.5 py-0.5 bg-miga rounded text-[10px] font-semibold uppercase text-masa-madre align-middle">Reventa</span>
                                    </template>
                                </div>
                                <div class="font-mono text-sm text-corteza shrink-0" x-text="fmtPercent(r)"></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-masa-madre">
                                <span x-text="fmt(r.quantity, 3) + ' ' + r.unit"></span>
                                <span x-show="scaled" class="font-mono" x-text="'→ ' + fmt(r.scaled_quantity, 3) + ' ' + r.unit"></span>
                            </div>
                            <template x-if="r.toggleUrl && canEdit">
                                <label class="flex items-center gap-2 text-xs text-masa-madre">
                                    <input type="checkbox" :checked="r.is_flour" @change="toggleFlour(r, $event.target.checked)"
                                        class="rounded border-gray-300 text-corteza focus:ring-horno" />
                                    Es harina
                                </label>
                            </template>
                            <template x-if="r.toggleUrl && !canEdit && r.is_flour">
                                <span class="text-xs font-semibold text-corteza">Harina</span>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Tabla (desktop) --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-miga/50 text-masa-madre text-xs">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Ingrediente</th>
                                <th class="px-4 py-2 text-center font-medium">Harina</th>
                                <th class="px-4 py-2 text-right font-medium">Cantidad</th>
                                <th class="px-4 py-2 text-left font-medium">Unidad</th>
                                <th class="px-4 py-2 text-right font-medium">%</th>
                                <th class="px-4 py-2 text-right font-medium" x-show="scaled">Cantidad escalada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            <template x-for="r in ingredientRows" :key="r.kind + r.id">
                                <tr :class="r.is_flour ? 'bg-harina/60' : ''">
                                    <td class="px-4 py-2 text-corteza">
                                        <span x-text="r.name"></span>
                                        <template x-if="r.kind === 'product'">
                                            <span class="ml-1 px-1.5 py-0.5 bg-miga rounded text-[10px] font-semibold uppercase text-masa-madre align-middle">Reventa</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <template x-if="r.toggleUrl && canEdit">
                                            <input type="checkbox" :checked="r.is_flour" @change="toggleFlour(r, $event.target.checked)"
                                                class="rounded border-gray-300 text-corteza focus:ring-horno" />
                                        </template>
                                        <template x-if="r.toggleUrl && !canEdit">
                                            <span x-text="r.is_flour ? '✓' : ''"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono" x-text="fmt(r.quantity, 3)"></td>
                                    <td class="px-4 py-2 text-masa-madre" x-text="r.unit"></td>
                                    <td class="px-4 py-2 text-right font-mono" x-text="fmtPercent(r)"></td>
                                    <td class="px-4 py-2 text-right font-mono" x-show="scaled" x-text="fmt(r.scaled_quantity, 3) + ' ' + r.unit"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Sub-recetas --}}
            <div class="bg-white rounded-lg shadow" x-show="subrecipeRows.length > 0">
                <div class="px-4 py-3 border-b border-miga">
                    <h3 class="text-sm font-semibold text-corteza">Sub-recetas</h3>
                    <p class="text-xs text-masa-madre mt-0.5">Se cuentan como un ingrediente más, sin desglosar su harina.</p>
                </div>
                <div class="divide-y divide-miga">
                    <template x-for="r in subrecipeRows" :key="r.id">
                        <div class="px-4 py-2 flex items-center justify-between gap-3 text-sm">
                            <span class="text-corteza min-w-0 truncate" x-text="r.name"></span>
                            <span class="flex items-center gap-4 shrink-0">
                                <span class="font-mono text-masa-madre" x-text="fmt(r.quantity, 3) + ' ' + r.unit"></span>
                                <span x-show="scaled" class="font-mono text-masa-madre" x-text="'→ ' + fmt(r.scaled_quantity, 3) + ' ' + r.unit"></span>
                                <span class="font-mono text-corteza w-16 text-right" x-text="fmtPercent(r)"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
