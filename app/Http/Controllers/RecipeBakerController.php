<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\BakerPercentages;
use Illuminate\View\View;

class RecipeBakerController extends Controller
{
    public function show(Recipe $recipe, BakerPercentages $baker): View
    {
        $this->authorize('view', $recipe);

        return view('recipes.baker', [
            'recipe' => $recipe,
            'baker' => $baker->for($recipe),
            'canEdit' => auth()->user()->can('update', $recipe),
        ]);
    }
}
