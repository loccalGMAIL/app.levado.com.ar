---
name: project-larastan
description: Larastan v3 instalado (nivel 5, baseline de 496 errores) y plan por lotes para vaciarlo
metadata:
  type: project
---

`phpstan.neon` (nivel 5, `paths: [app]`) incluye `phpstan-baseline.neon`. Se corre con `composer analyse`. Rama `v0.14.1-larastan`.

**Why:** el código nuevo se chequea ya; los 496 errores viejos se registran en el baseline y se pagan de a poco.
**How to apply:** lote A = genéricos en relaciones de `app/Models` (`@return BelongsTo<X, $this>`), lote B = `@property-read` de atributos no-schema, lote C = services/controllers. Tras cada lote: regenerar baseline, pint, tests, commit. Las comparaciones "siempre falsas" pueden ser bugs reales: avisar, no silenciar.
