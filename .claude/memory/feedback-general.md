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

## Convención de nombre de ramas
Las ramas siempre llevan el número de versión primero, seguido del cambio en kebab-case. Ejemplo: `v0.8.7-compras-iva-percepciones`.
**Why:** el usuario lo indicó explícitamente y corrigió una rama nombrada `feat/compras-iva-percepciones`.
**How to apply:** al crear o renombrar una rama de feature, usar el formato `v{version}-{descripcion-kebab}`. Nunca usar prefijos tipo `feat/`, `fix/`, etc.

## Memorias solo en la ruta del proyecto (no en la global)
`CLAUDE.md` indica explícitamente escribir toda memoria en `.claude/memory/` (proyecto, versionado en git) **en vez de** la ruta global `~/.claude/projects/.../memory/`.
**Why:** mantiene las memorias versionadas y portables entre dispositivos/apps. Una entrada anterior de este archivo pedía duplicar en ambas rutas, pero esa ruta global (con el nombre de carpeta viejo `levado-com-ar-app`) no existe en disco — la instrucción de `CLAUDE.md` es la vigente y la reemplaza.
**How to apply:** escribir y actualizar memorias únicamente en `.claude/memory/` del proyecto. No crear ni mantener una copia en la ruta global.
