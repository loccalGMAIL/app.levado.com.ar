---
name: domain-model-articulos
description: "ADR del modelo de dominio Insumos vs Artículos: el Artículo es dueño único de costo y precio; roadmap de centralización (rama v0.13.0)"
metadata:
  type: project
---

# Modelo de dominio: Insumos vs Artículos (ADR, jul 2026)

Ratificado con el usuario antes de reestructurar Producción. Ver [[feature-articulos-produccion]], [[project-roadmap]].

## Decisión
Separar **Insumos** (lo que se compra para consumir en producción; parte de las recetas; no se venden)
de **Artículos** (lo único comercializable: elaborados vía Producción o de reventa), y **centralizar toda
la lógica comercial (costo + precio) en el Artículo**. Compras, Producción, Stock y Ventas consumen esa
información sin duplicar reglas.

- **Evolucionar** el modelo actual, NO unificar a un "Item master" único (rechazado por riesgo). Insumos =
  `Ingredient` + `Packaging`; Artículos = `Product` (`ProductType` manufactured|resale); Stock único vía `CatalogItemType`.
- **Artículo = agregado raíz comercial** (`Product`), único dueño de:
  - **Costo vigente** `Product::currentCost()`: elaborado → cache `recipe.unit_cost` (mantenido por
    `RecipeCostPropagator`; insumos+MO+sub-recetas, **sin overhead**); reventa → `cost_per_unit` (último costo, de Compras).
  - **Precio** (futuro): `precioPara(lista)` con política (manual/margen/recargo). Hoy el precio del elaborado
    vive en la Receta (`recipe_prices`) — **se decidió moverlo al Artículo** (Fase 2).
- **Receta = BOM puro** de un elaborado (1—1). Semielaborados siguen siendo Recetas intermedias (phantom, no Artículos).
- El **cómputo** del costo elaborado ya vive en `RecipeCostCalculator`/`RecipeCostPropagator`; el Artículo solo **expone** la lectura.
- No existe módulo de **Ventas** todavía (greenfield). El Dashboard de rentabilidad hoy es **receta-céntrico**
  (`recipes.unit_cost` + overhead vs `recipe_prices`) — se repuntará al Artículo en Fase 2.

## Roadmap de centralización
- **P1 ✅ (hecho)**: `Product::currentCost()` + `currentCostSource()` (extrae la regla que estaba inline en
  `products/index`); se usa para **valuar elaborados** en `/stock` (index + kardex; antes valuaban 0) a nivel de
  **lectura** (sin tocar el ledger). `StockController` eager-loada `recipe` para no lazy-loadear. `ProductCostTest` (5). 566 tests.
- **P2 🔶 (en curso)**: mover pricing Receta→Artículo. Decisiones: margen contra **costo total** (`Product::fullCost()` =
  currentCost + prorrateo de overhead; reventa sin overhead); **quitar el campo precio del *form* de receta**; **mantener
  la edición inline** de precio (Dashboard/`/recipes`/detalle) pero repuntada al artículo. Hecho hasta ahora:
  (1) `Product::currentPrice()`/`fullCost()` + migración backfill `recipe_prices`→`product_prices` (318 precios);
  (2) el **catálogo `/products` muestra costo/precio/margen** (solo lectura), leyendo el precio de la **fuente viva**
  (recipe_prices para elaborado vía su receta, product_prices para reventa) para ser consistente con el Dashboard sin
  split-brain. **Falta el switch coordinado**: superficie de edición unificada en el Artículo + repuntar Dashboard/recipes
  a `product_prices` + retirar escritura de `recipe_prices`/`RecipePriceController` + quitar el campo del form de receta.
- **P3 🔲**: políticas de precio (manual/margen/recargo) + métodos de costeo de reventa (promedio ponderado) configurables.
- **P4 🔲**: reestructuración del Módulo de Producción sobre este modelo. Luego Ventas.

## Notas de diseño
- `currentCost()` es el **costo estándar** del artículo (para valuar existencias); el costo del **evento** de
  producción (`productions.total_cost`, consumo físico) queda aparte a propósito.
- Overhead de gastos fijos: excluido de `currentCost()`; si el pricing lo necesita, se decide en P2.
- Unificar `Ingredient`+`Packaging` en un solo "Insumo": posible mejora futura, baja prioridad, fuera de alcance.
