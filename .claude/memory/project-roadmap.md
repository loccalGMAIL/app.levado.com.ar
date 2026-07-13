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

## Módulo de Compras — v0.7.x (rama `feature/compras`)
- 3.1 Compras Fase 1 — Escaneo y digitalización: ✅ completo (v0.7.1)
  - `purchases` + `purchase_lines` con `iva_rate`
  - InvoiceExtractor (Claude Haiku 4.5 vision), PurchaseLineRecorder
  - Vistas: index, show, scan/create, scan/review
  - Modal quick-create proveedor en review; iconos de acción; IVA por renglón persistido
- 3.2 Compras Fase 2 — Match e imputación de costos: ✅ completo (v0.7.2)
  - Vista dedicada `/purchases/{id}/match`
  - Botón en índice con indicador de estado (ámbar/verde) + `applied_count`
  - Cálculo reactivo Alpine.js: auto-conversión de unidades compatibles, divisor editable para incompatibles
  - Parser de descripción: detecta "X 25 Kg", "x5lts", etc. para pre-llenar el divisor (✦)
  - `PurchaseLineRecorder::applyWithCost()` para aplicar costo explícito sin conversión
  - `matchLine()` acepta `unit_cost` override del form
- 3.3 Compras UX — Selects con buscador y límite de decimales: ✅ completo (v0.7.3)
  - Tom Select en todos los selects del módulo de compras (proveedor en create/review, insumo en match)
  - `data-maxdecimals="4"` + listener global en `app.js` para inputs de precio y cantidad
  - CSS de Tom Select integrado con overrides de estilos Tailwind (colores del proyecto)

## Listas de Precios — v0.8.0 (rama `claude/price-lists-planning-1h3rio`)
- ✅ Completo (2026-06-12) — 270 tests verdes
- Tablas: `price_lists` (tenant_id, name, adjustment_pct decimal 6,2 nullable, is_default, active; unique tenant+name), `recipe_prices` (unique price_list+recipe; price decimal 10,2 NOT NULL — sin fila = sin precio), `recipe_price_logs` (patrón ingredient_price_logs)
- `recipes.selling_price` ELIMINADA: `recipe_prices` es la única fuente de verdad. `selling_price` sigue como campo virtual en forms de receta → escribe en la lista default vía `RecipePriceWriter`
- Lista "General" default por tenant: lazy `Tenant::defaultPriceList()` (firstOrCreate); migración de datos copió los selling_price existentes. No desactivable, sin % de ajuste
- `adjustment_pct` solo PRE-LLENA sugerencias en celdas vacías de la matriz (round 2 decimales, server-side); nunca se re-aplica a precios guardados
- `RecipePriceController::update`: PATCH `recipes/{recipe}/prices/{priceList}` JSON (mismo shape que el viejo updateSellingPrice). 403 receta/lista ajena, 422 lista inactiva
- Vistas: `price-lists/index` (CRUD modales) + `price-lists/matrix` (matriz receta × lista, edición inline por celda, margen semáforo); selector de lista en dashboard (fallback a default si param inválido)
- `RecipeController::copy` duplica precios en todas las listas; sort por precio en recipes/index via subquery

## v0.8.5 — Fix compras y renombrado Envases → Descartables
- Fix: bulk apply sugerencias IA, decimales en costo, dropdown de producto en compras
- Renombrado "Envases" → "Descartables" en menú lateral, mobile nav y breadcrumbs

## v0.8.6 — Mejoras UX generales
- Flash toasts, iconos en tablas, loading en modales
- Coherencia de íconos y color en acciones de tablas
- Refactor: Policy classes, `scopeActive()`, reemplaza `abort_unless`
- Refactor: JS a módulo Vite, `LazilyRefreshDatabase` en tests

## v0.8.7 — Compras: IVA, percepciones y edición inline
- Control de IVA y percepciones por factura (`/purchases`)
- Edición inline de costo en packaging
- Advertencia de comprobantes duplicados

## v0.8.8 — Subdivisiones para ingredientes y descartables (2026-06-18)
- Ingredientes: soporte de subdivisiones para ítems vendidos por envase (ej: bandeja de 12 unidades)
- Descartables: subdivisiones para ítems vendidos por presentación
- Compras: matcheo de renglones muestra subdivisiones disponibles
- Fix: modal de proveedor nuevo no cierra el modal del descartable
- **281 tests, todos verdes**

