<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSettings = [
            'price_alert_days' => '30',
            'min_margin_alert_pct' => '20',
        ];

        foreach (Tenant::all() as $tenant) {
            foreach ($defaultSettings as $key => $value) {
                $tenant->settings()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );
            }
        }

        $this->command->info('Settings por defecto aplicados a todos los tenants.');
    }
}
