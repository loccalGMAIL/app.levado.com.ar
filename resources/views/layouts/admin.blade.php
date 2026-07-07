<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' — ' : '' }}Backoffice — {{ config('app.name') }}</title>

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
    </head>
    <body class="font-sans antialiased bg-harina text-corteza">

        {{-- Barra superior del backoffice --}}
        <nav class="bg-corteza text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-14 items-center">
                    <div class="flex items-center gap-6">
                        <a href="{{ route('admin.dashboard') }}" class="hover:opacity-80 transition-opacity">
                            <x-application-logo class="h-7 w-auto text-white" />
                        </a>
                        <a href="{{ route('admin.tenants.index') }}"
                            class="text-sm {{ request()->routeIs('admin.tenants.*') ? 'text-white font-medium' : 'text-orange-200 hover:text-white' }} transition-colors">
                            Tenants
                        </a>
                        <a href="{{ route('admin.users.index') }}"
                            class="text-sm {{ request()->routeIs('admin.users.*') ? 'text-white font-medium' : 'text-orange-200 hover:text-white' }} transition-colors">
                            Usuarios
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}"
                            class="text-sm {{ request()->routeIs('admin.audit-logs.*') ? 'text-white font-medium' : 'text-orange-200 hover:text-white' }} transition-colors">
                            Registro
                        </a>
                        <a href="{{ route('admin.mails.index') }}"
                            class="text-sm {{ request()->routeIs('admin.mails.*') ? 'text-white font-medium' : 'text-orange-200 hover:text-white' }} transition-colors">
                            Emails
                        </a>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-orange-200">{{ Auth::user()->name }}</span>
                        <a href="{{ route('dashboard') }}" class="text-orange-200 hover:text-white transition-colors">
                            ← App
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-orange-200 hover:text-white transition-colors">
                                Salir
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        @isset($header)
            <header class="bg-white border-b border-miga">
                <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            <x-flash-messages />
            {{ $slot }}
        </main>

    </body>
</html>
