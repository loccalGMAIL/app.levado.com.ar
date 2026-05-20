<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'tenant_id',
        'target_type',
        'target_id',
        'action',
        'payload',
        'was_impersonating',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'was_impersonating' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
