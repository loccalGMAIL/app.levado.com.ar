<?php

namespace App\Models;

use App\Enums\CondicionIva;
use App\Enums\MobileShortcut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Atributos agregados por withCount()/withExists() en las queries que los usan.
 *
 * @property-read int|null $total_users
 * @property-read int|null $active_users
 * @property-read int|null $pending_invitations
 * @property-read bool|null $fixed_costs_exists
 * @property-read bool|null $labor_types_exists
 * @property-read bool|null $ingredients_exists
 */
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
            'recurring_materialized_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<DeliveryPerson, $this>
     */
    public function deliveryPeople(): HasMany
    {
        return $this->hasMany(DeliveryPerson::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return HasMany<ProductionOrder, $this>
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /**
     * @return HasMany<RecurringProductionRequest, $this>
     */
    public function recurringProductionRequests(): HasMany
    {
        return $this->hasMany(RecurringProductionRequest::class);
    }

    /** @var Location|null Cache por instancia: defaultLocation() se llama una vez por ítem en los bucles de compra */
    private ?Location $cachedDefaultLocation = null;

    /**
     * Sucursal por defecto para operaciones de stock. Lazy, espejo de defaultPriceList():
     * cubre tenants existentes sin locations y tenants nuevos sin hooks de creación.
     */
    public function defaultLocation(): Location
    {
        return $this->cachedDefaultLocation ??= $this->locations()->where('is_default', true)->first()
            ?? $this->locations()->orderBy('id')->first()
            ?? $this->locations()->create(['name' => 'Casa Central', 'is_default' => true, 'active' => true]);
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    /**
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * @return HasMany<Packaging, $this>
     */
    public function packagings(): HasMany
    {
        return $this->hasMany(Packaging::class);
    }

    /**
     * @return HasMany<FixedCost, $this>
     */
    public function fixedCosts(): HasMany
    {
        return $this->hasMany(FixedCost::class);
    }

    /**
     * @return HasMany<FixedCostCategory, $this>
     */
    public function fixedCostCategories(): HasMany
    {
        return $this->hasMany(FixedCostCategory::class);
    }

    /**
     * @return HasMany<VariableExpense, $this>
     */
    public function variableExpenses(): HasMany
    {
        return $this->hasMany(VariableExpense::class);
    }

    /**
     * @return HasMany<VariableExpenseCategory, $this>
     */
    public function variableExpenseCategories(): HasMany
    {
        return $this->hasMany(VariableExpenseCategory::class);
    }

    /**
     * @return HasMany<LaborType, $this>
     */
    public function laborTypes(): HasMany
    {
        return $this->hasMany(LaborType::class);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ProductCategory, $this>
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /**
     * @return HasMany<Production, $this>
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    /**
     * @return HasMany<PriceList, $this>
     */
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

    /**
     * @return HasMany<TenantSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
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

    /** @var float|null Cache por instancia: dashboard y business piden total y overhead en la misma request */
    private ?float $cachedTotalFixedCosts = null;

    /**
     * Suma de gastos fijos activos del mes. Único dueño de esta consulta:
     * no repetir `fixedCosts()->active()->sum(...)` en controllers ni vistas.
     */
    public function totalFixedCosts(): float
    {
        return $this->cachedTotalFixedCosts ??= (float) $this->fixedCosts()->active()->sum('monthly_amount');
    }

    /**
     * Overhead por hora productiva (gastos fijos ÷ horas productivas del mes).
     * Es la fórmula central del costeo: cualquier cambio va acá y solo acá.
     * Devuelve null si el tenant no cargó horas productivas.
     */
    public function overheadPerHour(): ?float
    {
        $productiveHours = (int) $this->productive_hours_month;

        return $productiveHours > 0 ? $this->totalFixedCosts() / $productiveHours : null;
    }

    /** @var array<string, string>|null Cache por instancia de tenant_settings (evita una query por getSetting) */
    private ?array $cachedSettings = null;

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $this->cachedSettings ??= $this->settings()->pluck('value', 'key')->all();

        return $this->cachedSettings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->cachedSettings = null;
    }

    /**
     * Accesos de la barra inferior mobile, en orden. Si el setting falta, está
     * corrupto o no tiene exactamente MobileShortcut::SLOTS accesos válidos y
     * distintos, vuelve a los defaults.
     *
     * @return array<int, MobileShortcut>
     */
    public function mobileShortcuts(): array
    {
        $raw = (string) $this->getSetting('mobile_nav.shortcuts', '');

        $shortcuts = [];
        foreach (explode(',', $raw) as $value) {
            $shortcut = MobileShortcut::tryFrom(trim($value));
            if ($shortcut === null || in_array($shortcut, $shortcuts, true)) {
                return MobileShortcut::defaults();
            }
            $shortcuts[] = $shortcut;
        }

        return count($shortcuts) === MobileShortcut::SLOTS ? $shortcuts : MobileShortcut::defaults();
    }
}
