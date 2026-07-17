# Auditoría Técnica Integral — Levado (app.levado.com.ar)

**Fecha:** 16 de julio de 2026
**Alcance:** código completo del repositorio (v0.10.0, rama `master` en `81e1c71`)
**Enfoque:** auditoría externa — no se asume que la documentación interna esté completa.

---

## 1. Resumen ejecutivo

Levado es un SaaS multi-tenant (Laravel 13, PHP 8.3, MySQL, Blade/Tailwind/Alpine) para costeo de recetas de panaderías, con módulos de compras (con digitalización por IA), stock (ledger inmutable), listas de precios y backoffice de administración.

**Veredicto general: el estado del código es notablemente bueno para un proyecto en fase MVP.** La auditoría encontró:

- Servicios de dominio bien delimitados (`StockService` como único punto de escritura del ledger, `RecipeCostPropagator` con BFS y control de ciclos, `PurchaseLineRecorder`), policies por modelo, form requests, enums tipados, transacciones donde importan, locks pesimistas en stock, escapado de `LIKE`, validaciones `exists` con scope de tenant, y 396 tests verdes incluyendo suite de aislamiento entre tenants.
- La deuda técnica **documentada** en `.claude/memory/` es honesta y coincide con lo que se ve en el código.

Los riesgos reales detectados se concentran en tres frentes:

1. **Aislamiento de tenants por convención, no por estructura** — el scoping es manual en ~104 puntos; ya hubo una fuga real (corregida en v0.10.0). No hay red de seguridad estructural (global scope / scoped bindings).
2. **Exposición de comprobantes fiscales** — las imágenes de facturas se guardan en el disco `public`; el controller que las sirve exige autorización, pero el symlink `/storage` las deja accesibles sin autenticación a quien conozca la URL.
3. **Techo de escalabilidad conocido** — dashboard que recalcula todo en memoria, propagación de costos síncrona, llamada a la API de IA dentro del request HTTP, sin colas ni caché. Aceptable hoy (hosting compartido, pocos tenants), pero es la primera pared con la que va a chocar el crecimiento.

No se encontró ningún problema **crítico** activo (explotable hoy con impacto grave). Hay 3 hallazgos de severidad **Alta**, 9 **Media** y el resto **Baja**.

---

## 2. Principales riesgos encontrados

| # | Riesgo | Severidad | Área |
|---|--------|-----------|------|
| R1 | Aislamiento multi-tenant sin defensa estructural (scoping manual repetido en ~104 sitios) | Alta | SaaS / Seguridad |
| R2 | Comprobantes de facturas en disco `public` accesibles sin autenticación vía `/storage` | Alta | Seguridad |
| R3 | Endpoint de escaneo con IA sin rate limiting ni cuota por tenant (costo económico directo) | Alta | Seguridad / Costos |
| R4 | Dashboard: recálculo completo de costos en PHP + orden y paginación en memoria | Media | Rendimiento |
| R5 | Propagación de costos síncrona en el request (sin colas) | Media | Rendimiento / Escalabilidad |
| R6 | Resolución de tenant no determinista si un usuario pertenece a más de un tenant | Media | SaaS |
| R7 | Sin restricción UNIQUE en BD para facturas duplicadas (el chequeo es solo advisory en el front) | Media | Base de datos |
| R8 | `Gate::before` da acceso total a super admins a datos de cualquier tenant, incluso impersonando otro | Media | Seguridad |

---

## 3. Deudas técnicas documentadas (verificadas en código)

Registradas en `.claude/memory/project_status.md` y confirmadas por esta auditoría:

| ID | Deuda | Ubicación | Estado verificado |
|----|-------|-----------|-------------------|
| DD1 | `VariableExpenseCategoryController` duplica ~90 líneas de `FixedCostCategoryController` (deliberada, esperar tercer consumidor) | `app/Http/Controllers/VariableExpenseCategoryController.php` | Confirmada — `diff` muestra 98 líneas divergentes solo en nombres |
| DD2 | Patrón "proveedor inactivo en select" sin revisar en recetas y líneas de compra | `RecipeController:178-180`, `PurchaseController:360-361` (método `match`) | Confirmada — `->active()` sigue filtrando los catálogos que alimentan selects de edición |
| DD3 | Suma de overhead (`fixedCosts()->active()->sum('monthly_amount')`) duplicada en 5 lugares | `DashboardController:50`, `RecipePriceController`, `RecipeController:182`, `BusinessController`, `recipes/show.blade.php` | Confirmada |
| DD4 | Recálculo de costos síncrono (sin queue workers en Hostinger) | `RecipeCostPropagator`, mails síncronos | Confirmada — decisión consciente de infraestructura |
| DD5 | Entrada "Gastos" en grupo "Costos" del sidebar cuando los gastos variables no son costos | Sidebar / navegación | Menor, UX |

Esta documentación es un activo: pocas bases de código mantienen un registro de deuda tan fiel.

---

## 4. Deudas técnicas detectadas por la auditoría (no documentadas)

### 4.1 Seguridad

#### S1 — Comprobantes de facturas en disco público
- **Severidad: Alta** · **Esfuerzo: Bajo**
- **Ubicación:** `PurchaseController::storeInvoiceImage()` (línea 291), `PurchaseScanController::scan()` (línea 60), `config/filesystems.php`.
- **Descripción:** las imágenes/PDF de facturas se guardan con `Storage::disk('public')`. El controller que las sirve (`invoiceImage()`) sí exige `authorize('update')`, pero el disco público implica que con `php artisan storage:link` cualquier persona **sin autenticación** puede descargar `https://app.levado.com.ar/storage/purchases/{tenant_id}/{uuid}.jpg`. El UUID dificulta adivinar, pero la URL puede filtrarse (historial, logs, proxies) y no caduca nunca.
- **Impacto técnico:** bypass del control de acceso ya implementado.
- **Riesgo de negocio:** exposición de datos fiscales de clientes (CUIT, precios de proveedores, volúmenes de compra) — sensible entre competidores del mismo rubro.
- **Recomendación:** mover a un disco privado (`local`) y servir siempre por `invoiceImage()` (que ya existe y funciona). Migración: mover archivos + actualizar `invoice_image_path`. No usar `storage:link` en producción, o excluir `purchases/`.

#### S2 — Endpoint de escaneo IA sin rate limiting ni cuota
- **Severidad: Alta** · **Esfuerzo: Bajo**
- **Ubicación:** `routes/web.php` (`POST purchases/scan`), `PurchaseScanController::scan()`.
- **Descripción:** cada request dispara una llamada a la API de Anthropic (con imagen de hasta 10 MB, timeout 60 s). Solo protegen `auth` + rol. No hay `throttle`, ni cuota mensual por tenant, ni registro de consumo.
- **Impacto técnico:** un usuario (o un script con sesión válida) puede saturar workers PHP-FPM y quemar crédito de API sin límite.
- **Riesgo de negocio:** costo económico directo e ilimitado; DoS del hosting compartido.
- **Recomendación:** `->middleware('throttle:10,1')` como mínimo inmediato; a mediano plazo, contador de escaneos por tenant/mes en `tenant_settings` con límite configurable (base para planes de suscripción).

#### S3 — `Gate::before` + impersonación anulan las fronteras de tenant para super admins
- **Severidad: Media** · **Esfuerzo: Bajo**
- **Ubicación:** `AppServiceProvider:43-47`, `SetTenantContext:40-42`.
- **Descripción:** `Gate::before` devuelve `true` para todo super admin, así que **ninguna policy se evalúa** para ellos. Impersonando al tenant A, un super admin puede abrir por URL recursos del tenant B (`RecipePolicy` nunca corre). Es coherente con el rol, pero: (a) mezcla datos de dos tenants en una misma sesión de impersonación sin advertencia, y (b) esos accesos cruzados no quedan auditados.
- **Recomendación:** durante impersonación, limitar el bypass al tenant impersonado (verificar `tenant_id` del recurso contra la sesión) y registrar en `admin_audit_logs` los accesos a recursos fuera del tenant impersonado.

