<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\RecipePrintSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecipePrintController extends Controller
{
    public function __construct(private readonly RecipePrintSheet $sheet) {}

    public function show(Request $request, Recipe $recipe): View
    {
        $this->authorize('view', $recipe);

        return view('recipes.print.screen', [
            'recipe' => $recipe,
            'sheet' => $this->sheet->for($recipe, $this->flourGrams($request)),
        ]);
    }

    public function pdf(Request $request, Recipe $recipe): Response
    {
        $this->authorize('view', $recipe);

        return Pdf::loadView('recipes.print.pdf', ['sheet' => $this->sheet->for($recipe, $this->flourGrams($request))])
            ->setPaper('a4')
            ->download('receta-'.Str::slug($recipe->name).'.pdf');
    }

    /**
     * Gramos de harina a los que escalar la hoja (query ?flour=), si vino.
     */
    private function flourGrams(Request $request): ?float
    {
        $data = $request->validate(['flour' => ['nullable', 'numeric', 'gt:0']]);

        return isset($data['flour']) ? (float) $data['flour'] : null;
    }
}
