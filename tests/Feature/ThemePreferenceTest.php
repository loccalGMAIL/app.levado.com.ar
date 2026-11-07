<?php

use App\Enums\Theme;
use App\Models\User;

/**
 * El tag <html> de la respuesta, para distinguir el data-theme del layout del de
 * las miniaturas del selector de Perfil.
 */
function htmlTag(string $html): string
{
    preg_match('/<html[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

test('guarda el tema elegido desde el selector de perfil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.theme'), ['theme' => 'oscuro'])
        ->assertNoContent();

    expect($user->refresh()->theme)->toBe(Theme::Oscuro);
});

test('«Automático» borra la preferencia', function (?string $automatico) {
    $user = User::factory()->create(['theme' => Theme::Centeno]);

    $this->actingAs($user)
        ->patchJson(route('profile.theme'), ['theme' => $automatico])
        ->assertNoContent();

    expect($user->refresh()->theme)->toBeNull();
})->with(['null' => [null], 'vacío' => ['']]);

test('rechaza un tema que no existe', function () {
    $user = User::factory()->create(['theme' => Theme::Claro]);

    $this->actingAs($user)
        ->patchJson(route('profile.theme'), ['theme' => 'violeta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('theme');

    expect($user->refresh()->theme)->toBe(Theme::Claro);
});

test('sin JS el formulario guarda y vuelve a perfil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.theme'), ['theme' => 'centeno'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'theme-updated');

    expect($user->refresh()->theme)->toBe(Theme::Centeno);
});

test('un invitado no puede cambiar el tema', function () {
    $this->patchJson(route('profile.theme'), ['theme' => 'oscuro'])->assertUnauthorized();
});

test('el layout aplica el tema elegido sin esperar al JS', function (Theme $theme) {
    $user = User::factory()->create(['theme' => $theme]);

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    expect(htmlTag($html))->toContain('data-theme="'.$theme->value.'"')
        ->and($html)->toContain('<meta name="theme-color" content="'.$theme->themeColor().'">')
        ->toContain('let preference = "'.$theme->value.'";');
})->with(Theme::cases());

test('sin preferencia el layout deja el tema en automático', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    expect(htmlTag($html))->not->toContain('data-theme')
        ->and($html)->toContain('let preference = null;')
        ->toContain("matchMedia('(prefers-color-scheme: dark)')");
});

test('las páginas de invitado también resuelven el tema automático', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect(htmlTag($html))->not->toContain('data-theme')
        ->and($html)->toContain('let preference = null;');
});

test('perfil muestra el selector con el tema actual marcado', function () {
    $user = User::factory()->create(['theme' => Theme::Centeno]);

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    expect($html)->toContain('Apariencia')
        ->toContain('value="centeno" class="sr-only"')
        ->and(preg_match('/value="centeno"[^>]*checked/', $html))->toBe(1)
        ->and(preg_match('/value=""[^>]*checked/', $html))->toBe(0);
});

test('el menú de usuario y el panel «Más» mobile tienen el selector de tema', function (?Theme $theme, string $marcado) {
    $user = User::factory()->create(['theme' => $theme]);

    $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

    preg_match_all('/<button type="button" role="radio" title="([^"]+)"\s+aria-checked="true"/', $html, $checked);

    expect(substr_count($html, 'role="radiogroup" aria-label="Tema"'))->toBe(2)
        ->and($checked[1])->toBe([$marcado, $marcado]);
})->with([
    'automático' => [null, 'Automático'],
    'oscuro' => [Theme::Oscuro, 'Oscuro'],
    'centeno' => [Theme::Centeno, 'Centeno'],
]);
