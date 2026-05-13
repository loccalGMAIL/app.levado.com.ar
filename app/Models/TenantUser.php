<?php

namespace App\Models;

use App\Enums\TenantUserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUser extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'active',
    ];

    protected $casts = [
        'role' => TenantUserRole::class,
        'active' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $primaryKey = null;

    public $incrementing = false;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
