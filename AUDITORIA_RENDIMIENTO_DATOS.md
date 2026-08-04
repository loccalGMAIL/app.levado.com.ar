# Auditoría de acceso a datos y rendimiento de consultas — Levado

**Fecha:** 4 de agosto de 2026
**Alcance:** código completo del repositorio, acotado a acceso a datos y rendimiento de consultas.
**Enfoque:** eliminar N+1 y reducir consultas **sin alterar el comportamiento funcional**.
**Stack auditado:** Laravel 13.7, PHP 8.3, MySQL en producción, Blade + Alpine (no hay Inertia/Vue).

Complementa a `AUDITORIA_DEUDA_TECNICA.md` (16/07/2026), que ya había marcado el techo de
escalabilidad como riesgo genérico —«dashboard que recalcula todo en memoria, propagación de costos
síncrona»—. Este informe convierte ese titular en hallazgos con ubicación, medición estimada y
código propuesto.

---

## 0. Resumen ejecutivo

**El diseño de lectura es bueno y no hay que reescribirlo.** El dashboard y la matriz de precios ya
paginan, filtran y ordenan en SQL sobre los caches denormalizados `recipes.unit_cost` y
`recipes.labor_hours`; los precios se traen en una query e indexan por id; hay `withCount`,
`withSum` y `selectSub` usados correctamente. Quien haya escrito esas pantallas sabía lo que hacía.

El costo se corrió a tres lugares donde nadie lo miró:

1. **La propagación de escritura** (`RecipeCostPropagator`) recorre el árbol de recetas nodo por
   nodo haciendo I/O en cada paso.
2. **La reconciliación de alertas** (`NotificationService::syncStateAlerts`) corre síncrona en cada
   carga del dashboard y del centro de alertas, y es O(catálogo del tenant).
3. **La capa de autorización** (`Gate::before` → `User::isSuperAdmin()`) emite una query por cada
   `@can` y cada `authorize()`. Hay 18 `@can` en una sola vista y 57 `authorize()` en controllers.

**30 hallazgos enumerados** — 11 de N+1 (§1), 12 de consultas ineficientes (§2) y 7 de índices
faltantes (§4):

| Severidad | Cantidad | Detalle | Naturaleza |
|---|---|---|---|
| 🔴 Crítica | 8 | N1-N6, I2, I3 | Escalan con el volumen de datos del tenant; ya son medibles hoy |
| 🟠 Alta | 11 | N7-N9, Q1-Q5, I1, I5, I7 | Costo fijo por request en rutas de uso diario |
| 🟡 Media | 10 | N10, N11, Q6-Q11, I4, I6 | Consultas evitables, overfetching, colecciones sin límite |
| 🟢 Baja | 1 | Q12 | Comandos de mantenimiento sin acotar |

A eso se suman **12 riesgos transversales** (§5, `R1`-`R12`), que en su mayoría **no son hallazgos
nuevos**: reagrupan los anteriores por tipo de riesgo. Las excepciones son `R6` (los N+1 son
silenciosos en producción), `R10` y `R11`, que se documentan para dejar cerrados los puntos de
eventos de modelo y de jobs — en ambos casos **no se encontró problema**.

La §3 (optimización de Eloquent) no agrega hallazgos: mapea las herramientas de la API contra los
hallazgos ya enumerados.

**Los tres cambios de mejor relación beneficio/riesgo** (detalle en §7):

| | Cambio | Efecto | Riesgo |
|---|---|---|---|
| 1 | Eager-load de `tenantUsers` en `SetTenantContext` | −18 queries en `/recipes/{id}`, −2 en **cada** request | Bajo |
| 2 | `withExists()` en el view composer de onboarding | −5 queries en **cada página** de la app | Muy bajo |
| 3 | Morph map real (`enforceMorphMap`) para `stockable` | Elimina un N+1 y desbloquea `with()` / `whereHasMorph()` | Bajo, sin migración de datos |

**Restricción que condiciona todo el informe.** Los tests corren sobre **SQLite en memoria**
(`phpunit.xml:27-28`) mientras producción es MySQL. Todo el SQL propuesto acá es portable entre
ambos motores; donde hubo que elegir entre el constructor de Eloquent y SQL crudo, se eligió
Eloquent (`whereColumn`, `leftJoinSub`, `withExists`, `having`) precisamente por eso.

**Nota metodológica.** Los conteos de queries son **estimaciones derivadas de leer el camino de
ejecución**, no mediciones con profiler. Cada uno se presenta con su fórmula visible (p. ej. «7 ×
nodos del cierre») para que se pueda auditar el razonamiento. Antes de dar por buena cualquier
corrección conviene confirmar el número real con Telescope, Debugbar o un `DB::listen()` temporal.

---

## 1. Problemas N+1

### 🔴 N1 — `RecipeCostPropagator::propagateFrom()` — BFS con I/O por nodo

**Ubicación:** `app/Services/RecipeCostPropagator.php:18-56`

```php
while (! empty($queue)) {
    $id = array_shift($queue);
    if (isset($visited[$id])) { continue; }
    $visited[$id] = true;

    $node = Recipe::with([                          // ← 5 queries (1 receta + 4 relaciones)
        'ingredientLines.ingredient',
        'packagingLines.packaging',
        'laborLines.laborType',
        'subrecipeLines.childRecipe',
    ])->find($id);

    if (! $node) { continue; }

    $costs = $this->calculator->calculate($node);
    $node->update([                                  // ← 1 UPDATE
        'unit_cost' => $costs['cost_per_unit'],
        'labor_hours' => $costs['total_labor_hours'],
    ]);

    $parentIds = $node->parentSubrecipeLines()->pluck('recipe_id')->toArray();  // ← 1 SELECT
    // …
}
```

**Problema.** El recorrido del grafo y la carga de datos están entrelazados: cada iteración del
`while` hace una ronda completa de I/O. Son **~7 queries por nodo** del cierre de ancestros. No es
un N+1 accidental por lazy loading —el `with()` está bien puesto— es un N+1 *estructural*: el
`with()` se ejecuta una vez por nodo en vez de una vez por recorrido.

**Queries estimadas.** `7 × nodos del cierre`. Un árbol con 20 recetas afectadas ≈ **140 queries**
en una request HTTP síncrona.

**Dónde duele.** `RecipeLineController` invoca `propagateFrom()` en **12 puntos** (líneas 57, 80,
95, 113, 124, 133, 151, 162, 171, 208, 219, 228). Los métodos `updateIngredientLine` (`:80`),
`updatePackagingLine` (`:124`), `updateLaborLine` (`:162`) y `updateSubrecipeLine` (`:219`) son
endpoints **AJAX de edición inline**: el usuario ajusta una cantidad en la vista de receta y dispara
el árbol completo por cada confirmación de campo.

**Código optimizado propuesto.** Separar recorrido de carga, en tres fases:

```php
public function propagateFrom(Recipe $recipe): void
{
    $this->propagateManyFrom([$recipe->id]);
}

/**
 * Recalcula el cache de costo de las recetas semilla y de todos sus ancestros.
 *
 * @param  iterable<int>  $seedIds
 */
public function propagateManyFrom(iterable $seedIds): void
{
    // Fase 1 — cierre de ancestros por NIVELES: O(profundidad) queries, no O(nodos).
    $closure = $this->ancestorClosure($seedIds);

    if ($closure === []) {
        return;
    }

    // Fase 2 — una sola carga para todo el cierre: 5 queries fijas.
    $recipes = Recipe::withoutGlobalScopes()
        ->with([
            'ingredientLines.ingredient',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
        ])
        ->whereIn('id', $closure)
        ->get()
        ->keyBy('id');

    // Fase 3 — recálculo en orden topológico (hijos antes que padres), en memoria.
    foreach ($this->topologicalOrder($recipes) as $recipe) {
        $costs = $this->calculator->calculate($recipe);

        $recipe->unit_cost = $costs['cost_per_unit'];
        $recipe->labor_hours = $costs['total_labor_hours'];

        // CLAVE: el padre lee childRecipe->unit_cost del modelo YA hidratado.
        // Sin esto el padre usaría el valor viejo de BD y el resultado cambiaría.
        foreach ($recipe->subrecipeLines as $line) {
            if ($fresh = $recipes->get($line->child_recipe_id)) {
                $line->setRelation('childRecipe', $fresh);
            }
        }

        $recipe->save();
    }
}
```

> ⚠️ **Punto delicado de esta refactorización.** El código actual funciona porque el BFS visita los
> hijos antes que los padres **y** relee cada nodo de la BD, así que el padre encuentra el
> `unit_cost` del hijo ya persistido. La versión en lote elimina esa relectura, de modo que el orden
> topológico y el `setRelation()` de arriba dejan de ser una optimización y pasan a ser **requisito
> de corrección**. `RecipeCostPropagatorTest` y `RecipeCostCalculatorSubrecipeTest` ya cubren
> árboles anidados: tienen que seguir verdes sin tocarlos.

**Beneficio esperado.** Cierre de 20 nodos: ~140 queries → **~10**. El costo deja de escalar con el
tamaño del árbol y pasa a escalar con su profundidad (2-4 en la práctica).

**Riesgo:** 🟠 Alto — es la refactorización más invasiva del informe. Requiere tests verdes antes y
después, y conviene hacerla sola, en su propio commit.

---

### 🔴 N2 — `propagateFromIngredient/Packaging/LaborType()` — tres defectos en cinco líneas

**Ubicación:** `app/Services/RecipeCostPropagator.php:58-92` (tres métodos idénticos en forma)

```php
public function propagateFromIngredient(int $ingredientId): void
{
    $recipeIds = RecipeIngredientLine::where('ingredient_id', $ingredientId)
        ->pluck('recipe_id')
        ->unique();                                        // (a)

    Recipe::whereIn('id', $recipeIds)->get()               // (b)
        ->each(fn (Recipe $recipe) => $this->propagateFrom($recipe));   // (c)
}
```

**Problema.** Tres defectos independientes:

- **(a) Deduplicación en PHP en vez de SQL.** `->pluck()->unique()` transporta todas las filas
  duplicadas por la red para descartarlas en memoria. Corresponde `->distinct()->pluck('recipe_id')`.
- **(b) Overfetching puro — la query entera sobra.** Se hidratan modelos `Recipe` completos, con
  todas sus columnas y sus casts, para que `propagateFrom()` use **únicamente `$recipe->id`**
  (`RecipeCostPropagator.php:21`). Los ids ya los tenía el `pluck` anterior.
- **(c) Trabajo repetido combinatorio.** Cada iteración de `each()` llama a `propagateFrom()`, que
  arranca con su **propio `$visited` vacío**. Un ancestro compartido por 10 recetas hijas se
  recalcula 10 veces, con sus ~7 queries cada vez.