#### S4 — Resolución de tenant no determinista con múltiples membresías
- **Severidad: Media** · **Esfuerzo: Bajo**
- **Ubicación:** `SetTenantContext:44` — `$user->tenantUsers()->where('active', true)->value('tenant_id')`.
- **Descripción:** sin `ORDER BY`, si un usuario pertenece a dos tenants el tenant resuelto depende del plan de ejecución de MySQL. El selector de tenant está previsto pero no construido; mientras tanto la consulta debería ser determinista.
- **Recomendación:** agregar `->orderBy('id')` (o un flag `is_primary`), y opcionalmente un guard que alerte si hay más de una membresía activa.

#### S5 — Falta de restricción UNIQUE para facturas duplicadas
- **Severidad: Media** · **Esfuerzo: Bajo**
- **Ubicación:** `create_purchases_table` (solo índices no únicos), `PurchaseController::checkDuplicate()`.
- **Descripción:** el duplicado se detecta solo con un chequeo JS advisory previo al submit. Dos submits simultáneos, o un cliente que ignore el aviso, insertan la misma factura dos veces → costos y stock duplicados.
- **Recomendación:** índice único `(tenant_id, supplier_id, invoice_number)` (con `invoice_number NOT NULL`; los null quedan fuera del unique en MySQL, lo cual es correcto para compras sin comprobante) + manejo del `QueryException` con mensaje amable.

#### S6 — Higiene de configuración de producción
- **Severidad: Baja** · **Esfuerzo: Bajo**
- **Descripción:** `.env.example` trae `APP_DEBUG=true`, `APP_LOCALE=en` (la app es es-AR), sin `SESSION_SECURE_COOKIE=true` ni `APP_TIMEZONE=America/Argentina/...`. No es una vulnerabilidad en sí (depende del `.env` real), pero el example es la checklist de deploy de facto.
- **Recomendación:** alinear `.env.example` con los valores esperados de producción comentados, y documentar la checklist de deploy (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS forzado).

#### S7 — Menores
- `{!! $icon !!}` en `components/sidebar-item.blade.php:12`: único raw output del proyecto; hoy solo recibe SVGs estáticos internos — riesgo bajo, dejar comentado el contrato ("solo SVG hardcodeado").
- `Log::info('invitation.accept.start', ...)` y logs de debug en `InvitationController` — restos de depuración en flujo productivo (ruido en logs, no riesgo).
- Los métodos `store()` de la mayoría de los controllers no llaman `authorize('create', ...)`: la autorización descansa solo en el middleware `role:` de la ruta. Funciona, pero es inconsistente con el resto (policies en show/update/delete) y frágil ante un futuro cambio de rutas.

### 4.2 Arquitectura

#### A1 — Aislamiento de tenants por convención (el hallazgo estructural más importante)
- **Severidad: Alta** · **Esfuerzo: Medio**
- **Descripción:** no existe trait `BelongsToTenant`, ni global scope, ni scoped route bindings. El aislamiento se logra repitiendo tres patrones manuales: `$tenant->relación()` en queries (~104 usos de `app(Tenant::class)`), policies que comparan `tenant_id`, y `Rule::exists()->where('tenant_id', ...)` en validaciones. **El sistema ya falló una vez por esto** (fuga de `supplier_id` entre tenants, corregida en v0.10.0): cada nueva feature debe re-acordarse de las tres capas.
- **Impacto técnico:** cada endpoint nuevo es una oportunidad de fuga de datos entre clientes.
- **Riesgo de negocio:** una fuga entre tenants en un SaaS B2B es el peor incidente posible de confianza.
- **Recomendación:** defensa en profundidad incremental, sin big-bang:
  1. Trait `BelongsToTenant` con global scope (`where tenant_id = app(Tenant::class)->id` cuando hay tenant resuelto) + auto-fill de `tenant_id` en `creating`.
  2. `->scopeBindings()` en las rutas anidadas (`recipes/{recipe}/ingredient-lines/{line}`) para eliminar los `abort_unless($line->recipe_id === $recipe->id)` manuales.
  3. Mantener las policies y los `Rule::exists` scoped actuales como segunda capa.
  4. La suite `TenantIsolationTest` existente valida la migración.

