<?php

use App\Enums\TenantUserRole;
use App\Models\FixedCostLog;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VariableExpense;

function userForVariableExpense(TenantUserRole $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);
    $category = $tenant->variableExpenseCategories()->create(['name' => 'General']);

    return [$user, $tenant, $category];
}

function ownerForVariableExpense(): array
{
    return userForVariableExpense(TenantUserRole::Owner);
}

// --- CRUD ---

test('owner puede listar gastos variables', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'Reparación horno',
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('Reparación horno');
});

test('viewer puede ver la lista de gastos variables', function () {
    [$user, $tenant, $category] = userForVariableExpense(TenantUserRole::Viewer);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'Flete extraordinario',
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('Flete extraordinario');
});

test('owner puede crear un gasto variable', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Reparación heladera',
            'variable_expense_category_id' => $category->id,
            'amount' => '80000.00',
            'expense_date' => '2026-03-12',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    $expense = $tenant->variableExpenses()->where('name', 'Reparación heladera')->first();

    expect($expense)->not->toBeNull()
        ->and($expense->expense_date->format('Y-m-d'))->toBe('2026-03-12')
        ->and((float) $expense->amount)->toBe(80000.0);
});

test('admin puede crear un gasto variable', function () {
    [$user, $tenant, $category] = userForVariableExpense(TenantUserRole::Admin);

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Multa',
            'variable_expense_category_id' => $category->id,
            'amount' => '15000',
            'expense_date' => '2026-05-01',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->where('name', 'Multa')->exists())->toBeTrue();
});

test('viewer no puede crear gastos variables', function () {
    [$user, , $category] = userForVariableExpense(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Gas',
            'variable_expense_category_id' => $category->id,
            'amount' => '3000',
            'expense_date' => '2026-01-01',
        ])
        ->assertForbidden();
});

test('owner puede editar un gasto variable', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $expense = VariableExpense::factory()->for($tenant)->create([
        'name' => 'Original',
        'variable_expense_category_id' => $category->id,
        'expense_date' => '2026-02-01',
    ]);

    $this->actingAs($user)
        ->put(route('variable-expenses.update', $expense), [
            'name' => 'Editado',
            'variable_expense_category_id' => $category->id,
            'amount' => '9999.00',
            'expense_date' => '2026-04-20',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    $expense->refresh();

    expect($expense->name)->toBe('Editado')
        ->and($expense->expense_date->format('Y-m-d'))->toBe('2026-04-20')
        ->and((float) $expense->amount)->toBe(9999.0);
});

test('owner puede eliminar un gasto variable', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $expense = VariableExpense::factory()->for($tenant)->create([
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->delete(route('variable-expenses.destroy', $expense))
        ->assertRedirect(route('variable-expenses.index'));

    expect(VariableExpense::find($expense->id))->toBeNull();
});

test('viewer no puede eliminar gastos variables', function () {
    [$user] = userForVariableExpense(TenantUserRole::Viewer);

    $tenant = Tenant::factory()->create();
    $category = $tenant->variableExpenseCategories()->create(['name' => 'X']);
    $expense = VariableExpense::factory()->for($tenant)->create([
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->delete(route('variable-expenses.destroy', $expense))
        ->assertForbidden();

    expect(VariableExpense::find($expense->id))->not->toBeNull();
});

// --- Validación ---

test('nombre es obligatorio al crear gasto variable', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'variable_expense_category_id' => $category->id,
            'amount' => '1000',
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('name');
});

test('categoría es obligatoria al crear gasto variable', function () {
    [$user] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Sin categoría',
            'amount' => '1000',
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('variable_expense_category_id');
});

test('fecha es obligatoria al crear gasto variable', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Sin fecha',
            'variable_expense_category_id' => $category->id,
            'amount' => '1000',
        ])
        ->assertSessionHasErrors('expense_date');
});

test('monto es obligatorio y no puede ser negativo', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Sin monto',
            'variable_expense_category_id' => $category->id,
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Monto negativo',
            'variable_expense_category_id' => $category->id,
            'amount' => '-1',
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('amount');
});

// --- Proveedor ---

test('owner puede crear un gasto variable con proveedor', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora Sur']);

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Bolsas',
            'variable_expense_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'amount' => '15000',
            'expense_date' => '2026-07-15',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->where('name', 'Bolsas')->first()->supplier_id)->toBe($supplier->id);
});