**Queries estimadas.** `1 + 1 + (7 × nodos × N recetas semilla)`, con recálculo redundante de todo
ancestro compartido. Es el multiplicador que convierte N1 en un problema crítico.

**Dónde duele.** Se invoca desde `PurchaseLineRecorder:237` y `:246` (por cada línea de compra
imputada), `IngredientController:112`, `PackagingController:112`, `PackagingCostController:39`,
`LaborTypeController:69` y `FixIngredientSubdivisionCosts:96-97`.

**Código optimizado propuesto.**

```php
public function propagateFromIngredient(int $ingredientId): void
{
    $this->propagateManyFrom(
        RecipeIngredientLine::where('ingredient_id', $ingredientId)
            ->distinct()                       // (a) dedup en SQL
            ->pluck('recipe_id'),              // (b) sin hidratar modelos
    );                                         // (c) un solo cierre, un solo $visited
}
```

Idéntico para `propagateFromPackaging()` (`RecipePackagingLine`) y `propagateFromLaborType()`
(`RecipeLaborLine`).

**Beneficio esperado.** Elimina una query completa, el transporte de duplicados y **todo** el
recálculo redundante de ancestros compartidos. Combinado con N1, una actualización de costo de un
ingrediente usado en 15 recetas pasa de ~1.000 queries a ~12.

**Riesgo:** 🟢 Bajo una vez que N1 está hecho (depende de `propagateManyFrom`).

---

### 🔴 N3 — `RecipeShowViewModel::availableSemiElaborates()` — BFS por candidata

**Ubicación:** `app/Services/RecipeShowViewModel.php:139-152`

```php
private function availableSemiElaborates(Recipe $recipe, Tenant $tenant): Collection
{
    return $tenant->recipes()
        ->where('is_semi_elaborate', true)
        ->active()
        ->where('id', '!=', $recipe->id)
        ->orderBy('name')
        ->get()
        ->filter(fn ($candidate) => ! $this->propagator->isAncestor($candidate->id, $recipe->id, $tenant->id))
        ->values();
}
```

Y cada `isAncestor()` es a su vez un BFS con **una query por nodo visitado**
(`RecipeCostPropagator.php:113-120`):

```php
$parentIds = DB::table('recipe_subrecipe_lines')
    ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
    ->where('recipe_subrecipe_lines.child_recipe_id', $current)
    ->where('recipes.tenant_id', $tenantId)
    ->pluck('recipe_subrecipe_lines.recipe_id')
    ->toArray();
```

**Problema.** El filtro se evalúa candidata por candidata, y cada evaluación abre su propio recorrido
del grafo. **N × M queries** en cada carga de `/recipes/{id}`.

Pero el defecto de fondo es conceptual: **el conjunto de exclusión no depende de la candidata**. Lo
que se está preguntando N veces es siempre lo mismo — «¿cuáles son los ancestros de `$recipe`?» —,
que se responde **una sola vez**.

**Queries estimadas.** `semi-elaboradas activas × nodos del árbol de cada una`. Un catálogo con 15
semi-elaboradas y árboles de 4 niveles ≈ **60 queries** solo para poblar un `<select>`.

**Código optimizado propuesto.** Calcular el cierre de ancestros una vez y mover el descarte a SQL:

```php
private function availableSemiElaborates(Recipe $recipe, Tenant $tenant): Collection
{
    // Una sola vez, por niveles: O(profundidad) queries.
    $ancestorIds = $this->propagator->ancestorIdsOf($recipe->id, $tenant->id);

    return $tenant->recipes()
        ->where('is_semi_elaborate', true)
        ->active()
        ->whereKeyNot($recipe->id)
        ->whereNotIn('id', $ancestorIds)   // ← el descarte ocurre en la BD
        ->orderBy('name')
        ->get();                           // ← ya no hace falta ->filter()->values()
}
```

con el cierre por niveles en el propagador (misma primitiva que necesita N1):

```php
/** @return array<int, int> ids de todas las recetas que usan a $recipeId, directa o indirectamente */
public function ancestorIdsOf(int $recipeId, int $tenantId): array
{
    $found = [];
    $level = [$recipeId];

    while ($level !== []) {
        $parents = DB::table('recipe_subrecipe_lines')
            ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
            ->whereIn('recipe_subrecipe_lines.child_recipe_id', $level)   // ← nivel entero
            ->where('recipes.tenant_id', $tenantId)
            ->distinct()
            ->pluck('recipe_subrecipe_lines.recipe_id')
            ->all();

        $level = array_values(array_diff($parents, $found));
        $found = array_merge($found, $level);
    }

    return $found;
}
```

`isAncestor()` se conserva como fachada (`in_array($ancestorId, $this->ancestorIdsOf(...))`) para no
tocar a `RecipeLineController:201`, que lo usa correctamente una sola vez.

**Beneficio esperado.** 60 queries → **4-5**, y además desaparece el `filter()` sobre una colección
completa ya hidratada: menos memoria y menos hidratación de modelos que se iban a descartar.

**Alternativa evaluada:** una única query con `WITH RECURSIVE` (soportado por MySQL 8 y por SQLite
3.8.3+). **No se recomienda** — ver §9.6.

**Riesgo:** 🟢 Bajo — el comportamiento observable es idéntico y `RecipeSubrecipeLineTest` cubre la
detección de ciclos.

---

### 🔴 N4 — `NotificationService::syncLowStock()` — `find()` por fila, en cada carga del dashboard

**Ubicación:** `app/Services/NotificationService.php:37-83`

```php
$levels = StockLevel::where('tenant_id', $tenant->id)->get()      // ← TODO el stock del tenant
    ->filter(fn (StockLevel $level) => $level->hasAlert());       // ← filtro en PHP

foreach ($levels as $level) {
    $item = $level->stockable_type === 'ingredient'
        ? Ingredient::find($level->stockable_id)                  // ← N+1
        : Packaging::find($level->stockable_id);
    // …
    $this->raise(/* … */);                                        // ← SELECT + INSERT/UPDATE por alerta
}
```

**Problema.** Tres N+1 apilados: la colección completa sin filtrar, el `find()` por fila y el
`raise()` por alerta. Y corre **síncrono en cada carga** del dashboard (`DashboardController:31`) y
del centro de alertas (`NotificationController:18`).

**Queries estimadas.** `1 + N ítems en alerta + (2 × N alertas)`. Con 40 ítems en alerta ≈ **121
queries**, más la hidratación de *todos* los `stock_levels` del tenant aunque estén sanos.

**Código optimizado propuesto.** Tres correcciones independientes:

**(a) El predicado `hasAlert()` va en SQL.** La lógica de `StockLevel::hasAlert()` (`:56-63`) es
traducible sin SQL crudo, con `whereColumn` (portable MySQL/SQLite):

```php
$levels = StockLevel::where('tenant_id', $tenant->id)
    ->where(fn ($q) => $q
        ->where('quantity', '<', 0)
        ->orWhere(fn ($q2) => $q2
            ->whereNotNull('min_quantity')
            ->whereColumn('quantity', '<=', 'min_quantity')))
    ->get();
```

> El beneficio principal acá **no son las queries, es la hidratación**: la colección pasa de «todo el
> stock del tenant» a «lo que está en alerta», que normalmente es un puñado de filas.
> Contrapartida honesta: la condición queda duplicada entre `hasAlert()` (PHP) y el scope (SQL).
> Conviene encapsularla en un `scopeInAlert()` en el modelo, al lado de `hasAlert()`, para que las
> dos versiones vivan juntas y no se desincronicen.

**(b) Los `find()` se agrupan por tipo.** Solución mínima, sin tocar el modelo de datos:

```php
$byType = $levels->groupBy('stockable_type');

$items = [
    'ingredient' => Ingredient::whereIn('id', $byType->get('ingredient', collect())->pluck('stockable_id'))->get()->keyBy('id'),
    'packaging'  => Packaging::whereIn('id', $byType->get('packaging', collect())->pluck('stockable_id'))->get()->keyBy('id'),
];

foreach ($levels as $level) {
    $item = $items[$level->stockable_type][$level->stockable_id] ?? null;
    if (! $item) { continue; }
    // …
}
```

N queries → **2**. Ver **N5** para la solución de fondo, que es mejor.

**(c) El `raise()` por alerta se agrupa.** Detalle en **Q1**; y en §9.3 se explica por qué el
`upsert()` masivo —la respuesta obvia— **no es aplicable acá**.

**Beneficio esperado.** ~121 queries → **~6**, y la hidratación cae de «todo el stock» a «lo que
está en alerta».

**Riesgo:** 🟡 Medio — `NotificationAlertsTest` cubre el comportamiento; el punto de atención es que
la traducción del predicado a SQL sea exacta (incluido el caso `min_quantity IS NULL`).

---

### 🔴 N5 — Polimorfismo escrito a mano: `stockable` y `purchaseable` no pueden hacer eager loading

**Ubicación:** `app/Models/StockLevel.php:46-53`, `app/Models/StockMovement.php:66-93`,
`app/Models/PurchaseLine.php:52-60`, `app/Enums/CatalogItemType.php`

```php
// StockMovement.php:66-93 — dos belongsTo apuntando a la MISMA columna
public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class, 'stockable_id'); }
public function packaging(): BelongsTo  { return $this->belongsTo(Packaging::class, 'stockable_id'); }

public function stockable(): Ingredient|Packaging|null      // ← método, no relación
{
    return match ($this->stockable_type) {
        CatalogItemType::Ingredient->value => $this->ingredient,
        CatalogItemType::Packaging->value  => $this->packaging,
        default => null,
    };
}
```

**Problema.** `stockable()` **parece** una relación pero es un método que devuelve un modelo, así que
no se puede hacer `with('stockable')`, ni `loadMorph()`, ni `whereHasMorph()`. Cualquier consumidor
está condenado al lazy loading fila por fila. Es la causa raíz del N+1 de N4 y de todos los que
aparezcan en el futuro sobre estas tres tablas.

**El hallazgo:** el proyecto está **a una línea** de tener polimorfismo real. `CatalogItemType`
(`app/Enums/CatalogItemType.php`) ya declara el mapeo:

```php
/** @return class-string<Ingredient|Packaging> */
public function modelClass(): string
{
    return match ($this) {
        self::Ingredient => Ingredient::class,
        self::Packaging  => Packaging::class,
    };
}
```

…y los valores persistidos en BD (`'ingredient'`, `'packaging'`) **ya son exactamente** las claves
que usaría un morph map de Eloquent. **No hace falta migración de datos.**

**Código optimizado propuesto.** En `AppServiceProvider::boot()`:

```php
Relation::enforceMorphMap(
    collect(CatalogItemType::cases())
        ->mapWithKeys(fn (CatalogItemType $type) => [$type->value => $type->modelClass()])
        ->all(),
);
```

y en los tres modelos, reemplazar los pares de `belongsTo` por la relación real:

