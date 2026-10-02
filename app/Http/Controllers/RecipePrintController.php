<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\RecipePrintSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecipePrintController extends Controller
{
    public function __construct(private readonly RecipePrintSheet $sheet) {}

    public function show(Recipe $recipe): View
    {
        $this->authorize('view', $recipe);

        return view('recipes.print.screen', [
            'recipe' => $recipe,
            'sheet' => $this->sheet->for($recipe),
        ]);
    }

    public function pdf(Recipe $recipe): Response
    {
        $this->authorize('view', $recipe);

        return Pdf::loadView('recipes.print.pdf', ['sheet' => $this->sheet->for($recipe)])
            ->setPaper('a4')
            ->download('receta-'.Str::slug($recipe->name).'.pdf');
    }
}