test('el proveedor es opcional', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Multa',
            'variable_expense_category_id' => $category->id,
            'amount' => '5000',
            'expense_date' => '2026-07-15',
        ])
        ->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenses()->where('name', 'Multa')->first()->supplier_id)->toBeNull();
});

test('el listado muestra el proveedor del gasto', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora Norte']);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'Bolsas',
        'variable_expense_category_id' => $category->id,
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('Distribuidora Norte');
});

test('el filtro por proveedor acota el listado y el total del período', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplierA = Supplier::factory()->for($tenant)->create(['name' => 'ProveedorA']);
    $supplierB = Supplier::factory()->for($tenant)->create(['name' => 'ProveedorB']);

    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoDeA',
        'variable_expense_category_id' => $category->id,
        'supplier_id' => $supplierA->id,
        'amount' => '1000.00',
    ]);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoDeB',
        'variable_expense_category_id' => $category->id,
        'supplier_id' => $supplierB->id,
        'amount' => '700.00',
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index', ['supplier' => $supplierB->id]))
        ->assertOk()
        ->assertSee('GastoDeB')
        ->assertDontSee('GastoDeA')
        ->assertViewHas('totalPeriod', fn ($total) => (float) $total === 700.0);
});

test('aislamiento: no se puede asignar un proveedor de otro tenant', function () {
    [$user, , $category] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Con proveedor ajeno',
            'variable_expense_category_id' => $category->id,
            'supplier_id' => $otherSupplier->id,
            'amount' => '1000',
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('editar un gasto cuyo proveedor está inactivo no borra el proveedor', function () {
    // Regresión del bug que sí tiene ingredients/modals/edit.blade.php: si el select
    // lista sólo proveedores activos, el de un proveedor dado de baja no matchea, el
    // select cae en «— Ninguno —» y guardar cualquier otro campo pierde el dato.
    [$user, $tenant, $category] = ownerForVariableExpense();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Baja', 'active' => false]);
    $expense = VariableExpense::factory()->for($tenant)->create([
        'variable_expense_category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'amount' => '1000.00',
    ]);

    // El modal de edición debe ofrecer la opción del proveedor inactivo, marcada como tal.
    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('Proveedor Baja')
        ->assertSee('(inactivo)');

    // Y guardar sólo el monto no puede tirar el proveedor.
    $this->actingAs($user)
        ->put(route('variable-expenses.update', $expense), [
            'name' => $expense->name,
            'variable_expense_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'amount' => '2500.00',
            'expense_date' => $expense->expense_date->format('Y-m-d'),
        ])
        ->assertRedirect(route('variable-expenses.index'));

    expect($expense->refresh()->supplier_id)->toBe($supplier->id)
        ->and((float) $expense->amount)->toBe(2500.0);
});

test('un proveedor inactivo no se ofrece al crear un gasto nuevo', function () {
    [$user, $tenant] = ownerForVariableExpense();
    Supplier::factory()->for($tenant)->create(['name' => 'ProveedorActivo', 'active' => true]);
    Supplier::factory()->for($tenant)->create(['name' => 'ProveedorInactivo', 'active' => false]);

    $response = $this->actingAs($user)->get(route('variable-expenses.index'))->assertOk();

    // Ambos existen en la página (el de edición y el filtro los listan), pero el select
    // de alta sólo debe ofrecer el activo.
    $html = $response->getContent();
    expect($html)->toContain('id="create_ve_supplier"');

    $createSelect = str($html)->after('id="create_ve_supplier"')->before('</select>')->toString();

    expect($createSelect)->toContain('ProveedorActivo')
        ->and($createSelect)->not->toContain('ProveedorInactivo');
});

// --- Ausencias deliberadas de diseño ---

test('crear un gasto variable no genera historial de precios', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)->post(route('variable-expenses.store'), [
        'name' => 'Reparación',
        'variable_expense_category_id' => $category->id,
        'amount' => '5000',
        'expense_date' => '2026-01-01',
    ]);

    expect(FixedCostLog::count())->toBe(0);
});

test('los gastos variables no tienen toggle de activo', function () {
    expect(fn () => route('variable-expenses.toggle-active'))
        ->toThrow(InvalidArgumentException::class);
});

// --- Filtros ---

test('los filtros de fecha acotan el listado y el total del período', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoDeEnero',
        'variable_expense_category_id' => $category->id,
        'amount' => '1000.00',
        'expense_date' => '2026-01-15',
    ]);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoDeMarzo',
        'variable_expense_category_id' => $category->id,
        'amount' => '500.00',
        'expense_date' => '2026-03-15',
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertSee('GastoDeMarzo')
        ->assertDontSee('GastoDeEnero')
        ->assertViewHas('totalPeriod', fn ($total) => (float) $total === 500.0);
});

