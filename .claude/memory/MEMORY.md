# Memory Index — Levado

- [Roadmap MVP v2.1](project-roadmap.md) — Estado de etapas, versioning, rama activa, prioridades
- [Estado del proyecto](project_status.md) — Versión actual, lo que está hecho y próximos pasos
- [Módulo de Compras](feature-compras.md) — Tablas, flujos, servicios, vistas y estado de fases del módulo de compras. Incluye los tres estados del renglón, el cambio de semántica del contador a *resueltos* y la memoria de vinculación por proveedor (act. v0.12.7)
- [Módulo de Existencias](feature-existencias.md) — Ledger inmutable, StockService, integración con compras, edición inline y orden por columnas (act. v0.9.2, sin Merma)
- [Dashboard nuevo](feature-dashboard.md) — Rediseño gráfico v0.12.1: tabla sobre caches en SQL + gauge/barras/dona con ApexCharts self-hosted; trampas de división entera en SQLite y `@json` multilínea. Desborde de montos grandes y vuelta a 0 decimales en los KPI de importe (act. v0.12.6)
- [Cifras grandes en cards](pattern-cifras-responsive.md) — `.kpi-card` / `.kpi-figure`: por qué el tamaño se mide contra el ancho de la card y no del viewport, las trampas de `@layer` y de `contain: inline-size`, y cómo se verifica en 10 anchos (v0.12.6)
- [Centro de Alertas](feature-alertas.md) — Feed persistido `notifications` (stock bajo, salto de costo, costo desactualizado, compras sin imputar); reconcile-on-read, config en Administración → Alertas
- [Modelo de dominio Insumos vs Artículos](domain-model-articulos.md) — ADR: el Artículo es dueño único de **costo** (`currentCost`/`fullCost` ✅ P1) y **precio** (`product_prices` ✅ P2); Receta = BOM. **P3 en curso** (costeo reventa + políticas de precio: backend ✅, UI 🔲); P4 = Producción
- [P3 · UI de política de precio (pendiente)](p3-ui-pendiente.md) — cómo retomar el paso 3: superficies, contrato del endpoint `products.prices.update`, backend ya listo (enum `PricingPolicy`, `product_prices.policy_*`, recalculador)
- [Artículos + Producción](feature-articulos-produccion.md) — Módulo product-céntrico (rama v0.13.0): Producto=SKU vendible/stockeable, Receta=BOM; pricing repartido (elaborado en receta, reventa en producto); **etapas 1–3 completas ✅** (catálogo, stock, compra reventa, Producción backend+UI)
- [Backoffice de Administración](project-backoffice.md) — B.1 MVP y B.2 SaaS: diseño, tablas, impersonación, auditoría. **Será reemplazado por un panel administrativo completo nuevo** (decisión 17/07/2026)
- [Arquitectura del proyecto](project-architecture.md) — Dominio, resolución de tenant, stack, convenciones de desarrollo
- [Plan de largo plazo](plan-largo-plazo.md) — Pendientes de la auditoría atados al panel administrativo nuevo, la apertura pública y la migración de hosting (cuotas, colas, selector de tenant, baja/exportación)
- [Perfil del usuario](user-profile.md) — Fundador/dev de Levado, contexto del negocio, idioma, preferencias
- [Feedback general](feedback-general.md) — Boost ≠ auth, no i18n, no comentarios innecesarios
- [CRUD con modales](feedback-crud-modals.md) — Create/edit siempre en modales; estructura modals/, patrón Alpine/Blade, rutas mínimas
