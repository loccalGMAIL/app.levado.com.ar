---
name: feature-accesos-rapidos-mobile
description: Barra inferior mobile con 3 accesos editables por tenant (enum MobileShortcut + tenant_settings), pantalla en Administración (rama v0.16.0)
metadata:
  type: project
---
# Accesos rápidos editables en la barra mobile (rama `v0.16.0-accesos-rapidos-mobile`)

La bottom nav mobile es **Inicio · 3 accesos configurables · Más**. Cada tenant elige los 3 del medio
en Administración → Accesos rápidos (`mobile-shortcuts.edit/update`, gate `edit-settings` = owner +
super_admin; admin/viewer reciben 403). Afecta a todo el equipo del tenant.

- **Catálogo:** `App\Enums\MobileShortcut` (11 casos). Concentra label, shortLabel, ruta, patrones de
  `routeIs`, grupo del drawer, ícono SVG y `ability()` (sólo Reparto exige `manage-costs`).
- **Persistencia:** `tenant_settings`, clave `mobile_nav.shortcuts`, CSV (`stock,products,reparto`).
  Sin migración. `Tenant::mobileShortcuts()` cae a `MobileShortcut::defaults()` (Recetas, Ingredientes,
  Compras) si falta, hay valores inválidos/repetidos o no son exactamente `SLOTS` (3).
- **Render:** view composer en `AppServiceProvider` para `components.mobile-bottom-nav` entrega
  `$barShortcuts`, `$drawerGroups` y `$moreActivePatterns`. Lo que sale de la barra aparece solo en el
  drawer "Más" (nada queda inaccesible). Un acceso que el rol no puede ver se omite de la barra (un
  viewer con Reparto ve 2 slots, no un hueco).
- Tests: `tests/Feature/MobileShortcutsTest.php`.

**Why:** cada negocio usa la app distinto; antes los 4 destinos de la barra estaban hardcodeados.
**How to apply:** un destino nuevo para la barra = un caso nuevo en el enum (no tocar el Blade).
