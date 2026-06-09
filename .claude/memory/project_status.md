---
name: Levado — Estado actual del proyecto
description: Progreso al 09/06/2026
type: project
---
# Estado del proyecto — 9 de junio 2026

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
- **Versión actual:** 0.7.1 (rama `feature/compras`, pendiente merge a master)

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

### Módulo de Compras — v0.7.1 ✅ (rama feature/compras)
Ver `feature-compras.md` para detalle completo.
- Fase 1 (escaneo + digitalización) completa
- IVA por renglón almacenado (`iva_rate` en `purchase_lines`)
- Fase 2 (match e imputación de costos) pendiente de UI definitiva

## Convenciones establecidas
- Baja lógica siempre (nunca DELETE físico)
- tenant_id primero en índices compuestos
- Pint para formateo de PHP
- Commits con Co-Authored-By: Claude
- Rama master para producción
- CHANGELOG.md con Keep a Changelog + Semantic Versioning
- Memoria del proyecto en `.claude/memory/` (versionada en el repo)

## Próximos pasos sugeridos
- **Fase 2 compras:** definir y construir la pantalla de match (asociar renglones escaneados con insumos e imputar costos). `matchLine` y `applyLineSuggestions` ya existen en el controller, falta la UI.
- Merge de `feature/compras` a `master` y deploy a producción (configurar `ANTHROPIC_API_KEY` en Hostinger)
- Mejoras UX mobile adicionales si surgen al probar en dispositivo real
- Importación CSV de ingredientes/packaging/gastos fijos
