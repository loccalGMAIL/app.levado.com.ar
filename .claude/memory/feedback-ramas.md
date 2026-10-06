---
name: feedback-ramas
description: Todo cambio al repo se hace en una rama nueva, nunca directo sobre master
metadata:
  type: feedback
---

Antes de hacer cambios, crear siempre una rama nueva (convención `vX.Y.Z-tema`).

**Why:** el usuario lo pidió explícitamente al empezar el trabajo de Larastan (v0.14.1).
**How to apply:** si el working tree está en `master`, hacer `git checkout -b ...` antes de editar o commitear; los cambios sin commitear viajan a la rama nueva.
