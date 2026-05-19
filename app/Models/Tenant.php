<?php

namespace App\Models;

use App\Enums\CondicionIva;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'razon_social',
        'cuit',
        'condicion_iva',
        'country',
        'currency',
        'logo_path',
        'productive_hours_month',
        'active',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'productive_hours_month' => 'integer',
        'condicion_iva' => CondicionIva::class,
        'onboarding_completed_at' => 'datetime',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(Packaging::class);
    }

    public function fixedCosts(): HasMany
    {
        return $this->hasMany(FixedCost::class);
    }

    public function fixedCostCategories(): HasMany
    {
        return $this->hasMany(FixedCostCategory::class);
    }

    public function laborTypes(): HasMany
    {
        return $this->hasMany(LaborType::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'active', 'created_at'])
            ->wherePivot('active', true);
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null
            || $this->recipes()->exists();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
