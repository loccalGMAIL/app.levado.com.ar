<?php

namespace App\Models;

use App\Enums\CondicionIva;
use Illuminate\Database\Eloquent\Builder;
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

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'productive_hours_month' => 'integer',
            'condicion_iva' => CondicionIva::class,
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Sucursal por defecto para operaciones de stock. Lazy, espejo de defaultPriceList():
     * cubre tenants existentes sin locations y tenants nuevos sin hooks de creación.
     */
    public function defaultLocation(): Location
    {
        return $this->locations()->where('is_default', true)->first()
            ?? $this->locations()->orderBy('id')->first()
            ?? $this->locations()->create(['name' => 'Casa Central', 'is_default' => true, 'active' => true]);
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

    public function variableExpenses(): HasMany
    {
        return $this->hasMany(VariableExpense::class);
    }

    public function variableExpenseCategories(): HasMany
    {
        return $this->hasMany(VariableExpenseCategory::class);
    }

    public function laborTypes(): HasMany
    {
        return $this->hasMany(LaborType::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function defaultPriceList(): PriceList
    {
        return $this->priceLists()->firstOrCreate(
            ['is_default' => true],
            ['name' => 'General', 'active' => true],
        );
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

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
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