#### A2 — Controladores gordos
- **Severidad: Media** · **Esfuerzo: Medio**
- **Ubicación:** `RecipeController` (569 líneas, 20 acciones), `PurchaseController` (487 líneas, 14 acciones).
- **Descripción:** `RecipeController` mezcla CRUD de receta + CRUD de 4 tipos de líneas (ingrediente/envase/mano de obra/sub-receta) con lógica casi idéntica entre tipos (validar → crear/actualizar/borrar → propagar). `show()` además arma 4 estructuras de datos para Alpine (90 líneas de transformación) que son responsabilidad de una capa de presentación.
- **Recomendación:** extraer `RecipeLineController` (o uno por tipo con base común), y mover el armado de `*LinesData` a un ViewModel/Presenter (`RecipeShowViewModel`). El patrón línea-CRUD-propagar puede unificarse en un método parametrizado por relación.

#### A3 — Servicios instanciados con `new` en vez de inyectados
- **Severidad: Baja** · **Esfuerzo: Bajo**
- **Ubicación:** `new RecipeCostCalculator(new UnitConverter)` en `DashboardController:17`, `RecipeController:175`, `new UnitConverter` suelto en varios métodos.
- **Descripción:** inconsistente con el propio proyecto (los demás servicios van por constructor). Impide sustitución en tests y duplica instancias.
- **Recomendación:** constructor injection en todos los casos; ambos servicios son stateless, pueden ser singletons.

#### A4 — `TenantSetting::getSetting()` hace una query por llamada
- **Severidad: Baja** · **Esfuerzo: Bajo**
- **Ubicación:** `Tenant::getSetting()` (`app/Models/Tenant.php:151`).
- **Recomendación:** memoizar los settings del tenant en el modelo (una query por request) — hoy hay pocos consumidores, pero el patrón invita a multiplicar queries.

#### A5 — Lógica de overhead sin dueño único
- **Severidad: Media** · **Esfuerzo: Bajo**
- **Descripción:** ya documentada (DD3), pero merece ascenso de prioridad: la fórmula overhead/hora (suma de fijos ÷ horas productivas) está en 5 sitios, uno de ellos **dentro de un getter Alpine en un Blade**. Es la fórmula más sensible del negocio (afecta el costo de todas las recetas).
- **Recomendación:** `OverheadCalculator` (o método `Tenant::overheadPerHour()`) como único dueño; el Blade recibe el valor calculado.

#### A6 — Ausencia total de Jobs/Events/Listeners
- **Severidad: Baja hoy, Media a 12 meses** · **Esfuerzo: Alto**
- **Descripción:** decisión consciente (Hostinger sin workers). Consecuencias actuales: mails síncronos (el request de invitación espera al SMTP), propagación de costos síncrona, escaneo IA síncrono. El código está bien encapsulado en servicios, así que la migración a colas será mecánica cuando haya infraestructura.
- **Recomendación:** no actuar ahora; al migrar de hosting (o a Laravel Cloud), convertir `Mail::send` → `queue()` y envolver `propagateFrom`/`extract` en Jobs. Diseñar los servicios nuevos ya pensando en serializabilidad (IDs, no modelos completos).

### 4.3 Base de datos

Diseño general **sólido**: FKs con `constrained()` y política de borrado explícita en todas las tablas, índices compuestos con `tenant_id` primero (convención cumplida), migración correctiva de índices (`add_missing_indexes`), precisión decimal ampliada donde hizo falta, ledger de stock inmutable con contramovimientos y unique para carreras de creación.

Hallazgos:

| ID | Hallazgo | Severidad | Detalle |
|----|----------|-----------|---------|
| D1 | Sin UNIQUE para facturas duplicadas | Media | Ver S5 |
| D2 | Polimorfismo con strings libres (`'ingredient'`/`'packaging'`) en `purchase_lines.purchaseable_*` y `stock_movements.stockable_*` sin `Relation::enforceMorphMap` | Baja | Un typo en un string nuevo crea filas huérfanas silenciosas. Centralizar en enum o morph map. |
| D3 | Factories incompletas | Baja | No hay factory de `Purchase`, `PurchaseLine`, `StockMovement`, `Invitation`, `MailTemplate` — los tests los construyen a mano, más frágil y verboso. |
| D4 | `tenant_settings` como EAV string | Baja | Correcto para el volumen actual; tipar los valores (cast por clave) cuando crezcan los settings. |
| D5 | Seeders mínimos (`TenantSeeder`, `UserSeeder`) | Baja | Falta un seeder de demo completo (recetas + compras + stock) para onboarding de desarrollo y ambientes de prueba. |

### 4.4 Rendimiento

#### P1 — Dashboard de rentabilidad: todo en memoria
- **Severidad: Media** (Alta a partir de ~500 recetas activas por tenant) · **Esfuerzo: Medio**
- **Ubicación:** `DashboardController::index()`.
- **Descripción:** carga **todas** las recetas activas con 3 relaciones anidadas, recalcula el costo de cada una en PHP con `RecipeCostCalculator` (ignorando la columna cacheada `recipes.unit_cost` que `RecipeCostPropagator` mantiene precisamente para esto), ordena en colección y pagina con `LengthAwarePaginator` manual. Cada carga del dashboard es O(recetas × líneas).
- **Recomendación:** confiar en `unit_cost` cacheado (agregar columna cacheada `total_labor_hours` si hace falta para el overhead), calcular margen en SQL y paginar en BD. El propagador ya garantiza la frescura del cache.

#### P2 — `availableSemiElaborates` ejecuta un BFS por candidata en cada vista de receta
- **Severidad: Baja** · **Esfuerzo: Medio**
- **Ubicación:** `RecipeController:558-568` + `RecipeCostPropagator::isAncestor()`.
- **Descripción:** por cada sub-receta candidata se recorre el grafo con queries (`isAncestor`). Con S sub-recetas y profundidad D son S×D queries por page view de `recipes/show`.
- **Recomendación:** cargar todas las aristas `recipe_subrecipe_lines` del tenant en una query y resolver ancestros en memoria.

#### P3 — Llamada IA síncrona en el request (60 s de worker bloqueado)
- **Severidad: Media** · **Esfuerzo: Alto (requiere colas)**
- Ver A6/S2. En hosting compartido, 2-3 escaneos simultáneos pueden agotar los workers PHP para todo el sitio.

#### P4 — Caché desaprovechada
- **Severidad: Baja** · **Esfuerzo: Bajo**
- **Descripción:** `CACHE_STORE=database` configurado y sin un solo uso de `Cache::` en `app/`. Candidatos naturales: overhead por tenant (invalidado al tocar fijos/horas), settings de tenant, catálogos para selects.

#### P5 — `preventLazyLoading` solo fuera de producción
- **Severidad: Baja** · **Descripción:** estándar y correcto, pero significa que un N+1 nuevo pasa silencioso en prod. Considerar `Model::handleLazyLoadingViolationUsing` con log en producción para detectarlos sin romper.

### 4.5 Frontend

| ID | Hallazgo | Severidad | Detalle |
|----|----------|-----------|---------|
| F1 | `recipes/show.blade.php` de **902 líneas** con lógica Alpine embebida (incluida la fórmula de overhead) | Media | Partir en parciales por sección (líneas de ingredientes / envases / MO / sub-recetas comparten estructura) y mover el JS a módulos Vite como ya se hizo con `image-compress.js`. |
| F2 | Patrón tabla-desktop + tarjetas-mobile + búsqueda + orden + paginación repetido en ~15 index views (`packaging/index` 488 L, `ingredients/index` 382 L...) | Media | Extraer componentes `x-data-table` / `x-sortable-th` (ya existe el precedente exitoso de `x-expense-tabs` y `x-semi-badge`). Cada vista nueva hoy copia ~200 líneas. |
| F3 | JS híbrido: módulos Vite (`resources/js`, 557 L, bien) conviven con Alpine inline extenso en Blades | Baja | Aceptable con Alpine; el criterio a fijar: >50 líneas de lógica → módulo. |
| F4 | Modales create/edit siguen el patrón documentado en `feedback-crud-modals.md` de forma consistente | ✔ | Sin acción. |

