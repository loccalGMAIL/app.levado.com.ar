<?php

use App\Enums\TenantUserRole;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Services\OrderSheetWeekService;
use Carbon\CarbonImmutable;

// "Hoy" en estos tests: miércoles 18/11/2026. Su lunes es el 16/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
    $this->weeks = app(OrderSheetWeekService::class);
});

/** Planilla con una semana cargada (lunes dado) y una fila con las cantidades indicadas. */
function sheetWithWeek(string $monday, array $quantities = [10, 0, 0, 0, 0, 0, 5]): array
{
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn($monday)->create(['tenant_id' => $tenant->id]);
    $line = OrderSheetLine::factory()->for($week, 'week')->withQuantities($quantities)->create();

    return [$sheet, $week, $line, $tenant];
}

test('mondayOf lleva cualquier día de la semana a su lunes', function () {
    expect($this->weeks->mondayOf(CarbonImmutable::parse('2026-11-18'))->toDateString())->toBe('2026-11-16')
        ->and($this->weeks->mondayOf(CarbonImmutable::parse('2026-11-22'))->toDateString())->toBe('2026-11-16')
        ->and($this->weeks->mondayOf(CarbonImmutable::parse('2026-11-23'))->toDateString())->toBe('2026-11-23');
});

test('una semana futura nueva se crea como copia de la anterior', function () {
    [$sheet, , $line] = sheetWithWeek('2026-11-16');

    $next = $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-25'));

    expect($next)->not->toBeNull()
        ->and($next->starts_on->toDateString())->toBe('2026-11-23')
        ->and($next->lines)->toHaveCount(1)
        ->and($next->lines->first()->product_id)->toBe($line->product_id)
        ->and($next->lines->first()->quantities())->toBe([10.0, 0.0, 0.0, 0.0, 0.0, 0.0, 5.0]);
});

test('con un hueco de semanas copia la última semana cargada', function () {
    [$sheet, , $line] = sheetWithWeek('2026-11-16');

    $far = $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-12-09'));

    expect($far->starts_on->toDateString())->toBe('2026-12-07')
        ->and($far->lines->first()->product_id)->toBe($line->product_id);
});

test('editar una semana no toca las demás', function () {
    [$sheet, $week, $line] = sheetWithWeek('2026-11-16');
    $next = $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-23'));

    $this->weeks->save($next, [['product_id' => $line->product_id, 'quantities' => [99, 1, 1, 1, 1, 1, 1]]]);

    expect($week->fresh()->lines->first()->quantities())->toBe([10.0, 0.0, 0.0, 0.0, 0.0, 0.0, 5.0])
        ->and($next->fresh()->lines->first()->day_1)->toBe(99.0);
});

test('una semana pasada sin datos no se crea al mirarla', function () {
    [$sheet] = sheetWithWeek('2026-11-16');

    expect($this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-02')))->toBeNull()
        ->and($sheet->weeks()->count())->toBe(1);
});

test('ensureWeek sí crea una semana pasada, vacía si no hay anterior', function () {
    [$sheet] = sheetWithWeek('2026-11-16');

    $past = $this->weeks->ensureWeek($sheet, CarbonImmutable::parse('2026-11-02'));

    expect($past->starts_on->toDateString())->toBe('2026-11-02')
        ->and($past->lines)->toHaveCount(0);
});

test('pedir la misma semana dos veces no la duplica', function () {
    [$sheet] = sheetWithWeek('2026-11-16');

    $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-23'));
    $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-24'));

    expect($sheet->weeks()->count())->toBe(2);
});

test('save reemplaza las filas, respeta el orden y lleva los negativos a cero', function () {
    [$sheet, $week, $line, $tenant] = sheetWithWeek('2026-11-16');
    $otro = Product::factory()->for($tenant)->create();

    $this->weeks->save($week, [
        ['product_id' => $otro->id, 'quantities' => [1, 2, 3, 4, 5, 6, 7]],
        ['product_id' => $line->product_id, 'quantities' => [-5, null, '2.5']],
    ]);

    $lines = $week->fresh()->lines;

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->product_id)->toBe($otro->id)
        ->and($lines[1]->quantities())->toBe([0.0, 0.0, 2.5, 0.0, 0.0, 0.0, 0.0]);
});

test('recopy deja la semana igual a la anterior', function () {
    [$sheet, , $line] = sheetWithWeek('2026-11-16');
    $next = $this->weeks->weekFor($sheet, CarbonImmutable::parse('2026-11-23'));
    $this->weeks->save($next, []);
    expect($next->fresh()->lines)->toHaveCount(0);

    $this->weeks->recopy($next->fresh());

    $lines = $next->fresh()->lines;
    expect($lines)->toHaveCount(1)
        ->and($lines->first()->product_id)->toBe($line->product_id)
        ->and($lines->first()->quantities())->toBe([10.0, 0.0, 0.0, 0.0, 0.0, 0.0, 5.0]);
});

test('la copia solo mira la planilla de la semana, no las de otras', function () {
    [$sheet, , , $tenant] = sheetWithWeek('2026-11-16');
    $otra = OrderSheet::factory()->for($tenant)->create();

    $week = $this->weeks->weekFor($otra, CarbonImmutable::parse('2026-11-23'));

    expect($week->lines)->toHaveCount(0);
});
