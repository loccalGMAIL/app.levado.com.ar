<?php

use App\Enums\TenantUserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $draft
 */
function fakeReceiptRead(array $draft): void
{
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($draft)]],
        ]),
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('el comprobante escaneado va al disco privado y nunca al público', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Flete',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $category->id,
    ]);

    $path = $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->json('path');

    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('escanear un comprobante devuelve los campos leídos', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplier = $tenant->suppliers()->create(['name' => 'Ferretería Rosales', 'active' => true]);

    fakeReceiptRead([
        'name' => 'Ferretería: tornillos, cinta y silicona',
        'amount' => 24500.75,
        'expense_date' => '2026-05-14',
        'supplier_name' => 'FERRETERIA ROSALES S.R.L.',
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($user)
        ->post(route('variable-expenses.scan'), [
            'receipt' => UploadedFile::fake()->image('ticket.jpg', 800, 600),
        ])
        ->assertOk();

    $response->assertJson([
        'name' => 'Ferretería: tornillos, cinta y silicona',
        'amount' => 24500.75,
        'expense_date' => '2026-05-14',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
    ]);

    expect($response->json('path'))->toStartWith("variable-expenses/{$tenant->id}/");
    Storage::disk('local')->assertExists($response->json('path'));
});

test('un comprobante de varios ítems queda resumido en una sola descripción', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Ferretería: tornillos, cinta y silicona',
        'amount' => 24500,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $category->id,
    ]);

    $name = $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->json('name');

    expect($name)->toBe('Ferretería: tornillos, cinta y silicona')
        ->and($name)->not->toContain("\n");
});

test('un importe en formato argentino se interpreta como número', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Luz',
        'amount' => '13.891,40',
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->assertJson(['amount' => 13891.40]);
});

test('el proveedor matchea aunque el comprobante venga en mayúsculas y sin tildes', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplier = $tenant->suppliers()->create(['name' => 'Panificación Güemes', 'active' => true]);

    fakeReceiptRead([
        'name' => 'Insumos',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => 'PANIFICACION GUEMES S.A.',
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->assertJson(['supplier_id' => $supplier->id]);
});

test('si el proveedor leído no está en el listado, el gasto queda sin proveedor', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $tenant->suppliers()->create(['name' => 'Molino San José', 'active' => true]);

    fakeReceiptRead([
        'name' => 'Flete',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => 'Transportes Quilmes',
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->assertJson(['supplier_id' => null]);
});

test('se descarta una categoría sugerida que es de otro negocio', function () {
    [$user, $tenant] = ownerForVariableExpense();
    [, $otherTenant] = ownerForVariableExpense();
    $otherCategory = $otherTenant->variableExpenseCategories()->first();

    fakeReceiptRead([
        'name' => 'Flete',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $otherCategory->id,
    ]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertOk()
        ->assertJson(['category_id' => null]);
});

test('si la lectura falla, el comprobante no queda huérfano en el disco', function () {
    [$user] = ownerForVariableExpense();

    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('sin API key configurada el scan avisa en vez de romper', function () {
    [$user] = ownerForVariableExpense();

    config(['services.anthropic.key' => null]);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertStatus(422);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('una foto grande se achica antes de mandarse a la IA', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Ferretería',
        'amount' => 100,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $category->id,
    ]);

    $path = $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('grande.jpg', 4000, 3000)])
        ->assertOk()
        ->json('path');

    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));

    expect(max($width, $height))->toBeLessThanOrEqual(1600);
});

test('un viewer no puede escanear comprobantes', function () {
    [$user] = userForVariableExpense(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->assertForbidden();
});

test('el archivo escaneado se referencia por path, sin volver a subirlo', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Flete',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $category->id,
    ]);

    $path = $this->actingAs($user)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->json('path');

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-14',
        'receipt_image_path' => $path,
    ])->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->first()->receipt_image_path)->toBe($path)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('no se puede referenciar el comprobante de otro negocio con un path armado a mano', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    [$otherUser, $otherTenant, $otherCategory] = ownerForVariableExpense();

    fakeReceiptRead([
        'name' => 'Flete ajeno',
        'amount' => 5000,
        'expense_date' => '2026-05-14',
        'supplier_name' => null,
        'category_id' => $otherCategory->id,
    ]);

    $otherPath = $this->actingAs($otherUser)
        ->post(route('variable-expenses.scan'), ['receipt' => UploadedFile::fake()->image('t.jpg', 400, 300)])
        ->json('path');

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Intento',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-14',
        'receipt_image_path' => $otherPath,
    ])->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->first()->receipt_image_path)->toBeNull();
});

test('un path inventado que no existe en el disco se ignora', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Intento',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-14',
        'receipt_image_path' => "variable-expenses/{$tenant->id}/no-existe.jpg",
    ])->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->first()->receipt_image_path)->toBeNull();
});