```php
public function stockable(): MorphTo
{
    return $this->morphTo();     // usa stockable_type + stockable_id, ya nombradas por convención
}
```

Las columnas ya siguen la convención de Laravel (`stockable_type`/`stockable_id`,
`purchaseable_type`/`purchaseable_id`), así que `morphTo()` funciona sin argumentos.

**Beneficio esperado.** Desbloquea `->with('stockable')`, `->load('stockable')`, `loadMorph()` y
`whereHasMorph()` sobre `StockLevel`, `StockMovement` y `PurchaseLine`. N4(b) se simplifica a
`->with('stockable')` y `StockService::reverseMovement:196` (`$original->stockable()`) deja de
lazy-loadear dentro del bucle de `PurchaseController::destroy`.

> **Precaución.** `enforceMorphMap()` es estricto: si alguna columna `*_type` de la BD guarda un
> valor fuera del mapa, Eloquent lanza excepción. Antes de aplicarlo conviene verificar en
> producción que no haya valores inesperados:
> ```sql
> SELECT DISTINCT stockable_type FROM stock_movements
> UNION SELECT DISTINCT stockable_type FROM stock_levels
> UNION SELECT DISTINCT purchaseable_type FROM purchase_lines;
> ```
> Debe devolver exactamente `ingredient` y `packaging` (más `NULL` en `purchase_lines`, que es
> legítimo: es un renglón sin asociar).
>
> El docblock de `CatalogItemType` dice hoy «No son morphs de Eloquent». Si se aplica este cambio,
> **hay que actualizar ese comentario**, o queda mintiendo.

**Riesgo:** 🟢 Bajo, condicionado a la verificación de arriba. Cambio estructural de alto valor.

---

### 🔴 N6 — `Gate::before` → `isSuperAdmin()`: una query por cada `@can`

**Ubicación:** `app/Providers/AppServiceProvider.php:43-47` + `app/Models/User.php:55-61`

```php
// AppServiceProvider.php:43-47 — corre en CADA verificación de autorización
Gate::before(function (User $user) {
    if ($user->isSuperAdmin()) {
        return true;
    }
});

// User.php:55-61 — y esto es una query, sin cachear
public function isSuperAdmin(): bool
{
    return $this->tenantUsers()
        ->where('role', TenantUserRole::SuperAdmin->value)
        ->where('active', true)
        ->exists();
}
```

**Problema.** `Gate::before` se ejecuta en **toda** verificación de autorización. Como
`isSuperAdmin()` no memoiza nada, cada `@can` de una vista y cada `authorize()` de un controller
emite una query idéntica.

**Queries estimadas.** `1 × cada @can + 1 × cada authorize()`. Medido sobre el repo:
`resources/views/recipes/show.blade.php` tiene **18 `@can`** → **18 queries idénticas** solo para
decidir qué botones pintar. Hay **57 `authorize()`** en controllers y `@can` en otras 17 vistas
(`purchases/show` 11, `packaging/index` 9, `ingredients/index` 7, `fixed-costs/index` 7…).

Lo mismo aplica a `roleInTenant()` (`User.php:38-46`), que consulta en cada llamada y es invocado
por `CheckTenantRole:28` y por las tres Gates de `AppServiceProvider:49-79`.

**Código optimizado propuesto.** La solución obvia —memoizar un `bool` en la instancia— funciona,
pero deja a ambos métodos siendo *métodos que consultan la BD*, que es justo lo que los hace
peligrosos dentro de vistas y bucles. La corrección idiomática es **hacerlos relacionales**:

```php
// SetTenantContext.php — una sola carga, al principio del request
$user->loadMissing('tenantUsers.tenant');
```

```php
// User.php — leen de la colección ya hidratada, sin emitir queries
public function isSuperAdmin(): bool
{
    return $this->tenantUsers
        ->contains(fn (TenantUser $tu) => $tu->active && $tu->role === TenantUserRole::SuperAdmin);
}

public function roleInTenant(Tenant $tenant): ?TenantUserRole
{
    return $this->tenantUsers
        ->first(fn (TenantUser $tu) => $tu->active && $tu->tenant_id === $tenant->id)
        ?->role;
}
```

**Beneficio esperado.** `/recipes/{id}`: **−18 queries**. Todas las vistas con `@can` y todos los
controllers con `authorize()` se benefician. Y habilita **N7**, que colapsa el middleware.

> **Trade-off a declarar.** Con `Model::preventLazyLoading(! app()->isProduction())` activo
> (`AppServiceProvider:19`), cualquier camino que llegue a `isSuperAdmin()` sin haber cargado
> `tenantUsers` **lanzará excepción en desarrollo**. Es deseable —convierte el olvido en un test
> rojo— pero hay caminos fuera del middleware `tenant` que hay que cubrir: `EnsureSuperAdmin:17`
> (backoffice `/admin`), `AuthenticatedSessionController:41` (login) y los tres form requests de
> `Admin/` (`StoreTenantRequest:11`, `StoreUserRequest:13`, `UpdateTenantRequest:13`). Por eso el
> `loadMissing()` va también en `EnsureSuperAdmin`, o —más simple y a prueba de olvidos— dentro del
> propio `isSuperAdmin()` como `$this->loadMissing('tenantUsers')` en la primera línea: sigue siendo
> **una** query por request en lugar de N.

**Riesgo:** 🟡 Medio — toca la ruta de autorización, que es sensible. `TenantIsolationTest` y la
suite de `Auth` tienen que quedar verdes sin modificaciones.

---

### 🟠 N7 — `SetTenantContext`: 3 queries por request para resolver el tenant

**Ubicación:** `app/Http/Middleware/SetTenantContext.php:31-53`

```php
if ($user->isSuperAdmin() && $request->session()->has('impersonating_tenant_id')) {   // ← query 1
    return Tenant::find($request->session()->get('impersonating_tenant_id'));
}

$tenantId = $user->tenantUsers()->where('active', true)
    ->orderBy('tenant_id')->value('tenant_id');                                       // ← query 2

if ($tenantId === null) { return null; }

return Tenant::find($tenantId);                                                       // ← query 3
```

**Problema.** Tres viajes a la BD en **cada request autenticada** para resolver algo que vive en una
sola relación.

**Queries estimadas.** `3 × cada request` con middleware `tenant` (es decir, casi toda la app).

**Código optimizado propuesto.** Con el `loadMissing('tenantUsers.tenant')` de N6 ya hecho:

```php
private function resolveTenant(Request $request): ?Tenant
{
    $user = $request->user();

    if (! $user instanceof User) {
        return null;
    }

    $user->loadMissing('tenantUsers.tenant');     // ← 1 query, reutilizada por todo el request

    if ($user->isSuperAdmin() && $request->session()->has('impersonating_tenant_id')) {
        return Tenant::find($request->session()->get('impersonating_tenant_id'));
    }

    return $user->tenantUsers
        ->where('active', true)
        ->sortBy('tenant_id')      // mismo criterio determinista que el orderBy actual
        ->first()
        ?->tenant;
}
```

**Beneficio esperado.** 3 queries → **1**, y esa única query es la que además vuelve gratis a N6. El
camino de impersonación mantiene su `Tenant::find()` porque apunta a un tenant que no está en la
relación del usuario — es correcto que consulte.

**Riesgo:** 🟢 Bajo. Ojo con preservar el orden determinista (`orderBy('tenant_id')` → `sortBy`), que
el comentario del código señala como intencional.

---

### 🟠 N8 — `PurchaseController::applyLineSuggestions()` — el N+1 en cascada

**Ubicación:** `app/Http/Controllers/PurchaseController.php:576-608`

```php
foreach ($pending as $line) {
    $line->setRelation('purchase', $purchase);    // ← bien: evita un lazy load por línea
    try {
        DB::transaction(function () use ($line) {
            $this->lineRecorder->apply($line);     // ← y acá adentro se desata todo
            $this->linkMemory->remember($line);
        });
        $applied++;
    } // …
}
```

**Problema.** Cada `apply()` (`PurchaseLineRecorder.php:78-138`) dispara: `Ingredient::find()` +
creación de price log + `update()` + **N2 completo** (que a su vez dispara N1) +
`raiseCostSpike()` + `syncPurchaseLineEntry()` (que incluye N9). Es el punto donde todos los
hallazgos críticos se multiplican entre sí.

**Queries estimadas.** `líneas × (5 + costo completo de N2)`. Una factura de 30 renglones sobre un
catálogo con árboles de sub-recetas se va a **miles de queries en una única request HTTP**.

**Crédito donde corresponde:** el `setRelation('purchase', $purchase)` de la línea 591 es
deliberado y correcto — evita un lazy load de `purchase` por línea. **`matchLine()` no lo hace**, y
ahí `apply()` sí lazy-loadea (`PurchaseLineRecorder:86`, `:116`, `:181`, `:186`, `:279-285`).

**Código optimizado propuesto.** No hay una reescritura local que lo arregle: **el remedio es N1 +
N2**, que atacan la raíz. Dos mejoras locales complementarias:

```php
// 1) Cargar el ítem de catálogo una vez para todas las líneas, en vez de find() por línea.
$pending = $purchase->lines()
    ->whereNotNull('purchaseable_id')
    ->whereNull('cost_applied_at')
    ->whereNull('excluded_at')
    ->with('purchaseable')       // ← posible una vez aplicado N5 (morph map)
    ->get();

// 2) Propagar UNA vez al final, no por línea.
//    Requiere extraer la propagación de applyIngredientCost()/applyPackagingCost().
$this->propagator->propagateManyFrom($touchedRecipeIds);
```

**Beneficio esperado.** Con N1+N2+N5 aplicados, una factura de 30 renglones baja de miles de queries
a decenas. La propagación diferida al final del lote es la que más aporta.

**Riesgo:** 🟠 Alto — `PurchaseCrudTest`, `PurchaseMatchSubdivisionTest` y
`StockPurchaseIntegrationTest` cubren este camino, y diferir la propagación cambia *cuándo* se
escribe el cache (no *qué* se escribe). Hacer después de N1/N2, nunca antes.

---

### 🟠 N9 — `StockService::syncPurchaseLineEntry()` — resolución de sucursal dentro del bucle

**Ubicación:** `app/Services/StockService.php:149`

```php
return $this->registerMovement(
    item: $item,
    location: $item->tenant->defaultLocation(),   // ← lazy load + hasta 3 queries, POR MOVIMIENTO
    // …
);
```

**Problema.** `$item->tenant` lazy-loadea el tenant (1 query) y `defaultLocation()`
(`Tenant.php:53-58`) hace hasta 3 más, porque encadena `first()` → `first()` → `create()` sin
memoizar:

```php
public function defaultLocation(): Location
{
    return $this->locations()->where('is_default', true)->first()
        ?? $this->locations()->orderBy('id')->first()
        ?? $this->locations()->create(['name' => 'Casa Central', 'is_default' => true, 'active' => true]);
}
```

