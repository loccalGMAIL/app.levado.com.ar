---
name: plan-largo-plazo
description: "Largo plazo de la auditoría técnica — pendientes atados al panel administrativo nuevo, la apertura pública y la migración de hosting"
---

# Plan de largo plazo (auditoría técnica)

Origen: `AUDITORIA_DEUDA_TECNICA.md` (jul 2026). El corto y mediano plazo están
implementados (v0.10.1 y v0.12.0). Lo de acá queda **pendiente a propósito**
porque depende de decisiones de negocio/infraestructura, no de código. La mayor
parte se destraba con el **panel administrativo nuevo** que reemplaza al
backoffice actual.

## 1. Panel administrativo nuevo (reemplaza el backoffice `/admin`)

Decisión del 17/07/2026: el backoffice actual (B.1) se retira; no invertir en
mejorarlo más allá de fixes. Requisitos de diseño que el panel nuevo hereda de
la auditoría — **desde el día uno, no como mejora posterior**:

### Impersonación (cierra el hallazgo S3)
- **Acotada al tenant impersonado.** Hoy el trait `BelongsToTenant` ya oculta
  estructuralmente los datos de otros tenants durante la impersonación (v0.11.1,
  anclado con test en `BelongsToTenantTest`), pero `Gate::before` sigue
  devolviendo `true` para super admins: si el panel nuevo agrega rutas o
  contextos sin tenant resuelto, esa red no aplica. El panel debe definir la
  regla explícita: impersonando A, **nada** de B es accesible ni visible.
- **Auditoría de la sesión de impersonación** en `admin_audit_logs`: inicio y
  fin ya se registran; agregar registro de todo intento de acceso fuera del
  tenant impersonado (hoy son 404 silenciosos).
- Banner visual persistente de "estás impersonando a X" con salida en un click
  (existe en el backoffice actual — conservarlo).

### Cuotas y límites por tenant (prerequisito de la apertura pública)
- Hoy **no existe el concepto de plan ni límite**. El escaneo IA tiene
  `throttle:10,1` por usuario (v0.10.1), pero nada impide que un tenant consuma
  API sin tope mensual.
- Diseñar junto al panel: contadores de consumo por tenant (escaneos IA/mes,
  usuarios activos, recetas, storage de comprobantes), límites configurables
  por plan desde el panel, y el comportamiento al alcanzarlos (bloqueo suave
  con aviso, no pérdida de datos).
- `tenant_settings` (K/V) alcanza para los límites; los contadores de consumo
  probablemente necesiten tabla propia (`tenant_usage` mensual).

### Baja y exportación de datos de tenant
- Hoy la baja es `active=false` (bloquea el acceso, correcto) pero no hay flujo
  de exportación ni purga. Relevante para regulación de datos personales y para
  la confianza comercial ("me llevo mis datos").
- El panel nuevo debería ofrecer: exportación completa (CSV/JSON de recetas,
  ingredientes, compras, stock, gastos) y purga diferida (baja → ventana de
  retención → borrado físico, que hoy no existe en ninguna tabla).

### Métricas SaaS
- El dashboard del panel: tenants activos, actividad reciente por tenant,
  consumo de IA, crecimiento. `admin_audit_logs` ya registra la actividad de
  dominio (creaciones/ediciones por tenant) — es la fuente natural.

## 2. Colas (al migrar de hosting, o a Laravel Cloud)

Hostinger compartido no tiene workers persistentes; todo es síncrono a
propósito. Al migrar, convertir en Jobs (el código ya está encapsulado en
servicios, la migración es mecánica):
- **Mails** (`Mail::send` → `queue()`): invitaciones, bienvenida, reset.
- **Escaneo IA** (`InvoiceExtractor`, `ExpenseReceiptExtractor`): hoy bloquea
  un worker PHP-FPM hasta 60 s dentro del request. Es el mayor beneficiario.
- **Propagación de costos** (`RecipeCostPropagator`): síncrona está bien a la
  escala actual; encolar solo si un cambio de precio de ingrediente muy usado
  se vuelve lento (medir antes).
- Regla de diseño vigente: los servicios nuevos deben ser serializables
  (recibir IDs, no closures) para que el pasaje a Jobs sea trivial.

## 3. Selector de tenant (usuarios multi-tenant)

- Hoy un usuario con dos membresías activas resuelve **siempre el tenant de
  menor ID** (`SetTenantContext`, determinista desde v0.10.1). El selector en
  el header está previsto desde el diseño original (`project-architecture.md`).
- Al construirlo: el tenant elegido va a la sesión, `SetTenantContext` lo lee
  validando la membresía, y el selector solo aparece con >1 membresía activa.
  Ojo con la interacción con la impersonación de super admins (la sesión ya
  usa `impersonating_tenant_id` — no mezclar las dos claves).

## 4. Cache por tenant (hallazgo P4)

- `CACHE_STORE=database` configurado y sin ningún uso de `Cache::` en `app/`.
- Candidatos cuando haya presión real (no cachear especulativamente):
  overhead por hora (invalidar al tocar gastos fijos u horas productivas),
  settings del tenant (hoy memoizados por request, suficiente), catálogos para
  selects en tenants con muchos ítems.
- Con Redis (post-migración de hosting) usar tags por tenant para invalidar.

## 5. Menores que quedaron anotados

- **Grafo de sub-recetas en memoria** (P2): `availableSemiElaborates` corre un
  BFS con queries por candidata en cada carga de `/recipes/{id}`. Optimizar
  solo si el uso de semi-elaboradas crece (una query de todas las aristas del
  tenant y resolver ancestros en memoria).
- **`purchases/index` y `price-lists/matrix`**: migrarlas a mano a
  `x-sortable-th`/`x-responsive-table` cuando se las toque (tienen variantes
  de markup propias; el resto de los índices ya usa los componentes).
- **`recipes/show` (902 líneas)**: partirla en parciales por sección cuando se
  la vuelva a tocar; el backend ya quedó limpio (`RecipeShowViewModel`).
- **Archivos huérfanos de escaneos abandonados** (compras y gastos): comando de
  limpieza programado (los paths viven en `purchases/{tenant}/` y el ledger de
  referencias está en las tablas — borrar lo no referenciado con >48 h).
- **Duplicación asumida en visión IA**: `ExpenseReceiptExtractor` duplica ~40 L
  de `InvoiceExtractor`; extraer un `ClaudeVision` recién con un 4º consumidor.
- **`VariableExpenseCategoryController`** duplica `FixedCostCategoryController`:
  extraer base recién con un 3er tipo de categoría (decisión v0.10.0).
