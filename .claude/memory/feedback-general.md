---
name: feedback-general
description: Correcciones y preferencias de trabajo confirmadas por el usuario
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 07b581ae-f96f-4331-9543-aea46735b797
---

# Feedback del usuario

## Laravel Boost no es un paquete de auth
No confundir Laravel Boost con autenticación. Laravel Boost es el MCP server de Anthropic para herramientas de AI en el proyecto Laravel (Boost tools: database-query, search-docs, browser-logs, get-absolute-url, etc.).
**Why:** el usuario corrigió explícitamente cuando se asumió que Boost era para auth. Para autenticación se usa Laravel Breeze.
**How to apply:** siempre que se mencione auth, usar Breeze. Boost = herramienta de desarrollo AI, no de usuarios finales.

## No crear comentarios ni documentos innecesarios
No crear archivos de documentación, verificación scripts, ni comentarios obvios en el código.
**Why:** instrucción explícita en CLAUDE.md del proyecto.
**How to apply:** solo comentar cuando el WHY es no obvio. No crear README ni docs salvo pedido explícito.

## Hardcodear español, no usar sistema i18n
Todas las vistas están en español rioplatense hardcodeado. No usar `__()` ni sistema de traducciones.
**Why:** la app es español-only; agregar una capa de i18n agrega complejidad sin valor.
**How to apply:** al crear o editar vistas Blade, escribir el copy directamente en español sin helpers de traducción.

## Guardar memorias en ambas rutas
Siempre escribir cada memoria en DOS rutas: `.claude/memory/` (proyecto, versionado en git) Y `C:\Users\Claudio\.claude\projects\D--DESARROLLO-CoDiGo-levado-com-ar-app\memory\` (global, cargada por el harness).
**Why:** el harness auto-carga desde la ruta global, pero CLAUDE.md exige la ruta del proyecto para portabilidad entre dispositivos. Si solo se escribe en una, divergen.
**How to apply:** cada vez que se cree o modifique un archivo de memoria, hacer Edit/Write en ambas rutas. También actualizar MEMORY.md en ambas si corresponde.
