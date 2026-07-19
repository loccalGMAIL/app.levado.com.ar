---
name: feature-alertas
description: Centro de alertas persistido (notifications) — stock bajo, salto de costo, costo desactualizado, compras sin imputar; config en Administración (v0.12.x)
metadata:
  type: project
---
# Centro de Alertas (v0.12.x, rama v0.12.1/CambioDashboard)

Feed persistido de avisos accionables para la operación diaria. Reemplaza la franja "RESUMEN
OPERATIVO" del dashboard (ahora **"Alertas"**) y agrega un menú **Administración → Alertas**
(config) y una página de feed `notifications.index`. Ver [[feature-dashboard]].

## Arquitectura
- **Tabla `notifications`** (tenant-scoped, `use BelongsToTenant`): `type, severity (red/amber/blue),
  title, body, action_url, subject_type/subject_id, dedupe_key, meta (json), read_at, resolved_at`.
  **Único punto de escritura: `NotificationService`.** No es por usuario: es del tenant (realidad de
  1–2 operadores; marcar leído/descartar es tenant-wide).
- **`App\Enums\NotificationType`**: `LowStock`, `CostSpike`, `StaleCost`, `UnappliedPurchase` con
  `label()/severity()/icon()/settingKey()`.
- **Dos naturalezas, distinguidas por `dedupe_key`:**
  - **De evento** (`CostSpike`): se registra en el instante de la compra. Dedup por
    `cost_spike:purchase_line:{id}` (updateOrCreate, re-imputar no duplica). No se auto-resuelve.
  - **De estado** (`LowStock`/`StaleCost`/`UnappliedPurchase`): se **reconcilian al leer**
    (`NotificationService::syncStateAlerts`, llamado en `DashboardController::index` y
    `NotificationController::index`). Idempotentes: `raise()` no revive una resuelta (si el estado
    recurre, crea una nueva, no leída); `resolveMissing()` marca `resolved_at` a las que ya no aplican
    (o a todas las de un tipo apagado). **Se eligió reconcile-on-read en vez de cron/cola** porque
    producción (Hostinger shared) no tiene worker garantizado — ver [[project_status]].

## Detección de cada tipo
- **LowStock**: recorre `stock_levels` con `hasAlert()` (bajo mínimo o negativo, ya existía en
  [[feature-existencias]]). action_url → kardex (`stock.show`).
- **CostSpike**: hook en `PurchaseLineRecorder::applyIngredientCost/applyPackagingCost` — captura
  `$oldCost = $item->cost_per_unit` ANTES del update; si `$oldCost > 0` (hay baseline; el primer costo
  NO dispara) y el aumento ≥ umbral (`alerts.cost_spike.threshold_pct`, default 15), llama
  `raiseCostSpike`. El servicio se inyecta en el constructor del recorder.
- **StaleCost**: ingredientes activos cuyo último `ingredient_price_logs.recorded_at` (o `created_at`
  si no hay log) es anterior a `now()-N` días (`alerts.stale_cost.days`, default 60). Query con
  `selectSub(max(recorded_at))`.
- **UnappliedPurchase**: compras con renglones `cost_applied_at` null (cubre tanto sin-match como
  matcheado-sin-aplicar). action_url → `purchases.match`.

## Config (Administración → Alertas)
- `AlertSettingsController` (edit/update) + `UpdateAlertSettingsRequest`, patrón `BusinessController`
  con `getSetting`/`setSetting`. Ruta en el grupo `role:super_admin,owner` (gate `edit-settings`).
- Claves `TenantSetting` (defaults): `alerts.low_stock.enabled` (1), `alerts.cost_spike.enabled` (1) +
  `alerts.cost_spike.threshold_pct` (15), `alerts.stale_cost.enabled` (1) + `alerts.stale_cost.days`
  (60), `alerts.unapplied_purchase.enabled` (1). Toggles apagados → `syncStateAlerts` resuelve sus
  alertas vivas.

## Feed y permisos
- `NotificationController` (index con filtro leídas/todas, markRead, markAllRead, dismiss) en el grupo
  **de solo-lectura** (`auth,verified,tenant`, sin `role`) → **todos los roles, incluido viewer**,
  ven y accionan su feed. (Ojo: al agregar rutas, el grupo `role:...,admin` de escritura empieza en
  otra línea; poner el feed en el grupo de lectura o el viewer recibe 403.)
- Dashboard: `syncStateAlerts` + top 6 no leídas activas; el strip "Alertas" muestra pills con
  `action_url` y "Ver todas (N) →". Los ítems informativos positivos (más rentable, utilidad) siguen
  en la card "Resumen del negocio". Se quitaron los botones "Ingrediente" y "Lista de precios" de las
  acciones rápidas (quedan "Nueva receta" y "Compra").

## Tests
`NotificationAlertsTest` (14): salto sobre/bajo umbral y primer costo, umbral configurable, low stock
raise+resolve idempotente, stale cost, unapplied purchase, toggle apaga tipo, dashboard muestra no
leídas, markRead/dismiss/markAllRead, config owner-only (viewer 403 en config pero 200 en feed),
aislamiento entre tenants.

## Alcance / v2
Tenant-scoped y reconcile-on-read. Pendiente si migra el hosting: scan por cron/cola, notificaciones
por usuario, y quizá más tipos (margen bajo ya vive en el dashboard). Enlaza [[feature-compras]],
[[feature-existencias]], [[feature-dashboard]].
