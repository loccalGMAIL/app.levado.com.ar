<?php

use App\Enums\MarginTier;

// Los cortes de margen son la razón de ser del enum: antes vivían en siete
// lugares con dos escalas distintas. Estos tests fijan los bordes exactos.

test('clasifica el margen en los bordes de cada tramo', function (?float $pct, MarginTier $expected) {
    expect(MarginTier::fromPercent($pct))->toBe($expected);
})->with([
    'sin margen' => [null, MarginTier::Indefinido],
    'apenas bajo 20' => [19.99, MarginTier::Baja],
    'justo 20' => [20.0, MarginTier::Regular],
    'apenas bajo 40' => [39.99, MarginTier::Regular],
    'justo 40' => [40.0, MarginTier::Media],
    'apenas bajo 60' => [59.99, MarginTier::Media],
    'justo 60' => [60.0, MarginTier::Alta],
    'margen negativo' => [-15.0, MarginTier::Baja],
    'margen enorme' => [99.5, MarginTier::Alta],
]);

test('el 35% queda en Regular y no en Alta', function () {
    // El caso concreto de la incoherencia vieja: con la escala 30/15 del
    // controller esto respondía verde mientras la badge decía «Regular».
    expect(MarginTier::fromPercent(35.0))->toBe(MarginTier::Regular)
        ->and(MarginTier::fromPercent(35.0)->textColor())->toBe('text-orange-600')
        ->and(MarginTier::fromPercent(35.0)->label())->toBe('Regular');
});

test('cada tramo tiene etiqueta y color propios', function () {
    $labels = array_map(fn (MarginTier $t) => $t->label(), MarginTier::cases());
    $colors = array_map(fn (MarginTier $t) => $t->textColor(), MarginTier::cases());

    expect($labels)->toBe(['Alta', 'Media', 'Regular', 'Baja', '—'])
        ->and($colors)->toHaveCount(count(array_unique($colors)));
});
