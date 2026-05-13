<?php

namespace Database\Seeders;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant interno del equipo Levado (super admin)
        $levadoHq = Tenant::firstOrCreate(
            ['name' => 'Levado HQ'],
            [
                'country' => 'AR',
                'currency' => 'ARS',
                'productive_hours_month' => 160,
                'active' => true,
            ]
        );

        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@levado.com'],
            [
                'name' => 'Admin Levado',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        TenantUser::firstOrCreate(
            ['tenant_id' => $levadoHq->id, 'user_id' => $superAdmin->id],
            ['role' => TenantUserRole::SuperAdmin->value, 'active' => true]
        );

        // Tenant demo para cliente
        $demoTenant = Tenant::firstOrCreate(
            ['name' => 'Panadería Demo'],
            [
                'country' => 'AR',
                'currency' => 'ARS',
                'productive_hours_month' => 160,
                'active' => true,
            ]
        );

        $owner = User::firstOrCreate(
            ['email' => 'owner@demo.com'],
            [
                'name' => 'Owner Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        TenantUser::firstOrCreate(
            ['tenant_id' => $demoTenant->id, 'user_id' => $owner->id],
            ['role' => TenantUserRole::Owner->value, 'active' => true]
        );

        $this->command->info('Super admin: admin@levado.com / password (tenant: Levado HQ)');
        $this->command->info('Owner demo: owner@demo.com / password (tenant: Panadería Demo)');
    }
}
