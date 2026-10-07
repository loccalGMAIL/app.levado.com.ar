<?php

namespace App\Enums;

enum MobileShortcut: string
{
    case Recipes = 'recipes';
    case ProductionOrders = 'production_orders';
    case Reparto = 'reparto';
    case Products = 'products';
    case Purchases = 'purchases';
    case Suppliers = 'suppliers';
    case Stock = 'stock';
    case Ingredients = 'ingredients';
    case Packaging = 'packaging';
    case LaborTypes = 'labor_types';
    case Expenses = 'expenses';

    /** Cantidad de slots editables de la barra inferior (Inicio y Más son fijos). */
    public const SLOTS = 3;

    /**
     * Accesos de la barra cuando el tenant no configuró nada.
     *
     * @return array<int, self>
     */
    public static function defaults(): array
    {
        return [self::Recipes, self::Ingredients, self::Purchases];
    }

    public function label(): string
    {
        return match ($this) {
            self::Recipes => 'Recetas',
            self::ProductionOrders => 'Órdenes de producción',
            self::Reparto => 'Reparto',
            self::Products => 'Artículos',
            self::Purchases => 'Compras',
            self::Suppliers => 'Proveedores',
            self::Stock => 'Stock',
            self::Ingredients => 'Ingredientes',
            self::Packaging => 'Descartables',
            self::LaborTypes => 'Mano de Obra',
            self::Expenses => 'Gastos',
        };
    }

    /** Texto para la barra inferior (entra en `text-[10px]` con 5 slots). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::ProductionOrders => 'Órdenes',
            self::Packaging => 'Descartables',
            self::LaborTypes => 'Mano de obra',
            default => $this->label(),
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Recipes => 'recipes.index',
            self::ProductionOrders => 'production-orders.index',
            self::Reparto => 'reparto.clientes.index',
            self::Products => 'products.index',
            self::Purchases => 'purchases.index',
            self::Suppliers => 'suppliers.index',
            self::Stock => 'stock.index',
            self::Ingredients => 'ingredients.index',
            self::Packaging => 'packaging.index',
            self::LaborTypes => 'labor-types.index',
            self::Expenses => 'fixed-costs.index',
        };
    }

    /**
     * Patrones de `routeIs` que marcan el acceso como activo.
     *
     * @return array<int, string>
     */
    public function activePatterns(): array
    {
        return match ($this) {
            self::Recipes => ['recipes.*'],
            self::ProductionOrders => ['production-orders.*', 'production.*'],
            self::Reparto => ['reparto.*'],
            self::Products => ['products.*'],
            self::Purchases => ['purchases.*', 'credit-notes.*'],
            self::Suppliers => ['suppliers.*'],
            self::Stock => ['stock.*'],
            self::Ingredients => ['ingredients.*'],
            self::Packaging => ['packaging.*'],
            self::LaborTypes => ['labor-types.*'],
            self::Expenses => ['fixed-costs.*', 'variable-expenses.*'],
        };
    }

    /** Grupo del drawer "Más" (mismos títulos que el sidebar). */
    public function group(): string
    {
        return match ($this) {
            self::Recipes, self::ProductionOrders, self::Reparto => 'Producción',
            self::Products, self::Purchases, self::Suppliers, self::Stock => 'Existencias',
            self::Ingredients, self::Packaging, self::LaborTypes, self::Expenses => 'Costos',
        };
    }

    /** Gate requerido para ver el acceso, o null si lo ve cualquier rol. */
    public function ability(): ?string
    {
        return match ($this) {
            self::Reparto => 'manage-costs',
            default => null,
        };
    }

    /** Contenido SVG (heroicons outline, viewBox 24) del ícono. */
    public function iconPath(): string
    {
        return match ($this) {
            self::Recipes => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />',
            self::ProductionOrders => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />',
            self::Reparto => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />',
            self::Products, self::Ingredients => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
            self::Purchases => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />',
            self::Suppliers => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 18H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v3M8 10h8M8 14h4m4 4v-4m0 4h-4m4 0l-3-3" />',
            self::Stock => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />',
            self::Packaging => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4M12 3v18" />',
            self::LaborTypes => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />',
            self::Expenses => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
        };
    }
}
