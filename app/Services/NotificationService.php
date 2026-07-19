<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Ingredient;
use App\Models\IngredientPriceLog;
use App\Models\Notification;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockLevel;
use App\Models\Tenant;

/**
 * Único punto de escritura del feed de alertas (tabla notifications).
 *
 * Dos naturalezas:
 *  - De estado (low stock / costo desactualizado / compras sin imputar): se
 *    RECONCILIAN al abrir el dashboard o la página de alertas — se crean las que
 *    faltan y se resuelven las que ya no aplican. Idempotentes por dedupe_key.
 *  - De evento (salto de costo): se registran en el instante de la compra.
 */
class NotificationService
{
    /**
     * Reconcilia las 3 alertas de estado del tenant respetando los toggles y
     * umbrales de tenant_settings. Un tipo apagado resuelve sus alertas vivas.
     */
    public function syncStateAlerts(Tenant $tenant): void
    {
        $this->syncLowStock($tenant);
        $this->syncStaleCost($tenant);
        $this->syncUnappliedPurchases($tenant);
    }

    private function syncLowStock(Tenant $tenant): void
    {
        $type = NotificationType::LowStock;

        if (! $this->enabled($tenant, $type)) {
            $this->resolveMissing($tenant, $type, []);

            return;
        }

        $levels = StockLevel::where('tenant_id', $tenant->id)->get()
            ->filter(fn (StockLevel $level) => $level->hasAlert());

        $activeKeys = [];

        foreach ($levels as $level) {
            $item = $level->stockable_type === 'ingredient'
                ? Ingredient::find($level->stockable_id)
                : Packaging::find($level->stockable_id);

            if (! $item) {
                continue;
            }

            $key = "low_stock:{$level->stockable_type}:{$level->stockable_id}";
            $activeKeys[] = $key;

            $qty = (float) $level->quantity;
            $unit = $item->unit->short();
            $body = $level->isNegative()
                ? "Stock negativo: {$this->num($qty)} {$unit}."
                : "Quedan {$this->num($qty)} {$unit} (mínimo {$this->num((float) $level->min_quantity)} {$unit}).";

            $this->raise(
                tenant: $tenant,
                type: $type,
                dedupeKey: $key,
                title: "Stock bajo: {$item->name}",
                body: $body,
                actionUrl: route('stock.show', ['type' => $level->stockable_type, 'id' => $level->stockable_id]),
                subjectType: $level->stockable_type,
                subjectId: $level->stockable_id,
            );
        }

        $this->resolveMissing($tenant, $type, $activeKeys);
    }

    private function syncStaleCost(Tenant $tenant): void
    {
        $type = NotificationType::StaleCost;

        if (! $this->enabled($tenant, $type)) {
            $this->resolveMissing($tenant, $type, []);

            return;
        }

        $days = max(1, (int) $tenant->getSetting('alerts.stale_cost.days', '60'));
        $threshold = now()->subDays($days);

        $ingredients = Ingredient::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->select('ingredients.*')
            ->selectSub(
                IngredientPriceLog::selectRaw('max(recorded_at)')
                    ->whereColumn('ingredient_id', 'ingredients.id'),
                'last_cost_at',
            )
            ->get();

        $activeKeys = [];

        foreach ($ingredients as $ingredient) {
            $lastCostAt = $ingredient->last_cost_at ?? $ingredient->created_at;

            if ($lastCostAt >= $threshold) {
                continue;
            }

            $key = "stale_cost:ingredient:{$ingredient->id}";
            $activeKeys[] = $key;

            $this->raise(
                tenant: $tenant,
                type: $type,
                dedupeKey: $key,
                title: "Costo sin actualizar: {$ingredient->name}",
                body: "El costo no se actualiza hace más de {$days} días.",
                actionUrl: route('ingredients.index', ['search' => $ingredient->name]),
                subjectType: 'ingredient',
                subjectId: $ingredient->id,
            );
        }

        $this->resolveMissing($tenant, $type, $activeKeys);
    }

