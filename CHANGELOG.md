# Changelog — Levado

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/).
Versiones siguiendo [Semantic Versioning](https://semver.org/lang/es/).

---

## [0.8.8] — 2026-06-18

### Subdivisiones para envases e ingredientes por unidad

#### Agregado

- **Subdivisiones en ingredientes con unidad `u`:** al registrar un ingrediente vendido por unidad (ej.: paquete de galletas), se puede indicar cuántas unidades trae la presentación ("Unidades por envase") y cómo se llama la sub-unidad ("galleta", "rebanada"). El campo `cost_per_unit` almacena el precio por sub-unidad.
- **Subdivisiones en envases/descartables:** los envases soportan ahora el mismo mecanismo. Al registrar un envase que viene en pack (ej.: caja de 100 bolsas kraft), se indica la cantidad por presentación y el nombre de la sub-unidad ("bolsa", "etiqueta"). La tabla de envases muestra "100 bolsa / presentación" debajo del nombre cuando tiene subdivisiones configuradas.
- **Vista de matcheo de compras:** la columna de ingredientes en `/purchases/{id}/match` muestra la info de subdivisión al vincular un renglón de factura, para que sea visible cuántas sub-unidades trae cada presentación.

#### Corregido

- **`cost_per_unit` almacena precio por sub-unidad:** el campo refleja el costo de una sola sub-unidad (ej.: $1 por galleta), no el precio del paquete completo.

#### Corregido

- **Modal de crear/editar envase — proveedor nuevo:** el botón "+ Nuevo" ya no cierra el modal del envase antes de abrir el quick-create de proveedor. Ahora abre el quick-create encima (z-index superior), y al crear el proveedor el select se actualiza automáticamente con la nueva opción seleccionada, replicando el comportamiento del modal de ingredientes.

#### Técnico

- Migraciones: `add_subdivisions_to_ingredients_table` y `add_subdivisions_to_packagings_table` (`subdivisions` UNSIGNED INT nullable, `subdivision_label` VARCHAR 50 nullable).
- `StoreIngredientRequest`, `UpdateIngredientRequest`, `StorePackagingRequest` y `UpdatePackagingRequest` validados con `nullable|integer|min:2` y `nullable|string|max:50`.
- 4 nuevos tests en `PackagingCrudTest`: crear con subdivisiones, crear sin subdivisiones (null), editar agregando subdivisiones y validar mínimo de 2.

---

## [0.8.7] — 2026-06-18

### Compras — Control de IVA y percepciones por factura

#### Agregado

- **Control de IVA a nivel de factura:** al registrar una compra (modal de nueva compra y revisión de factura escaneada) se puede establecer la alícuota IVA de toda la factura — Sin IVA / 10,5% / 21%. El valor se guarda en `purchases.default_iva_rate` y pre-rellena el selector de IVA en cada línea que se agregue manualmente desde el detalle de la compra.
- **Percepciones por línea:** cuando la factura tiene IVA, se puede activar una percepción de porcentaje libre (ej. 3,5%). Se almacena en `purchase_lines.percepcion_rate` (decimal %) y `purchases.default_percepcion_rate`. El cálculo es `percepcion_$ = subtotal_neto × percepcion% / 100`.
- **"Aplicar a todos los renglones" en el escaneo:** el panel de IVA/percepciones de la vista de revisión de factura escaneada incluye un botón que distribuye los valores seleccionados a todas las filas de la tabla vía Alpine.js (`@apply-tax-defaults`). Cada fila también es editable individualmente.
- **Columna Percepción $ en tablas:** la tabla de renglones en el detalle de compra (`purchases/show`) y en la revisión de escaneo ahora muestran columnas separadas para IVA $, Percepción $ y Total; reemplazando la anterior columna "Subtotal c/IVA".
- **Modal editar línea:** incluye el campo Percepción % con cálculo reactivo en tiempo real (muestra IVA $, Percepción $ y Total c/IVA+Perc.).

#### Técnico

- Migraciones: `add_percepcion_rate_to_purchase_lines_table` (DECIMAL 5,2) y `add_tax_defaults_to_purchases_table` (`default_iva_rate` DECIMAL 5,4, `default_percepcion_rate` DECIMAL 5,2).
- `PurchaseLineRecorder::storePending()` y `recompute()` persisten `percepcion_rate`.
- `StorePurchaseRequest`, `StorePurchaseLineRequest`, `UpdatePurchaseLineRequest` y `StoreScannedPurchaseRequest` validados con las nuevas reglas.

---

## [0.8.6] — 2026-06-18

### UX — Mejoras de interfaz: feedback visual, íconos y tooltips

#### Agregado

- **Flash messages en el layout:** los mensajes de éxito y error aparecen como toast fijo en la esquina superior derecha (`fixed top-4 right-4 z-50`) y se auto-cierran a los 4 segundos con transición de opacidad. Se eliminaron los bloques flash inline de cada vista; ahora se usa el componente `<x-flash-messages>` en el layout.
- **Loading state en modales:** todos los botones "Guardar" de los modales de create/edit tienen `data-loading="Guardando…"` que activa el handler global de `app.js` para prevenir doble-submit y mostrar feedback durante el POST.
- **Íconos en acciones de tablas:** las acciones de cada fila (editar, activar/desactivar) se migraron a botones icon-only con SVG Heroicons, `aria-label` y `title` para tooltips nativos.
- **Componentes `<x-status-badge>` y `<x-empty-state>`:** eliminan duplicación de los patrones de badge activo/inactivo y mensaje de lista vacía en las vistas index.
- **Favicon:** se agregaron `favicon.ico` y `favicon.svg` con la identidad de Levado, referenciados en ambos layouts (`app.blade.php` y `admin.blade.php`).

#### Corregido

- **Ícono "Desactivar":** reemplaza el eye-slash (asociado a "ocultar") por el símbolo de prohibición ⊘ (`no-symbol` de Heroicons) — significado más claro para desactivar un registro.
- **Color del botón desactivar:** ahora es `text-red-500` siempre visible (antes solo aparecía en hover).
- **Ícono "Ver detalle" en Recetas y Compras:** reemplaza el ojo por el ícono de lápiz para coherencia con el resto de las vistas.
- **Tooltips en todos los botones icon-only:** atributo `title` nativo en todos los botones de acción (se eligió `title` nativo sobre CSS para evitar recorte por `overflow-x-auto`).

#### Técnico / Refactor

- **Auditoría best-practices Laravel (fases 1–3):** corrección de N+1 queries, casts Eloquent, índices en migraciones, extracción de JS a módulo Vite (`price-editor.js`), `LazilyRefreshDatabase` en tests de feature.
- **Policies y scopes de autorización:** clases Policy para los modelos principales; scope `scopeActive()`; reemplazo de `abort_unless()` por autorización de policy.

---

## [0.8.5] — 2026-06-15

### Fix — Compras: botón aplicar sugerencias, decimales y dropdown de producto

#### Corregido

- **Botón "Aplicar sugerencias de IA":** el bulk apply fallaba silenciosamente cuando los renglones sugeridos tenían unidad de compra `u` e ingrediente en `kg`/`gr`. Ahora `PurchaseLineRecorder::apply()` intenta parsear la descripción del renglón (patrón "X 25 Kg", "x5lts", etc.) para derivar la cantidad por unidad, replicando la lógica del frontend. Solo requiere intervención manual cuando la descripción tampoco tiene cantidad. El mensaje de feedback distingue entre líneas aplicadas, salteadas por unidades y errores inesperados.
- **Decimales del cálculo de costo unitario:** la división entre precio y factor de conversión producía floats con 12+ decimales en el input visible. Los tres puntos de asignación de `unitCost` en el JS ahora redondean a 2 decimales (`Math.round(x * 100) / 100`). El input usa `step="0.01"` y `data-maxdecimals="2"`.
- **Dropdown de producto atrapado en scroll:** TomSelect se inicializa con `dropdownParent: 'body'`, evitando que el `overflow-x-auto` del contenedor de la tabla recorte el dropdown cuando hay pocos productos.

#### UX

- Menú lateral y mobile nav: "Gastos Fijos" renombrado a "Gastos".

---

## [0.8.4] — 2026-06-15

### Recetas — Selector de lista de precios en el detalle de receta

#### Agregado

- **Selector de lista de precios en el sidebar de `/recipes/{id}`:** cuando el tenant tiene más de una lista activa, aparece un dropdown junto al título "Precio de venta" que permite cambiar de lista sin salir de la receta. Al cambiar la lista, el campo de precio y la barra de margen se actualizan reactivamente con el precio guardado en esa lista.
- **Guardado AJAX por lista:** el botón "Guardar precio" (antes submit de formulario HTML) ahora llama a `PATCH /recipes/{id}/prices/{priceList}` y muestra estado "Guardando…" mientras espera. No hay recarga de página.

#### Técnico
- `RecipeController::show()` carga todas las listas activas y los precios de la receta en todas ellas (`$priceLists`, `$allPrices`).
- `$defaultPrice` se deriva de `$allPrices` en lugar de una query extra.
- Alpine.js: nuevos datos `priceLists`, `allPrices`, `selectedListId`, `savingPrice`; nuevos métodos `changeList()` y `savePrice()`.

---

## [0.8.3] — 2026-06-15

### Fix — Inconsistencia en costo/u entre dashboard y detalle de receta

#### Corregido

- **`cost_per_unit` sin overhead en el dashboard y endpoint de precios:** el dashboard de rentabilidad y la respuesta AJAX de edición inline de precios calculaban el costo por unidad usando solo costos directos (ingredientes + mano de obra + packaging + subrecetas), omitiendo los gastos fijos prorrateados por horas. La vista de detalle de receta sí los incluía, generando valores distintos entre las dos pantallas.
- **Solución:** `RecipeCostCalculator::calculate()` ahora devuelve también `total_labor_hours`. `DashboardController` y `RecipePriceController` suman `fixedCost = labor_hours × overhead_per_hour` antes de derivar `cost_per_unit` y márgenes, igualando el criterio de la vista de receta.

---

## [0.8.2] — 2026-06-15

### Refactor — Calidad interna del módulo de listas de precios

#### Mejorado

- **`RecipePriceWriter::set()` sin lazy load innecesario:** el log de precio usaba `$recipePrice->recipe->priceLogs()` cargando la relación desde el modelo intermedio cuando `$recipe` ya estaba disponible como parámetro. Ahora usa `$recipe->priceLogs()` directamente, eliminando 1 SELECT por receta en operaciones bulk.
- **Transacciones en aplicación masiva de sugerencias:** `applySuggestions()` y `applyAllSuggestions()` envuelven sus loops de escritura en `DB::transaction()`. Si alguna escritura falla a mitad de camino, ningún precio queda persistido parcialmente.

---

## [0.8.1] — 2026-06-15

### Listas de precios — Aplicación masiva de sugerencias + selector en recetas

#### Agregado

- **Botón "Aplicar sugerencias" por lista (`/price-lists`):** en la columna de acciones de cada lista no-base con % de ajuste activa, aplica de una sola vez el precio sugerido a todas las recetas que todavía no tienen precio en esa lista. No sobreescribe precios ya cargados.
- **Botón "Aplicar sugerencias" en la matriz (`/price-lists/matrix`):** aplica las sugerencias pendientes en todas las listas con % de ajuste en un único click. Redirige con el conteo de precios aplicados.
- **Selector de lista de precios en el listado de recetas (`/recipes`):** dropdown en el formulario de filtros (visible cuando hay más de una lista activa) que recarga la tabla mostrando los precios de la lista elegida. La columna de precio cambia de nombre, el ordenamiento y la edición inline operan sobre la lista seleccionada. El selector se preserva al buscar y filtrar.

#### Técnico
- Suite completa: **270 tests**, todos verdes

---

## [0.8.0] — 2026-06-12

### Listas de precios

#### Agregado

- **Listas de precios por tenant (`/price-lists`):** cada negocio define sus propias listas (ej. Mostrador, Mayorista, Cafeterías) con CRUD en modales. Cada lista puede tener un **% de ajuste opcional** sobre la lista base que pre-llena precios sugeridos.
- **Lista "General" (base):** se crea automáticamente por tenant (lazy vía `Tenant::defaultPriceList()`) y absorbe los `selling_price` existentes en la migración de datos. No se puede desactivar y no acepta % de ajuste.
- **Precios por receta y lista (`recipe_prices`):** un monto fijo por receta en cada lista, con histórico de cambios en `recipe_price_logs` (mismo patrón que los price logs de insumos).
- **Matriz de precios (`/price-lists/matrix`):** vista comparativa receta × lista con costo/u de referencia, edición inline por celda, margen % con semáforo y precios sugeridos en gris para celdas vacías (se confirman con un click + Enter).
- **Selector de lista en el dashboard de rentabilidad:** permite ver márgenes y semáforo según cualquier lista activa; por defecto muestra la lista base.
- **Endpoint `PATCH /recipes/{recipe}/prices/{priceList}`:** reemplaza a `PATCH /recipes/{recipe}/selling-price` y devuelve el mismo shape JSON (precio + margen formateado + color).

#### Cambiado

- **`recipes.selling_price` eliminada:** `recipe_prices` es la única fuente de verdad del precio de venta. Los formularios de receta (crear, editar, precio en detalle) siguen mostrando el campo, que ahora escribe en la lista base. Copiar una receta duplica sus precios en todas las listas.

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

## [0.7.3] — 2026-06-09

### Compras — Selects con buscador y límite de decimales en inputs

#### Agregado

- **Tom Select en selects de compras:** todos los selects del módulo de compras cuentan ahora con campo de búsqueda integrado para agilizar la carga:
  - Selector de proveedor en el modal "Nueva compra" y en la pantalla de revisión de factura escaneada.
  - Selector de insumo/descartable en la vista de vinculación (`/purchases/{id}/match`), donde la lista puede tener cientos de opciones.
- **Límite de 4 decimales en inputs de precio y cantidad:** todos los inputs numéricos de los formularios de compra (alta de renglón, edición de renglón, revisión de factura escaneada y campo de costo unitario en la vista match) limitan la entrada a un máximo de 4 lugares decimales en tiempo real. `step` también actualizado a `0.0001` para mantener consistencia.

#### Cambiado

- **Vista revisión de factura (`scan/review`):** el select de proveedor migró de renderizado dinámico con Alpine `x-for` a opciones server-side (`@foreach`), lo que permite inicializar Tom Select correctamente. La integración con el evento `supplier-created` (alta rápida de proveedor) se mantiene vía la API de Tom Select (`addOption` + `setValue`).
- **`resources/css/app.css`:** importa el CSS de Tom Select con overrides de estilos para que los dropdowns de búsqueda sean coherentes con el diseño Tailwind (colores `corteza`, `horno`, `miga`).
- **`resources/js/app.js`:** importa y expone `TomSelect` como `window.TomSelect`; inicializa automáticamente los selects con `data-searchable`; registra listener global para limitar decimales en inputs con `data-maxdecimals="4"`.

---

## [0.7.2] — 2026-06-09

### Compras — Vinculación de renglones con catálogo (Fase 2)

#### Agregado

- **Vista dedicada `/purchases/{id}/match`:** pantalla exclusiva para vincular los renglones de una factura con insumos o descartables del catálogo. Separada del detalle de compra para mantener cada vista enfocada en una sola tarea.
- **Botón de vinculación en el índice de compras:** ícono de cadena (link) por fila, visible solo cuando la compra tiene renglones. Ámbar cuando hay renglones pendientes de vincular; verde cuando todos están aplicados. El `title` muestra cuántos renglones quedan pendientes.
- **Cálculo reactivo del costo unitario (Alpine.js):** al seleccionar un insumo en la vista de match, se calcula y muestra el costo por unidad del catálogo antes de aplicar.
  - Unidades compatibles (kg↔gr, L↔ml, etc.): conversión automática, sin campo adicional.
  - Unidades incompatibles (u → kg/gr/L): aparece campo divisor editable ("÷ N unidad/u").
  - El campo costo por unidad es siempre editable antes de confirmar.
- **Parser de descripción para cantidad de paquete:** al seleccionar un insumo con unidades incompatibles, el sistema analiza la descripción del renglón buscando patrones como "X 25 Kg", "x5lts", "× 200 ml". Si detecta una cantidad compatible con el insumo, pre-llena el divisor automáticamente con marca ✦ (ámbar) para que el usuario lo verifique antes de aplicar.
- **`PurchaseLineRecorder::applyWithCost()`:** nuevo método que aplica un costo unitario explícito (provisto por el usuario) sin pasar por la conversión de unidades. Permite imputar compras donde la unidad de la factura (unidad/bolsa) es incompatible con la unidad del insumo (kg/gr).
- **`applied_count` en el índice de compras:** el query de listado ahora incluye el conteo de renglones ya aplicados vía `withCount`, usado para determinar el color del botón de vinculación sin queries adicionales.

#### Cambiado

- **`PurchaseController::matchLine()`:** acepta campo opcional `unit_cost` (decimal). Si está presente, usa `applyWithCost()` en lugar de `apply()`, permitiendo aplicar costos calculados por el usuario para unidades incompatibles.
- **Vista de match:** columna "Cantidad (factura)" eliminada. El foco es la descripción del producto, el precio unitario de la factura y el insumo sugerido con su costo editable.
- **Select de insumos en vista de match:** muestra la unidad del catálogo entre paréntesis (ej: "Harina 000 (kg)") para que el usuario pueda identificar rápidamente la unidad de medida antes de vincular.

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
