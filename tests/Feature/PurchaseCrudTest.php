<?php

use App\Enums\TenantUserRole;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function ownerForPurchaseCrud(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('owner puede crear una compra manual sin comprobante', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice_number' => '0001-0001',
        ])
        ->assertRedirect();

    $purchase = $tenant->purchases()->firstOrFail();
    expect($purchase->invoice_image_path)->toBeNull();
});

test('crear una compra manual adjunta el comprobante', function () {
    Storage::fake('public');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->image('ticket.jpg', 800, 600),
        ])
        ->assertRedirect();

    $purchase = $tenant->purchases()->firstOrFail();
    expect($purchase->invoice_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($purchase->invoice_image_path);
});

test('un comprobante con formato inválido es rechazado', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->create('ticket.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('invoice');

    expect($tenant->purchases()->count())->toBe(0);
});

test('editar una compra reemplaza el comprobante y borra el anterior', function () {
    Storage::fake('public');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    Storage::disk('public')->put("purchases/{$tenant->id}/original.jpg", 'bytes-originales');
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
        'invoice_image_path' => "purchases/{$tenant->id}/original.jpg",
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->image('nuevo.jpg', 800, 600),
        ])
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->invoice_image_path)->not->toBe("purchases/{$tenant->id}/original.jpg");
    Storage::disk('public')->assertExists($purchase->invoice_image_path);
    Storage::disk('public')->assertMissing("purchases/{$tenant->id}/original.jpg");
});

test('editar una compra sin adjuntar archivo conserva el comprobante existente', function () {
    Storage::fake('public');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    Storage::disk('public')->put("purchases/{$tenant->id}/original.jpg", 'bytes-originales');
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
        'invoice_image_path' => "purchases/{$tenant->id}/original.jpg",
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'notes' => 'actualizado',
        ])
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->invoice_image_path)->toBe("purchases/{$tenant->id}/original.jpg");
    Storage::disk('public')->assertExists("purchases/{$tenant->id}/original.jpg");
});
