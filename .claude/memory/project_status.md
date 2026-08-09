---
name: Levado — Estado actual del proyecto
description: Progreso al 15/07/2026
type: project
---
# Estado del proyecto — 17 de julio 2026

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
- **Versión actual:** 0.12.8. La fuente de verdad es **`config/app.php`**; `package.json` y `package-lock.json` la espejan (ver [[project-architecture]] → Convenciones).
- **Último deploy anotado acá:** 0.11.0 — **mergeada (PR #43) y desplegada en producción el 17/07/2026**. Producción venía de 0.9.x, así que ese deploy le trajo de una v0.10.0 (gastos variables), v0.10.1 (auditoría) y v0.11.0. Se corrió `invoices:relocate`: **165 comprobantes movidos al disco privado**. Los deploys de la serie 0.12.x no quedaron registrados en esta memoria: **no asumir qué versión corre en producción a partir de este archivo.**
- **Al subir una base corregida a producción, el orden es: respaldo → subir la base → desplegar código → `migrate` → `invoices:relocate` → `optimize:clear`.** Si se despliega el código y se corre `migrate` antes de subir la base, la migración del índice único aborta contra los duplicados viejos. Con la base ya subida, `migrate` saltea lo que viene registrado y sólo corre lo que falta. Ojo: el import pisa `sessions` y cierra todas las sesiones.

## Todo lo que está hecho

### v0.12.11 — El navegador recibía el mismo JS una vez por fila (rama `v0.12.11-FIXAlpinePorFila`)
- El objeto Alpine de edición inline vivía **adentro del `@foreach`**: 68 líneas × 20 recetas en el
  dashboard, 47 por **celda** en la matriz (recetas × listas), 37 por fila en ingredientes. Ahora se
  define una vez en `resources/js/rows/` y se registra con `Alpine.data()` en `alpine:init` — es el
  **primer `Alpine.data()` del proyecto**. Ver [[pattern-listados-fuente-unica]] → «El Alpine por fila».
- Medido con 20 registros: dashboard 4266 → 2926 líneas (−31%), matriz 7388 → 3708 (−50%),
  ingredientes 3798 → 3078 (−19%).
- **Bug corregido:** los cortes de margen vivían en 7 lugares con **dos escalas** (60/40/20 en el
  dashboard, 30/15 en `RecipePriceController` y la matriz). Editar un precio inline pintaba el número
  con la otra escala. Enum `MarginTier` como dueño único; el controller devuelve `margin_tier` y no
  `margin_color`. **Cambio visible: los colores de la matriz se corren** (35% pasa de verde a naranja).
- `PriceListMatrixTest` tenía un `assertDontSee('savePrice')` que al extraer el JS **pasaba siempre
  sin proteger nada**; reapuntado y verificado contra la quita del `@can`.
- Verificado en navegador que la edición inline funciona de verdad en las tres pantallas.
- **`packaging/index` y `recipes/index` migradas a `x-data-table` + Alpine extraído en la misma
  pasada** (tenían las dos duplicaciones a la vez): envases 6209 → 3109 líneas (−50%), recetas
  4855 → 2296 (−53%). `stockCell` y `costCell` comparten implementación en `rows/inline-number.js`.
- **Pendiente del frente:** `purchases/show` y `purchases/scan/review`; y 5 listados todavía en
  `x-responsive-table` (`variable-expenses`, `price-lists/index`, `purchases/index`, `fixed-costs`,
  `stock`).

### v0.12.10 — Los listados mandaban cada fila dos veces (rama `v0.12.10-FIXCodigoDuplicado`)
- **Fuente única de render en los listados**: se escribe sólo el `<tr>` y el CSS decide si se ve como
  fila de tabla o como card. Componentes `x-data-table` / `.row` / `.cell` con roles de celda, más
  `x-icon`, `x-list-header` y `x-list-filters` para el HTML repetido de alrededor. Ver
  [[pattern-listados-fuente-unica]] para el mecanismo y las trampas.
- Migradas `ingredients/index` y `labor-types/index`. **Pendientes las otras 7** que siguen en
  `x-responsive-table` (que se mantiene funcionando), más `team/index`, `recipes/show` y
  `purchases/show`, que copian el patrón a mano.
- `DataTableComponentsTest` anclaba la duplicación (`assertSeeInOrder(['X','X']) // card + fila`);
  reescrito para anclar lo contrario, con revert-check.
- **Las cuatro fuentes de la versión estaban desincronizadas** (`config/app.php` 0.12.8,
  `package.json` 0.12.9, lock 0.12.8): el bump a 0.12.10 las realinea.

### v0.12.8 — El botón «Crear categoría» era intocable en mobile (rama `claude/mobile-gastos-variables-button-5v1wic`)
- El alta inline de categoría vivía en una columna de un `grid-cols-3` **fijo**; con `flex-1` + `min-width:auto` la fila se desbordaba y el botón caía debajo del campo de fecha (item posterior del grid ⇒ se pintaba encima y comía el tap). Solución: `grid-cols-1 sm:grid-cols-3` + `sm:col-span-2`, y `min-w-0` en el input.
- Aplicado a los 4 modales con ese layout (crear/editar gasto variable, crear/editar costo fijo) + el modal de categorías. **Sin rebuild de assets**: las utilidades ya estaban en el CSS compilado.

### v0.12.7 — Memoria de vinculación de productos (rama `claude/product-linking-invoices-7uqgph`)
- Tabla `supplier_product_links` + servicio `ProductLinkMemory`: la vinculación de un renglón queda guardada **por proveedor** y la próxima factura llega pre-vinculada. Antes vivía sólo en `purchase_lines` y cada escaneo dependía de la IA.
- **Pre-selecciona, nunca aplica** — el renglón recordado queda pendiente (test-ancla). El divisor de unidades incompatibles también se recuerda, lo que hace que «Aplicar N sugerencias» resuelva renglones que antes salteaba siempre.
- **Al desplegar, correr `purchases:backfill-product-links`** (probar antes con `--dry-run`) para sembrar la memoria desde las facturas ya imputadas. Ver [[feature-compras]] → Memoria de vinculación.

### v0.12.0 — Mediano plazo de la auditoría, 1er lote (rama `claude/technical-debt-audit-obzekw`, pendiente de merge)
- **Dashboard sobre caches:** `unit_cost` + nueva columna `recipes.labor_hours` (propagador la mantiene); orden y paginación en SQL con margen/margen % y NULLs al final. Matriz de precios y edición inline de precio también leen el cache. Comando **`recipes:refresh-costs`** para backfill — **correrlo tras el deploy, junto con `migrate`**.
- **DD2 cerrada:** el select del match de compras lista ítems inactivos marcados `(inactivo)` (antes un renglón con ítem dado de baja caía en «sin asociar» y guardarlo revertía stock). En recetas la trampa no existía (catálogos solo de alta).
- **Enum `CatalogItemType`** dueño de `'ingredient'`/`'packaging'` (purchase_lines/stock_*/URLs de stock). Valores en BD sin cambios.
- **Factories nuevas:** Purchase (proveedor hereda tenant), PurchaseLine (+`matchedTo*`), Invitation (+`expired`/`accepted`), MailTemplate. StockMovement sin factory a propósito (ledger = solo StockService).
- Menores: DI de calculator/converter, gate `super-admin` muerto eliminado.
- Tests: helper global `propagateRecipeCosts()` para seeds manuales (los caches no se llenan solos al crear líneas con `::create()`). **465 tests, todos verdes.**
- **2º lote (misma versión):** `RecipeController` 565→236 L (`RecipeLineController` + `RecipeShowViewModel`, sin cambio de contrato); componentes `x-sortable-th` y `x-responsive-table` adoptados en las 9 vistas de índice con markup uniforme. **Pendiente:** `purchases/index` y `price-lists/matrix` (variantes propias, migrarlas a mano), y `recipes/show` (902 L) sigue siendo la vista más grande — partirla en parciales cuando se la vuelva a tocar. **467 tests, todos verdes.**

### v0.11.2 — Páginas de error con branding (rama `claude/technical-debt-audit-obzekw`, pendiente de merge)
- Páginas 404/403/419/500/503 propias en castellano (`resources/views/errors/`), sobre un layout `minimal.blade.php` **autónomo** (sin Vite/componentes/fuentes externas: una página de error no puede depender de nada rompible). El 404 explica el caso cross-tenant de v0.11.1; el 403 filtra el default en inglés de Laravel y muestra los mensajes propios de `abort()`. 3 tests nuevos. **457 tests, todos verdes.**

### v0.11.1 — Aislamiento estructural entre tenants (PR #44, mergeada a master)
- **Trait `BelongsToTenant`** (prioridad #1 del mediano plazo de la auditoría) en los 15 modelos de dominio con `tenant_id`: global scope + auto-fill de `tenant_id` cuando hay tenant resuelto. Sin tenant resuelto (admin, artisan, tests) no aplica — esos contextos scopean explícito. **Excluidos a propósito:** `TenantUser` (se consulta antes de resolver el tenant; `isSuperAdmin()` debe ver Levado HQ al impersonar), `TenantSetting`, `AdminAuditLog`.
- `SetTenantContext` adelantado en la prioridad de middleware (antes de `SubstituteBindings`, en `bootstrap/app.php`): el scope aplica también al route-model binding → **recurso de otro tenant = 404 directo** (antes 403 vía policy). Policies y reglas `exists` scopeadas quedan como segunda capa; la convención `$tenant->relación()` sigue vigente.
- **Mitiga S3:** impersonando al tenant A, los recursos del tenant B dan 404 incluso para super admin (`Gate::before` saltea policies pero no el scope). Anclado con test.
- 16 tests de aislamiento actualizados (403→404), 6 anclas nuevas en `BelongsToTenantTest`. **454 tests, todos verdes.**
- Al escribir tests: tras un request, el tenant queda bound y `Model::find()` sobre datos de otro tenant devuelve null — usar `withoutGlobalScope('tenant')` para asertar sobre registros ajenos.

### v0.10.1 — Auditoría técnica + quick wins de seguridad
- **`AUDITORIA_DEUDA_TECNICA.md`** en la raíz del repo: auditoría externa completa (seguridad, arquitectura, BD, rendimiento, frontend, SaaS) con plan priorizado en 3 etapas. La etapa de **corto plazo** se implementó completa en esta versión.
- Implementado (quick wins): comprobantes de compras al **disco privado** (+ comando `invoices:relocate` y fallback legacy al público), `throttle:10,1` en el escaneo IA, resolución de tenant determinista (`orderBy('tenant_id')`), **unique** `(tenant_id, supplier_id, invoice_number)` en purchases + validación con mensaje amable en los 3 requests, `Tenant::totalFixedCosts()`/`overheadPerHour()` como único dueño de la fórmula (cierra la deuda DD3 en los 4 controllers), **scoped bindings** en rutas anidadas de líneas (404 en vez de 403 para línea ajena; params renombrados `{line}` → `{ingredientLine}`/`{packagingLine}`/`{laborLine}`/`{subrecipeLine}`, URLs sin cambios), `getSetting()` memoizado por instancia, transacción en `RecipeController::copy()`, checklist de producción en `.env.example`, sin logs de debug en invitaciones.
- **Pendiente del plan — mediano plazo:** trait `BelongsToTenant` con global scope (prioridad #1), dashboard sobre `unit_cost` cacheado, componente `x-data-table`, partir `RecipeController` + ViewModel, morph map para polimorfismos, factories faltantes (Purchase/PurchaseLine/StockMovement/Invitation/MailTemplate), DD2 (selects con ítems inactivos en recetas y líneas de compra).
- **Pendiente del plan — largo plazo:** documentado en detalle en `.claude/memory/plan-largo-plazo.md` (panel administrativo nuevo con impersonación acotada y cuotas, colas al migrar de hosting, selector de tenant, cache por tenant, baja/exportación).
- **Decisión (17/07/2026):** el hallazgo S3 de la auditoría (impersonación de super admin sin acotar al tenant impersonado, sin auditoría de accesos cruzados) **no se parchea sobre el backoffice actual**: el plan es construir un **panel administrativo completo nuevo y retirar el backoffice actual**. S3 se resuelve como requisito de diseño de ese panel (impersonación acotada + auditoría desde el día uno).
- **Al deployar:** correr `php artisan migrate` (la migración del unique aborta con detalle si hay facturas duplicadas preexistentes) y `php artisan invoices:relocate`.
- **400 tests, todos verdes** (4 nuevos)

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

### v0.11.0 — Comprobantes en Gastos Variables
- Los gastos variables guardan su comprobante (foto o PDF), con dos caminos como en Compras: adjuntar sin más, o **"Leer con IA"** que prellena descripción, monto, fecha, proveedor y categoría. El botón es opcional: sin tocarlo el comprobante se guarda igual (mismo criterio que v0.9.1, por los tickets manuscritos). Los gastos fijos no lo tienen: son montos mensuales sin fecha propia.
- **El scan vive dentro del modal, no en una pantalla aparte.** Un gasto son 4 campos: el modal ya es la pantalla de revisión, así que sube por `fetch` y prellena ahí mismo. Compras necesita su `scan/review` porque una factura son 30 renglones que hay que asociar al catálogo uno por uno — la asimetría es deliberada.
- **Un comprobante de varios ítems se resume en la descripción** (un gasto es un monto único, no tiene renglones).
- **`ReceiptStorer::safePath()` es el guard de la feature:** tras el scan el archivo ya está en disco y el form lo referencia por path en un hidden input, o sea que es controlable por el cliente. Sin el guard, un path armado a mano apunta un gasto propio al comprobante de otro negocio. Mismo patrón que `PurchaseScanController::safeImagePath()`. **Toda feature que devuelva un path al navegador y lo acepte de vuelta necesita este guard.**
- El archivo se guarda **antes** de llamar a la IA y se borra si la lectura falla (no deja huérfanos, no le hace perder la foto al usuario). El scan responde 422 con el mensaje ya en español.
- Doble guardado evitado con: el botón limpia el `<input type="file">` tras el scan, y el controller usa `hasFile()` / `elseif filled(path)`.
- **Un `<input type="file">` no se puede repopular tras un error de validación** (restricción del navegador). El hidden del path sí sobrevive vía `old()`; para el adjunto manual hay que avisarle al usuario que re-adjunte. Ojo: la lista de `$errors->hasAny([...])` que reabre el modal debe incluir el campo del archivo, si no un mime rechazado se pierde sin mostrarse.
- **`SupplierMatcher` pliega acentos (`Str::ascii`) antes de comparar:** los comprobantes se imprimen en mayúsculas y sin tildes ("PANIFICACION GUEMES S.A.") y el proveedor se carga como se escribe ("Panificación Güemes"). Sin plegarlos nunca matchean. Arregla de paso el matcheo en Compras, que arrastraba la limitación.
- **Compras no se tocó** (salvo extraer `SupplierMatcher`): `storeInvoiceImage()` sigue duplicado en sus 2 controllers y `ExpenseReceiptExtractor` duplica ~40 L de la mecánica HTTP de `InvoiceExtractor`. Deuda asumida: refactorizar el módulo más crítico dentro de una feature de Gastos mezclaba un cambio de comportamiento con uno estructural. Si aparece un 4º consumidor de visión, ahí sí extraer un `ClaudeVision`.
- Riesgo conocido no resuelto: escanear y cerrar el modal sin guardar deja el archivo huérfano en disco. Compras tiene lo mismo (abandonar el review). **Confirmado en la verificación manual: 4 scans de prueba dejaron 4 archivos huérfanos.** Candidato a un comando de limpieza.
- **Comprobantes en `Storage::disk('local')` (privado), nunca en `public`.** El symlink `/storage` deja el disco público accesible sin login y un comprobante es dato fiscal. Sin fallback al público (a diferencia de compras): gastos nace en el privado, no hay legacy que relocalizar. Hay 2 tests que fijan que el público quede vacío. **Toda feature que guarde archivos de usuario va al disco privado + ruta autenticada.**
- **Un endpoint que llama a la IA necesita `throttle`**: es plata directa por request y bloquea un worker hasta 60 s. Tanto `purchases.scan` como `variable-expenses.scan` usan `throttle:10,1`.
- **Al verificar visión, mirar SIEMPRE la imagen que recibe la API antes de culpar al prompt.** Es el error más caro de esta sesión: dos rondas ajustando el prompt por lecturas alucinadas (montos inexistentes, ítems inventados, fechas raras) que venían de un comprobante dado vuelta 180° en el fixture. Con la imagen derecha, el prompt **sin tocar** acierta todo. Guardar la imagen que se manda a la API y abrirla es un chequeo de 30 segundos que ahorra horas.
- **Medir el contrafáctico antes de afirmar que un cambio de prompt arregla algo.** `git show <commit-viejo>:archivo > archivo`, correr N veces contra la API real, restaurar con `git checkout archivo`. Ojo: eso mide contra el fixture, y si el fixture es sintético la medición miente (ver el punto siguiente).
- **DECISIÓN (17/07/2026): los comprobantes MANUSCRITOS no se leen más con IA.** Se cargan a mano y la foto queda sólo como respaldo. Motivo, medido sobre los presupuestos reales del talonario Taloffice: el modelo no los lee, inventa. Casos concretos: leyó "105,000" como **105** (una compra de 105 mil pesos anotada como 105 pesos), y una fecha manuscrita `10|6|26` como **2027-12-16**. Al releerlos con el pipeline arreglado empeoraban en vez de mejorar. Son los tickets del proveedor de las migas y las supremas. **La IA sirve para facturas impresas; para las manuscritas, carga manual + foto.**
- **El escáner era la única fuente de las 25 compras duplicadas** (ninguna carga manual). Se limpiaron el 17/07/2026 mirando las 12 facturas una por una: 14 bajas + 2 totales corregidos (id=121 → 38.000, id=140 → 105.000). Respaldo JSON previo en el scratchpad de esa sesión. Los renglones estaban bien en ambos casos, así que los costos de insumos nunca se vieron afectados.
- **Un fixture sintético NO mide la precisión del lector de visión: da falsos verdes.** Contra un JPEG generado con GD el prompt daba 5/5 en fecha y monto, y de ahí se concluyó (mal, dos veces) que no había bug. **Los datos reales de Confitería Orfano lo desmienten:** 25 compras duplicadas, *todas* creadas por el escáner, donde el mismo papel escaneado dos veces dio resultados distintos:
  - **Fecha día/mes dada vuelta:** factura `0004-00084240` con `2026-02-06` en una copia y `2026-06-02` en otra. El correlativo prueba cuál vale (`84240` va antes de `84273`, del 03/06): la real es 02/06, o sea que "02/06/2026" se leyó a la americana. Mismo patrón en `00003-00003084` (07/01 vs 01/07).
  - **Monto con un cero de menos:** factura `45649`, 3.800 vs 38.000.
  - **Precio distinto del mismo renglón** entre dos escaneos: "Miga" a 9.500 y a 9.000.
  - Por eso `InvoiceExtractor` y `ExpenseReceiptExtractor` llevan la regla DÍA/MES/AÑO y el control del total. **Para medir de verdad hacen falta fotos reales, no fixtures generados.**
- **El escáner de compras venía creando duplicados en producción**: el aviso del front era sólo informativo y no había constraint. Lo cierra el índice único de v0.10.1, pero la migración **aborta hasta limpiar los duplicados preexistentes** (11 grupos / 25 compras en el tenant 3). Sin movimientos de stock asociados; los 47 renglones ya imputaron costos y borrar la compra no los revierte.
- **Para probar formato de fecha usar una fecha AMBIGUA** (05/03) y no una imposible (14/05): con día > 12 no hay nada que desambiguar y el test no prueba nada.
- **Giro de comprobantes + fix de EXIF (mismo release).** Botones de girar de a 90° en los 5 puntos de captura: alta y edición de gastos, y las 3 pantallas de Compras (escaneo, alta manual, edición). `window.rotateInvoiceImage(file, degrees)` en `image-compress.js`, componente `x-rotate-button` (props `direction`/`method`).
- **Trampa del EXIF (aplica a cualquier captura de foto):** los teléfonos guardan la orientación en el EXIF, no en los píxeles, y `canvas.toBlob()` **descarta el EXIF** al re-encodear. Hay que decodificar con `createImageBitmap(file, { imageOrientation: 'from-image' })` para hornear la orientación en los píxeles **antes** del re-encode; el default del estándar cambió con el tiempo, así que se declara explícito. **Ojo: es blindaje, no un bug observado** — verificado que Chrome 148 ya aplicaba el EXIF solo, incluso con `'none'`. No repetir la afirmación de que "arregla fotos giradas" sin medirlo en el navegador objetivo.
- **`InvoiceImagePreparer` aplica la orientación EXIF (arreglado en v0.10.1).** Antes `imagecreatefromstring` la ignoraba y `imagejpeg` la descartaba: en el camino sin JS (scripts off, o `compressInvoiceImage` devolviendo el original tras un error, p.ej. OOM en gama baja) la foto de 12MP se achicaba acostada **y perdía el EXIF para siempre** — irreversible. Comprobado end-to-end: mismo archivo crudo al endpoint de scan devolvía $8.500 y fecha inventada; con el fix devuelve el total real. **Regla general: si vas a re-encodear una foto con GD, leé el EXIF ANTES — `imagejpeg()` lo tira.** Se cubren las 8 orientaciones (incluidos los espejados 2/4/5/7); `imagerotate` es antihorario y el EXIF se define en horario.
- **Cada giro re-encodea desde el archivo original**, acumulando el ángulo en el estado (`baseFile` + `rotation`). Girar sobre el resultado anterior apila una generación de pérdida JPEG por click.
- La lógica de `x-data` del picker queda duplicada en las 5 vistas (misma decisión que v0.9.1). Unificarla exige renombrar el estado de las 3 vistas de Compras (`invoiceFileName` vs `receiptName`) y **no hay tests de browser que respalden ese refactor** — la suite sólo verifica que el markup renderice (`ReceiptRotationTest`). Si se agrega Pest browser testing, ahí sí conviene extraer un `x-receipt-picker`.

### v0.10.0 — Gastos Variables
- La pantalla de Gastos ahora tiene dos pestañas: **Gastos Fijos** (los de siempre, que alimentan el overhead por hora) y **Gastos Variables** (gastos ocasionales o imprevistos, puramente administrativos, que **no intervienen en ningún cálculo de costo, margen ni receta**). Preparados para los reportes y el análisis financiero futuros.
- Un gasto variable es un evento puntual: nombre, categoría, **fecha**, **monto único** y **proveedor opcional** (del listado de proveedores ya existente). Sin `active`, sin historial de precios. Catálogo de categorías propio, separado del de fijos. Tiene borrado (los fijos no).
- **Trampa del proveedor inactivo (regla general del proyecto):** todo `<select>` de edición debe listar **todos** los proveedores (marcando `(inactivo)`), no sólo los activos. Si lista sólo activos, un proveedor dado de baja no matchea, el select cae en la opción vacía, y guardar cualquier otro campo **borra el proveedor en silencio** (selects opcionales) o **bloquea el guardado** (selects `required`). El filtrado por activo va en la **vista** (`$suppliers->where('active', true)` en el alta), no en el controller, porque el mismo `$suppliers` alimenta alta y edición. Arreglado en v0.10.0 en gastos variables, ingredientes, descartables y compras, con tests de regresión en las 4 suites.
- **El mismo patrón sigue sin revisar en otras entidades con `active`:** `RecipeController:178-180` pasa `$ingredients`/`$packagings`/`$laborTypes` activos a `recipes/show`, y `PurchaseController:357-358` a las líneas de compra. Una receta con una línea cuyo ingrediente se dio de baja podría sufrir lo mismo. Pendiente de encarar.
- **Decisión de arquitectura clave:** tabla separada `variable_expenses` en vez de una columna `type` en `fixed_costs`. El overhead se calcula con `$tenant->fixedCosts()->active()->sum('monthly_amount')` **duplicado en 5 lugares** (`DashboardController:50`, `RecipePriceController:43`, `RecipeController:182`, `BusinessController:19` y un getter Alpine en `recipes/show.blade.php:112`); con una columna `type` los 5 pasaban a necesitar `->where('type','fixed')` y olvidar uno habría inflado el costo de todas las recetas en silencio. Con tabla separada el aislamiento es estructural y esos 5 call sites no se tocaron. Además no hubo migración de datos.
- **La pestaña es el selector de tipo** (no hay selector en el formulario): los campos divergen entre tipos, así que un gasto no se puede convertir de uno a otro.
- Componentes nuevos reutilizables: `x-expense-tabs` (data-driven, un tipo nuevo = una línea) y `x-expense-categories-modal` (extracción parametrizada del modal de categorías, compartido por ambas pestañas).
- Deuda deliberada: `VariableExpenseCategoryController` duplica ~90 L de `FixedCostCategoryController`. Con 2 consumidores una abstracción es prematura; extraer a base class cuando aparezca un tercer tipo.
- Pendiente de considerar: la entrada "Gastos" vive en el grupo "Costos" del sidebar, y los gastos variables explícitamente no son costos. Revisar cuando lleguen los reportes financieros.
- **Corregido en la misma versión:** el bug del proveedor inactivo en ingredientes, descartables y compras (casos reales en datos: compra #48 y los ingredientes "Azucar"/"Limon esc"), y un **agujero de aislamiento entre tenants**: `Store`/`Update` de ingredientes y descartables validaban `supplier_id` con `exists:suppliers,id` sin scope de tenant y sin el `abort_unless` que sí tiene `PurchaseController:81`, permitiendo asignarse un proveedor ajeno y ver su nombre en el listado propio. Confirmado con un test que fallaba y ahora pasa.
- **396 tests, todos verdes** (44 nuevos, incluido el ancla de que los gastos variables no mueven el overhead y los de regresión del proveedor inactivo).

### v0.9.3 — Recetas: editar unidad de una línea de ingrediente
- Ya existía (desde v0.4.0) poder elegir, al agregar un ingrediente a una receta, una unidad distinta a la unidad base del ingrediente (con conversión automática de costo vía `UnitConverter`). Faltaba poder cambiar esa unidad en una línea ya creada — solo se podía editar la cantidad.
- `RecipeController::updateIngredientLine()` ahora acepta y valida `unit` igual que al agregar; el `<select>` de unidad en `/recipes/{id}` (antes un badge de solo lectura) se habilita en edición, filtrado a unidades compatibles. Al cambiar la unidad, la cantidad se recalcula automáticamente en el cliente para mantener la misma proporción (ej: 2 kg → 2000 gr).
- Fix de Alpine.js: `x-model` en `<select>` con `<option>` de `x-for` no reflejaba el valor inicial; se resolvió con `:selected` explícito por opción + `@change` propio.
- El módulo de "Producción" (descuento de stock al fabricar) sigue sin construir — fuera de alcance a pedido del usuario.
- **352 tests, todos verdes**

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
- Panel administrativo completo nuevo (reemplaza el backoffice actual; ver `project-backoffice.md`) — prerequisito para apertura pública; resuelve S3 de la auditoría por diseño
- Etapa 3: Productos y Stock