    private function syncUnappliedPurchases(Tenant $tenant): void
    {
        $type = NotificationType::UnappliedPurchase;

        if (! $this->enabled($tenant, $type)) {
            $this->resolveMissing($tenant, $type, []);

            return;
        }

        $purchases = Purchase::where('tenant_id', $tenant->id)
            ->whereHas('lines', fn ($q) => $q->whereNull('cost_applied_at'))
            ->withCount(['lines as pending_lines_count' => fn ($q) => $q->whereNull('cost_applied_at')])
            ->with('supplier')
            ->get();

        $activeKeys = [];

        foreach ($purchases as $purchase) {
            $key = "unapplied_purchase:{$purchase->id}";
            $activeKeys[] = $key;

            $pending = (int) $purchase->pending_lines_count;
            $label = trim(($purchase->supplier?->name ?? 'Compra').' '.($purchase->invoice_number ? "#{$purchase->invoice_number}" : ''));

            $this->raise(
                tenant: $tenant,
                type: $type,
                dedupeKey: $key,
                title: "Compra sin imputar: {$label}",
                body: $pending === 1
                    ? '1 renglón pendiente de asociar o imputar.'
                    : "{$pending} renglones pendientes de asociar o imputar.",
                actionUrl: route('purchases.match', $purchase),
                subjectType: 'purchase',
                subjectId: $purchase->id,
            );
        }

        $this->resolveMissing($tenant, $type, $activeKeys);
    }

    /**
     * Alerta de evento: el costo de un ítem subió por encima del umbral al
     * imputar una compra. Dedup por línea para no duplicar al re-imputar.
     */
    public function raiseCostSpike(PurchaseLine $line, Ingredient|Packaging $item, float $oldCost, float $newCost): void
    {
        if ($oldCost <= 0) {
            return; // sin baseline previo no hay "salto"
        }

        $tenant = $line->purchase->tenant;

        if (! $this->enabled($tenant, NotificationType::CostSpike)) {
            return;
        }

        $threshold = (float) $tenant->getSetting('alerts.cost_spike.threshold_pct', '15');
        $pct = ($newCost - $oldCost) / $oldCost * 100;

        if ($pct < $threshold) {
            return;
        }

        $this->raise(
            tenant: $tenant,
            type: NotificationType::CostSpike,
            dedupeKey: "cost_spike:purchase_line:{$line->id}",
            title: "Salto de costo: {$item->name}",
            body: 'El costo subió '.$this->num($pct, 1)."% (de \${$this->num($oldCost)} a \${$this->num($newCost)}).",
            actionUrl: route('purchases.show', $line->purchase),
            subjectType: $line->isIngredient() ? 'ingredient' : 'packaging',
            subjectId: $item->id,
            meta: ['old_cost' => $oldCost, 'new_cost' => $newCost, 'pct' => round($pct, 2)],
        );
    }

    public function markRead(Notification $notification): void
    {
        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(Tenant $tenant): void
    {
        Notification::where('tenant_id', $tenant->id)
            ->whereNull('resolved_at')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function dismiss(Notification $notification): void
    {
        $notification->update(['resolved_at' => now(), 'read_at' => $notification->read_at ?? now()]);
    }

    private function enabled(Tenant $tenant, NotificationType $type): bool
    {
        return filter_var(
            $tenant->getSetting("alerts.{$type->settingKey()}.enabled", '1'),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * Crea la alerta si no hay una viva con la misma dedupe_key; si la hay,
     * refresca su texto. No revive una ya resuelta: si el estado recurre tras
     * resolverse, se crea una nueva (queda no leída de nuevo).
     *
     * @param  array<string, mixed>  $meta
     */
    private function raise(
        Tenant $tenant,
        NotificationType $type,
        string $dedupeKey,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): void {
        $existing = Notification::where('tenant_id', $tenant->id)
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
                'meta' => $meta ?: null,
            ]);

            return;
        }

        Notification::create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'severity' => $type->severity(),
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'dedupe_key' => $dedupeKey,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Resuelve las alertas vivas de un tipo cuya dedupe_key ya no está entre las
     * activas (el estado se recuperó, o el tipo se apagó → lista vacía).
     *
     * @param  array<int, string>  $activeKeys
     */
    private function resolveMissing(Tenant $tenant, NotificationType $type, array $activeKeys): void
    {
        Notification::where('tenant_id', $tenant->id)
            ->where('type', $type->value)
            ->whereNull('resolved_at')
            ->when(
                $activeKeys !== [],
                fn ($q) => $q->whereNotIn('dedupe_key', $activeKeys),
            )
            ->update(['resolved_at' => now()]);
    }

    private function num(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}
