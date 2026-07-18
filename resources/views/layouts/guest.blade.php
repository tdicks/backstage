<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-100">
        <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-blue-950 via-slate-900 to-slate-950 px-4 py-8 sm:px-6">
            <div>
                <a href="/">
                    <x-application-logo class="h-20 w-20 fill-current text-blue-300" />
                </a>
            </div>

            <div class="mt-6 w-full max-w-md overflow-hidden rounded-xl border border-blue-900/40 bg-slate-100/95 px-6 py-5 text-slate-900 shadow-2xl shadow-blue-950/50 backdrop-blur">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
