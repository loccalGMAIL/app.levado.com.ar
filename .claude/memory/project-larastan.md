---
name: project-larastan
description: Larastan v3 nivel 5 con baseline de 9 errores; cómo correrlo y qué quedó pendiente
metadata:
  type: project
---

`phpstan.neon` (nivel 5, `paths: [app]`, `parseModelCastsMethod: true`) incluye `phpstan-baseline.neon`. Se corre con `composer analyse`. Rama `v0.14.1-larastan`.

Pasó de 496 errores a 9 en el baseline: genéricos en relaciones, `parseModelCastsMethod`, `@property-read` de atributos de `withCount()`/`selectRaw()`, `?->` redundante antes de `??`, tipos de colecciones.

**Why:** el código nuevo se chequea ya; el baseline sólo guarda lo que no se pudo arreglar sin cambiar comportamiento.
**How to apply:** no agregar entradas al baseline: arreglar el código. Los 9 restantes: `Invitation::isExpired` y `ProductPrice::policyPayload` (chequeos defensivos de nulos), `FixedCostHistory` (Carbon\Carbon vs Illuminate\Support\Carbon), `ProductionOrderSheets`, `ProductionService`, `CreateProductsFromRecipes`, `VerifyEmailController` (User no implementa `MustVerifyEmail`).

Tests: la suite corre completa (`php artisan test --compact`, ~1090 tests, ~1 min). Los helpers compartidos entre archivos viven en `tests/Pest.php`.
