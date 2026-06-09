# Changelog — Levado

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/).
Versiones siguiendo [Semantic Versioning](https://semver.org/lang/es/).

---

## [0.7.1] — 2026-06-09

### Compras — IVA por renglón, iconos de acción y fixes

#### Agregado

- **Columna `iva_rate` en `purchase_lines`:** alícuota de IVA (0%, 10,5% ó 21%) almacenada por renglón. Se persiste en todos los flujos: escaneo de factura, alta manual y edición.
- **Discriminación de IVA en vista detalle de compra (`/purchases/{id}`):** columnas "IVA $" y "Subtotal c/IVA" calculadas server-side desde el `iva_rate` guardado. El selector de alícuota está disponible en el modal de edición y en el formulario de alta manual.
- **Selector de alícuota IVA en revisión de factura escaneada:** la pantalla de revisión muestra el select de alícuota por renglón; el valor elegido se guarda junto con la compra.
- **Iconos de acción en tablas de compras:** reemplaza los textos "Editar", "Eliminar" y "Ver detalle →" por iconos SVG (lápiz, papelera, ojo) con `title` para tooltip y área de click con `p-1`.
- **Botón "Eliminar compra" en el índice:** ícono de papelera con confirmación; borra la compra, sus renglones (cascade FK) y la imagen de factura del storage.
- **Modal quick-create de proveedor en revisión de factura:** cuando la IA detecta un proveedor que no existe en el catálogo, aparece el botón "creálo acá" que abre el modal sin salir del formulario. Al crear el proveedor, el select se actualiza y lo selecciona automáticamente vía evento `supplier-created`.

#### Corregido

- **Overflow en `subtotal` y `unit_price` de `purchase_lines`:** `decimal(10,4)` (máx. $999.999) reemplazado por `decimal(14,4)` para soportar subtotales superiores a $1.000.000 (ej.: 200 bolsas × $13.891,40 = $2.778.280).

---

## [0.6.5] — 2026-06-08

### Mobile — Barra de navegación inferior + fixes responsive

#### Agregado

- **Bottom navigation bar en mobile:** barra fija en el fondo de pantalla con los accesos directos a Inicio, Recetas, Ingredientes y Gastos Fijos. Reemplaza el menú hamburguesa del top nav en pantallas < 640px, dando una experiencia de app nativa.
- **Drawer "Más":** panel deslizable desde abajo que agrupa el resto de la navegación (Envases, Mano de Obra, Proveedores, Negocio, Mi equipo, Sucursales) con overlay oscuro y cierre al tocar afuera. Respeta los permisos `@can` del sidebar.

#### Corregido

- **Tablas en Mi equipo sin scroll horizontal:** ambas tablas (invitaciones pendientes y miembros) envueltas con `overflow-x-auto` para no romper el layout en mobile.
- **Formulario de invitación:** cambia de `flex-row` a `flex-col` en mobile para que los campos no queden comprimidos.
- **Modal sin ancho completo en mobile:** el componente `modal.blade.php` ahora aplica `w-full` en mobile (antes solo lo hacía en `sm:`).
- **Bloque capacidad productiva en Mi negocio:** colapsa a columna vertical en mobile (`flex-col sm:flex-row`).
- **Botones del header de receta:** texto adaptativo con `whitespace-nowrap` para que no se deformen. En mobile muestran etiquetas cortas ("precio", "Desact.", "Editar") y el nombre de la receta se trunca con `truncate` cediendo espacio a los botones.

---

## [0.6.3] — 2026-05-28

### UX — Tablas con búsqueda, paginación y edición mejorada

#### Agregado

- **Búsqueda y filtro de estado** en todas las secciones del módulo de costos: Recetas, Ingredientes, Mano de obra, Envases, Gastos fijos y Proveedores. Cada listado acepta `?search=` (nombre) y `?status=active|inactive`.
- **Paginación de 20 ítems** con `withQueryString()` en los seis módulos; el pie muestra el total real con `->total()`.
- **Botón "Copiar" en recetas:** clona la receta (nombre + `(copia)`, inactiva por defecto) con todos sus renglones (ingredientes, mano de obra, envases y sub-recetas) y recalcula el costo. Disponible en el listado y en el detalle.
- **Header sticky en detalle de receta:** el bloque con nombre, Desactivar/Activar, Copiar, Editar info y Guardar precio queda fijo al hacer scroll.
- **Botón "Guardar precio" en el header** de la receta, asociado al formulario del sidebar vía atributo HTML `form=`; no requiere JavaScript adicional.
- **Nombre clickeable** en las tablas de Ingredientes, Envases, Mano de obra, Gastos fijos y Proveedores: abre el modal de edición directamente.

#### Corregido

- **Super admin sin permisos al impersonar:** `Gate::before()` ahora devuelve `true` para usuarios con `isSuperAdmin()`, permitiendo que el admin vea y use todos los controles de edición al operar como cualquier tenant.
- **"Volver al admin" en banner de impersonación:** eliminado. El banner muestra únicamente "Salir de impersonación →".
- **Scroll horizontal en detalle de receta:** las tarjetas de Ingredientes, Mano de obra, Envases y Sub-recetas cambian de `overflow-hidden` a `overflow-x-auto`; el header usa `flex-wrap` para no desbordarse en móvil.

---

## [0.6.2] — 2026-05-27

### Fix — Impersonación y acceso al backoffice

#### Corregido

- **Impersonación desde el detalle de tenant:** el botón "Operar como este tenant" redirigía correctamente pero la ruta de stop-impersonate estaba mal ordenada respecto al middleware `super-admin`, resultando en 403 intermitente. Corregido con reordenamiento y botón de acceso rápido en el listado de tenants.

---

## [0.6.1] — 2026-05-26

### Seguridad — Correcciones identificadas en code review

#### Corregido

**Críticos**
- **Escalada de privilegios vía invitación de equipo:** la validación del campo `role` aceptaba `super_admin`, permitiendo que un owner o admin creara un super admin del sistema invitando a un usuario con ese rol. Ahora solo se permiten `owner`, `admin` y `viewer`.
- **Doble hashing de contraseña al aceptar invitación:** se usaba `bcrypt()` explícito junto al cast `hashed` del modelo `User`, generando un doble hash que impedía el login a todo usuario nuevo registrado por invitación.

**Altos**
- **IDOR en cancelación de invitaciones:** `InvitationController::destroy` no verificaba que la invitación perteneciera al tenant actual, permitiendo que un admin de otro tenant la eliminara por ID. Corregido con `abort_unless` de pertenencia.
- **Rutas de gestión de equipo siempre retornaban 404:** `TenantUser` tenía `$primaryKey = null` sin columna `id`, lo que rompía el route model binding silenciosamente. Se agrega columna `id` autoincremental mediante migración de recreación de tabla.
- **Falta de verificación de tenant en `TeamController`:** los métodos `updateRole`, `deactivate` y `activate` aceptaban un `TenantUser` de cualquier tenant vía route binding sin verificar pertenencia. Corregido con `abort_unless` en los tres métodos.

**Medios**
- **Sin rate limiting en reset de contraseña:** las rutas `POST /forgot-password` y `POST /reset-password` carecían de throttle, exponiéndolas a flooding de emails. Se agrega `throttle:6,1` en ambas.
- **`AcceptInvitationRequest` pedía name/password incondicionalmente:** los usuarios existentes que aceptaban una invitación recibían errores de validación porque el formulario no mostraba esos campos para ellos. La validación ahora es condicional según si el usuario ya tiene cuenta.

**Bajos**
- **Wildcard injection en búsquedas de backoffice:** los términos `%` y `_` en el campo de búsqueda de tenants y usuarios pasaban sin escapar a la cláusula `LIKE`, permitiendo queries más costosas. Los wildcards ahora se escapan antes del binding.
- **Filtros de fecha sin validar en logs de auditoría:** los parámetros `from` y `to` se pasaban directamente a `whereDate()` sin verificar formato, pudiendo generar errores de base de datos con valores inválidos.

#### Técnico
- Suite completa: **216 tests**, todos verdes

---

## [0.6.0] — 2026-05-26

### Semi-elaboraciones — Recetas como ingrediente de otras recetas

#### Agregado

**Semi-elaboraciones**
- Flag `is_semi_elaborate` en recetas: al marcarlo, la receta puede usarse como componente de costo dentro de otras recetas
- Tabla `recipe_subrecipe_lines`: `recipe_id` (padre), `child_recipe_id` (semi-elaboración), `quantity_used`, `unit`, `cost_calculated`
- Sección "Sub-recetas" en el editor de receta con la misma UX que ingredientes: selector, cantidad inline editable, costo calculado en tiempo real
- Modal "Agregar sub-receta" con filtro de unidades compatible con el rendimiento de la semi (Alpine.js)
- Badge "semi" visible en el listado de recetas
- Checkbox "Es una semi-elaboración" en los modales de crear y editar receta

**Motor de costos (4.º término)**
- `RecipeCostCalculator` suma un cuarto término: `Σ convert(quantity_used → child.yield_unit) × child.unit_cost`
- El padre lee el `unit_cost` ya persistido del hijo — sin recursión

**Propagación de costos (síncrona)**
- `recipes.unit_cost` (decimal 10,4): costo unitario cacheado, escrito automáticamente por el propagador
- Nuevo servicio `RecipeCostPropagator`: al mutar cualquier línea de una receta, recalcula su `unit_cost` y luego BFS hacia arriba propagando a todas las recetas padres en orden topológico
- Triggers en `IngredientController`, `PackagingController` y `LaborTypeController`: cambio de precio → propaga a todas las recetas que usan ese recurso y sus cadenas de padres

**Validaciones e integridad**
- Detección de ciclos: `isAncestor()` hace BFS hacia arriba antes de insertar una nueva sub-receta; bloquea con error de validación si generaría un ciclo (DAG garantizado)
- Solo recetas con `is_semi_elaborate = true` y activas pueden usarse como sub-receta (validado server-side y filtrado en el dropdown)
- Baja lógica bloqueada: no se puede desactivar una semi-elaboración que está siendo usada por recetas activas; el error lista los nombres de las recetas bloqueantes

#### Técnico
- 29 nuevos tests en 4 archivos: `RecipeSubrecipeLineTest`, `RecipeToggleActiveGuardTest`, `RecipeCostPropagatorTest`, `RecipeCostCalculatorSubrecipeTest`
- Suite completa: **216 tests**, todos verdes

---

## [0.5.1] — 2026-05-26

### Fix — Unidad incompatible al agregar ingrediente en receta

#### Corregido

- **Root cause**: al seleccionar un ingrediente con unidad de volumen (ej. agua en ml) y enviar el formulario con una unidad de peso (kg), el backend lanzaba `abort_unless()` con código 422, mostrando la pantalla de error técnica de Laravel en lugar de un mensaje amigable.
- `storeIngredientLine()` reemplaza `abort_unless()` por `ValidationException::withMessages()`: el error ahora aparece debajo del campo "Unidad" dentro del mismo modal.
- El dropdown de unidades se filtra dinámicamente con Alpine.js al seleccionar un ingrediente: solo muestra las unidades del mismo grupo de compatibilidad (peso: gr/kg · volumen: ml/L/cc · unidad: u).

---

## [0.5.0] — 2026-05-19

### Onboarding tour + Receta rediseñada + UX

#### Agregado

**Onboarding tour (Shepherd.js)**
- Tour guiado de 5 pasos para tenants nuevos: Mi negocio → Gastos fijos → Mano de obra → Insumos → Primera receta
- `onboarding_completed_at` en `tenants`; se marca automáticamente al crear la primera receta
- `ViewComposer` calcula el paso activo desde el estado de la base de datos (sin wizard bloqueante)
- `window.levadoOnboarding` inyectado en `<head>` cuando el tour está activo; Alpine.js queda libre
- 12 tests de step computation, tracking de completion y aislamiento

**Detalle de receta rediseñado**
- Layout dos columnas: tablas de líneas (ingredientes / mano de obra / envases) + sidebar de costos fijo (`sticky top-4`)
- Edición de cantidades inline (spinner) con guardado automático vía PATCH (`/recipes/{id}/…-lines/{line}`)
- Cálculo de costos en tiempo real con Alpine.js: ingredientes, mano de obra, envases, gastos fijos proporcionales
- Simulador de margen: slider 0–80 % con precio sugerido y barra de color (verde / ámbar / rojo)
- Endpoints PATCH para `ingredient-lines`, `packaging-lines` y `labor-lines`

#### Mejorado

- **Modales apilados:** el modal de proveedor (quick-create) aparece sobre el modal de ingrediente sin cerrarlo; z-index vía `style` inline para evitar limitaciones de purge de Tailwind
- **Gastos fijos:** creación de categoría inline dentro del modal de nuevo/editar gasto, sin salir del flujo
- **Mi negocio:** sección "Capacidad productiva" reubicada debajo de los datos del negocio y fiscales
- **Navbar:** logo SVG de Levado como fallback cuando el tenant no tiene logo subido; logo ocupa todo el bloque de marca

#### Técnico
- `FixedCostCategoryController::store()` y `SupplierController::store()` retornan JSON cuando `Accept: application/json`
- `modal.blade.php` y `crud-modal.blade.php` reciben prop `z` (int) para z-index configurable
- Suite completa: **173 tests**, todos verdes

---

## [0.4.1] — 2026-05-22

### Fix — Onboarding tour no arrancaba en el dashboard

#### Corregido

- **Root cause**: `productive_hours_month` tenía `default(160)` en la BD, por lo que el backend calculaba siempre `step ≥ 1` y el bloque JS del dashboard (que solo escuchaba `step === 0`) nunca disparaba el tour.
- `productive_hours_month` pasa a nullable sin default; los tenants nuevos arrancan con `null` y caen en step 0. Migración incluida. Tenants existentes conservan su valor.
- `AppServiceProvider`: condición `=== 0` reemplazada por `!$productive_hours_month` (cubre `null` y `0`).
- `onboarding-tour.js`: el bloque del dashboard ahora cubre cualquier step pendiente (0–4) con título, texto y sidebar de destino apropiados para cada uno.
- Admin panel (crear tenant): campo de horas productivas pasa a ser opcional; el tenant lo completa durante el onboarding.

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

## [0.6.4] — 2026-06-05

### UX — Ordenamiento completo en tablas + edición inline de precio de venta

#### Agregado

- **Ordenamiento asc/desc en todas las datatables:** Ingredientes, Envases, Gastos fijos, Mano de obra, Recetas y Proveedores. Las columnas sortables se marcan con ↑/↓ y preservan el filtro/búsqueda activos.
- **Búsqueda en el dashboard:** el listado de rentabilidad acepta `?search=` por nombre de receta, consistente con el resto de los módulos.
- **Ordenamiento de columnas calculadas en el dashboard:** Costo/u, Margen y Margen % ahora son ordenables. Al ser valores calculados en PHP (no columnas SQL), el controlador carga todas las recetas activas, computa los costos, ordena la colección en memoria (nulls siempre al final) y pagina manualmente con `LengthAwarePaginator`.
- **Precio de venta editable inline en el dashboard:** clic sobre el precio abre un input; Enter o blur guardan vía `PATCH /recipes/{id}/selling-price`. El margen y margen % de la fila se actualizan reactivamente sin recargar la página.
- **Precio de venta editable inline en el listado de recetas:** misma UX que el dashboard; columna "Precio venta / u" visible y editable directamente desde el índice.
- **Recetas inactivas ocultas del dashboard:** solo recetas con `active = true` aparecen en la tabla de rentabilidad; el índice de recetas las sigue mostrando al final.

#### Corregido

- **Super admin bloqueado al impersonar:** `CheckTenantRole` ahora cortocircuita el chequeo de rol cuando el usuario es super admin con sesión de impersonación activa, permitiendo acceder a rutas de owner (`/business`, etc.) sin necesitar entrada en `tenant_users`.
- **Input de precio quedaba en blanco sin cambios:** eliminado `x-model` del input numérico (bug conocido de Alpine.js v3 con `type="number"`). Ahora se usa `x-ref` + lectura imperativa del DOM; `isDirty` evita enviar la petición si el usuario no modificó el valor.

---

## [Unreleased]

---

## [0.7.0] — 2026-06-08

### Módulo de Compras — Registro de facturas de proveedores

#### Agregado

**Módulo de compras**
- Tabla `purchases`: cabecera de factura (tenant, proveedor, N° de factura opcional, fecha, notas)
- Tabla `purchase_lines`: líneas de compra con tipo (ingrediente / envase), insumo, cantidad, unidad de compra, precio unitario y subtotal calculado
- `PurchaseController`: index con filtros (proveedor, rango de fechas), show con líneas, store, destroy (solo si sin líneas), storeLine, updateLine, destroyLine
- 3 Form Requests con validación (`StorePurchaseRequest`, `StorePurchaseLineRequest`, `UpdatePurchaseLineRequest`)
- Modelo `Purchase` con relaciones a Tenant, Supplier y PurchaseLine; helper `totalAmount()`
- Modelo `PurchaseLine` con helpers `isIngredient()` / `isPackaging()` y relaciones a Ingredient y Packaging

**Actualización automática de costos al cargar una factura**
- Al agregar o editar una línea de compra, el costo del ingrediente/envase se actualiza inmediatamente
- Conversión automática de unidades: si comprás 1 kg a $500/kg y el ingrediente se mide en gr, el sistema calcula y persiste $0.50/gr usando `UnitConverter`
- Se crea automáticamente una entrada en `ingredient_price_logs` / `packaging_price_logs`
- `RecipeCostPropagator` dispara recálculo en cascada de todas las recetas que usan el insumo actualizado
- Acción registrada en `AdminAuditLog`

**Vistas**
- `purchases/index.blade.php`: listado paginado con filtros por proveedor y rango de fechas
- `purchases/show.blade.php`: detalle con header de compra, tabla de ítems, formulario inline para agregar ítems (con selects dinámicos ingrediente/envase vía Alpine.js), modal de edición de línea
- Creación rápida de proveedor inline desde el modal de nueva compra (patrón quick-create existente)
- Botón "← Volver a compras" visible solo en mobile en la vista de detalle

**Navegación**
- "Compras" agregado al sidebar desktop (sección Costos, debajo de Mano de Obra)
- Barra inferior mobile: "Gastos" reemplazado por "Compras" (ícono carrito)
- "Gastos Fijos" movido al drawer "Más"
- Breadcrumbs actualizados para `purchases.*` y `purchases.show`

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
