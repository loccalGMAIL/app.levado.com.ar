# Changelog — Levado

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/).
Versiones siguiendo [Semantic Versioning](https://semver.org/lang/es/).

---

## [0.4.0] — 2026-05-18

### Etapas 2.4–2.7 completas — Módulo de Costos (cierre) + Recetas + Dashboard

#### Agregado

**Etapa 2.4 — Tipos de Mano de Obra**
- Tabla `labor_types` por tenant: nombre y costo por hora
- CRUD completo con toggle active; Gate `manage-costs` (owner+admin escriben, viewer lee)
- 10 tests de roles, validación y aislamiento

**Etapa 2.5 — UnitConverter**
- Servicio `App\Services\UnitConverter` para conversión entre unidades compatibles
- Dimensiones: peso (gr / kg), volumen (ml / L / cc), unidad (u)
- `convert()` retorna `null` para unidades incompatibles; `compatible()` para validar
- 16 tests unitarios

**Etapa 2.6 — Recetas**
- 4 tablas: `recipes`, `recipe_ingredient_lines` (cantidad + unidad), `recipe_packaging_lines`, `recipe_labor_lines`
- `RecipeController`: index, store, show, update, toggleActive + 6 métodos de líneas (store/destroy × 3 tipos)
- Cálculo de costo en tiempo real: UnitConverter convierte unidades de ingredientes al agregar líneas
- Vista detalle: resumen de costos (ingredientes / envases / mano de obra / total), desglose por sección, botones para agregar/quitar líneas
- `selling_price` (nullable) en recetas; campo en modales de crear y editar
- Sección "Recetas" en sidebar context-aware y top nav
- 22 tests de CRUD, roles, líneas, conversión de unidades y aislamiento

**Etapa 2.7 — Dashboard de Rentabilidad**
- `RecipeCostCalculator`: servicio reutilizable que encapsula el cálculo de costos por receta
- Dashboard principal reemplaza el placeholder: tabla de recetas activas con costo/u, precio de venta, margen $ y margen % (semáforo: verde ≥ 30 % / amarillo 15–29 % / rojo < 15 %)
- Tarjetas de resumen: recetas activas, total gastos fijos/mes, horas productivas/mes, overhead/hora
- Link directo a configurar horas productivas cuando están en cero
- 11 tests del dashboard (costo, margen, gastos fijos, overhead, aislamiento)

#### Técnico
- Suite completa: **161 tests**, todos verdes
- `RecipeCostCalculator` inyectado como dependencia en `DashboardController` y `RecipeController`

---

## [0.2.0] — 2026-05-13

### Etapa 1 completa — Fundación Web

#### Agregado
- **Auth (Breeze):** login, logout, recuperación de contraseña, verificación de email
- **Roles y permisos:** enum `TenantUserRole` (super_admin, owner, admin, viewer), Gates por rol, middleware `CheckTenantRole`
- **Multi-tenancy:** middleware `SetTenantContext` resuelve tenant desde el usuario autenticado; solo TenantUsers activos son considerados
- **Mi equipo:** invitaciones por email con token, listado de miembros, edición de rol, baja lógica (activar/desactivar)
- **Mi negocio:** edición de nombre, país, moneda, horas productivas mensuales y logo (upload a storage)
- **Mi perfil:** edición de nombre, email y contraseña
- **Branding Levado:** paleta Tailwind (masa-madre, corteza, harina, miga, horno, membrillo), tipografías Inter/Lora/JetBrains Mono, logo SVG wordmark
- **Layouts:** `app.blade.php` (tenant) y `guest.blade.php` (auth) con branding completo
- **Navegación:** links condicionales por rol (`@can`), menú de usuario con perfil y cerrar sesión
- **Vistas en español:** todas las vistas de auth y perfil hardcodeadas en español rioplatense
- **Registro bloqueado:** ruta `/register` eliminada; usuarios solo entran por invitación
- **Seeder demo:** tenant "Levado HQ" con `admin@levado.com` (super_admin) y tenant "Panadería Demo" con `owner@demo.com` (owner); password `password`
- **Factory:** `TenantFactory` con estado `inactive()`
- **Tests:** suite completa de 35 tests — auth, perfil, aislamiento de tenants por rol y entre tenants, usuario inactivo

#### Corregido
- `SetTenantContext` redirige al login (en vez de abort 404) cuando no hay tenant activo
- Dashboard requiere middleware `tenant` (antes era accesible sin tenant)
- `TenantUser.active = false` impide resolución del tenant (antes se ignoraba el estado del vínculo)

---

## [0.1.2] — 2026-05-11

### Etapas 1.1 y 1.2 — Setup y Multi-tenancy

#### Agregado
- Inicialización del proyecto Laravel 13 en Herd local
- Base de datos MySQL con migraciones `tenants` y `tenant_settings`
- Modelos `Tenant` y `TenantSetting` con helper `getSetting/setSetting`
- Middleware `SetTenantContext` (estructura base)
- Repositorio Git con ramas `master` y `develop`
- Versionado en `config/app.php` (`config('app.version')`)

---

## [Unreleased]

---

## [0.3.0] — 2026-05-18

### Backoffice B.1 + Frontend + Módulo de Costos (Etapa 2, parcial)

#### Agregado

**Backoffice de administración (B.1)**
- Panel `/admin` exclusivo para super admins (middleware `EnsureSuperAdmin`)
- Dashboard con widgets: tenants activos/inactivos, usuarios totales, actividad reciente
- Gestión completa de tenants: listado, alta, edición, activar/desactivar
- Vista de usuarios: listado global con tenants, roles y estado por usuario
- Impersonación de tenant: sesión como cualquier tenant con banner visible y botón de salida
- Logs de auditoría con filtros por acción, tenant y fecha
- Layout admin separado (`AdminLayout`) con navbar corteza y logo SVG adaptable

**Frontend — reestructuración de layout**
- Layout dos columnas: top nav fijo + sidebar izquierdo context-aware siempre visible
- Top nav: bloque de marca alineado al sidebar (logo o nombre del tenant), secciones, dropdown de usuario
- Sidebar: expande "Mi negocio" o "Costos" según la sección activa; oculto en móvil
- Componente `<x-crud-modal>` reutilizable para todos los módulos

**Modelo de datos base**
- Enum `CondicionIva` (RI/MO/EX/CF/NR) y campos fiscales en `tenants` (`razon_social`, `cuit`, `condicion_iva`)
- Vista "Mi negocio" reestructurada en dos columnas: datos del negocio + datos fiscales
- Módulo de sucursales (`locations`): CRUD completo para owner/super_admin con activar/desactivar

**Etapa 2.1 — Ingredientes**
- Tabla `ingredients` con enum `Unit` (gr/kg/ml/L/cc/u), costo por unidad y estado activo/inactivo
- Tabla `ingredient_price_logs`: historial inmutable de precios (sin timestamps), generado automáticamente al crear y al cambiar el costo
- Gate `manage-costs`: owner/admin escriben, viewer solo lee
- Vista CRUD con modales Alpine (crear, editar, activar/desactivar)

**Etapa 2.1b — Proveedores**
- Tabla `suppliers` (nombre, teléfono, email, notas, activo) vinculada a ingredientes y packaging
- Campo `brand` en ingredientes y packaging
- Modal "quick-create" de proveedor accesible desde los modales de ingrediente y packaging sin salir de la pantalla

**Etapa 2.2 — Packaging (Envases)**
- Tabla `packagings` con costo por unidad (decimal 10,4), marca y proveedor opcional
- Tabla `packaging_price_logs`: mismo patrón de historial inmutable que ingredientes
- Vista CRUD con modales Alpine

**Etapa 2.3 — Gastos Fijos**
- Tabla `fixed_costs` con monto mensual y estado activo/inactivo
- Tabla `fixed_cost_categories` per-tenant: categorías gestionables desde un modal inline (crear, renombrar, eliminar con guard si tiene gastos asignados)
- Tabla `fixed_cost_logs`: historial con `valid_from` editable por el usuario — permite cargar datos históricos retroactivos
- Total mensual activo calculado al pie del listado
- Modal de categorías reabre automáticamente tras cada operación vía session flag

#### Métricas
- Tests: 106 (todos verdes) — cubre CRUD, roles, aislamiento de tenants y trazabilidad de precios
- Nuevas tablas: `admin_audit_logs`, `locations`, `ingredients`, `ingredient_price_logs`, `suppliers`, `packagings`, `packaging_price_logs`, `fixed_costs`, `fixed_cost_logs`, `fixed_cost_categories`
