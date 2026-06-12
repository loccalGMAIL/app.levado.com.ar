---
name: security-fixes-2026-06
description: Arreglos de seguridad de junio 2026 (escalada de privilegios, invitaciones, facturas privadas) y bug de Rule::enum->only
metadata:
  type: project
---

Revisión de seguridad del backend (2026-06-12). Cambios aplicados y verificados con la suite (243 tests passing):

1. **Escalada de privilegios en equipo (CRÍTICO):** `TeamController::updateRole` validaba contra TODOS los roles (incluido `super_admin`), permitiendo que un admin de tenant se volviera super admin global. Ahora: roles asignables solo owner/admin/viewer (`Rule::in`), no se puede cambiar el rol propio, no se tocan super admins, y se exige al menos un owner activo. Mismas guardas en `deactivate`. Helper `hasOtherActiveOwner()`.

2. **Login sin contraseña vía invitación (ALTO):** `InvitationController::accept` hacía `Auth::login()` de un usuario existente sin verificar credenciales. Ahora un usuario ya registrado debe estar autenticado como sí mismo para aceptar; si no, se lo manda a login (guardando `url.intended`). La vista `accept-invitation.blade.php` tiene 3 estados: nuevo (form completo), existente-autenticado (botón aceptar), existente-no-auth (`$mustLogin` → botón iniciar sesión).

3. **Facturas accesibles sin auth (ALTO):** las imágenes de facturas estaban en el disco `public` (symlink `/storage`), accesibles sin login. Movidas al disco `local` (privado). Se sirven solo por `purchases.invoice` (compra guardada) y la nueva ruta `purchases.scan.preview` (borrador en revisión, valida pertenencia al tenant vía `safeImagePath`). Los logos en BusinessController siguen en `public` (son públicos a propósito).

4. **Validación de proveedor tenant-scoped:** `StorePurchaseRequest` y `StoreScannedPurchaseRequest` ahora usan `Rule::exists('suppliers','id')->where('tenant_id', app(Tenant::class)->id)`.

5. **BUG latente encontrado y corregido:** `Rule::enum(Class)->only([Enum::Case->value, ...])` con **valores string SIEMPRE falla** ("The selected role is invalid"). Hay que pasar los **casos del enum**, no `->value`. `InviteTeamMemberRequest` tenía este bug → invitar miembros estaba roto en producción. Corregido pasando casos. Ojo si aparece este patrón en otro lado.

Tests nuevos: `TeamRoleGuardTest.php`, casos agregados en `InvitationAcceptTest.php` y `PurchaseScanTest.php`.

Relacionado: [[feature-compras]]