test('el filtro de categoría acota el listado', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    $otherCategory = $tenant->variableExpenseCategories()->create(['name' => 'Otra']);

    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoCategoriaA',
        'variable_expense_category_id' => $category->id,
    ]);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'GastoCategoriaB',
        'variable_expense_category_id' => $otherCategory->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index', ['category' => $otherCategory->id]))
        ->assertOk()
        ->assertSee('GastoCategoriaB')
        ->assertDontSee('GastoCategoriaA');
});

// --- Categorías ---

test('owner puede crear una categoría de gasto variable', function () {
    [$user, $tenant] = ownerForVariableExpense();

    $this->actingAs($user)
        ->post(route('variable-expense-categories.store'), ['name' => 'Reparaciones'])
        ->assertRedirect(route('variable-expenses.index'));

    expect($tenant->variableExpenseCategories()->where('name', 'Reparaciones')->exists())->toBeTrue();
});

test('crear categoría por json devuelve 201 con id y nombre', function () {
    [$user] = ownerForVariableExpense();

    $response = $this->actingAs($user)
        ->postJson(route('variable-expense-categories.store'), ['name' => 'Imprevistos']);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'name'])
        ->assertJson(['name' => 'Imprevistos']);
});

test('owner puede renombrar una categoría de gasto variable', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->put(route('variable-expense-categories.update', $category), ['name' => 'Renombrada'])
        ->assertRedirect(route('variable-expenses.index'));

    expect($category->refresh()->name)->toBe('Renombrada');
});

test('se puede eliminar una categoría sin gastos', function () {
    [$user, , $category] = ownerForVariableExpense();

    $this->actingAs($user)
        ->delete(route('variable-expense-categories.destroy', $category))
        ->assertRedirect(route('variable-expenses.index'));

    expect($category->exists())->toBeFalse();
});

test('no se puede eliminar una categoría con gastos asignados', function () {
    [$user, $tenant, $category] = ownerForVariableExpense();
    VariableExpense::factory()->for($tenant)->create([
        'variable_expense_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->delete(route('variable-expense-categories.destroy', $category))
        ->assertSessionHas('category_error');

    expect($tenant->variableExpenseCategories()->whereKey($category->id)->exists())->toBeTrue();
});

// --- Aislamiento ---

test('aislamiento: gastos variables de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->variableExpenseCategories()->create(['name' => 'Ext']);
    VariableExpense::factory()->for($otherTenant)->create([
        'name' => 'AjenoVE',
        'variable_expense_category_id' => $otherCategory->id,
    ]);

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertDontSee('AjenoVE');
});

test('aislamiento: no se puede editar gasto variable de otro tenant', function () {
    [$user, , $category] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->variableExpenseCategories()->create(['name' => 'Ext']);
    $other = VariableExpense::factory()->for($otherTenant)->create([
        'variable_expense_category_id' => $otherCategory->id,
    ]);

    $this->actingAs($user)
        ->put(route('variable-expenses.update', $other), [
            'name' => 'Hack',
            'variable_expense_category_id' => $category->id,
            'amount' => '1',
            'expense_date' => date('Y-m-d'),
        ])
        ->assertForbidden();
});

test('aislamiento: no se puede eliminar gasto variable de otro tenant', function () {
    [$user] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->variableExpenseCategories()->create(['name' => 'Ext']);
    $other = VariableExpense::factory()->for($otherTenant)->create([
        'variable_expense_category_id' => $otherCategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('variable-expenses.destroy', $other))
        ->assertForbidden();

    expect(VariableExpense::find($other->id))->not->toBeNull();
});

test('aislamiento: no se puede asignar una categoría de otro tenant', function () {
    [$user] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->variableExpenseCategories()->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->post(route('variable-expenses.store'), [
            'name' => 'Con categoría ajena',
            'variable_expense_category_id' => $otherCategory->id,
            'amount' => '1000',
            'expense_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('variable_expense_category_id');
});

test('aislamiento: no se puede eliminar categoría de gasto variable de otro tenant', function () {
    [$user] = ownerForVariableExpense();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->variableExpenseCategories()->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->delete(route('variable-expense-categories.destroy', $otherCategory))
        ->assertForbidden();
});