**Queries estimadas.** `hasta 4 × cada movimiento de stock`, y esto vive **dentro** de los bucles de
N8 y de `PurchaseController::destroy:208-214`.

**Código optimizado propuesto.** Memoizar por instancia, exactamente como el propio modelo ya hace
con `getSetting()` (`Tenant.php:172-180`):

```php
/** @var Location|null Cache por instancia: defaultLocation() se llama una vez por ítem en los bucles de compra */
private ?Location $cachedDefaultLocation = null;

public function defaultLocation(): Location
{
    return $this->cachedDefaultLocation ??= $this->locations()->where('is_default', true)->first()
        ?? $this->locations()->orderBy('id')->first()
        ?? $this->locations()->create(['name' => 'Casa Central', 'is_default' => true, 'active' => true]);
}
```

Y en `StockService`, aceptar la `Location` resuelta por el llamador en vez de resolverla por
movimiento, para que la instancia de `Tenant` sea la misma (la del container) y el cache aplique.

**Beneficio esperado.** Hasta 4 queries por movimiento → **hasta 4 por request**. También beneficia a
`IngredientController:49` y `PackagingController:49`, que la invocan en el `index`.

**Riesgo:** 🟢 Bajo. Ojo: el cache queda obsoleto si se cambia la sucursal default en el mismo
request (`LocationController::update:62`); ahí conviene invalidarlo, igual que `setSetting()`
invalida `$cachedSettings`.

---

### 🟡 N10 — Cadenas de relaciones sin eager loading en el camino de compras

**Ubicación:** `app/Services/PurchaseLineRecorder.php:86, 116, 181, 186, 279-285`;
`app/Services/NotificationService.php:191`

```php
// PurchaseLineRecorder.php:86
abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Ingrediente no válido.');

// PurchaseLineRecorder.php:279-285
$purchase = $line->purchase;                                          // ← lazy load
$hit = $this->linkMemory->recall($purchase->tenant, /* … */);         // ← y otro encima

// NotificationService.php:191
$tenant = $line->purchase->tenant;                                    // ← cliente → compra → tenant
```

**Problema.** Cadenas `línea → compra → tenant` resueltas de a un salto por vez. En
`applyLineSuggestions` están mitigadas por el `setRelation` de `:591`, pero en `matchLine()`
(`:448-571`) y en `updateLinePrice()` (`:232-272`) no.

**Queries estimadas.** `2 × cada línea procesada` fuera de `applyLineSuggestions`.

**Código optimizado propuesto.** Eager loading en el borde, donde se resuelve el binding:

```php
// PurchaseController::matchLine() y updateLine(), al inicio
$line->setRelation('purchase', $purchase->loadMissing('tenant'));
```

o, de forma más robusta, declararlo en el modelo para que no dependa de que cada llamador se acuerde:

```php
// PurchaseLine.php
protected $with = ['purchase'];    // evaluar el costo: encarece TODA consulta de líneas
```

> Recomendación: **la primera opción**. El `$with` global abarata este camino pero encarece
> `PurchaseController::index`, `show` y `match`, que ya traen la compra por otra vía. Un `$with`
> global es la clase de optimización que resuelve un problema y crea tres.

**Riesgo:** 🟢 Bajo.

---

### 🟡 N11 — `admin/tenants/show`: la relación se carga y además se cuenta tres veces

**Ubicación:** `app/Http/Controllers/Admin/TenantController.php:77-88` +
`resources/views/admin/tenants/show.blade.php:56`

```php
$tenant->load(['tenantUsers.user']);        // ← ya trae TODAS las membresías

$metrics = [
    'active_users'         => $tenant->tenantUsers()->where('active', true)->count(),   // ← query
    'total_users'          => $tenant->tenantUsers()->count(),                          // ← query
    'pending_invitations'  => $tenant->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
];
```

```blade
{{-- show.blade.php:56 --}}
Usuarios ({{ $tenant->tenantUsers->count() }})    {{-- cuenta en PHP lo mismo que $metrics --}}
```

**Problema.** La colección ya está en memoria y aun así se emiten dos `count()` sobre la misma
relación; la vista vuelve a contar en PHP un dato que `$metrics` ya calculó.

**Queries estimadas.** 3 evitables (2 de 3 son puro trabajo repetido).

**Código optimizado propuesto.**

```php
$tenant->load(['tenantUsers.user'])
    ->loadCount([
        'tenantUsers as total_users',
        'tenantUsers as active_users' => fn ($q) => $q->where('active', true),
        'invitations as pending_invitations' => fn ($q) => $q
            ->whereNull('accepted_at')->where('expires_at', '>', now()),
    ]);
```

Un solo viaje con los tres conteos, y la vista usa `$tenant->total_users` en vez de contar en PHP.

**Riesgo:** 🟢 Bajo.

---

## 2. Consultas ineficientes

### 🟠 Q1 — `NotificationService::raise()` — SELECT + escritura por alerta

**Ubicación:** `app/Services/NotificationService.php:252-291`

```php
$existing = Notification::where('tenant_id', $tenant->id)
    ->where('dedupe_key', $dedupeKey)
    ->whereNull('resolved_at')
    ->first();                       // ← 1 SELECT por alerta

if ($existing) {
    $existing->update([/* … */]);    // ← 1 UPDATE
    return;
}

Notification::create([/* … */]);     // ← o 1 INSERT
```

**Problema.** Invocado dentro de los tres bucles de `syncStateAlerts()`. **2 queries por alerta**, en
cada carga del dashboard.

**Código optimizado propuesto.** Traer las vivas del tipo en **una** query (el índice
`notifications_dedupe_index` ya existe) e insertar las nuevas en lote:

```php
$live = Notification::where('tenant_id', $tenant->id)
    ->where('type', $type->value)
    ->whereNull('resolved_at')
    ->get()
    ->keyBy('dedupe_key');

$toInsert = [];
foreach ($candidates as $candidate) {
    $existing = $live->get($candidate['dedupe_key']);

    if ($existing === null) {
        $toInsert[] = $candidate + ['created_at' => now(), 'updated_at' => now()];
        continue;
    }

    // Solo escribir si el texto realmente cambió — en la práctica, casi nunca.
    $existing->fill(['title' => $candidate['title'], 'body' => $candidate['body'], /* … */]);
    if ($existing->isDirty()) {
        $existing->save();
    }
}

if ($toInsert !== []) {
    Notification::insert($toInsert);     // ← una sola escritura
}
```

El `isDirty()` es la parte que más aporta: hoy se emite un UPDATE por alerta viva aunque el texto sea
idéntico al que ya está guardado.

**Beneficio esperado.** `2 × N` → `1 + 1 + (updates realmente necesarios ≈ 0)`.

> ⚠️ **`upsert()` no sirve acá** y el motivo es de diseño, no de rendimiento — ver §9.3.

**Riesgo:** 🟡 Medio — `Notification::insert()` saltea eventos y casts (`meta` es `array` y habría que
serializarlo a mano). `NotificationAlertsTest` es la red.

---

### 🟠 Q2 — `syncStaleCost()`: subconsulta correlacionada + filtro en PHP

**Ubicación:** `app/Services/NotificationService.php:85-133`

```php
$ingredients = Ingredient::where('tenant_id', $tenant->id)
    ->where('active', true)
    ->select('ingredients.*')
    ->selectSub(
        IngredientPriceLog::selectRaw('max(recorded_at)')
            ->whereColumn('ingredient_id', 'ingredients.id'),
        'last_cost_at',
    )
    ->get();                                     // ← TODOS los ingredientes activos

foreach ($ingredients as $ingredient) {
    $lastCostAt = $ingredient->last_cost_at ?? $ingredient->created_at;
    if ($lastCostAt >= $threshold) { continue; } // ← el descarte ocurre en PHP
    // …
}
```

**Crédito:** el `selectSub` ya evita el N+1 obvio. El problema es que **el filtro quedó en PHP**, así
que se traen e hidratan *todos* los ingredientes activos para descartar la mayoría.

**Código optimizado propuesto.** `leftJoinSub`: la agregación se hace **una vez** sobre el log
completo, y el descarte queda en la BD.

```php
$lastLogs = IngredientPriceLog::selectRaw('ingredient_id, max(recorded_at) as last_cost_at')
    ->groupBy('ingredient_id');

$ingredients = Ingredient::where('ingredients.tenant_id', $tenant->id)
    ->where('ingredients.active', true)
    ->select('ingredients.*')
    ->addSelect('l.last_cost_at')
    ->leftJoinSub($lastLogs, 'l', 'l.ingredient_id', '=', 'ingredients.id')
    ->whereRaw('coalesce(l.last_cost_at, ingredients.created_at) < ?', [$threshold])
    ->get();
```

> **El factor dominante no es esta reescritura, es el índice.** La agregación ya existía; lo que
> hace que sea barata es `ingredient_price_logs (ingredient_id, recorded_at)` — ver §4, I3. Sin ese
> índice, el `group by` recorre el log entero igual, y la ganancia se reduce a la hidratación
> evitada. Con el índice, el `group by` se resuelve por índice.
>
> El `whereRaw` con `coalesce` es portable MySQL/SQLite; se usa aquí porque el constructor de
> Eloquent no expresa `coalesce` sobre dos columnas de tablas distintas sin verbosidad excesiva.

**Riesgo:** 🟡 Medio — cambia el plan de ejecución; verificar que `NotificationAlertsTest` cubra el
caso «ingrediente sin logs» (el `coalesce` a `created_at`).

---

### 🟠 Q3 — `syncUnappliedPurchases()`: el mismo predicado evaluado dos veces

**Ubicación:** `app/Services/NotificationService.php:135-179`

```php
$unresolved = fn ($q) => $q->whereNull('cost_applied_at')->whereNull('excluded_at');

$purchases = Purchase::where('tenant_id', $tenant->id)
    ->whereHas('lines', $unresolved)                                  // ← subconsulta 1
    ->withCount(['lines as pending_lines_count' => $unresolved])      // ← subconsulta 2, idéntica
    ->with('supplier')
    ->get();                                                          // ← sin límite
```

**Problema.** Dos subconsultas sobre `purchase_lines` con el mismo predicado: una para filtrar y otra
para contar. Y el `get()` no tiene techo: un tenant con años de compras sin imputar las trae todas.

**Código optimizado propuesto.** Una sola subconsulta, con `having` sobre el alias del `withCount`:

```php
$purchases = Purchase::where('tenant_id', $tenant->id)
    ->withCount(['lines as pending_lines_count' => $unresolved])
    ->having('pending_lines_count', '>', 0)     // ← reemplaza al whereHas
    ->with('supplier')
    ->get();
```