### 4.6 Calidad de código

- **PSR-12 / Pint:** consistente, sin desvíos detectados.
- **Nombres:** descriptivos y en convención (mezcla es/en deliberada: dominio en español, código en inglés — consistente).
- **Código muerto / comentado / imports sin uso:** prácticamente inexistente. Único candidato: `Gate::define('super-admin', ...)` en `AppServiceProvider` — el `Gate::before` ya devuelve `true` para super admins antes de evaluarlo, y el middleware de rutas admin usa `can:super-admin`… que también corre `before` primero. La definición nunca decide nada; verificar y simplificar.
- **Tests:** 396 verdes, 47 archivos, incluyendo aislamiento de tenants, regresiones puntuales de bugs reales y cálculo de costos. Faltan: tests del flujo de impersonación cruzando tenants (S3), y `RecipeController::copy()` sin transacción (ver abajo).
- **`RecipeController::copy()` sin `DB::transaction`:** replica receta + 4 tipos de líneas + precios en operaciones sueltas; una falla a mitad deja una copia parcial activa=false (visible). Severidad Baja, esfuerzo Bajo: envolver en transacción.
- **PHP versión:** producción/compose exigen `^8.3`; conviene fijar la misma minor en CI y local para evitar drift (este contenedor corre 8.4).

### 4.7 SaaS específico

| Aspecto | Estado | Observación |
|---------|--------|-------------|
| Aislamiento de datos | ⚠️ | Funciona pero por convención (A1) — prioridad #1 |
| Roles y permisos | ✔ | 4 roles, middleware + gates + policies coherentes |
| Configuración por cliente | ✔ | `tenant_settings` K/V, suficiente para esta etapa |
| Onboarding | ✔ | Tour + composer de pasos; el composer hace hasta 5 counts por page view para tenants sin onboarding (menor, se autolimita al completar) |
| Cuotas / límites por plan | ✘ | No existe concepto de plan ni límite (escaneos IA, usuarios, recetas). Prerequisito para apertura pública (S2) |
| Billing | ✘ | No existe — está en roadmap (B.2), correcto |
| Baja de tenant | ⚠️ | `active=false` bloquea acceso (bien), pero no hay flujo de exportación/purga de datos (relevante para regulación de datos personales) |
| Backups / disaster recovery | ? | Fuera del repo; depende de Hostinger. Documentar estrategia |

---

## 5. Oportunidades de refactorización (consolidado)

1. **Trait `BelongsToTenant` + scoped bindings** (A1) — la refactorización con mejor relación riesgo/beneficio del proyecto.
2. **`OverheadCalculator` único** (A5/DD3) — elimina la quintuplicación de la fórmula más sensible.
3. **Partir `RecipeController`** en Recipe + RecipeLine + ViewModel (A2).
4. **Componente `x-data-table`** para las 15 vistas index (F2).
5. **Base class/trait para category controllers** — cuando aparezca el tercer tipo (DD1, esperar como está decidido).
6. **Trait `RecordsActivity`** — el bloque `$this->recorder->record(...)` se repite ~25 veces con la misma forma; un trait u observer lo reduce a una línea.
7. **DI de `RecipeCostCalculator`/`UnitConverter`** (A3).

## 6. Mejoras de arquitectura

- Defensa en profundidad multi-tenant (A1) — corto plazo.
- ViewModels/Presenters para vistas pesadas (A2, F1) — mediano.
- Preparación para colas: mantener toda operación pesada dentro de servicios ya encapsulados (A6) — al cambiar de hosting.
- Morph map para polimorfismos (D2) — corto.
- Módulo de cuotas por tenant (SaaS) — antes de la apertura pública.

## 7. Mejoras de rendimiento

- Dashboard sobre `unit_cost` cacheado + paginación en BD (P1).
- Grafo de sub-recetas en memoria para `availableSemiElaborates` (P2).
- Cache de overhead y settings por tenant (P4, A4).
- Colas para IA y mails cuando haya workers (P3).

