<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
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

            <div class="flex-1 min-w-0 flex flex-col">

                @isset($header)
                    <header class="bg-white border-b border-miga">
                        <div class="py-5 px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    {{ $slot }}
                </main>

            </div>
        </div>

    </body>
</html>
