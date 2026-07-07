<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

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
    <body class="font-sans text-corteza antialiased bg-harina min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

        <div class="mb-8">
            <a href="/" class="text-corteza hover:text-horno transition-colors">
                <x-application-logo class="h-12 w-auto" />
            </a>
        </div>

        <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-sm rounded-xl border border-miga">
            {{ $slot }}
        </div>

    </body>
</html>
