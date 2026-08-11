<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    <main class="relative flex min-h-screen items-center overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="absolute inset-0 opacity-40" style="background-image: linear-gradient(135deg, rgb(15 23 42) 25%, transparent 25%), linear-gradient(225deg, rgb(15 23 42) 25%, transparent 25%), linear-gradient(45deg, rgb(15 23 42) 25%, transparent 25%), linear-gradient(315deg, rgb(15 23 42) 25%, rgb(2 6 23) 25%); background-position: 24px 0, 24px 0, 0 0, 0 0; background-size: 48px 48px; background-repeat: repeat;"></div>
        <div class="relative mx-auto w-full max-w-5xl">
            <div class="grid items-center gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16">
                <section>
                    <div class="flex items-center gap-4 text-amber-400">
                        <x-application-logo class="h-16 w-16" />
                        <span class="text-lg font-semibold uppercase tracking-[0.22em] text-slate-200">Backstage</span>
                    </div>
                    <h1 class="mt-10 max-w-3xl text-5xl font-semibold leading-tight text-white sm:text-6xl">Make the next jam happen.</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">Backstage keeps jam sessions, sets, songs, performers, and live running order in one shared room, so everyone can spend less time coordinating and more time playing.</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-md bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-slate-950">
                            Join Backstage
                            <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex items-center rounded-md border border-slate-600 bg-slate-900/80 px-5 py-3 text-sm font-semibold text-slate-100 transition hover:border-slate-400 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2 focus:ring-offset-slate-950">Log in</a>
                    </div>
                </section>

                <section class="border-l-2 border-amber-400/80 pl-6 sm:pl-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-300">The room at a glance</p>
                    <dl class="mt-6 space-y-6">
                        <div>
                            <dt class="text-lg font-semibold text-slate-100">Build the lineup</dt>
                            <dd class="mt-1 text-sm leading-6 text-slate-400">Create sets, add songs, and find the players for every part.</dd>
                        </div>
                        <div>
                            <dt class="text-lg font-semibold text-slate-100">Keep everyone in sync</dt>
                            <dd class="mt-1 text-sm leading-6 text-slate-400">Share requests, confirm slots, and see who is ready to play.</dd>
                        </div>
                        <div>
                            <dt class="text-lg font-semibold text-slate-100">Run it live</dt>
                            <dd class="mt-1 text-sm leading-6 text-slate-400">Use the live board to keep the night moving from one set to the next.</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </main>
</body>
</html>