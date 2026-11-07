<?php

use App\Enums\Theme;

/*
 * Los temas (claro / oscuro / centeno) funcionan redefiniendo variables CSS: cada
 * color de tailwind.config.js es `rgb(var(--c-…) / <alpha-value>)` y los valores
 * viven en el `:root` de resources/css/app.css. Un color escrito a mano —un hex en
 * una vista, un `bg-white`— queda igual en todos los temas y rompe el oscuro sin
 * que nada falle. Estas anclas fijan que no vuelva a pasar.
 */

/**
 * Vistas que se renderizan fuera del CSS de la app y por eso llevan colores
 * literales: mails, PDFs de dompdf (sin variables CSS) y la página de error
 * autónoma.
 */
const VISTAS_CON_COLORES_LITERALES = [
    'emails/',
    'errors/minimal.blade.php',
    'fixed-costs/report/_styles.blade.php',
    'production-orders/sheets/_styles.blade.php',
];

/**
 * El fondo blanco que `image-compress.js` pinta en el canvas antes de exportar a
 * JPEG: es parte de la imagen que se sube, no de la interfaz.
 */
const JS_CON_COLORES_LITERALES = [
    'image-compress.js',
];

function projectPath(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, string> ruta relativa => contenido
 */
function sourceFiles(string $directory, string $extension, array $excluded = []): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(projectPath($directory), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $relative = str_replace(projectPath($directory).'/', '', $file->getPathname());

        if (! str_ends_with($relative, $extension)) {
            continue;
        }

        foreach ($excluded as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                continue 2;
            }
        }

        $files[$relative] = (string) file_get_contents($file->getPathname());
    }

    ksort($files);

    return $files;
}

/**
 * @return list<string> "archivo:línea  texto" de cada coincidencia
 */
function matchesIn(array $files, string $pattern): array
{
    $found = [];

    foreach ($files as $path => $contents) {
        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $found[] = $path.':'.($index + 1).'  '.trim($line);
            }
        }
    }

    return $found;
}

function appCssWithoutComments(): string
{
    return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(projectPath('resources/css/app.css')));
}

/**
 * Variables de un bloque de tema de app.css.
 *
 * @param  string  $selector  `:root` (claro), `oscuro` o `centeno`
 * @return array<string, string> nombre (sin `--c-`) => canales RGB
 */
function themeTokens(string $selector): array
{
    $pattern = $selector === ':root'
        ? '/:root,\s*\[data-theme="claro"\]\s*\{(.*?)\}/s'
        : '/\[data-theme="'.$selector.'"\]\s*\{(.*?)\}/s';

    preg_match($pattern, appCssWithoutComments(), $block);
    preg_match_all('/--c-([\w-]+)\s*:\s*([\d ]+);/', $block[1] ?? '', $tokens);

    return array_combine($tokens[1], array_map('trim', $tokens[2]));
}

/**
 * @return list<string>
 */
function definedColorTokens(): array
{
    return array_keys(themeTokens(':root'));
}

/**
 * Contraste WCAG entre dos colores dados como canales RGB ("61 43 31").
 */
