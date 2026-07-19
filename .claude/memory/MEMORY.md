# Memory Index — Levado

- [Roadmap MVP v2.1](project-roadmap.md) — Estado de etapas, versioning, rama activa, prioridades
- [Estado del proyecto](project_status.md) — Versión actual, lo que está hecho y próximos pasos
- [Módulo de Compras](feature-compras.md) — Tablas, flujos, servicios, vistas y estado de fases del módulo de compras (act. v0.9.1)
- [Módulo de Existencias](feature-existencias.md) — Ledger inmutable, StockService, integración con compras, edición inline y orden por columnas (act. v0.9.2, sin Merma)
- [Dashboard nuevo](feature-dashboard.md) — Rediseño gráfico v0.12.1: tabla sobre caches en SQL + gauge/barras/dona con ApexCharts self-hosted; trampas de división entera en SQLite y `@json` multilínea
- [Centro de Alertas](feature-alertas.md) — Feed persistido `notifications` (stock bajo, salto de costo, costo desactualizado, compras sin imputar); reconcile-on-read, config en Administración → Alertas
- [Backoffice de Administración](project-backoffice.md) — B.1 MVP y B.2 SaaS: diseño, tablas, impersonación, auditoría. **Será reemplazado por un panel administrativo completo nuevo** (decisión 17/07/2026)
- [Arquitectura del proyecto](project-architecture.md) — Dominio, resolución de tenant, stack, convenciones de desarrollo
- [Plan de largo plazo](plan-largo-plazo.md) — Pendientes de la auditoría atados al panel administrativo nuevo, la apertura pública y la migración de hosting (cuotas, colas, selector de tenant, baja/exportación)
- [Perfil del usuario](user-profile.md) — Fundador/dev de Levado, contexto del negocio, idioma, preferencias
- [Feedback general](feedback-general.md) — Boost ≠ auth, no i18n, no comentarios innecesarios
- [CRUD con modales](feedback-crud-modals.md) — Create/edit siempre en modales; estructura modals/, patrón Alpine/Blade, rutas mínimas
