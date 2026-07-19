<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta del feed del negocio. Tenant-scoped; sólo la escribe NotificationService.
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'severity',
        'title',
        'body',
        'action_url',
        'subject_type',
        'subject_id',
        'dedupe_key',
        'meta',
        'read_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'meta' => 'array',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** Vigentes: sin resolver. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
