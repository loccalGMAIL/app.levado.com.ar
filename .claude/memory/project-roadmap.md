---
name: project-roadmap
description: "Roadmap MVP v2.1 — etapas, estado y prioridades del proyecto Levado"
metadata: 
  node_type: memory
  type: project
  originSessionId: 07b581ae-f96f-4331-9543-aea46735b797
---

# Roadmap MVP v2.1 (Mayo 2026)

**Objetivo del MVP:** que el panadero sepa cuánto le cuesta fabricar cada producto.
**Stack:** Laravel 13 + Boost (MCP) + MySQL + Blade/Tailwind/Alpine.js

## Etapa 1 — Fundación Web ✅ COMPLETA (v0.2.0)
- 1.1 Setup inicial: ✅
- 1.2 Multi-tenancy: ✅
- 1.3 Auth y Roles: ✅
- 1.4 Mi negocio: ✅
- 1.5 Branding Levado: ✅
- 1.6 Coming soon site (levado.com.ar, HTML estático): ✅ Subido a GitHub, deploy pendiente
- 1.7 Cierre Etapa 1 (tests flujo completo, seeder demo, smoke test): ✅

## Backoffice de Administración ✅ B.1 COMPLETO (2026-05-14)
- Dashboard, gestión de tenants, impersonación, audit logs, vista de usuarios
- B.2 Backoffice SaaS: 🔲 No iniciado (prerequisito para apertura pública)

## Front — Restructuración ✅ COMPLETA (2026-05-14)
- Layout dos columnas: top nav + sidebar izquierdo context-aware

## Modelo de datos base ✅ COMPLETO (2026-05-14)
- CondicionIva enum, campos fiscales en tenants, locations/sucursales

## Etapa 2 — Módulo de Costos
- 2.1 Ingredientes: ✅ Completo (2026-05-14)
  - Unit enum, tabla ingredients, IngredientController CRUD + toggleActive
  - Gate manage-costs (owner+admin escriben, viewer lee)
  - Price log automático al crear/cambiar costo
  - 11 tests

- 2.1b Proveedores: ✅ Completo (2026-05-18)
  - Tabla suppliers (tenant_id, name, phone, email, notes, active)
  - SupplierController CRUD + toggleActive; store usa back() (funciona desde cualquier página)
  - Modal "quick-create" supplier accesible desde modales de ingredientes y packaging
  - Ingredientes vinculados a supplier_id + campo brand; price log con orderByDesc('id')
  - 8 tests

- 2.2 Packaging (Envases): ✅ Completo (2026-05-18)
  - Tabla packagings (tenant_id, name, brand, supplier_id nullable, cost_per_unit decimal 10,4, active)
  - Tabla packaging_price_logs (packaging_id, cost_per_unit, recorded_at; sin timestamps)
  - PackagingController CRUD + toggleActive + price log automático
  - Vista index con tabla, modales create/edit, botón quick-create supplier
  - 10 tests CRUD + 4 tests price log

- 2.3 Gastos Fijos: ✅ Completo (2026-05-18)
  - Tabla fixed_cost_categories (tenant_id, name) — per-tenant, gestionable desde modal
  - Tabla fixed_costs (tenant_id, fixed_cost_category_id nullable FK, name, monthly_amount decimal 10,2, active)
  - Tabla fixed_cost_logs (fixed_cost_id, monthly_amount, valid_from date; sin timestamps)
  - FixedCostController CRUD + toggleActive + log solo si cambia monthly_amount
  - FixedCostCategoryController (store/update/destroy con bloqueo si hay gastos asignados)
  - valid_from editable por usuario (permite cargar datos históricos)
  - Vista index: tabla ordenada, total activo al pie, botón "Categorías" + botón "Nuevo gasto"
  - Modal categorías: listado inline, edición inline Alpine, agregar nueva, eliminar con guard
  - Modal reopens tras operaciones de categorías via session('reopen_categories')
  - 20 tests (CRUD, roles, logs, categorías, aislamiento)
  - 106 tests en suite completa ✅

- 2.4 Tipos Mano de Obra: ✅ Completo (2026-05-18)
  - Tabla labor_types (tenant_id, name, hourly_rate decimal 10,2, active)
  - LaborTypeController CRUD + toggleActive; Gate manage-costs
  - 10 tests

- 2.5 Unit Converter: ✅ Completo (2026-05-18)
  - Servicio puro App\Services\UnitConverter
  - Dimensiones: peso (gr/kg), volumen (ml/L/cc), unidad (u)
  - compatible() + convert() via base normalization; null si incompatible
  - 16 tests unitarios

- 2.6 Recetas: ✅ Completo (2026-05-18)
  - Tablas: recipes, recipe_ingredient_lines (unit + UnitConverter), recipe_packaging_lines, recipe_labor_lines
  - RecipeController: index, store, show (costo en tiempo real), update, toggleActive + 6 métodos de líneas
  - Costo por unidad = (ingredientes + envases + mano de obra) / yield_quantity
  - Vistas: index + show con 3 secciones + 4 modales (create, edit-info, add-ingredient, add-packaging, add-labor)
  - Sección "Recetas" en sidebar + top nav
  - 22 tests (CRUD, roles, líneas, costo, aislamiento)
  - 155 tests en suite completa ✅

- 2.7 Dashboard Rentabilidad: ✅ Completo (2026-05-18)
  - RecipeCostCalculator service; selling_price en recipes
  - Dashboard: tabla rentabilidad (costo/u, precio venta, margen %, semáforo)
  - Tarjetas: recetas activas, gastos fijos/mes, horas productivas, overhead/hora
  - 11 tests

- 2.8 Cierre MVP: ✅ Completo (2026-05-18)
  - v0.4.0 — 5 commits lógicos + release + changelog
  - 161 tests, todos verdes

## Etapas futuras (post-MVP)
3 — Productos y Stock | 4 — POS Desktop (Tauri+SQLite) | 5 — ARCA | 6 — Backoffice SaaS | 7 — Apertura SaaS pública

## Versioning
- Rama activa: `v0.6.3`
- Versión actual: `0.6.3` — búsqueda, paginación, edición inline y UX de recetas

**Why:** El MVP prioriza costos de producción (el diferenciador real); POS y stock son etapas posteriores.
**How to apply:** Al sugerir tareas, respetar el orden de dependencias. No construir stock ni POS hasta tener recetas completas.
