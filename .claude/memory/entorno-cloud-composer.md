---
name: entorno-cloud-composer
description: Cómo instalar vendor/ en una sesión cloud de Claude Code, donde los zip de GitHub están bloqueados
metadata:
  type: reference
---

# Composer en sesiones cloud

En el entorno cloud `composer install` falla ("Could not authenticate against github.com"): el proxy bloquea los zipball de `api.github.com`, pero el `git clone` de repos públicos funciona.

1. `composer install --prefer-source --no-scripts` baja casi todo por git.
2. `phpstan/phpstan` sólo tiene dist: clonar el commit del lock (`git fetch --depth 1 https://github.com/phpstan/phpstan.git <sha>`), `git archive` a un zip y apuntar ahí el `dist` en una **copia** del lock (`COMPOSER=composer.tmp.json composer install …`), borrando la copia después. No tocar `composer.json`/`composer.lock`.
3. Correr como root: `COMPOSER_ALLOW_SUPERUSER=1 composer analyse`.
4. Tests: `cp .env.example .env && php artisan key:generate`, luego `php artisan test --compact` (sin `--parallel`).
