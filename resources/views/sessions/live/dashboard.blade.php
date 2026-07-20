<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live – {{ $session->name }} – {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" style="background: #0f172a; color: #f1f5f9; min-height: 100vh;">
    <div
        class="flex min-h-screen flex-col"
        x-data="liveJamDisplay({
            dataUrl: @js(route('sessions.live.data', $session)),
        })"
        x-init="init()"
    >
        {{-- Header --}}
        <header class="border-b border-slate-800 bg-slate-950/80 px-6 py-4 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-100">{{ $session->name }}</h1>
                    <p class="text-sm text-slate-400">{{ $session->date->format('l, F j, Y') }}</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <div>Live Display</div>
                    <div x-show="lastUpdated" x-cloak x-text="`Updated: ${lastUpdated}`"></div>
                </div>
            </div>
        </header>

        {{-- Main content --}}
        <main class="flex-1 px-4 py-8 sm:px-6">
            <div class="mx-auto max-w-6xl">

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center justify-center py-24 text-slate-400">
                    <svg class="mr-3 h-6 w-6 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading live session…
                </div>

                {{-- No active state --}}
                <div x-show="!loading && activeSets.length === 0" x-cloak class="flex flex-col items-center justify-center gap-4 py-24 text-center text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                    <p class="text-lg">The jam hasn't started yet. Check back soon!</p>
                </div>

                {{-- Sets grid --}}
                <div x-show="!loading && activeSets.length > 0" x-cloak>

                    {{-- Now Playing --}}
                    <template x-if="playingNow">
                        <div class="mb-8">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-400"></span>
                                <h2 class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Now Playing</h2>
                            </div>
                            <div
                                class="overflow-hidden rounded-2xl border-2 border-emerald-500 shadow-xl shadow-emerald-900/40"
                                style="background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(15,23,42,0.6));"
                            >
                                <div class="px-6 py-5">
                                    <div class="mb-1 flex flex-wrap items-baseline gap-3">
                                        <h3 class="text-2xl font-bold text-slate-100" x-text="playingNow.name"></h3>
                                        <span class="text-slate-400" x-show="playingNow.owner" x-text="`by ${playingNow.owner}`"></span>
                                    </div>
                                    <template x-if="playingNow.duration_seconds > 0">
                                        <p class="mb-3 text-sm text-emerald-400/80" x-text="`Approx. ${formatDuration(playingNow.duration_seconds)}`"></p>
                                    </template>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="song in playingNow.songs" :key="song.id">
                                            <div class="rounded-lg bg-slate-800/60 px-3 py-2">
                                                <p class="text-sm font-medium text-slate-200" x-text="`${song.artist} – ${song.title}`"></p>
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    <template x-for="slot in song.slots.filter(sl => sl.filled)" :key="slot.id">
                                                        <span class="rounded bg-emerald-900/60 px-1.5 py-0.5 text-[11px] text-emerald-300" x-text="`${slot.name}: ${slot.user_name}`"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Coming Up --}}
                    <template x-if="comingUp">
                        <div class="mb-8">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-amber-400">Coming Up Next</h2>
                            <div
                                class="overflow-hidden rounded-xl border border-amber-600/40 bg-amber-950/20"
                            >
                                <div class="px-5 py-4">
                                    <div class="mb-1 flex flex-wrap items-baseline gap-3">
                                        <h3 class="text-xl font-semibold text-slate-100" x-text="comingUp.name"></h3>
                                        <span class="text-slate-400" x-show="comingUp.owner" x-text="`by ${comingUp.owner}`"></span>
                                    </div>
                                    <template x-if="comingUp.duration_seconds > 0">
                                        <p class="mb-2 text-sm text-amber-400/70" x-text="`Approx. ${formatDuration(comingUp.duration_seconds)}`"></p>
                                    </template>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="song in comingUp.songs" :key="song.id">
                                            <span class="rounded-md bg-slate-800/50 px-2.5 py-1.5 text-sm text-slate-300" x-text="`${song.artist} – ${song.title}`"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Upcoming queue --}}
                    <template x-if="upcomingSets.length > 0">
                        <div class="mb-8">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-400">Up Later</h2>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="set in upcomingSets" :key="set.id">
                                    <div class="rounded-xl border border-slate-800 bg-slate-800/30 px-4 py-3">
                                        <p class="font-semibold text-slate-200" x-text="set.name"></p>
                                        <p class="text-xs text-slate-500" x-show="set.owner" x-text="`by ${set.owner}`"></p>
                                        <template x-if="set.duration_seconds > 0">
                                            <p class="mt-1 text-xs text-slate-500" x-text="`~${formatDuration(set.duration_seconds)}`"></p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Postponed / Finished --}}
                    <template x-if="finishedSets.length > 0 || postponedSets.length > 0">
                        <div class="opacity-50">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500">Completed / Postponed</h2>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="set in postponedSets" :key="set.id">
                                    <span class="flex items-center gap-1 rounded-full border border-rose-900 bg-rose-950/40 px-3 py-1 text-sm text-rose-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        <span x-text="set.name"></span>
                                    </span>
                                </template>
                                <template x-for="set in finishedSets" :key="set.id">
                                    <span class="flex items-center gap-1 rounded-full border border-slate-700 bg-slate-800/50 px-3 py-1 text-sm text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                        <span x-text="set.name"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </div>

    <script>
    function liveJamDisplay(config) {
        return {
            sets: [],
            loading: true,
            lastUpdated: '',

            init() {
                this.fetchData();
                setInterval(() => this.fetchData(), 5000);
            },

            async fetchData() {
                try {
                    const resp = await fetch(config.dataUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!resp.ok) { return; }
                    const payload = await resp.json();
                    this.sets = payload.sets || [];
                    if (payload.updated_at) {
                        this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();
                    }
                } catch (e) {
                    // Silently fail
                } finally {
                    this.loading = false;
                }
            },

            get activeSets() {
                return this.sets.filter(s => s.status !== 'postponed' && s.status !== 'finished');
            },

            get playingNow() {
                return this.sets.find(s => s.status === 'playing_now') ?? null;
            },

            get comingUp() {
                return this.sets.find(s => s.status === 'coming_up') ?? null;
            },

            get upcomingSets() {
                return this.sets.filter(s => s.status === 'pending').sort((a, b) => a.order - b.order);
            },

            get postponedSets() {
                return this.sets.filter(s => s.status === 'postponed');
            },

            get finishedSets() {
                return this.sets.filter(s => s.status === 'finished');
            },

            formatDuration(seconds) {
                if (!seconds) { return ''; }
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return m + ':' + String(s).padStart(2, '0');
            },
        };
    }
    </script>
</body>
</html>
