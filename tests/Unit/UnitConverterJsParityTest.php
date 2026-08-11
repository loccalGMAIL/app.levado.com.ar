<?php

use App\Enums\Unit;
use App\Services\UnitConverter;

/**
 * `resources/js/units.js` duplica en el browser la tabla de UnitConverter, porque los
 * formularios de compra tienen que reexpresar el precio unitario sin ida y vuelta al
 * servidor. Si el enum Unit crece y la tabla JS no la sigue, el hint de conversión
 * desaparece en silencio y vuelve el bug que motivó todo esto: $2000 el kilo cargados
 * como $2000 el gramo.
 *
 * @return array<string, array<string, float>>
 */
function jsUnitConversionTable(): array
{
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/units.js');

    expect($source)->toBeString();

    $found = preg_match('/export const UNIT_CONV = (\{.*?\n\});/s', $source, $matches);

    expect($found)->toBe(1, 'No se pudo encontrar UNIT_CONV en resources/js/units.js');

    $json = str_replace("'", '"', $matches[1]);
    $json = preg_replace('/,(\s*[}\]])/', '$1', $json);

    $table = json_decode($json, true);

    expect($table)->toBeArray();

    return $table;
}

it('cubre exactamente los mismos pares de unidades que UnitConverter', function () {
    $converter = new UnitConverter;
    $table = jsUnitConversionTable();

    foreach (Unit::cases() as $from) {
        foreach (Unit::cases() as $to) {
            $php = $converter->convert(1.0, $from, $to);
            $js = $table[$from->value][$to->value] ?? null;

            $pair = "{$from->value} → {$to->value}";

            if ($php === null) {
                expect($js)->toBeNull("La tabla JS convierte $pair pero UnitConverter lo considera incompatible");

                continue;
            }

            expect($js)->not->toBeNull("UnitConverter convierte $pair pero falta en la tabla JS");
            expect((float) $js)->toBe($php, "El factor de $pair no coincide entre PHP y JS");
        }
    }
});

it('no declara unidades que el enum Unit desconoce', function () {
    $known = array_map(fn (Unit $unit) => $unit->value, Unit::cases());

    foreach (jsUnitConversionTable() as $from => $targets) {
        expect($known)->toContain($from);

        foreach (array_keys($targets) as $to) {
            expect($known)->toContain($to);
        }
    }
});
