---
name: project-architecture
description: "Decisiones de arquitectura del proyecto Levado — dominio, tenant, stack, convenciones"
metadata: 
  node_type: memory
  type: project
  originSessionId: 07b581ae-f96f-4331-9543-aea46735b797
---

# Arquitectura del Proyecto Levado

## Dominios
- `levado.com.ar` — sitio público estático ("coming soon" en MVP, landing completa post-apertura)
- `app.levado.com.ar` — aplicación Laravel (todos los clientes)
- Cada uno con SSL Let's Encrypt independiente
- `robots.txt` en `app.` bloquea indexación; el dominio raíz sí se indexa
- Cookies y sesiones acotadas a `app.levado.com.ar`

## Resolución de Tenant
- Dominio único sin subdominios por tenant en MVP (sin DNS wildcard, sin complejidad de routing)
- `SetTenantContext` middleware: resuelve tenant desde `tenant_users` del usuario autenticado
- Bound via `App::instance(Tenant::class, $tenant)` en el service container
- Si un usuario pertenece a más de un tenant: selector de tenant en header (no prioritario en MVP)

## Roles
`super_admin` | `owner` | `admin` | `viewer`
(store_manager y cashier se agregan cuando lleguen sucursales y POS)

## Stack
- PHP 8.3, Laravel 13, MySQL, Blade/Tailwind/Alpine.js
- Laravel Breeze (Blade stack) para auth scaffolding
- Pest v4 para tests
- Laravel Boost = MCP server de AI tools (NO es un paquete de auth)
- Hostinger hosting (sin workers persistentes — recálculo síncrono en MVP)

## Convenciones
- Toda migración con `tenant_id` como primer campo en índices compuestos
- Baja lógica siempre: ninguna tabla usa DELETE físico en producción
- Sin registro público — usuarios entran solo por invitación (ruta /register eliminada)
- Tests prioritarios: RecipeCostCalculator, UnitConverter, recálculo cascada

## Email transaccional
- SMTP Hostinger vía `noreply@levado.com.ar`
- DNS: SPF + DKIM + DMARC (`p=none` inicial, endurecer después)
- Soporte en `soporte@levado.com.ar`

## Migraciones futuras previstas (no crear ahora)
- `tenant_users.store_id`, `register_id`, `max_discount_pct` → cuando lleguen sucursales/POS
- `stores`, `registers`, `stock_items`, `stock_movements` → módulo stock/POS
- `sales`, `sale_items`, `sale_payments`, `register_sessions` → POS desktop
- `products.recipe_id` → módulo de productos
