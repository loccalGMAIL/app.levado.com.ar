---
name: Levado — Estado actual del proyecto
description: Progreso al 14/07/2026
type: project
---
# Estado del proyecto — 14 de julio 2026

## Estructura local
```
D:\DESARROLLO\CoDiGo\levado.com.ar\
├── app\     ← Proyecto Laravel (repo: github.com/loccalGMAIL/app.levado.com.ar)
└── site\    ← Coming soon HTML (repo: github.com/loccalGMAIL/levado.com.ar)
```

## Infraestructura
- **Local:** Laravel 13 + Boost, PHP 8.3, MySQL, Laragon (servidor en localhost:8000 cuando se usa `php artisan serve`)
- **Producción:** Hostinger shared hosting con SSH
  - `levado.com.ar` → `public_html/` (coming soon estático)
  - `app.levado.com.ar` → `domains/app.levado.com.ar/public_html/` (symlink a `public/` de Laravel)
- **Git:** rama `master` (producción). Deploy con git push + PR manual.
- **Versión actual:** 0.9.2 (rama `v0.9.2-existencias-orden-elimina-merma-badge-semi`; `master` en 0.9.1)

## Todo lo que está hecho

### Etapa 1 — Fundación Web ✅
- Auth (Breeze), roles (super_admin/owner/admin/viewer), multi-tenancy
- Mi equipo, Mi negocio, Mi perfil
- Branding Levado (paleta, tipografías, logo SVG)
- Backoffice super admin con impersonación y audit logs

### Etapa 2 — Módulo de Costos ✅
- Ingredientes, Envases, Gastos Fijos, Mano de Obra, Proveedores
- Recetas con semi-elaboraciones (sub-recetas), propagación de costos síncrona
- Dashboard de rentabilidad con margen, semáforo, edición inline de precio
- UnitConverter, RecipeCostCalculator, RecipeCostPropagator
- Onboarding tour (Shepherd.js) 5 pasos
- Búsqueda, paginación (20 items), ordenamiento en todas las datatables
- Botón "Copiar" en recetas, header sticky en detalle de receta
- **216 tests, todos verdes**

### UX Mobile — v0.7.0 ✅
- Bottom navigation bar fija en mobile (< 640px): Inicio, Recetas, Ingredientes, Gastos Fijos + drawer "Más"
- Drawer deslizable con resto de navegación, respeta @can, overlay con cierre
- Reemplaza hamburger del top nav en mobile
- Fixes responsive: tablas con overflow-x-auto, formulario Mi equipo colapsa, modal w-full en mobile, capacidad productiva colapsa, botones header receta con texto adaptativo

### Módulo de Compras — v0.8.x–v0.9.1 ✅ (en master hasta v0.9.0; v0.9.1 en rama)
- Fase 1: escaneo, digitalización, IVA/percepciones por renglón y factura
- Fase 2: match e imputación de costos con cálculo reactivo Alpine.js y `unit_cost` override
- Fase 3: selects con Tom Select, límite de decimales, buscador y columnas ordenables en índice
- Detalle de factura: modal edición de cabecera, banner de progreso, badge por renglón, tfoot con totales
- v0.8.14: si la factura no tiene IVA (todos los renglones con alícuota 0) el detalle oculta "Total factura (con IVA)" y muestra "—" en la columna IVA (patrón Percepción), con etiquetas ajustadas
- Compresión de imagen al escanear (cliente y servidor)
- Responsive mobile: tarjetas en todas las vistas de tablas
- **v0.9.1:** comprobante (foto/PDF) adjuntable en la carga manual de compras (sin IA), reemplazable al editar — para tickets manuscritos

### Módulo de Existencias — v0.9.0, act. v0.9.2 ✅ (en rama v0.9.2)
- Ledger inmutable `stock_movements` + cache `stock_levels`; `StockService` único punto de escritura
- Entrada automática de stock al imputar costos de compras (con conversión de unidades y subdivisiones); reversión por contramovimientos
- UI `/stock` (tabs, valuación, alertas, mínimos) + kardex por ítem + modales ajuste/recuento/mínimo
- Columna Stock con edición inline en `/ingredients` y `/packaging` (valor absoluto → recuento)
- Sidebar en grupos colapsables (Producción / Existencias / Costos / Administración) con persistencia en localStorage; ítem "Existencias" renombrado a "Stock"
- **v0.9.2:** columnas Nombre/Stock actual/Mínimo ordenables; función "Merma" eliminada (redundante con Ajuste)
- Ver `.claude/memory/feature-existencias.md`

### v0.9.2 — Orden en Existencias, sin Merma, badge Semi, paginación en castellano
- Ordenamiento por columnas en `/stock` (Nombre, Stock actual, Mínimo)
- Eliminación completa de "Merma" (ruta, controlador, form request, modal, caso de enum) — sin datos históricos que migrar
- Badge "semi" agregada al Dashboard para recetas semielaboradas, extraída a componente reutilizable `x-semi-badge`
- `lang/es.json`: traduce "Showing/to/of/results/Pagination Navigation" — corrige el texto en inglés de la paginación en las 15 tablas del sistema
- 350 tests, todos verdes

### Otras mejoras post-MVP (v0.8.x)
- Subdivisiones en ingredientes y descartables + `cost_per_package` + columna "Por envase"
- Listas de precios (matriz receta × lista, ajuste %, precios por lista)
- Responsive mobile en todas las vistas (tarjetas Alpine.js)
- Flash toasts, iconos Heroicons, loading en modales, favicon
- Policies, `scopeActive()`, módulos Vite, `LazilyRefreshDatabase`
- Traducciones al español: `lang/es/{validation,auth,passwords,pagination}.php` + `lang/es.json` (cadenas sueltas de vistas vendor, ej. paginación)
- Mail de invitación sincrónico (sin queue worker en producción)

## Convenciones establecidas
- Baja lógica siempre (nunca DELETE físico)
- tenant_id primero en índices compuestos
- Pint para formateo de PHP
- Commits con Co-Authored-By: Claude
- Rama master para producción
- CHANGELOG.md con Keep a Changelog + Semantic Versioning
- Memoria del proyecto en `.claude/memory/` (versionada en el repo)

## Próximos pasos sugeridos
- Deploy a producción (Hostinger) — configurar `ANTHROPIC_API_KEY` y queue/mail settings
- Importación CSV de ingredientes/packaging/gastos fijos
- Backoffice SaaS (B.2) — prerequisito para apertura pública
- Etapa 3: Productos y Stock
