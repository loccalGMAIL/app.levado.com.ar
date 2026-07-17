<?php

use App\Enums\TenantUserRole;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VariableExpense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('el comprobante va al disco privado y nunca al público', function () {
    // Un comprobante es un dato fiscal: en el disco público el symlink
    // /storage lo dejaría accesible sin login a quien supiera la URL.
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $path = $tenant->variableExpenses()->first()->receipt_image_path;

    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('el alta ofrece adjuntar el comprobante y leerlo con IA', function () {
    [$user] = ownerForVariableExpense();

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('multipart/form-data', escape: false)
        ->assertSee('name="receipt"', escape: false)
        ->assertSee('name="receipt_image_path"', escape: false)
        ->assertSee('Leer con IA')
        ->assertSee(route('variable-expenses.scan'), escape: false);
});

test('un viewer no ve el formulario de alta con comprobante', function () {
    [$user] = userForVariableExpense(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertDontSee('Leer con IA');
});

test('crear un gasto sin comprobante lo deja vacío', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Multa',
            'variable_expense_category_id' => $category->id,
            'amount' => '15000',
            'expense_date' => '2026-05-01',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->first()->receipt_image_path)->toBeNull();
});

test('crear un gasto con comprobante lo guarda en la carpeta del negocio', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Reparación heladera',
            'variable_expense_category_id' => $category->id,
            'amount' => '80000',
            'expense_date' => '2026-03-12',
            'receipt' => UploadedFile::fake()->image('ticket.jpg', 800, 600),
        ])
        ->assertRedirect(route('variable-expenses.index'));

    $expense = $tenant->variableExpenses()->first();

    expect($expense->receipt_image_path)->toStartWith("variable-expenses/{$tenant->id}/");
    Storage::disk('local')->assertExists($expense->receipt_image_path);
});

test('un comprobante con un tipo de archivo no permitido se rechaza', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Flete',
            'variable_expense_category_id' => $category->id,
            'amount' => '5000',
            'expense_date' => '2026-05-01',
            'receipt' => UploadedFile::fake()->create('ticket.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('receipt');

    expect($tenant->variableExpenses()->count())->toBe(0);
});

test('una foto grande se achica antes de guardarse', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Ferretería',
            'variable_expense_category_id' => $category->id,
            'amount' => '9000',
            'expense_date' => '2026-05-01',
            'receipt' => UploadedFile::fake()->image('grande.jpg', 4000, 3000),
        ]);

    $path = $tenant->variableExpenses()->first()->receipt_image_path;
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));

    expect(max($width, $height))->toBeLessThanOrEqual(1600);
});

test('editar adjuntando un comprobante nuevo borra el anterior', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('viejo.jpg', 400, 300),
    ]);

    $expense = $tenant->variableExpenses()->first();
    $oldPath = $expense->receipt_image_path;

    $this->actingAs($user)->put(route('variable-expenses.update', $expense), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('nuevo.jpg', 400, 300),
    ])->assertRedirect(route('variable-expenses.index'));

    $newPath = $expense->fresh()->receipt_image_path;

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('local')->assertExists($newPath);
    Storage::disk('local')->assertMissing($oldPath);
});

test('editar sin adjuntar archivo conserva el comprobante', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $expense = $tenant->variableExpenses()->first();
    $path = $expense->receipt_image_path;

    $this->actingAs($user)->put(route('variable-expenses.update', $expense), [
        'name' => 'Flete corregido',
        'variable_expense_category_id' => $category->id,
        'amount' => '6000',
        'expense_date' => '2026-05-01',
    ])->assertRedirect(route('variable-expenses.index'));

    expect($expense->fresh()->receipt_image_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
});

test('eliminar un gasto borra su comprobante', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $expense = $tenant->variableExpenses()->first();
    $path = $expense->receipt_image_path;

    $this->actingAs($user)
        ->delete(route('variable-expenses.destroy', $expense))
        ->assertRedirect(route('variable-expenses.index'));

    Storage::disk('local')->assertMissing($path);
});

test('el comprobante se sirve a través de la app', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $expense = $tenant->variableExpenses()->first();

    $this->actingAs($user)
        ->get(route('variable-expenses.receipt', $expense))
        ->assertOk();
});

test('la ruta del comprobante responde 404 si el gasto no tiene uno', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $expense = VariableExpense::factory()->for($tenant)->create([
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.receipt', $expense))
        ->assertNotFound();
});

test('un viewer del mismo negocio puede ver el comprobante', function () {
    [$owner, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($owner)->post(route('variable-expenses.store'), [
        'name' => 'Flete',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $viewer = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $viewer->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($viewer)
        ->get(route('variable-expenses.receipt', $tenant->variableExpenses()->first()))
        ->assertOk();
});

test('no se puede ver el comprobante de un gasto de otro negocio', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    [$otherUser, $otherTenant, $otherCategory] = ownerForVariableExpense();

    $this->actingAs($otherUser)->post(route('variable-expenses.store'), [
        'name' => 'Flete ajeno',
        'variable_expense_category_id' => $otherCategory->id,
        'amount' => '5000',
        'expense_date' => '2026-05-01',
        'receipt' => UploadedFile::fake()->image('ticket.jpg', 400, 300),
    ]);

    $otherExpense = $otherTenant->variableExpenses()->first();

    $this->actingAs($user)
        ->get(route('variable-expenses.receipt', $otherExpense))
        ->assertNotFound();
});
