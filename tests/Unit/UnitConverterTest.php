<?php

use App\Enums\Unit;
use App\Services\UnitConverter;

beforeEach(function () {
    $this->converter = new UnitConverter;
});

// --- Peso ---

test('convierte gramos a kilogramos', function () {
    expect($this->converter->convert(500, Unit::Gramo, Unit::Kilogramo))->toBe(0.5);
});

test('convierte kilogramos a gramos', function () {
    expect($this->converter->convert(1.5, Unit::Kilogramo, Unit::Gramo))->toBe(1500.0);
});

test('misma unidad peso retorna el mismo valor', function () {
    expect($this->converter->convert(200, Unit::Gramo, Unit::Gramo))->toBe(200.0);
});

// --- Volumen ---

test('convierte mililitros a litros', function () {
    expect($this->converter->convert(250, Unit::Mililitro, Unit::Litro))->toBe(0.25);
});

test('convierte litros a mililitros', function () {
    expect($this->converter->convert(2, Unit::Litro, Unit::Mililitro))->toBe(2000.0);
});

test('cc equivale a ml en conversión directa', function () {
    expect($this->converter->convert(100, Unit::Centimetro3, Unit::Mililitro))->toBe(100.0);
});

test('convierte cc a litros', function () {
    expect($this->converter->convert(500, Unit::Centimetro3, Unit::Litro))->toBe(0.5);
});

test('misma unidad volumen retorna el mismo valor', function () {
    expect($this->converter->convert(300, Unit::Mililitro, Unit::Mililitro))->toBe(300.0);
});

// --- Unidad ---

test('misma unidad u retorna el mismo valor', function () {
    expect($this->converter->convert(4, Unit::Unidad, Unit::Unidad))->toBe(4.0);
});

// --- Incompatibilidad ---

test('retorna null al convertir peso a volumen', function () {
    expect($this->converter->convert(100, Unit::Gramo, Unit::Mililitro))->toBeNull();
});

test('retorna null al convertir volumen a unidad', function () {
    expect($this->converter->convert(1, Unit::Litro, Unit::Unidad))->toBeNull();
});

test('retorna null al convertir peso a unidad', function () {
    expect($this->converter->convert(1, Unit::Kilogramo, Unit::Unidad))->toBeNull();
});

// --- compatible() ---

test('peso y peso son compatibles', function () {
    expect($this->converter->compatible(Unit::Gramo, Unit::Kilogramo))->toBeTrue();
});

test('volumen y volumen son compatibles', function () {
    expect($this->converter->compatible(Unit::Mililitro, Unit::Litro))->toBeTrue();
    expect($this->converter->compatible(Unit::Centimetro3, Unit::Litro))->toBeTrue();
});

test('peso y volumen no son compatibles', function () {
    expect($this->converter->compatible(Unit::Gramo, Unit::Mililitro))->toBeFalse();
});

test('unidad solo es compatible consigo misma', function () {
    expect($this->converter->compatible(Unit::Unidad, Unit::Unidad))->toBeTrue();
    expect($this->converter->compatible(Unit::Unidad, Unit::Gramo))->toBeFalse();
});
