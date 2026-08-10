<?php

namespace App\Enums;

use App\Models\Ingredient;
use App\Models\Packaging;

/**
 * Dueño único de los discriminadores que viajan en
 * purchase_lines.purchaseable_type y stock_movements/stock_levels.stockable_type
 * (y en las URLs /stock/{type}). Desde N5 (auditoría 04/08/2026) son también
 * las claves del morph map real de Eloquent (Relation::enforceMorphMap() en
 * AppServiceProvider), así que además de discriminadores manuales son los
 * valores persistidos que Eloquent usa para resolver stockable/purchaseable.
 * Los valores están persistidos en BD y en rutas, así que NO se pueden
 * renombrar. Un typo en un string suelto creaba filas huérfanas silenciosas;
 * contra el enum, explota.
 */
enum CatalogItemType: string
{
    case Ingredient = 'ingredient';
    case Packaging = 'packaging';

    /** @return class-string<Ingredient|Packaging> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Ingredient => Ingredient::class,
            self::Packaging => Packaging::class,
        };
    }

    public static function for(Ingredient|Packaging $item): self
    {
        return $item instanceof Ingredient ? self::Ingredient : self::Packaging;
    }
}
