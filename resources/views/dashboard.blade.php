<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-300">Backstage</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-100">Welcome back, {{ auth()->user()->name }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <section aria-labelledby="next-jam-heading">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-300">Your next jam</p>
                        <h2 id="next-jam-heading" class="mt-1 text-2xl font-semibold text-slate-100">Your commitments</h2>
                    </div>
                    <a href="{{ route('my-sets.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400">
                        My Sets
                        <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>

                @if ($nextSession)
                    <div class="mt-5 border border-amber-700/80 bg-amber-950/30 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-amber-300">{{ $nextSession->date->format('l, F j') }}</p>
                                <h3 class="mt-2 text-3xl font-semibold text-white">{{ $nextSession->name }}</h3>
                            </div>
                            <a href="{{ route('sessions.show', $nextSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-amber-500 px-3 py-2 text-sm font-semibold text-amber-100 transition hover:bg-amber-500 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-amber-300">
                                Open session
                                <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>

                        <div class="mt-6 grid gap-3">
                            @foreach ($nextSessionSets as $set)
                                <a href="{{ route('sessions.show', $nextSession).'#set-'.$set->id }}" class="group flex items-center justify-between gap-4 border border-slate-700 bg-slate-900/80 px-4 py-3 transition hover:border-amber-500 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <div class="min-w-0">
                                        <h4 class="truncate font-semibold text-slate-100 group-hover:text-white">{{ $set->name }}</h4>
                                        <p class="mt-1 text-sm text-slate-400">
                                            @foreach ($set->songs as $song)
                                                {{ $song->artist }} - {{ $song->title }}@if (! $loop->last); @endif
                                            @endforeach
                                        </p>
                                    </div>
                                    <x-heroicon-m-chevron-right class="h-5 w-5 shrink-0 text-slate-500 transition group-hover:text-amber-300" aria-hidden="true" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="mt-5 border border-dashed border-slate-700 bg-slate-900/50 px-6 py-12 text-center">
                        <x-heroicon-m-musical-note class="mx-auto h-10 w-10 text-slate-500" aria-hidden="true" />
                        <p class="mt-4 text-lg font-semibold text-slate-200">No upcoming slots yet</p>
                        <p class="mt-2 text-sm text-slate-400">Browse the jam sessions to find a set that needs you.</p>
                        <a href="{{ route('sessions.index') }}" class="mt-5 inline-flex items-center gap-1.5 rounded-md border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-400 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400">
                            Browse jam sessions
                            <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                        </a>
                    </div>
                @endif
            </section>

            <aside class="border-t border-slate-800 pt-6 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-300">Quick links</p>
                <nav class="mt-4 grid gap-2" aria-label="Dashboard quick links">
                    <a href="{{ route('sessions.index') }}" class="flex items-center gap-3 border border-slate-700 bg-slate-900 px-3 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                        <x-heroicon-m-calendar-days class="h-5 w-5 text-amber-300" aria-hidden="true" />
                        Jam Sessions
                    </a>
                    <a href="{{ route('my-sets.index') }}" class="flex items-center gap-3 border border-slate-700 bg-slate-900 px-3 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                        <x-heroicon-m-musical-note class="h-5 w-5 text-emerald-300" aria-hidden="true" />
                        My Sets
                    </a>
                    <a href="{{ route('directory.index') }}" class="flex items-center gap-3 border border-slate-700 bg-slate-900 px-3 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">
                        <x-heroicon-m-user-group class="h-5 w-5 text-sky-300" aria-hidden="true" />
                        Who&apos;s Who
                    </a>
                </nav>
            </aside>
        </div>
    </div>
</x-app-layout>