function contrastRatio(string $foreground, string $background): float
{
    $luminance = function (string $channels): float {
        $linear = array_map(function (string $channel): float {
            $value = ((int) $channel) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, explode(' ', $channels));

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    };

    $lighter = max($luminance($foreground), $luminance($background));
    $darker = min($luminance($foreground), $luminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

it('no usa bg-white en las vistas: el fondo de cards y modales es bg-superficie', function () {
    $views = sourceFiles('resources/views', '.blade.php', VISTAS_CON_COLORES_LITERALES);

    expect(matchesIn($views, '#(?<![\w-])(?:[\w-]+:)*bg-white(?![\w/-])#'))->toBe([]);
});

it('no usa los colores de texto como fondo oscuro: botones y barras van con tinta, barra y sobre-barra', function () {
    // En el tema oscuro corteza y masa-madre se aclaran (son texto) y harina se
    // oscurece (es el fondo): un botón bg-corteza quedaría claro con letra blanca.
    $views = sourceFiles('resources/views', '.blade.php', VISTAS_CON_COLORES_LITERALES);

    expect(matchesIn($views, '#(?<![\w-])(?:[\w-]+:)*(?:bg-corteza|bg-masa-madre|text-harina)(?![\w-])#'))->toBe([]);
});

it('no escribe colores hex en las vistas', function () {
    $views = sourceFiles('resources/views', '.blade.php', VISTAS_CON_COLORES_LITERALES);
    $views = array_map(fn (string $contents) => preg_replace('/<meta name="theme-color"[^>]*>/', '', $contents), $views);

    expect(matchesIn($views, '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?(?:[0-9a-fA-F]{2})?\b/'))->toBe([]);
});

it('no escribe colores hex en el JS', function () {
    $scripts = sourceFiles('resources/js', '.js', JS_CON_COLORES_LITERALES);

    expect(matchesIn($scripts, '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?(?:[0-9a-fA-F]{2})?\b/'))->toBe([]);
});

it('no escribe colores hex en app.css fuera de los comentarios', function () {
    expect(matchesIn(['app.css' => appCssWithoutComments()], '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?(?:[0-9a-fA-F]{2})?\b/'))->toBe([]);
});

it('define en :root cada token que usan la config de Tailwind, el CSS, las vistas y los gráficos', function () {
    $config = (string) file_get_contents(projectPath('tailwind.config.js'));

    preg_match_all("/token\('([\w-]+)'\)/", $config, $tokens);
    preg_match_all("/scale\('(\w+)', \[([\d, ]+)\]\)/", $config, $scales, PREG_SET_ORDER);

    $used = $tokens[1];

    foreach ($scales as [, $name, $shades]) {
        foreach (array_map('trim', explode(',', $shades)) as $shade) {
            $used[] = "{$name}-{$shade}";
        }
    }

    $sources = appCssWithoutComments()
        .implode("\n", sourceFiles('resources/views', '.blade.php', VISTAS_CON_COLORES_LITERALES))
        .implode("\n", sourceFiles('resources/js', '.js'));

    preg_match_all('/var\(--c-([\w-]+)\)/', $sources, $variables);
    preg_match_all("/themeColor\('([\w-]+)'\)/", $sources, $chartColors);

    $used = array_values(array_unique([...$used, ...$variables[1], ...$chartColors[1]]));

    expect($used)->not->toBeEmpty()
        ->and(array_values(array_diff($used, definedColorTokens())))->toBe([]);
});

it('no usa variantes dark: en las vistas: el tema oscuro sale de las variables', function () {
    // Tailwind resuelve dark: con prefers-color-scheme, así que se aplicaría según
    // el sistema operativo y no según el tema que eligió el usuario.
    $views = sourceFiles('resources/views', '.blade.php', VISTAS_CON_COLORES_LITERALES);

    expect(matchesIn($views, '#(?<![\w-])dark:#'))->toBe([]);
});

it('el tema oscuro redefine todas las variables: una que falte quedaría clara', function () {
    $claro = themeTokens(':root');
    $oscuro = themeTokens('oscuro');

    expect($claro)->not->toBeEmpty()
        ->and(array_values(array_diff(array_keys($claro), array_keys($oscuro))))->toBe([])
        ->and(array_values(array_diff(array_keys($oscuro), array_keys($claro))))->toBe([]);
});

it('centeno sólo redefine variables que existen', function () {
    $centeno = themeTokens('centeno');

    expect($centeno)->not->toBeEmpty()
        ->and(array_values(array_diff(array_keys($centeno), definedColorTokens())))->toBe([]);
});

it('el color de la barra del navegador coincide con el fondo de cada tema', function (Theme $theme) {
    $tokens = [...themeTokens(':root'), ...($theme === Theme::Claro ? [] : themeTokens($theme->value))];
    $hex = sprintf('#%02X%02X%02X', ...array_map('intval', explode(' ', $tokens['harina'])));

    expect($theme->themeColor())->toBe($hex);
})->with(Theme::cases());

it('mantiene el contraste de los textos principales en cada tema', function (Theme $theme) {
    // Pisos: 4,5 (AA) para texto; 3 para el acento, que en claro ya está en 4,0
    // como texto sobre blanco y se usa sobre todo en botones y enlaces.
    $tokens = [...themeTokens(':root'), ...($theme === Theme::Claro ? [] : themeTokens($theme->value)), 'white' => '255 255 255'];

    $pairs = [
        ['corteza', 'harina', 4.5], ['corteza', 'superficie', 4.5], ['corteza', 'miga', 4.5],
        ['masa-madre', 'harina', 4.5], ['masa-madre', 'superficie', 4.5], ['masa-madre', 'miga', 4.5],
        ['sobre-barra', 'barra', 4.5], ['white', 'tinta', 4.5],
        ['horno', 'superficie', 3], ['white', 'horno', 3],
        ['gray-500', 'superficie', 4.5], ['gray-600', 'superficie', 4.5],
        ['red-600', 'superficie', 4.5], ['red-700', 'red-50', 4.5],
        ['green-700', 'green-50', 4.5], ['amber-700', 'amber-50', 4.5],
    ];

    $failing = [];

    foreach ($pairs as [$foreground, $background, $minimum]) {
        $ratio = contrastRatio($tokens[$foreground], $tokens[$background]);

        if ($ratio < $minimum) {
            $failing[] = sprintf('%s sobre %s: %.2f (mínimo %.1f)', $foreground, $background, $ratio, $minimum);
        }
    }

    expect($failing)->toBe([]);
})->with(Theme::cases());
