<?php

use Illuminate\Support\Facades\Blade;

test('list header renders title, subtitle and stacks actions on mobile', function () {
    $html = Blade::render('<x-list-header title="Compras" subtitle="Facturas."><a href="#">Uno</a></x-list-header>');

    expect($html)
        ->toContain('Compras')
        ->toContain('Facturas.')
        ->toContain('flex-col')
        ->toContain('sm:flex-row')
        ->toContain('auto-cols-fr')
        ->toContain('Uno');
});

test('list header omits the actions container when there are no actions', function () {
    $html = Blade::render('<x-list-header title="Stock" subtitle="Algo." />');

    expect($html)->toContain('Stock')->not->toContain('auto-cols-fr');
});

test('list header accepts a description slot that overrides the subtitle prop', function () {
    $html = Blade::render('<x-list-header title="Órdenes"><x-slot:description>Con <b>markup</b></x-slot:description></x-list-header>');

    expect($html)->toContain('Con <b>markup</b>');
});

test('list header omits the actions container when the slot only has whitespace', function () {
    $html = Blade::render("<x-list-header title=\"Recetas\" subtitle=\"Algo.\">\n    @if(false) <a>x</a> @endif\n</x-list-header>");

    expect($html)->toContain('Recetas')->not->toContain('auto-cols-fr');
});