> **Aclaración sobre `withWhereHas()`:** es la herramienta correcta cuando se necesita *filtrar y
> cargar la relación* en un solo paso, pero acá lo que se necesita es el **conteo**, no la colección
> de líneas. Cargar las líneas para contarlas en PHP sería peor. Por eso: `withCount` + `having`.

**Riesgo:** 🟢 Bajo.

---

### 🟠 Q4 — View composer de onboarding: hasta 6 queries en **cada página**

**Ubicación:** `app/Providers/AppServiceProvider.php:21-41`

```php
View::composer('layouts.app', function ($view) {
    try {
        $tenant = app(Tenant::class);
        if ($tenant->hasCompletedOnboarding()) { /* … */ return; }   // ← query si el timestamp es null

        $step = match (true) {
            ! $tenant->productive_hours_month      => 0,
            $tenant->fixedCosts()->count()   === 0 => 1,             // ← query
            $tenant->laborTypes()->count()   === 0 => 2,             // ← query
            $tenant->ingredients()->count()  === 0 => 3,             // ← query
            $tenant->recipes()->count()      === 0 => 4,             // ← query
            default => null,
        };
        // …
    } catch (\Throwable) { /* … */ }
});
```

**Problema.** Corre en **todas** las páginas que extienden `layouts.app` — es decir, la aplicación
entera. Y los cuatro `count()` solo se comparan contra `=== 0`: se está pagando un conteo completo
para responder una pregunta de existencia. Un tenant con 800 recetas cuenta las 800 para saber que
no son cero.

**Atenuante:** el `match` cortocircuita, así que el peor caso (4 counts) solo ocurre durante el
onboarding. Pero `hasCompletedOnboarding()` (`Tenant.php:145-149`) sí ejecuta
`$this->recipes()->exists()` en cada página **hasta que** `onboarding_completed_at` se setea.

**Código optimizado propuesto.** `withExists()`: cuatro `EXISTS` correlacionados en **un solo viaje**.

```php
View::composer('layouts.app', function ($view) {
    try {
        $tenant = app(Tenant::class);

        if ($tenant->onboarding_completed_at !== null) {
            $view->with('onboardingStep', null);      // sin ninguna query
            return;
        }

        $flags = Tenant::whereKey($tenant->id)
            ->withExists(['fixedCosts', 'laborTypes', 'ingredients', 'recipes'])
            ->first();                                 // ← 1 sola query

        $view->with('onboardingStep', match (true) {
            ! $tenant->productive_hours_month => 0,
            ! $flags->fixed_costs_exists      => 1,
            ! $flags->labor_types_exists      => 2,
            ! $flags->ingredients_exists      => 3,
            ! $flags->recipes_exists          => 4,
            default => null,
        });
    } catch (\Throwable) {
        $view->with('onboardingStep', null);
    }
});
```

**Beneficio esperado.** Hasta 6 queries → **1** durante el onboarding, y **0** una vez completado
(hoy sigue costando 1). En **todas** las páginas de la app.

> `doesntExist()` sería el cambio mínimo equivalente (`count() === 0` → `doesntExist()`), pero sigue
> costando 4-5 viajes. `withExists()` los une en uno solo.

**Riesgo:** 🟢 Bajo — `OnboardingTest` cubre los cinco pasos.

---

### 🟠 Q5 — Dashboard: colección completa recalculada en PHP para 4 agregados

**Ubicación:** `app/Http/Controllers/DashboardController.php:143-179`

```php
$statRows = $tenant->recipes()
    ->active()
    ->with([
        'ingredientLines.ingredient',
        'packagingLines.packaging',
        'laborLines.laborType',
        'subrecipeLines.childRecipe',
    ])
    ->get()                                            // ← TODAS las recetas activas, sin límite
    ->map(function ($recipe) use ($calculator, $prices, $overheadPerHour) {
        $costs = $calculator->calculate($recipe);      // ← recálculo completo en PHP
        // …
    });
```

**Problema.** Ocho queries (1 + 4 relaciones anidadas + sus pivotes) y la hidratación de **todo** el
catálogo de recetas con todas sus líneas, para producir cuatro agregados
(`$avgMarginPct`, `$bestRecipeRow`, `$lowMarginCount`, `$costDistributionForChart`). El consumo de
memoria crece linealmente con el catálogo y no tiene techo.

**Aquí la respuesta obvia es la incorrecta.** El reflejo es «moverlo a SQL con joins agrupados».
**No se puede sin cambiar los resultados:** el costo de ingredientes requiere **conversión de
unidades** (`UnitConverter`, invocado en `RecipeCostCalculator:25-29` y `:50-54`), que vive en PHP y
no es expresable en SQL sin codificar la tabla de conversiones dentro de la consulta. El pedido es
explícito en no alterar el comportamiento funcional, así que la vía del join queda descartada.

**Código optimizado propuesto.** Partir el problema según lo que cada dato necesita de verdad:

**(a) Margen promedio, mejor receta y conteo de margen bajo — no necesitan el desglose.** Salen del
cache `unit_cost` + `labor_hours` + `recipe_prices`, con **las mismas expresiones SQL que la tabla
paginada ya construye** en `DashboardController:57-65`. Una query agregada, cero hidratación, y cero
riesgo de divergencia — porque `unit_cost` **ya es** el resultado con las unidades convertidas:

```php
$stats = $tenant->recipes()->active()
    ->selectRaw("avg({$marginPctSql}) as avg_margin_pct", $marginPctBindings)
    ->selectRaw("sum(case when ({$marginPctSql}) < 20 then 1 else 0 end) as low_margin_count", $marginPctBindings)
    ->first();
```

**(b) Solo la dona necesita el desglose por componente, y son cuatro números.**
`RecipeCostCalculator::calculate()` (`:65-73`) ya devuelve `ingredient_cost`, `packaging_cost` y
`labor_cost`; hoy se descartan. Persistirlos junto a `unit_cost` convierte la dona en un `SUM()`:

```php
// migración: recipes + ingredient_cost, packaging_cost, labor_cost (decimal 14,4, nullable)
// RecipeCostPropagator ya calcula los tres valores — solo hay que guardarlos.

$distribution = $tenant->recipes()->active()
    ->selectRaw('sum(ingredient_cost) as ing, sum(packaging_cost) as pkg, sum(labor_cost) as lab, sum(labor_hours) as hrs')
    ->first();
```

Es coherente con el cache que el sistema ya mantiene, y el backfill sale con
**`php artisan recipes:refresh-costs`, comando que ya existe** (`RefreshRecipeCosts.php`).

**Beneficio esperado.** ~8 queries + hidratación de todo el catálogo → **2 queries agregadas** y
memoria constante.

**Riesgo:** 🟠 Alto — requiere migración y backfill, y hay que mantener las tres columnas nuevas
sincronizadas en el propagador. `DashboardCachedCostTest` y `DashboardRentabilidadTest` son la red.
**Alternativa provisional de riesgo bajo:** dejar el cálculo como está pero acotarlo con
`->limit()` o `chunkById()` para poner un techo a la memoria mientras se decide.

---

### 🟡 Q6 — `defaultPriceList()` seguido de traer la misma lista otra vez

**Ubicación:** `app/Http/Controllers/DashboardController.php:40-47`,
`app/Http/Controllers/PriceListController.php:30` y `:51`,
`app/Services/RecipeShowViewModel.php:40-45`

```php
// DashboardController.php:40-47
$tenant->defaultPriceList();                      // ← firstOrCreate: 1-2 queries, resultado descartado
$priceLists = $tenant->priceLists()->active()     // ← y acá se vuelve a traer, incluyéndola
    ->orderByDesc('is_default')->orderBy('name')->get();
$priceList = $priceLists->firstWhere('id', (int) request('price_list'))
    ?? $priceLists->firstWhere('is_default', true);
```

**Problema.** `defaultPriceList()` (`Tenant.php:110-116`) hace un `firstOrCreate` —1 SELECT, y un
INSERT si no existe— cuyo valor de retorno se **descarta**; se invoca solo por su efecto de
garantizar que la lista base exista. Inmediatamente después se trae la colección completa, que ya la
contiene.

**Código optimizado propuesto.** **El patrón correcto ya está en el repo**, en
`RecipeController::index:39-43`:

```php
$priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
$priceList = $priceLists->firstWhere('id', (int) request('price_list'))
    ?? $priceLists->firstWhere('is_default', true)
    ?? $priceLists->first()
    ?? $tenant->defaultPriceList();    // ← el firstOrCreate SOLO si de verdad no hay ninguna
```

No hay nada que inventar: uniformar los otros cuatro llamadores con este.

**Beneficio esperado.** −1 query en dashboard, matriz de precios, índice de listas y vista de receta.

**Riesgo:** 🟢 Bajo.

---

### 🟡 Q7 — `Purchase::totalAmount()` — agregado por llamada con la colección ya en memoria

**Ubicación:** `app/Models/Purchase.php:56-59` + `resources/views/purchases/show.blade.php:125`

```php
public function totalAmount(): float
{
    return (float) $this->lines()->sum('subtotal');    // ← query
}
```

```blade
{{-- show.blade.php:125 — pero la vista YA sumó lo mismo en la línea 30 --}}
${{ number_format($purchase->totalAmount(), 2, ',', '.') }}
```

**Problema.** `PurchaseController::show:185` hace `$purchase->load(['supplier', 'lines'])`, y la
vista calcula `$totalSubtotal = $purchase->lines->sum(...)` en su línea 30. `totalAmount()` emite una
query extra para recalcular exactamente eso.

**Código optimizado propuesto.**

```php
public function totalAmount(): float
{
    // Si las líneas ya están cargadas, sumar en memoria; si no, agregar en SQL.
    return (float) ($this->relationLoaded('lines')
        ? $this->lines->sum('subtotal')
        : $this->lines()->sum('subtotal'));
}
```

o, más simple, usar en la vista el `$totalSubtotal` que ya está calculado.

**Riesgo:** 🟢 Bajo.

---

### 🟡 Q8 — Selects del backoffice sin techo

**Ubicación:** `app/Http/Controllers/Admin/AuditLogController.php:30-31`,
`app/Http/Controllers/Admin/UserController.php:34`

```php
// AuditLogController.php:30-31
$actors  = User::orderBy('name')->get(['id', 'name']);
$tenants = Tenant::orderBy('name')->get(['id', 'name']);

// UserController.php:34
$tenants = Tenant::orderBy('name')->get(['id', 'name']);
```

**Problema.** Tablas completas para poblar `<select>` de filtro. Hoy es inofensivo (pocos tenants),
pero crece sin límite y es exactamente el tipo de consulta que se vuelve un problema justo cuando el
negocio empieza a andar bien.

**Crédito:** el `get(['id','name'])` está bien — ya evita traer columnas de más.

**Código optimizado propuesto.** A corto plazo, `pluck()` (evita hidratar modelos):

```php
$tenants = Tenant::orderBy('name')->pluck('name', 'id');
```

