<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Http\Requests\UpdateAlertSettingsRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AlertSettingsController extends Controller
{
    /**
     * Defaults de la config de alertas (tenant_settings, prefijo alerts.*).
     */
    private const DEFAULTS = [
        'low_stock_enabled' => true,
        'cost_spike_enabled' => true,
        'cost_spike_threshold_pct' => '15',
        'stale_cost_enabled' => true,
        'stale_cost_days' => '60',
        'unapplied_purchase_enabled' => true,
    ];

    public function edit(): View
    {
        $tenant = app(Tenant::class);

        $settings = [
            'low_stock_enabled' => $this->bool($tenant, NotificationType::LowStock),
            'cost_spike_enabled' => $this->bool($tenant, NotificationType::CostSpike),
            'cost_spike_threshold_pct' => $tenant->getSetting('alerts.cost_spike.threshold_pct', self::DEFAULTS['cost_spike_threshold_pct']),
            'stale_cost_enabled' => $this->bool($tenant, NotificationType::StaleCost),
            'stale_cost_days' => $tenant->getSetting('alerts.stale_cost.days', self::DEFAULTS['stale_cost_days']),
            'unapplied_purchase_enabled' => $this->bool($tenant, NotificationType::UnappliedPurchase),
        ];

        return view('alerts.edit', compact('settings'));
    }

    public function update(UpdateAlertSettingsRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        $tenant->setSetting('alerts.low_stock.enabled', $request->boolean('low_stock_enabled') ? '1' : '0');
        $tenant->setSetting('alerts.cost_spike.enabled', $request->boolean('cost_spike_enabled') ? '1' : '0');
        $tenant->setSetting('alerts.cost_spike.threshold_pct', (string) $request->validated('cost_spike_threshold_pct'));
        $tenant->setSetting('alerts.stale_cost.enabled', $request->boolean('stale_cost_enabled') ? '1' : '0');
        $tenant->setSetting('alerts.stale_cost.days', (string) $request->validated('stale_cost_days'));
        $tenant->setSetting('alerts.unapplied_purchase.enabled', $request->boolean('unapplied_purchase_enabled') ? '1' : '0');

        return back()->with('status', 'alerts-updated');
    }

    private function bool(Tenant $tenant, NotificationType $type): bool
    {
        return filter_var(
            $tenant->getSetting("alerts.{$type->settingKey()}.enabled", '1'),
            FILTER_VALIDATE_BOOLEAN,
        );
    }
}
