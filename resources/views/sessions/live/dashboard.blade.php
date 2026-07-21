<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live – {{ $session->name }} – {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    <div
        class="flex min-h-screen flex-col"
        x-data="liveJamDisplay({
            dataUrl: @js(route('sessions.live.data', $session)),
        })"
        x-init="init()"
    >
        <header class="border-b border-slate-800 bg-slate-900 px-4 py-3 sm:px-6">
            <div class="mx-auto grid max-w-7xl grid-cols-[auto_1fr_auto] items-center gap-4">
                <div class="flex items-center gap-3 text-amber-400">
                    <x-application-logo class="h-9 w-9" />
                    <span class="hidden text-sm font-semibold uppercase tracking-widest text-slate-300 sm:inline">Backstage</span>
                </div>
                <div class="min-w-0 text-center">
                    <h1 class="truncate text-xl font-semibold text-slate-100 sm:text-2xl">{{ $session->name }}</h1>
                    <p class="mt-0.5 text-sm text-slate-400">{{ $session->date->format('l, F j, Y') }}</p>
                </div>
                <div class="hidden text-right text-xs uppercase tracking-wide text-slate-500 sm:block">
                    <div>Live room</div>
                    <div x-show="lastUpdated" x-cloak x-text="lastUpdated"></div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-3 sm:px-5 lg:py-4">
            <div class="mx-auto max-w-7xl">
                <div x-show="loading" class="flex items-center justify-center py-24 text-slate-400">
                    <svg class="mr-3 h-6 w-6 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading live session…
                </div>

                <div x-show="!loading && sets.length === 0" x-cloak class="rounded-xl border border-slate-800 bg-slate-900 px-6 py-20 text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-slate-500">Standing by</p>
                    <p class="mt-3 text-2xl font-semibold text-slate-200">The jam has not started yet.</p>
                </div>

                <div x-show="!loading && sets.length > 0" x-cloak class="space-y-3">
                    <template x-if="playingNow">
                        <section>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <h2 class="text-sm font-semibold uppercase tracking-widest text-emerald-300">Playing Now</h2>
                                <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-400"></span>
                            </div>
                            <div class="rounded-xl border border-emerald-700 bg-emerald-950 p-3">
                                <div class="grid gap-3 lg:grid-cols-[1fr_1.45fr]">
                                    <div>
                                        <h3 class="text-2xl font-semibold leading-tight text-slate-50 sm:text-3xl" x-text="playingNow.name"></h3>
                                        <p class="mt-1.5 text-base text-emerald-100" x-show="playingNow.owner" x-text="playingNow.owner"></p>
                                        <p class="mt-2 whitespace-pre-line rounded-lg border border-emerald-800 bg-slate-950/50 px-3 py-1.5 text-sm text-slate-200" x-show="playingNow.details" x-text="playingNow.details"></p>
                                        <p class="mt-1.5 text-xs text-emerald-100" x-show="playingNow.participants" x-text="playingNow.participants"></p>
                                    </div>
                                    <div class="grid content-start gap-2">
                                        <template x-for="song in playingNow.songs" :key="song.id">
                                            <div class="rounded-lg border border-emerald-800 bg-slate-950/60 px-3 py-1.5">
                                                <p class="text-lg font-semibold text-slate-50" x-text="`${song.artist} – ${song.title}`"></p>
                                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                    <template x-for="slot in song.slots.filter(sl => sl.filled)" :key="slot.id">
                                                        <span
                                                            class="rounded-md px-1.5 py-0.5 text-xs"
                                                            :class="slot.checked_in ? 'bg-emerald-400 text-slate-950' : 'bg-emerald-900 text-emerald-100'"
                                                            :title="slot.checked_in ? 'Checked in' : 'Not checked in'"
                                                            x-text="`${slot.name}: ${slot.user_name}`"
                                                        ></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </template>

                    <template x-if="comingUpSets.length > 0">
                        <section>
                            <h2 class="mb-2 text-sm font-semibold uppercase tracking-widest text-amber-300">Coming Up</h2>
                            <div class="grid gap-2 lg:grid-cols-2">
                                <template x-for="set in comingUpSets" :key="set.id">
                                    <div class="rounded-xl border border-amber-700 bg-amber-950 p-3">
                                        <h3 class="text-xl font-semibold text-slate-50" x-text="set.name"></h3>
                                        <p class="mt-1 text-sm text-amber-100" x-show="set.owner" x-text="set.owner"></p>
                                        <p class="mt-1.5 whitespace-pre-line rounded-lg border border-amber-800 bg-slate-950/50 px-3 py-1.5 text-xs text-slate-200" x-show="set.details" x-text="set.details"></p>
                                        <p class="mt-1.5 text-xs text-amber-100" x-show="set.participants" x-text="set.participants"></p>
                                        <template x-if="set.songs.length > 0">
                                            <div class="mt-2 divide-y divide-amber-900 overflow-hidden rounded-lg border border-amber-800 bg-slate-950/50">
                                                <template x-for="song in set.songs" :key="song.id">
                                                    <div class="px-3 py-1.5">
                                                        <p class="text-base font-semibold text-slate-100" x-text="`${song.artist} – ${song.title}`"></p>
                                                        <template x-if="song.slots.filter(sl => sl.filled).length > 0">
                                                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                                <template x-for="slot in song.slots.filter(sl => sl.filled)" :key="slot.id">
                                                                    <span
                                                                        class="inline-block rounded-md px-1.5 py-0.5 text-xs"
                                                                        :class="slot.checked_in ? 'bg-amber-300 text-slate-950' : 'bg-amber-900 text-amber-100'"
                                                                        :title="slot.checked_in ? 'Checked in' : 'Not checked in'"
                                                                        x-text="`${slot.name}: ${slot.user_name}`"
                                                                    ></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </template>

                    <template x-if="upcomingSets.length > 0">
                        <section>
                            <h2 class="mb-2 text-sm font-semibold uppercase tracking-widest text-slate-400">Up Later</h2>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="set in upcomingSets" :key="set.id">
                                    <div class="rounded-xl border border-slate-800 bg-slate-900 p-2.5">
                                        <p class="text-base font-semibold text-slate-100" x-text="set.name"></p>
                                        <p class="mt-1 text-sm text-slate-400" x-show="set.owner" x-text="set.owner"></p>
                                        <template x-if="set.details">
                                            <p class="mt-1.5 whitespace-pre-line rounded-lg border border-slate-800 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-300" x-text="set.details"></p>
                                        </template>
                                        <template x-if="set.songs.length > 0">
                                            <div class="mt-1.5 divide-y divide-slate-800 overflow-hidden rounded-lg border border-slate-800 bg-slate-950">
                                                <template x-for="song in set.songs" :key="song.id">
                                                    <div class="px-2.5 py-1.5">
                                                        <p class="text-sm font-semibold text-slate-200" x-text="`${song.artist} – ${song.title}`"></p>
                                                        <template x-if="song.slots.filter(sl => sl.filled).length > 0">
                                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                                <template x-for="slot in song.slots.filter(sl => sl.filled)" :key="slot.id">
                                                                    <span
                                                                        class="inline-block rounded-md px-1.5 py-0.5 text-xs"
                                                                        :class="slot.checked_in ? 'bg-slate-700 text-slate-100' : 'bg-slate-800 text-slate-400'"
                                                                        :title="slot.checked_in ? 'Checked in' : 'Not checked in'"
                                                                        x-text="`${slot.name}: ${slot.user_name}`"
                                                                    ></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </template>

                    <template x-if="finishedSets.length > 0 || postponedSets.length > 0">
                        <section>
                            <h2 class="mb-2 text-sm font-semibold uppercase tracking-widest text-slate-500">Finished / Postponed</h2>
                            <div class="rounded-xl border border-slate-800 bg-slate-900 p-2.5">
                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <template x-for="set in postponedSets" :key="set.id">
                                    <div class="rounded-lg border border-rose-900 bg-rose-950/70 px-3 py-2">
                                        <div class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-rose-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            Postponed
                                        </div>
                                        <p class="mt-1 truncate text-sm font-semibold text-slate-100" x-text="set.name"></p>
                                    </div>
                                </template>
                                <template x-for="set in finishedSets" :key="set.id">
                                    <div class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                                        <div class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                            Finished
                                        </div>
                                        <p class="mt-1 truncate text-sm font-semibold text-slate-100" x-text="set.name"></p>
                                    </div>
                                </template>
                                </div>
                            </div>
                        </section>
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
                this.pollTimer = setInterval(() => this.fetchData(), 5000);
            },

            async fetchData() {
                try {
                    const resp = await fetch(config.dataUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!resp.ok) {
                        console.error('Data fetch failed:', resp.status, resp.statusText);
                        return;
                    }
                    const payload = await resp.json();
                    // Create new objects to trigger Alpine reactivity
                    this.sets = (payload.sets || []).map(s => ({ ...s }));
                    if (payload.updated_at) {
                        this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();
                    }
                } catch (e) {
                    console.error('Error fetching live data:', e);
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

            get comingUpSets() {
                return this.sets.filter(s => s.status === 'coming_up').sort((a, b) => a.order - b.order);
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