A mediano plazo, cuando la lista pase de ~200 elementos, cambiar el `<select>` por un autocompletar
con endpoint paginado.

**Riesgo:** 🟢 Bajo.

---

### 🟡 Q9 — `PriceListController::applyAllSuggestions()` — query dentro del bucle de listas

**Ubicación:** `app/Http/Controllers/PriceListController.php:196-208`

```php
foreach ($lists as $list) {
    $existing = RecipePrice::where('price_list_id', $list->id)
        ->whereIn('recipe_id', $recipeIds)
        ->pluck('price', 'recipe_id');      // ← 1 query POR LISTA
    foreach ($recipes as $recipe) { /* … */ }
}
```

**Problema.** N queries, una por lista de precios. Y adentro, `$this->writer->set()`
(`RecipePriceWriter:17-19`) hace su propio SELECT + `updateOrCreate` por receta — así que el costo
real es `listas × recetas × 3`.

**Código optimizado propuesto.** Traer todos los precios existentes en **una** query y agruparlos:

```php
$existingByList = RecipePrice::whereIn('price_list_id', $lists->pluck('id'))
    ->whereIn('recipe_id', $recipeIds)
    ->get()
    ->groupBy('price_list_id')
    ->map(fn ($group) => $group->pluck('price', 'recipe_id'));

foreach ($lists as $list) {
    $existing = $existingByList->get($list->id, collect());
    // …
}
```

**Beneficio esperado.** N queries → 1. El costo de `RecipePriceWriter::set()` queda como trabajo
pendiente aparte (es un escritor con log, y agruparlo requiere más cuidado).

**Riesgo:** 🟢 Bajo — `PriceListCrudTest` y `PriceListMatrixTest` cubren el camino.

---

### 🟡 Q10 — `PurchaseScanController`: el catálogo traído hasta seis veces por request

**Ubicación:** `app/Http/Controllers/PurchaseScanController.php:51-52, 80, 108-109, 121-122`

```php
$ingredients = $tenant->ingredients()->active()->orderBy('name')->get();   // :51
$packagings  = $tenant->packagings()->active()->orderBy('name')->get();    // :52
// …
$suppliers   = $tenant->suppliers()->active()->orderBy('name')->get();     // :80
// …
'ingredientNames' => $tenant->ingredients()->pluck('name', 'id'),          // :108 ← otra vez
'packagingNames'  => $tenant->packagings()->pluck('name', 'id'),           // :109 ← otra vez
```

**Problema.** `scan()` trae el catálogo de ingredientes y descartables **dos veces** cada uno: una
completa para el extractor (`:51-52`, solo activos) y otra como `pluck` para los nombres (`:108-109`,
todos). El comentario del código explica por qué los conjuntos difieren —correcto—, pero el segundo
par se puede derivar del primero más una consulta acotada a los inactivos que la memoria devolvió.

**Beneficio esperado.** 2 queries de 6, sobre un endpoint que además hace una llamada paga a la API
de IA. Prioridad baja en tiempo relativo, pero es trabajo gratis.

**Riesgo:** 🟢 Bajo. `PurchaseScanTest` cubre el flujo.

---

### 🟡 Q11 — El par «lista + conteo» de notificaciones, duplicado en dos controllers

**Ubicación:** `app/Http/Controllers/DashboardController.php:32-38`,
`app/Http/Controllers/NotificationController.php:22-29`

```php
$alerts = Notification::where('tenant_id', $tenant->id)->active()->unread()
    ->orderByDesc('created_at')->take(6)->get();
$alertCount = Notification::where('tenant_id', $tenant->id)->active()->unread()->count();
```

**Problema.** Dos queries con el mismo `WHERE`, repetidas textualmente en dos controllers. Las dos
son necesarias (una lista acotada + el total para el badge), así que **no hay una query que ahorrar**
— pero sí hay duplicación que invita a que las dos versiones se desincronicen.

**Código optimizado propuesto.** Extraer a un scope o a un método de `NotificationService`, de modo
que el criterio de «alerta viva no leída» viva en un solo lugar. **Es una corrección de diseño, no de
rendimiento**, y el informe la clasifica como tal.

**Riesgo:** 🟢 Bajo.

---

### 🟢 Q12 — Comandos que traen tablas completas en memoria

**Ubicación:** `app/Console/Commands/BackfillProductLinks.php:38-48` y `:85-89`,
`app/Console/Commands/FixIngredientSubdivisionCosts.php:42, 46`

```php
// BackfillProductLinks.php:85-89 — la tabla entera, sin filtro
$existing = SupplierProductLink::query()
    ->withoutGlobalScopes()
    ->get()
    ->map(fn (SupplierProductLink $link) => "{$link->tenant_id}|{$link->supplier_id}|{$link->raw_name_normalized}")
    ->flip();
```

**Problema.** Comandos de mantenimiento que cargan tablas completas en memoria. Es aceptable —corren
fuera del request, con supervisión— pero se rompen justo cuando más falta hacen: en el tenant grande.

**Código optimizado propuesto.** `chunkById()` o `lazy()`, siguiendo el patrón que
`RefreshRecipeCosts:32` **ya usa correctamente**. Para el caso de arriba, `pluck` de las tres
columnas en vez de hidratar modelos:

```php
$existing = SupplierProductLink::withoutGlobalScopes()
    ->select('tenant_id', 'supplier_id', 'raw_name_normalized')
    ->cursor()      // no materializa la colección entera
    ->map(fn ($l) => "{$l->tenant_id}|{$l->supplier_id}|{$l->raw_name_normalized}")
    ->flip();
```

**Riesgo:** 🟢 Bajo.

---

## 3. Optimización de Eloquent — oportunidades por API

Resumen de dónde aplica cada herramienta del arsenal de Eloquent en este código:

| API | Dónde aplicarla | Efecto |
|---|---|---|
| `with()` | `PurchaseController::applyLineSuggestions:584` (`with('purchaseable')`, tras N5) | Elimina el `find()` por línea |
| `load()` / `loadMissing()` | `SetTenantContext:31` (`loadMissing('tenantUsers.tenant')`) | Habilita N6 y N7 |
| `loadCount()` | `Admin/TenantController::show:79-85` | 3 queries → 1 |
| `withCount()` | Ya bien usado en `PurchaseController::index:54-60`, `PriceListController::index:33`, `Admin/TenantController::index:23` | — |
| `withExists()` | **View composer de onboarding** (`AppServiceProvider:29-36`) | 4-5 queries → 1, en cada página |
| `withSum()` | Ya bien usado en `PurchaseController::index:60` | — |
| `exists()` / `doesntExist()` | Reemplazo directo de los `count() === 0` de `AppServiceProvider:31-34` y `LocationController::store:36` | Evita contar para saber si hay algo |
| `value()` | Ya bien usado en `SetTenantContext:46` y `RecipePriceController:43-45` | — |
| `pluck()` | `Admin/AuditLogController:30-31`, `Admin/UserController:34` (en vez de `get(['id','name'])`) | Evita hidratar modelos |
| `select()` / `addSelect()` | `RecipeCostPropagator:63` — no necesita modelos, solo ids | Elimina una query entera (N2b) |
| `distinct()` | `RecipeCostPropagator:64, 76, 88` — reemplaza el `->unique()` en PHP | Dedup en SQL |
| `whereColumn()` | `NotificationService::syncLowStock:47` — traduce `hasAlert()` a SQL | Filtra en BD, no en PHP |
| `leftJoinSub()` | `NotificationService::syncStaleCost:98-106` | Agrega una vez, no por fila |
| `having()` | `NotificationService::syncUnappliedPurchases:150` — reemplaza el `whereHas` redundante | 2 subconsultas → 1 |
| `whereKeyNot()` | `RecipeShowViewModel:147` (`where('id','!=',…)`) | Legibilidad |
| `whereNotIn()` | `RecipeShowViewModel:150` — mueve el `filter()` a SQL | N×M queries → 1 (N3) |
| `chunkById()` / `cursor()` / `lazy()` | `BackfillProductLinks:38, 85`, `FixIngredientSubdivisionCosts:42, 46` | Memoria acotada |
| `paginate()` | Ya bien usado en los 12 `index` de catálogo | — |
| `morphTo()` + `enforceMorphMap()` | `StockLevel`, `StockMovement`, `PurchaseLine` (N5) | Desbloquea eager loading polimórfico |
| `loadMorph()` / `whereHasMorph()` | Disponibles **solo después** de N5 | — |
| `upsert()` | Evaluado para `NotificationService::raise()` — **descartado**, ver §9.3 | — |
| `withWhereHas()` | Evaluado para `syncUnappliedPurchases` — **no aplica**, se necesita el conteo (Q3) | — |
| `simplePaginate()` / `cursorPaginate()` | Opción para `stock/show` (ledger de movimientos, sólo navegación adelante/atrás) | Evita el `COUNT(*)` de `paginate()` |

**Nota sobre `cursorPaginate()`.** Es tentador aplicarlo en todos los listados, pero **rompe la
paginación por número de página** y no soporta ordenamientos arbitrarios por columna — que es
exactamente lo que hacen los `index` de este proyecto (`?sort=`&`?dir=`). Su único candidato
razonable es `StockController::show:90-95`, donde el orden es siempre `latest('id')`.

---

## 4. Índices sugeridos para MySQL

Contrastados contra el inventario completo de índices existentes en `database/migrations/`
(incluido `2026_06_15_165805_add_missing_indexes.php`, que ya cubrió las FK de líneas de receta y de
proveedor).

| # | Tabla | Columnas | Tipo | Justificación | Impacto |
|---|---|---|---|---|---|
| I1 | `recipes` | `(tenant_id, active, name)` | Compuesto | Hoy solo existe `index('tenant_id')` (`create_recipes_table:23`). Dashboard, `/recipes` y matriz filtran `tenant_id + active` y ordenan por `name`. Cubre filtro y orden en un solo índice. | 🟠 Alto |
| I2 | `recipe_prices` | `(recipe_id, price_list_id)` | Compuesto | La subconsulta correlacionada del dashboard (`DashboardController:57`) busca **por `recipe_id` primero**. El único existente es `(price_list_id, recipe_id)` — orden inverso, inservible como prefijo. Se ejecuta una vez por fila de la página. | 🔴 Crítico |
| I3 | `ingredient_price_logs` | `(ingredient_id, recorded_at)` | Compuesto | El `max(recorded_at)` por ingrediente de `syncStaleCost` (`NotificationService:101-104`). Hoy solo `index('ingredient_id')`. Convierte el `group by` en una lectura por índice. | 🔴 Crítico |
| I4 | `packaging_price_logs` | `(packaging_id, recorded_at)` | Compuesto | Simétrico a I3; aplica si se extiende la alerta de costo desactualizado a descartables. | 🟡 Medio |
| I5 | `purchase_lines` | `(purchase_id, cost_applied_at, excluded_at)` | Compuesto | El predicado «sin resolver» de `syncUnappliedPurchases:147` y los `withCount` condicionales de `PurchaseController::index:57-59`. Hoy solo `index('purchase_id')`. | 🟠 Alto |
| I6 | `packagings` | `(tenant_id, active)` | Compuesto | `ingredients` ya lo tiene (`create_ingredients_table:23`) y se consulta igual; `packagings` quedó con `index('tenant_id')` solo (`create_packagings_table:24`). Asimetría sin motivo. | 🟡 Medio |
| I7 | `stock_levels` | `(tenant_id, location_id, stockable_type)` | Compuesto | El único existente arranca por `stockable_type` (`stock_levels_stockable_unique`), así que no sirve de prefijo para los filtros por sucursal de `StockController::index:69-73`, `IngredientController:47-52` y `PackagingController:47-52`. | 🟠 Alto |