## 8. Mejoras de seguridad

- Disco privado para comprobantes (S1). **Prioridad máxima de seguridad.**
- Throttle + cuota en escaneo IA (S2).
- Impersonación acotada al tenant impersonado + auditoría de cruces (S3).
- Resolución de tenant determinista (S4).
- UNIQUE de facturas (S5/D1).
- Checklist de deploy en `.env.example` (S6).
- `authorize('create')` consistente en stores (S7).

## 9. Quick Wins (bajo esfuerzo, alto impacto)

| # | Acción | Esfuerzo | Impacto |
|---|--------|----------|---------|
| QW1 | Mover comprobantes a disco privado (el controller de streaming ya existe) | Bajo | Alto — cierra S1 |
| QW2 | `throttle:10,1` en `POST purchases/scan` | Bajo (1 línea) | Alto — acota S2 |
| QW3 | `->orderBy('id')` en resolución de tenant | Bajo (1 línea) | Medio — cierra S4 |
| QW4 | UNIQUE `(tenant_id, supplier_id, invoice_number)` en purchases | Bajo | Medio — cierra S5 |
| QW5 | `Tenant::overheadPerHour()` y reemplazar los 5 call sites | Bajo | Alto — protege la fórmula central |
| QW6 | `DB::transaction` en `RecipeController::copy()` | Bajo | Medio |
| QW7 | Quitar logs de debug de `InvitationController` | Bajo | Bajo |
| QW8 | `->scopeBindings()` en rutas anidadas de líneas | Bajo | Medio — elimina ~12 `abort_unless` manuales |
| QW9 | Memoizar `Tenant::getSetting()` | Bajo | Bajo-Medio |

## 10. Plan de implementación priorizado

### Corto plazo (1–2 semanas) — seguridad y quick wins
1. QW1 comprobantes privados (S1)
2. QW2 throttle escaneo (S2)
3. QW3 tenant determinista (S4)
4. QW4 unique facturas (S5)
5. QW5 overhead único (A5)
6. QW6–QW9
7. Checklist `.env` de producción (S6)

*Riesgo de regresión: mínimo. Todo cubierto por la suite existente + tests nuevos puntuales.*

### Mediano plazo (1–2 meses) — estructura multi-tenant y rendimiento
1. Trait `BelongsToTenant` con global scope, migrado modelo por modelo con `TenantIsolationTest` como red (A1)
2. Impersonación acotada + auditoría (S3)
3. Dashboard sobre `unit_cost` cacheado (P1)
4. Componente `x-data-table` y adopción progresiva en las vistas index (F2)
5. Partir `RecipeController` + ViewModel de `recipes/show` (A2, F1)
6. Morph map / enum para polimorfismos (D2); factories faltantes (D3)
7. Resolver patrón "ítem inactivo en select" pendiente en recetas y compras (DD2)

### Largo plazo (3–6 meses) — escalabilidad y apertura pública
1. Migración de hosting (o Laravel Cloud) → colas: mails, propagación, escaneo IA (A6, P3)
2. Cuotas y planes por tenant (escaneos, usuarios) — prerequisito de apertura pública
3. Cache estratégica por tenant (P4)
4. Selector de tenant para usuarios multi-tenant
5. Flujo de baja/exportación de datos de tenant
6. Grafo de sub-recetas en memoria (P2) si el uso de semi-elaboradas crece

---

## Anexo — Metodología

Revisión manual completa de: los 138 archivos PHP de `app/` (controllers, services, models, policies, middleware, requests), las 54 migraciones, rutas, configuración (`session`, `filesystems`, `services`), `.env.example`, las vistas de mayor tamaño, `resources/js`, la suite de tests (47 archivos) y la documentación interna (`.claude/memory/`, CHANGELOG). Búsquedas dirigidas de: raw output Blade (`{!!`), SQL injection (`DB::raw`, interpolaciones), scoping de tenant (`app(Tenant::class)`, `tenant_id`), rate limiting, código muerto y TODOs.
