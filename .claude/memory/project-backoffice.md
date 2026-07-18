---
name: project-backoffice
description: "Backoffice de Administración (B.1 MVP y B.2 SaaS) — diseño, tablas, alcance y decisiones de implementación"
metadata: 
  node_type: memory
  type: project
  originSessionId: 07b581ae-f96f-4331-9543-aea46735b797
---

# Backoffice de Administración

Herramientas internas para el equipo Levado (no accesibles por owners/admins de tenant).

> **⚠️ Plan vigente (17/07/2026):** el backoffice actual (B.1) va a ser **reemplazado por un panel administrativo completo nuevo** y retirado. Requisitos de diseño ya definidos para el panel nuevo, salidos de la auditoría técnica (`AUDITORIA_DEUDA_TECNICA.md`, hallazgo S3): impersonación **acotada al tenant impersonado** (sin acceso cruzado a otros tenants por URL durante la sesión de impersonación) y **auditoría en `admin_audit_logs` de todo acceso fuera del tenant impersonado**, desde el día uno. Hasta entonces, no invertir en mejoras del backoffice actual más allá de fixes. **Los requisitos completos del panel nuevo (impersonación acotada, cuotas por tenant, baja/exportación, métricas SaaS) están en [`plan-largo-plazo.md`](plan-largo-plazo.md).**

## Acceso y modelo de usuarios admin
- Ruta dedicada: `app.levado.com.ar/admin`
- Middleware: `EnsureSuperAdmin` — bloquea cualquier `/admin/*` si el usuario no tiene rol `super_admin`
- **Decisión de implementación:** los super admins pertenecen a un tenant interno llamado **"Levado HQ"** con rol `super_admin` en `tenant_users`. No hay flag global en `users`. Es la forma más limpia de modelar sin alterar el esquema actual.
- Layout separado: `layouts/admin.blade.php`, con franja superior en color Horno para distinguirlo visualmente del layout de tenant
- Desde la app normal, si el usuario logueado es super_admin, ve un enlace "Backoffice" en su menú

## B.1 — Backoffice MVP (en paralelo a Etapas 1 y 2)

**Estimación:** 1-2 semanas
**Dependencia:** tablas tenants, tenant_settings, users, tenant_users creadas (ya están)

### B.1.1 Gestión de Tenants
- Listado: nombre, país, estado, cantidad usuarios, cantidad recetas, última actividad, fecha creación
- Búsqueda por nombre/país; filtros activos/inactivos/todos
- Alta de tenant: crea tenant + usuario owner en users + vínculo en tenant_users + envía email de invitación
- Edición de configuración, activar/desactivar (baja lógica)
- Vista de detalle con 3 pestañas: Configuración | Usuarios | Métricas de uso

### B.1.2 Vista de detalle — Usuarios
- Listado: nombre, email, rol, estado, último login
- Acción "Resetear contraseña" (dispara flujo Breeze estándar)
- Acción "Desactivar usuario" (baja lógica en tenant_users)
- Sin crear usuarios desde el backoffice — el owner los invita desde "Mi equipo"

### B.1.3 Vista de detalle — Métricas de uso
- Cantidad de ingredientes, packaging, gastos fijos, mano de obra, recetas activas
- Última receta creada/modificada
- Último login del owner
- Volumen estimado de datos del tenant (en MB)

### B.1.4 Impersonación para soporte
- Botón "Operar como este tenant" en detalle del tenant
- Sesión marcada con `impersonated_tenant_id` y `impersonated_by_user_id`
- Banner permanente en la parte superior: "Impersonando: {nombre}. Salir →"
- Toda acción de escritura durante impersonación registrada en `admin_audit_logs` con `was_impersonating = true`
- Acciones destructivas deshabilitadas durante impersonación (no se puede dar de baja tenant ni eliminar usuarios)

### B.1.5 Logs de auditoría del backoffice

**Tabla nueva:**
```sql
admin_audit_logs (
  id, actor_user_id, target_type, target_id,
  action, payload_json, was_impersonating,
  ip_address, user_agent, created_at
)
```
- Servicio `AdminActivityRecorder` invocado desde controllers del backoffice
- Vista con filtros por actor, target, acción, rango de fechas
- Retención indefinida (sin baja)

### B.1.6 Dashboard del backoffice
- Widget: tenants activos / inactivos / totales
- Widget: usuarios totales en la plataforma
- Widget: tenants con actividad en los últimos 7 días
- Widget: tenants sin actividad en los últimos 30 días (señal de churn)
- Widget: últimas 10 acciones del backoffice (feed de auditoría)

## B.2 — Backoffice SaaS (prerequisito para apertura pública — Etapa 6)

**Estimación:** 3-5 semanas. No se construye hasta abrir el SaaS al público.

### Tablas previstas (no crear todavía):
```sql
plans (id, name, price_usd, billing_cycle, max_users, max_recipes, max_ingredients, max_stores, max_registers, features_json, active, ...)
subscriptions (id, tenant_id, plan_id, stripe_customer_id, stripe_subscription_id, status, trial_ends_at, current_period_start, current_period_end, canceled_at, ended_at, ...)
stripe_events (id, event_id, type, payload_json, processed_at, error_message, created_at)
system_announcements (id, title, body, severity, target_audience, target_tenants_json, starts_at, ends_at, created_by_user_id, created_at)
```

### Alcance B.2:
- CRUD de planes con límites y features_json
- Servicio `PlanLimitsEnforcer` para validar límites al crear recursos
- Gestión de suscripciones (cambio de plan, extensión de trial, cancelación — todas auditadas)
- Integración Stripe: Checkout, Billing Portal, webhooks con idempotencia via stripe_events
- Dashboard financiero: MRR, churn, trials, conversión, past_due
- Comunicaciones masivas: CRUD de system_announcements (banners en app o emails)

**Why:** el backoffice MVP es necesario desde el día 1 para dar de alta y soportar a los clientes iniciales. B.2 no tiene sentido hasta tener el SaaS abierto al público.
**How to apply:** al trabajar en B.1, respetar que NO hay campos de plan/suscripción en tenants todavía. Al preparar B.2, crear las tablas separadas. No mezclar.
