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
        <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-800/70 bg-slate-900/50 shadow-sm backdrop-blur">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @if (session('status'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3500)"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4"
                >
                    <div class="rounded-md bg-emerald-100 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main class="pb-8">
                {{ $slot }}
            </main>

            <footer class="border-t border-slate-800/70 px-4 py-6 text-xs text-slate-500 sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} TJD Tech</p>
                    <nav class="flex flex-wrap gap-x-4 gap-y-2" aria-label="Footer">
                        <a href="{{ route('about') }}" class="transition hover:text-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-950">About</a>
                        <a href="{{ route('help') }}" class="transition hover:text-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-950">Help</a>
                        <a href="{{ route('privacy') }}" class="transition hover:text-slate-300 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-950">Privacy Policy</a>
                    </nav>
                </div>
            </footer>
        </div>
    </body>
</html>