## v0.8.9 — Fix cálculo de costo con subdivisiones (2026-06-22)
- **Fix crítico:** `cost_per_unit` ahora almacena el precio por sub-unidad (no por envase completo). Al guardar un ingrediente/envase con subdivisiones, el precio ingresado se divide por la cantidad de sub-unidades antes de persistirse.
- **Columna "Por envase"** en tablas de ingredientes y envases — muestra precio del pack completo + precio por sub-unidad.
- **Hint dinámico** en modales crear/editar: etiqueta "Costo por envase" + "≈ $X / sub-unidad" reactivo con Alpine.js.
- **Comando artisan `ingredients:fix-subdivision-costs`:** corrige ítems existentes con subdivisiones que tenían el precio incorrecto; muestra previsualización y pide confirmación.
- Migración: agrega `cost_per_package` (DECIMAL 10,4 nullable) a `ingredients` y `packagings`.
- 4 nuevos tests cubriendo la división correcta, limpieza al quitar subdivisiones y no-efectos sin subdivisiones.

## v0.8.10 — Compras: detalle de factura mejorado + responsive mobile en todas las vistas (2026-06-27)
- **Modal de edición de cabecera de compra:** editar proveedor, fecha, N° factura, notas, IVA y percepción por defecto desde el detalle.
- **Banner de progreso de vinculación:** en detalle de compra muestra "X de Y renglones vinculados" con link al match.
- **Columna "Costo" y badge de estado por renglón:** Aplicado (verde), Pendiente (ámbar), Sin vincular (gris).
- **`tfoot` con totales:** subtotal neto, IVA total, percepción total y total de factura.
- **Formulario "Agregar renglón" en modal:** siguiendo el patrón CRUD del proyecto.
- **Tarjetas mobile en todas las vistas con tablas:** 8 vistas con Alpine.js `mobileExpanded` — mobile muestra cards, desktop muestra tabla. Cubre: Tipos de mano de obra, Gastos fijos, Listas de precios, Mi equipo, Compras (índice y detalle), Ingredientes, Descartables, Recetas (índice y detalle con 4 secciones de líneas).
- Fix: `LazyLoadingViolationException` al aplicar sugerencias de IA en bloque (relación `purchase` no pre-cargada).
- Fix: `checkDuplicate` excluye la compra actual al editar (evita falso positivo).

## v0.8.11 — Mejoras UI: decimales, columnas ordenables, buscador y navegación (2026-06-27)
- **Buscador en índice de compras:** filtra por número de factura o nombre de proveedor (server-side, combinable con otros filtros).
- **Columnas ordenables en índice de compras:** Fecha, N° Factura, Proveedor, Ítems, Total — alterna asc/desc, se preserva al paginar.
- **Columnas ordenables en detalle de receta:** las 4 secciones ordenan client-side con Alpine.js.
- **N° Factura como link** en índice de compras → va al detalle.
- **"← Recetas" en detalle de receta:** encabezado sticky con link para volver al índice.
- **Paginación en español:** `lang/es/pagination.php` — botones "Anterior" / "Siguiente".
- **Reorden de columnas en índice de compras:** Fecha → N° Factura → Proveedor.
- Fix: `step="0.01"` en todos los inputs numéricos (antes `step="0.0001"` hacía que Chrome rellenara con 4 decimales al editar).

## v0.8.12 — Fix invitación de equipo, traducciones al español, mail sincrónico (2026-07-01)
- **Fix validación de rol al invitar:** `Rule::enum()->only()` requería instancias del enum pero recibía strings. Corregido con `->except([TenantUserRole::SuperAdmin])` con instancias.
- **Traducciones al español:** creados `lang/es/validation.php`, `lang/es/auth.php` y `lang/es/passwords.php`. El locale ya era `es` pero faltaban los archivos. Actualizado `config/app.php`.
- **Mail sincrónico restaurado:** `Mail::queue()` fue cambiado a `Mail::send()` — sin worker en producción, los mails quedaban acumulados. Incluye logging en flujo de aceptación y null guard en `Invitation::isExpired()`.
- **Compresión de imagen al escanear factura:** imagen de factura se comprime en cliente y servidor antes de enviarla a la API de IA (reducción de payload).

