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

        // scopeInAlert() (equivalente en SQL de hasAlert()): solo trae lo que
        // está en alerta, no todo el stock del tenant para descartar en PHP.
        $levels = StockLevel::where('tenant_id', $tenant->id)->inAlert()->get();

        // find() por fila → 2 queries agrupadas por tipo (ingredient/packaging).
        $byType = $levels->groupBy('stockable_type');
        $items = [
            'ingredient' => Ingredient::whereIn('id', $byType->get('ingredient', collect())->pluck('stockable_id'))->get()->keyBy('id'),
            'packaging' => Packaging::whereIn('id', $byType->get('packaging', collect())->pluck('stockable_id'))->get()->keyBy('id'),
        ];

        $candidates = [];
        $activeKeys = [];

        foreach ($levels as $level) {
            $item = $items[$level->stockable_type][$level->stockable_id] ?? null;

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

            $candidates[] = [
                'dedupe_key' => $key,
                'title' => "Stock bajo: {$item->name}",
                'body' => $body,
                'action_url' => route('stock.show', ['type' => $level->stockable_type, 'id' => $level->stockable_id]),
                'subject_type' => $level->stockable_type,
                'subject_id' => $level->stockable_id,
            ];
        }

        $this->raiseMany($tenant, $type, $candidates);
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

        // leftJoinSub: la agregación max(recorded_at) se hace una vez sobre el
        // log completo, y el descarte de "no está vencido" ocurre en la BD en
        // vez de traer todos los ingredientes activos para filtrar en PHP.
        $lastLogs = IngredientPriceLog::selectRaw('ingredient_id, max(recorded_at) as last_cost_at')
            ->groupBy('ingredient_id');

        $ingredients = Ingredient::where('ingredients.tenant_id', $tenant->id)
            ->where('ingredients.active', true)
            ->select('ingredients.*')
            ->leftJoinSub($lastLogs, 'l', 'l.ingredient_id', '=', 'ingredients.id')
            ->whereRaw('coalesce(l.last_cost_at, ingredients.created_at) < ?', [$threshold])
            ->get();

        $candidates = [];
        $activeKeys = [];

        foreach ($ingredients as $ingredient) {
            $key = "stale_cost:ingredient:{$ingredient->id}";
            $activeKeys[] = $key;

            $candidates[] = [
                'dedupe_key' => $key,
                'title' => "Costo sin actualizar: {$ingredient->name}",
                'body' => "El costo no se actualiza hace más de {$days} días.",
                'action_url' => route('ingredients.index', ['search' => $ingredient->name]),
                'subject_type' => 'ingredient',
                'subject_id' => $ingredient->id,
            ];
        }

        $this->raiseMany($tenant, $type, $candidates);
        $this->resolveMissing($tenant, $type, $activeKeys);
    }

    private function syncUnappliedPurchases(Tenant $tenant): void
    {
        $type = NotificationType::UnappliedPurchase;

        if (! $this->enabled($tenant, $type)) {
            $this->resolveMissing($tenant, $type, []);

            return;
        }

        // Los renglones marcados como "no es un insumo" están resueltos: no imputan
        // costo, pero tampoco quedan pendientes, así que no deben mantener viva la alerta.
        // joinSub agrupado: filtra (INNER JOIN) y cuenta a la vez, en una sola
        // pasada sobre purchase_lines en vez de dos subconsultas correlacionadas.
        $pendingLines = PurchaseLine::query()
            ->selectRaw('purchase_id, count(*) as pending_lines_count')
            ->whereNull('cost_applied_at')
            ->whereNull('excluded_at')
            ->groupBy('purchase_id');

        $purchases = Purchase::where('purchases.tenant_id', $tenant->id)
            ->joinSub($pendingLines, 'pl', 'pl.purchase_id', '=', 'purchases.id')
            ->select('purchases.*', 'pl.pending_lines_count')
            ->with('supplier')
            ->get();

        $candidates = [];
        $activeKeys = [];

        foreach ($purchases as $purchase) {
            $key = "unapplied_purchase:{$purchase->id}";
            $activeKeys[] = $key;

            $pending = (int) $purchase->pending_lines_count;
            $label = trim(($purchase->supplier?->name ?? 'Compra').' '.($purchase->invoice_number ? "#{$purchase->invoice_number}" : ''));

            $candidates[] = [
                'dedupe_key' => $key,
                'title' => "Compra sin imputar: {$label}",
                'body' => $pending === 1
                    ? '1 renglón pendiente de asociar o imputar.'
                    : "{$pending} renglones pendientes de asociar o imputar.",
                'action_url' => route('purchases.match', $purchase),
                'subject_type' => 'purchase',
                'subject_id' => $purchase->id,
            ];
        }

        $this->raiseMany($tenant, $type, $candidates);
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
     * Versión en lote de raise(), para los tres reconciliadores de estado que
     * la llaman dentro de un foreach: una lectura de las alertas vivas del
     * tipo + un insert() para las nuevas, en vez de un SELECT y un
     * UPDATE/INSERT por candidata. No revive alertas resueltas: si no hay una
     * viva con esa dedupe_key, se inserta una nueva.
     *
     * @param  array<int, array{dedupe_key: string, title: string, body?: ?string, action_url?: ?string, subject_type?: ?string, subject_id?: ?int, meta?: array<string, mixed>}>  $candidates
     */
    private function raiseMany(Tenant $tenant, NotificationType $type, array $candidates): void
    {
        if ($candidates === []) {
            return;
        }

        $live = Notification::where('tenant_id', $tenant->id)
            ->where('type', $type->value)
            ->whereNull('resolved_at')
            ->get()
            ->keyBy('dedupe_key');

        $toInsert = [];
        $now = now();

        foreach ($candidates as $candidate) {
            $meta = $candidate['meta'] ?? [];
            $existing = $live->get($candidate['dedupe_key']);

            if ($existing === null) {
                $toInsert[] = [
                    'tenant_id' => $tenant->id,
                    'type' => $type->value,
                    'severity' => $type->severity(),
                    'title' => $candidate['title'],
                    'body' => $candidate['body'] ?? null,
                    'action_url' => $candidate['action_url'] ?? null,
                    'subject_type' => $candidate['subject_type'] ?? null,
                    'subject_id' => $candidate['subject_id'] ?? null,
                    'dedupe_key' => $candidate['dedupe_key'],
                    'meta' => $meta !== [] ? json_encode($meta) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            $existing->fill([
                'title' => $candidate['title'],
                'body' => $candidate['body'] ?? null,
                'action_url' => $candidate['action_url'] ?? null,
                'meta' => $meta ?: null,
            ]);

            // Evita un UPDATE cuando el texto no cambió — en la práctica, casi siempre.
            if ($existing->isDirty()) {
                $existing->save();
            }
        }

        if ($toInsert !== []) {
            Notification::insert($toInsert);
        }
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
