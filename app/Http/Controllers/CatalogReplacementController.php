<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\CatalogItemReplacer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reemplazo masivo de un ingrediente, un descartable o una sub-receta en
 * todas las recetas que lo usan — para cuando un insumo queda descontinuado.
 * La lógica vive en CatalogItemReplacer; este controller sólo resuelve los
 * modelos, valida pertenencia al tenant y traduce el resultado a flash/JSON.
 */
class CatalogReplacementController extends Controller
{
    public function __construct(
        private readonly CatalogItemReplacer $replacer,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'type' => ['required', Rule::in(['ingredient', 'packaging', 'recipe'])],
            'from_id' => ['required', 'integer'],
            'to_id' => ['required', 'integer'],
        ]);

        $result = match ($data['type']) {
            'ingredient' => $this->replacer->previewIngredient(
                $this->findIngredient($tenant, (int) $data['from_id']),
                $this->findIngredient($tenant, (int) $data['to_id']),
            ),
            'packaging' => $this->replacer->previewPackaging(
                $this->findPackaging($tenant, (int) $data['from_id']),
                $this->findPackaging($tenant, (int) $data['to_id']),
            ),
            'recipe' => $this->replacer->previewSubrecipe(
                $this->findRecipe($tenant, (int) $data['from_id']),
                $this->findRecipe($tenant, (int) $data['to_id']),
            ),
        };

        return response()->json($result);
    }

    public function replaceIngredient(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'to_id' => ['required', 'integer'],
            'deactivate_source' => ['nullable', 'boolean'],
            'migrate_supplier_links' => ['nullable', 'boolean'],
        ]);

        $to = $this->findIngredient($tenant, (int) $data['to_id']);

        $result = $this->replacer->replaceIngredient(
            $ingredient,
            $to,
            (bool) ($data['deactivate_source'] ?? false),
            (bool) ($data['migrate_supplier_links'] ?? false),
        );

        $this->recordReplacement('ingredient', $ingredient->id, $ingredient->name, $to, $result);

        return back()->with('status', $this->summaryMessage($ingredient->name, $to->name, $result));
    }

    public function replacePackaging(Request $request, Packaging $packaging): RedirectResponse
    {
        $this->authorize('update', $packaging);
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'to_id' => ['required', 'integer'],
            'deactivate_source' => ['nullable', 'boolean'],
        ]);

        $to = $this->findPackaging($tenant, (int) $data['to_id']);

        $result = $this->replacer->replacePackaging($packaging, $to, (bool) ($data['deactivate_source'] ?? false));

        $this->recordReplacement('packaging', $packaging->id, $packaging->name, $to, $result);

        return back()->with('status', $this->summaryMessage($packaging->name, $to->name, $result));
    }

    public function replaceRecipe(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'to_id' => ['required', 'integer'],
            'deactivate_source' => ['nullable', 'boolean'],
        ]);

        $to = $this->findRecipe($tenant, (int) $data['to_id']);

        $result = $this->replacer->replaceSubrecipe($recipe, $to, (bool) ($data['deactivate_source'] ?? false));

        $this->recordReplacement('recipe', $recipe->id, $recipe->name, $to, $result);

        return back()->with('status', $this->summaryMessage($recipe->name, $to->name, $result));
    }

    private function findIngredient(Tenant $tenant, int $id): Ingredient
    {
        return $tenant->ingredients()->findOrFail($id);
    }

    private function findPackaging(Tenant $tenant, int $id): Packaging
    {
        return $tenant->packagings()->findOrFail($id);
    }

    private function findRecipe(Tenant $tenant, int $id): Recipe
    {
        return $tenant->recipes()->findOrFail($id);
    }

    /**
     * @param  array{recipes: int, merged: int, links?: int}  $result
     */
    private function recordReplacement(string $targetType, int $fromId, string $fromName, Ingredient|Packaging|Recipe $to, array $result): void
    {
        $this->recorder->record(
            actor: request()->user(),
            targetType: $targetType,
            targetId: $fromId,
            action: "{$targetType}.replaced",
            payload: [
                'from' => $fromName,
                'to' => $to->name,
                'recipes' => $result['recipes'],
                'merged' => $result['merged'],
            ],
            tenantId: $to->tenant_id,
        );
    }

    /**
     * @param  array{recipes: int, merged: int, links?: int}  $result
     */
    private function summaryMessage(string $fromName, string $toName, array $result): string
    {
        if ($result['recipes'] === 0) {
            return "«{$fromName}» no se usaba en ninguna receta. No hubo nada que reemplazar.";
        }

        $message = "«{$fromName}» reemplazado por «{$toName}» en {$result['recipes']} receta(s).";

        if ($result['merged'] > 0) {
            $message .= " {$result['merged']} línea(s) se fusionaron con una existente.";
        }

        if (($result['links'] ?? 0) > 0) {
            $message .= " Se migraron {$result['links']} vínculo(s) de proveedor.";
        }

        return $message;
    }
}
