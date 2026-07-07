<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#FAF7F2">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @if(isset($onboardingStep) && $onboardingStep !== null)
        <script>
            window.levadoOnboarding = {
                step: {{ $onboardingStep }},
                route: '{{ request()->route()?->getName() ?? '' }}'
            };
        </script>
        @endif
    </head>
    <body class="font-sans antialiased bg-harina text-corteza">

        @if(session('impersonating_tenant_id'))
            <div class="bg-horno text-white text-sm text-center py-2 px-4 flex items-center justify-center gap-4">
                <span>Impersonando: <strong>{{ app(\App\Models\Tenant::class)->name }}</strong></span>
                <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                    @csrf
                    <button type="submit" class="underline hover:no-underline">Salir de impersonación →</button>
                </form>
            </div>
        @endif

        @include('layouts.navigation')

        <div class="flex min-h-[calc(100vh-4rem)]">

            <x-sidebar />

            <div class="flex-1 min-w-0 flex flex-col pb-16 sm:pb-0">

                @isset($header)
                    <header class="bg-white border-b border-miga">
                        <div class="py-5 px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    <x-flash-messages />
                    {{ $slot }}
                </main>

            </div>
        </div>

        <x-mobile-bottom-nav />

        <x-pwa-install-banner />

    </body>
</html>