**Migración propuesta** (documentada, **no ejecutada** en esta auditoría):

```php
Schema::table('recipes', fn (Blueprint $t) => $t->index(['tenant_id', 'active', 'name']));
Schema::table('recipe_prices', fn (Blueprint $t) => $t->index(['recipe_id', 'price_list_id']));
Schema::table('ingredient_price_logs', fn (Blueprint $t) => $t->index(['ingredient_id', 'recorded_at']));
Schema::table('purchase_lines', fn (Blueprint $t) => $t->index(['purchase_id', 'cost_applied_at', 'excluded_at']));
Schema::table('packagings', fn (Blueprint $t) => $t->index(['tenant_id', 'active']));
Schema::table('stock_levels', fn (Blueprint $t) => $t->index(['tenant_id', 'location_id', 'stockable_type']));
```

Conviene seguir el patrón defensivo de `2026_06_15_165805_add_missing_indexes.php`, que verifica con
`Schema::getIndexes()` antes de crear — hace la migración reejecutable sin romper.

> ⚠️ **Advertencia sobre los índices que este informe NO recomienda.** Es tentador agregar
> `(tenant_id, active)` a `suppliers`, `labor_types`, `fixed_costs` y `variable_expense_categories`
> «por consistencia». **No hacerlo sin medir antes.** En esas tablas `active = true` para
> prácticamente todas las filas, así que la selectividad del segundo campo es nula, MySQL no va a
> elegir el índice, y lo único seguro es el costo adicional en cada escritura. Verificar primero:
> ```sql
> SELECT active, COUNT(*) FROM suppliers GROUP BY active;
> ```
> Si la distribución no es al menos 80/20, el índice no se justifica.

---

## 5. Riesgos de rendimiento

| # | Riesgo | Dónde | Severidad |
|---|---|---|---|
| R1 | **Consultas dentro de loops** | `RecipeCostPropagator:31-48` (BFS), `NotificationService:52-80`, `PurchaseController:590-608`, `PurchaseController::destroy:208-214`, `PriceListController:196-208` | 🔴 |
| R2 | **Trabajo duplicado combinatorio** | `propagateFromIngredient:67` — `$visited` no compartido entre semillas: un ancestro común se recalcula una vez por hijo | 🔴 |
| R3 | **Overfetching** | `RecipeCostPropagator:66` (hidrata modelos para usar solo el id), `DashboardController:149-157` (catálogo completo con 4 relaciones), `NotificationService:47` (todo el stock para filtrar el 5%) | 🔴 |
| R4 | **Colecciones sin techo** | `DashboardController:149`, `NotificationService:153`, `Admin/AuditLogController:30-31`, `BackfillProductLinks:85` — todos `get()` sin `limit` ni `chunk` | 🟠 |
| R5 | **Costo fijo por request** | `SetTenantContext` (3 queries), view composer de onboarding (hasta 6), `Gate::before` (1 por `@can`) — se pagan en **cada** página | 🟠 |
| R6 | **N+1 silencioso en producción** | `Model::preventLazyLoading(! app()->isProduction())` (`AppServiceProvider:19`): los N+1 por lazy loading **lanzan en dev y se ignoran en producción** | 🟠 |
| R7 | **Grafo con potencial de ciclos** | El árbol de sub-recetas es un DAG protegido por `isAncestor()` (`RecipeLineController:201`). El guard está bien puesto, pero `propagateFrom` **depende** de `$visited` para no colgarse si un ciclo se colara por otra vía | 🟡 |
| R8 | **Escrituras en cascada síncronas** | Una imputación de compra dispara: price log → update del ítem → propagación del árbol → alerta de salto de costo → movimiento de stock → update del nivel. Todo dentro del request HTTP | 🟠 |
| R9 | **Accessors y métodos que consultan** | `User::isSuperAdmin()`, `User::roleInTenant()`, `Tenant::defaultLocation()`, `Tenant::defaultPriceList()`, `Purchase::totalAmount()`, `StockMovement::stockable()` — todos parecen getters y todos emiten queries | 🟠 |
| R10 | **Eventos de modelo** | `StockMovement::booted()` (`:45-54`) solo lanza excepciones (bien); `BelongsToTenant::creating` (`:44-48`) solo setea un atributo (bien). **Sin riesgo detectado** — se documenta para cerrar el punto | 🟢 |
| R11 | **Jobs con consultas repetidas** | **No aplica**: `QUEUE_CONNECTION` es `sync` y no hay clases `Job` en el proyecto. Es a la vez la ausencia de riesgo y la causa de R8 | — |
| R12 | **Underfetching** | `PurchaseLineRecorder:86, 279-285`, `NotificationService:191` — cadenas `línea → compra → tenant` resueltas de a un salto | 🟡 |

**Sobre R6, la recomendación más transversal del informe.** `preventLazyLoading` activo solo fuera de
producción significa que **todo N+1 por lazy loading es ruidoso en desarrollo y mudo en producción**.
Cualquier N+1 que haya llegado a producción lo hizo por un camino sin cobertura en dev. Vale la pena
mantener la protección **y agregar el manejador en producción**:

```php
// AppServiceProvider::boot()
Model::preventLazyLoading();     // en todos los entornos

Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
    if (app()->isProduction()) {
        Log::warning('lazy loading violation', ['model' => $model::class, 'relation' => $relation]);
        return;    // no romper la request del usuario
    }
    throw new LazyLoadingViolationException($model, $relation);
});
```

Esto convierte esta auditoría puntual en una **señal continua**: cada N+1 nuevo aparece en el log de
producción en vez de esperar a la próxima revisión manual.

---

## 6. Ranking de criticidad

| # | Prioridad | Problema | Impacto | Solución | Esfuerzo |
|---|---|---|---|---|---|
| 1 | 🔴 Crítica | N6 — `Gate::before` → `isSuperAdmin()` sin cachear | 1 query por cada `@can`/`authorize()`; 18 en una sola vista | Eager-load de `tenantUsers`, métodos relacionales | 30 min |
| 2 | 🔴 Crítica | N1 — BFS del propagador con I/O por nodo | ~7 queries × nodos, en cada edición de línea | Cierre por niveles + carga en lote + orden topológico | 3-4 h |
| 3 | 🔴 Crítica | N2 — propagación por semilla sin `$visited` compartido | Multiplica N1 por cada receta afectada | `propagateManyFrom(ids)` + `distinct()` | 1 h (tras N1) |
| 4 | 🔴 Crítica | N4 — `syncLowStock` con `find()` por fila | ~121 queries en cada carga del dashboard | Filtro en SQL + carga agrupada + `raise` en lote | 2 h |
| 5 | 🔴 Crítica | N3 — `isAncestor()` por candidata | N×M queries en `/recipes/{id}` | Cierre único + `whereNotIn` | 1 h |
| 6 | 🟠 Alta | Q4 — view composer de onboarding | Hasta 6 queries en **cada página** | `withExists()` | 20 min |
| 7 | 🟠 Alta | N7 — `SetTenantContext` con 3 queries | 3 queries en **cada request** | Relación ya cargada por N6 | 20 min |
| 8 | 🟠 Alta | I2/I3 — índices faltantes | Subconsultas sin índice utilizable | Migración de índices | 30 min |
| 9 | 🟠 Alta | N5 — polimorfismo sin morph map | Impide eager loading en 3 modelos | `enforceMorphMap()` + `morphTo()` | 1 h |
| 10 | 🟠 Alta | N8 — cascada en `applyLineSuggestions` | Miles de queries en una request | Se resuelve con N1+N2+N5 | — |
| 11 | 🟠 Alta | Q5 — dashboard sin techo de memoria | Crece con el catálogo, sin límite | Agregados sobre el cache + 3 columnas nuevas | 4 h |
| 12 | 🟠 Alta | N9 — `defaultLocation()` en el bucle | Hasta 4 queries por movimiento | Memoización, como `getSetting()` | 30 min |
| 13 | 🟡 Media | Q1, Q2, Q3 — `NotificationService` | 2 queries por alerta; filtros en PHP | `having`, `leftJoinSub`, inserción en lote | 2 h |
| 14 | 🟡 Media | Q6 — `defaultPriceList()` redundante | −1 query en 4 pantallas | Uniformar con `RecipeController::index` | 20 min |
| 15 | 🟡 Media | N10, N11, Q7-Q12 | Queries evitables, overfetching | Ver cada hallazgo | 3 h |
| 16 | 🟢 Baja | R6 — N+1 invisible en producción | Sin observabilidad | `handleLazyLoadingViolationUsing()` | 20 min |

---

## 7. Plan de corrección

### Fase 1 — Correcciones rápidas (menos de 15-30 minutos cada una)

Sin dependencias entre sí, sin cambios de esquema, alto beneficio inmediato:

| Orden | Hallazgo | Archivo | Cambio |
|---|---|---|---|
| 1.1 | Q4 | `AppServiceProvider.php:21-41` | `withExists()` en el view composer |
| 1.2 | N6 + N7 | `SetTenantContext.php:31-53`, `User.php:38-61` | `loadMissing('tenantUsers.tenant')` + métodos relacionales |
| 1.3 | N9 | `Tenant.php:53-58` | Memoizar `defaultLocation()` |
| 1.4 | Q6 | `DashboardController:40`, `PriceListController:30,51`, `RecipeShowViewModel:40` | Uniformar con el patrón de `RecipeController::index:39-43` |
| 1.5 | N2 (a,b) | `RecipeCostPropagator.php:61-92` | `distinct()->pluck()` y quitar el `->get()` |
| 1.6 | Q3 | `NotificationService.php:149-153` | `having` en vez de `whereHas` |
| 1.7 | Q7 | `Purchase.php:56-59` | `relationLoaded('lines')` |
| 1.8 | N11 | `Admin/TenantController.php:79-85` | `loadCount()` |
| 1.9 | R6 | `AppServiceProvider.php:19` | `handleLazyLoadingViolationUsing()` |
| 1.10 | I1-I7 | Migración nueva | Índices, con el patrón defensivo de `add_missing_indexes` |

