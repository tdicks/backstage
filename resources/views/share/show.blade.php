<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100">
        <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
            <article class="w-full rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">Backstage</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $heading }}</h1>
                <p class="mt-3 text-base leading-7 text-slate-300">{{ $summary }}</p>

                @if ($items->isNotEmpty())
                    <ul class="mt-5 space-y-2 text-sm text-slate-200">
                        @foreach ($items as $item)
                            <li class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2">{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        </main>
    </body>
</html>