## v0.8.13 — Estado de tablas preservado, PWA instalable, selects ordenados (2026-07-07)
- **Redirects que preservan contexto:** store/update de los CRUD con modal (ingredientes, envases, gastos fijos, mano de obra, listas de precios, proveedores, sucursales, categorías de gastos, admin usuarios) ahora usan `back(fallback: ...)` en vez de `redirect()->route('x.index')` — se conservan `page`/`search`/`status`/`sort`/`dir` al guardar. Las acciones de líneas de receta también.
- **Parámetro `volver` en detalle:** los links índice → `recipes.show` / `purchases.show` llevan la query string del índice en `?volver=`; el botón "Volver" y el breadcrumb la reinyectan al volver al índice.
- **Restauración de scroll:** `resources/js/scroll-restore.js` guarda `scrollY` por URL en `sessionStorage` (`pagehide`) y lo restaura al recargar la misma URL (TTL 5 min).
- **PWA instalable con offline básico:** `public/manifest.webmanifest`, `public/sw.js` (cache-first solo para `/build/`, `/icons/`, favicon; navegaciones network-first con fallback a `public/offline.html`, nunca cachea HTML), íconos PNG generados del favicon SVG en `public/icons/` (192, 512, maskable, apple-touch). Metas PWA en los 3 layouts.
- **Banner de instalación:** `components/pwa-install-banner.blade.php` (solo mobile, layout app) — botón "Instalar" con `beforeinstallprompt` en Android/Chrome, instrucciones "Compartir → Agregar a inicio" en iOS. Dismiss por 30 días en `localStorage`.
- **Select de sub-recetas ordenado:** `RecipeController::availableSemiElaborates()` con `orderBy('name')` (era el único select de datos sin ordenar). Los selects de enums/estáticos mantienen orden lógico a pedido del usuario.

## v0.8.14 — Detalle de compra sin IVA (2026-07-07)
- Si la factura no tiene IVA (alícuota 0 en todos los renglones), el detalle oculta "Total factura (con IVA)" y muestra "—" en la columna IVA.

## v0.8.15 — Banner PWA solo con botón "Instalar" + fixes (2026-07-07)
- **Banner con un único botón "Instalar":** ejecuta el prompt nativo si está disponible; solo muestra pasos manuales (por plataforma) al tocar el botón cuando el navegador no permite instalación directa. Nada de instrucciones de entrada.
- Fixes previos ya desplegados: fallback Android sin `beforeinstallprompt`, íconos 192/apple-touch regenerados (estaban en blanco), `AddType application/manifest+json` en `.htaccess`.
- Aprendizaje: `beforeinstallprompt` puede no dispararse (app ya instalada/conocida por Chrome, criterios de instalabilidad); iOS nunca permite instalación programática.

## v0.9.0 — Módulo de Existencias (2026-07-10)
- Ledger inmutable `stock_movements` + cache `stock_levels`, `StockService` único punto de escritura. Ver [[feature-existencias]].
- Integración con compras (entrada automática al imputar costo, reversión por contramovimiento), ajuste/merma/recuento/mínimo, UI `/stock` con tabs + kardex.
- Edición inline de stock en catálogos `/ingredients` y `/packaging` (valor absoluto → recuento con delta, endpoint JSON `stock.level.update`).
- Nota: existía una rama previa `v0.9.0/feature/stock-insumos` (arquitectura duplicada por entidad, `stock_on_hand` mutable) que se descartó; solo se rescató la idea de la edición inline.
- **340 tests, todos verdes**

## Navegación en grupos colapsables (2026-07-10, rama v0.9.0)
- Sidebar reorganizado en grupos colapsables (`components/sidebar-group.blade.php`, estado en localStorage, grupo activo forzado abierto): Producción (Recetas), Existencias (Compras, Proveedores, Stock), Costos (Ingredientes, Descartables, Mano de Obra, Gastos, Listas de Precios), Administración (Mi negocio, Sucursales, Mi equipo). Ítem "Existencias" renombrado a "Stock" (grupo se llama Existencias). Breadcrumbs y drawer mobile alineados.
- **Módulos futuros ya decididos para el menú**: "Artículos" (productos fabricados a partir de recetas + productos de reventa; inicio de Etapa 3) y "Movimientos" (listado global de stock_movements con filtros) van en el grupo Existencias; "Producción" (módulo) va en el grupo Producción.

## v0.9.1 — Compras: comprobante en la carga manual (2026-07-13)
- El flujo manual de compras (sin IA) ya existía; se agregó la posibilidad de adjuntar la foto/PDF del comprobante en el modal "+ Nueva compra" y reemplazarla desde "Editar compra" — pensado para tickets manuscritos que la IA de visión lee mal.
- Ver [[feature-compras]] sección "Compra manual con comprobante".
- 345 tests, todos verdes (5 nuevos en `PurchaseCrudTest`).

## Versioning
- Rama activa: `v0.9.1-compras-comprobante-manual` (sobre `master`, que ya tiene v0.9.0 mergeado)
- Versión actual: `0.9.1`

**Why:** El MVP prioriza costos de producción (el diferenciador real); POS y stock son etapas posteriores.
**How to apply:** Al sugerir tareas, respetar el orden de dependencias. No construir stock ni POS hasta tener recetas completas.