**Resultado esperado de la Fase 1:** −2 queries en cada request, −5 en cada página, −18 en
`/recipes/{id}`, y los índices que hacen baratas las correcciones de la Fase 2. **Sin ningún cambio
de comportamiento observable.**

### Fase 2 — Correcciones medianas (1-2 horas cada una)

| Orden | Hallazgo | Cambio | Depende de |
|---|---|---|---|
| 2.1 | N5 | Morph map + `morphTo()` en `StockLevel`, `StockMovement`, `PurchaseLine` | Verificación de valores `*_type` en producción |
| 2.2 | N3 | `ancestorIdsOf()` + `whereNotIn` en `availableSemiElaborates` | — |
| 2.3 | N4 | Filtro en SQL + carga agrupada en `syncLowStock` | 2.1 (para usar `with('stockable')`) |
| 2.4 | Q1 | `raise()` en lote | 2.3 |
| 2.5 | Q2 | `leftJoinSub` en `syncStaleCost` | I3 |
| 2.6 | N10, Q9, Q10, Q12 | Eager loading en el borde, agrupación de consultas | — |

### Fase 3 — Refactorizaciones importantes (medio día cada una)

| Orden | Hallazgo | Cambio | Notas |
|---|---|---|---|
| 3.1 | **N1** | Reescritura de `propagateFrom` en tres fases | **Commit propio y aislado.** El orden topológico pasa a ser requisito de corrección, no optimización. Tests de propagación verdes antes y después, sin modificarlos |
| 3.2 | N2 (c) | `propagateManyFrom()` con `$visited` compartido | Después de 3.1 |
| 3.3 | N8 | Propagación diferida al final del lote en `applyLineSuggestions` | Después de 3.2. Cambia *cuándo* se escribe el cache, no *qué* |
| 3.4 | Q5 | Columnas `ingredient_cost`/`packaging_cost`/`labor_cost` + agregados en SQL | Migración + backfill con `recipes:refresh-costs` |

### Fase 4 — Cambios arquitectónicos recomendados (no implementar sin decisión de producto)

**4.1 — Mover la propagación de costos y el sync de alertas a colas.**

Es la respuesta de fondo a R8 y al costo O(catálogo) de `syncStateAlerts`. Hoy `QUEUE_CONNECTION` es
`sync` y no hay una sola clase `Job` en el proyecto, así que **toda** escritura en cascada ocurre
dentro del request HTTP.

**Trade-off que hay que decidir explícitamente, porque cambia lo que el usuario ve:** si
`RecipeCostPropagator` pasa a una cola, el redirect posterior a editar una línea de receta puede
mostrar el **costo anterior** durante unos segundos. Hoy el sistema garantiza que el costo está al
día en el mismo request. Esa garantía es lo que se está negociando, y no es una decisión técnica —
es de producto. Mitigación posible: propagar de forma síncrona la receta editada (barato) y encolar
solo la propagación hacia los ancestros (caro y menos visible).

Para las alertas el trade-off es mucho más suave: nadie espera que el feed se reconcilie en el
milisegundo, así que `syncStateAlerts` es el mejor primer candidato a mover a un
`ScheduledCommand` cada 15 minutos + reconciliación puntual en las escrituras que la afectan.

**4.2 — Caché de lectura del dashboard.**

Con los caches `unit_cost`/`labor_hours` ya presentes, el paso siguiente natural es cachear los
agregados del dashboard por tenant con invalidación desde el propagador. Solo tiene sentido
**después** de la Fase 3: cachear una consulta ineficiente es esconder el problema, no resolverlo.

**4.3 — Presupuesto de consultas en los tests.**

La forma de que este informe no haya que repetirlo en seis meses. Con Pest ya instalado, un test que
falle cuando una pantalla clave supere su presupuesto de queries:

```php
it('el dashboard no supera su presupuesto de consultas', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) { $queries++; });

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($queries)->toBeLessThan(25);
});
```

Es la diferencia entre corregir los N+1 una vez y **mantenerlos corregidos**.

---

## 8. Optimizaciones evaluadas y **descartadas**

Un informe que solo suma cambios es un informe que no pensó los trade-offs. Estas seis se evaluaron
y **no se recomiendan**:

**8.1 — Convertir los tres `withCount`/`withSum` de `PurchaseController::index:54-60` en un
`leftJoinSub` agrupado.** Parece el caso de manual para «usemos joins»: tres subconsultas
correlacionadas sobre la misma tabla. Pero la query **ya está paginada a 20 filas**, así que las
subconsultas se evalúan 20 veces sobre un índice, mientras que el join agruparía `purchase_lines`
**entera** antes de paginar. Además rompería el `orderBy('lines_count')` del `match:77`, que ordena
por el alias del `withCount`. **No tocar.**

**8.2 — Reemplazar las subconsultas del dashboard (`DashboardController:57-65`) por joins.** Romperían
la semántica del `orderByRaw("({$sortExpr}) is null")` (`:101`), que empuja los nulos al final — un
`INNER JOIN` los eliminaría y un `LEFT JOIN` complicaría los bindings, que ya están cuidadosamente
documentados en `:69-73`. Con el índice I2 la subconsulta es una búsqueda directa. **No tocar.**

**8.3 — `upsert()` masivo en `NotificationService::raise()`.** Es la optimización obvia y **es
incorrecta acá**. `upsert()` necesita un índice **único** sobre `(tenant_id, dedupe_key)`, pero el
diseño exige lo contrario: el propio código documenta en `:246-249` que una alerta resuelta **no
debe revivir** y que, si el estado recurre, se crea una fila **nueva con la misma `dedupe_key`**. Un
único sobre esa pareja rompería la regla de negocio, y MySQL no tiene índices parciales para excluir
las resueltas. La alternativa aplicable es la de Q1: una lectura agrupada + `insert()` en lote.

**8.4 — Quitar las verificaciones de tenant de las 12 policies.** Son estructuralmente redundantes
con el global scope de `BelongsToTenant` —que ya hace que un recurso de otro tenant dé 404 en el
route-model binding— pero cuestan **0 queries**: comparan `$model->tenant_id` contra
`app(Tenant::class)->id`, ambos ya en memoria. Son defensa en profundidad deliberada, documentada en
el docblock del trait (`:22-24`). No hay nada que ganar y sí algo que perder.

**8.5 — Mover `RecipeCostCalculator` a SQL.** Rompe la conversión de unidades, que vive en
`UnitConverter` (PHP) y no es expresable en SQL sin codificar la tabla de conversiones dentro de la
consulta. Cambiaría los resultados, que es exactamente lo que el pedido prohíbe.

**8.6 — `WITH RECURSIVE` para el cierre de ancestros.** Es portable (MySQL 8 y SQLite 3.8.3+) y
resolvería el cierre en **una** query en vez de 3-5. Pero la variante por niveles con `whereIn`
logra casi lo mismo en Eloquent puro y legible, sin meter SQL recursivo crudo en un servicio de
dominio. Si el árbol de sub-recetas creciera a 8-10 niveles de profundidad, conviene reevaluarla.

---

## 9. Patrones correctos que ya existen en el código

Las correcciones de este informe deben **imitar lo que el repo ya hace bien**, no inventar un estilo
nuevo. Referencias:

- **`Tenant::getSetting()` (`Tenant.php:172-180`)** — memoización por instancia con
  `pluck('value','key')` y un comentario que explica por qué. Es el modelo exacto a seguir para
  `defaultLocation()` (N9).
- **`ProductLinkMemory::recallMany()` (`:45`)** — resuelve 30 renglones de factura en una query, con
  el comentario de `:38-39`: *«una factura con 30 líneas no puede costar 30 queries»*. Es
  precisamente el criterio que le falta a `syncLowStock`.
- **`RecipeController::index:39-43`** — resolución de la lista de precios default sin query extra. El
  patrón que Q6 propone uniformar.
- **`DashboardController:90-133`** — tabla paginada que filtra, ordena y pagina **en SQL** sobre los
  caches, sin recalcular nada en PHP, con los bindings documentados uno por uno. Es el estándar de
  calidad de este proyecto.
- **`PriceListController::matrix:82-86` y `RecipeController::index:64-66`** — precios traídos en una
  query e indexados por id, en vez de una consulta por fila.
- **`RefreshRecipeCosts:27-47`** — `chunkById` + `updateQuietly`, correcto para un backfill. El
  patrón que Q12 propone para los otros comandos.
- **`PurchaseController::applyLineSuggestions:591`** — `setRelation('purchase', $purchase)` para
  evitar un lazy load por línea. Correcto; solo falta replicarlo en `matchLine()` (N10).
- **`NotificationService::syncStaleCost:100-105`** — `selectSub` con `max(recorded_at)` para evitar
  el N+1 obvio. La corrección de Q2 lo mejora, no lo contradice.
- **`2026_06_15_165805_add_missing_indexes.php`** — migración de índices reejecutable, que verifica
  con `Schema::getIndexes()` antes de crear. El patrón a seguir para la migración de §4.
- **`Model::preventLazyLoading(! app()->isProduction())` (`AppServiceProvider:19`)** — la protección
  está puesta; solo falta extenderla a producción en modo log (R6).

---

## Anexo — Cómo verificar cada corrección

Ninguna de las correcciones de este informe debería cambiar el comportamiento observable. La forma
de comprobarlo:

1. **Contar antes y después.** Un `DB::listen()` temporal en `AppServiceProvider`, o Laravel
   Debugbar en local, sobre las cinco rutas críticas: `/dashboard`, `/recipes/{id}`, `/purchases`,
   `/purchases/{id}/match`, `/stock`.
2. **La suite completa verde, sin modificar tests.** Si una corrección de rendimiento obliga a
   cambiar un test de comportamiento, dejó de ser una corrección de rendimiento.
   `php artisan test --compact`.
3. **Redes específicas por hallazgo:** N1/N2 → `RecipeCostPropagatorTest`,
   `RecipeCostCalculatorSubrecipeTest`, `RecipeCostTest`. N3 → `RecipeSubrecipeLineTest`. N4/Q1/Q2/Q3
   → `NotificationAlertsTest`. N6/N7 → `TenantIsolationTest`, `tests/Feature/Auth`. N8 →
   `PurchaseCrudTest`, `PurchaseMatchSubdivisionTest`, `StockPurchaseIntegrationTest`. Q4 →
   `OnboardingTest`. Q5 → `DashboardCachedCostTest`, `DashboardRentabilidadTest`.
4. **`EXPLAIN` sobre las consultas afectadas por los índices**, en una copia de producción. Un índice
   que el optimizador no elige es costo de escritura sin contrapartida.
5. **`vendor/bin/pint --dirty --format agent`** antes de cerrar cualquier commit con PHP tocado.
