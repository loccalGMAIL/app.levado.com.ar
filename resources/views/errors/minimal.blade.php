{{--
    Layout de páginas de error. Autónomo a propósito: sin @vite, sin
    componentes, sin fuentes externas — si el error es un 500, esta página
    no puede depender de nada que pueda estar roto.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        :root {
            --masa-madre: #6B5B45;
            --corteza: #3D2B1F;
            --harina: #FAF7F2;
            --miga: #F2EAD8;
            --horno: #C8622A;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: var(--harina);
            color: var(--corteza);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }
        .logo { height: 3rem; width: auto; margin-bottom: 2rem; }
        .card {
            background: #fff;
            border: 1px solid var(--miga);
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(61, 43, 31, 0.05);
            padding: 2.5rem 2rem;
            max-width: 28rem;
            width: 100%;
        }
        .code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 0.875rem;
            letter-spacing: 0.1em;
            color: var(--horno);
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        h1 {
            font-family: 'Lora', ui-serif, Georgia, serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        p { color: var(--masa-madre); line-height: 1.6; margin-bottom: 1.75rem; }
        .btn {
            display: inline-block;
            background: var(--horno);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.9; }
        .back {
            display: block;
            margin-top: 1rem;
            color: var(--masa-madre);
            font-size: 0.875rem;
            text-decoration: underline;
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
        }
        .back:hover { color: var(--corteza); }
    </style>
</head>
<body>
    <img class="logo" src="/favicon.svg" alt="{{ config('app.name') }}">

    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        @hasSection('actions')
            @yield('actions')
        @else
            <a class="btn" href="{{ url('/') }}">Volver al inicio</a>
            <button class="back" onclick="history.back()">← Volver a la página anterior</button>
        @endif
    </div>
</body>
</html>
