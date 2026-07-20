<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Live Jam — {{ $session->name }}</h2>
                <p class="text-sm text-slate-400">{{ $session->date->format('l, F j, Y') }} &middot; Management Dashboard</p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('sessions.live.dashboard', $session) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-amber-400 hover:text-amber-300"
                >
                    <x-heroicon-m-tv class="h-4 w-4" aria-hidden="true" />
                    Live Display
                </a>
                <a
                    href="{{ route('sessions.show', $session) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-amber-400 hover:text-amber-300"
                >
                    <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" />
                    Back to Session
                </a>
            </div>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-3xl px-4 py-6 sm:px-6"
        x-data="liveJamManage({
            dataUrl: @js(route('sessions.live.data', $session)),
            updateUrl: @js(route('sessions.live.update', $session)),
            clearUrl: @js(route('sessions.live.clear', $session)),
            csrfToken: @js(csrf_token()),
        })"
        x-init="init()"
    >
        {{-- Status bar --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm">
                <span x-show="saveSuccess" x-cloak x-transition.opacity class="font-medium text-emerald-400">Saved!</span>
                <span x-show="saveError" x-cloak x-transition.opacity class="font-medium text-rose-400" x-text="saveError"></span>
                <span x-show="!saveSuccess && !saveError && lastUpdated" x-cloak class="text-slate-400">
                    Last saved: <span x-text="lastUpdated"></span>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="clearState()"
                    :disabled="clearBusy"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800 bg-rose-900/40 px-3 py-2 text-sm font-medium text-rose-300 transition hover:border-rose-500 hover:text-rose-200 disabled:opacity-50"
                >
                    <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                    Reset
                </button>
                <button
                    type="button"
                    @click="saveState()"
                    :disabled="saveBusy"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-amber-400 disabled:opacity-50"
                >
                    <x-heroicon-m-arrow-up-tray class="h-4 w-4" aria-hidden="true" />
                    <span x-show="!saveBusy">Update</span>
                    <span x-show="saveBusy" x-cloak>Saving…</span>
                </button>
            </div>
        </div>

        {{-- Legend --}}
        <div class="mb-4 flex flex-wrap gap-3 text-xs text-slate-400">
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-rose-500"></span> No slots filled</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400"></span> Partially filled</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> All slots filled</span>
            <span class="flex items-center gap-1 ml-2"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-slate-500"></span> Finished / Postponed</span>
        </div>

        {{-- Loading state --}}
        <div x-show="loading" x-cloak class="flex items-center justify-center py-16 text-slate-400">
            <svg class="mr-2 h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading sets…
        </div>

        {{-- Set cards --}}
        <div x-show="!loading" class="space-y-3">
            <template x-for="set in orderedSets" :key="set.id">
                <div
                    class="relative overflow-hidden rounded-xl border-2 transition-all"
                    :class="setCardClasses(set)"
                    :style="setCardStyle(set)"
                >
                    {{-- Status badge --}}
                    <div class="absolute right-3 top-3 flex items-center gap-1.5">
                        <template x-if="set.status === 'playing_now'">
                            <span class="inline-flex animate-pulse items-center gap-1 rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-300 ring-1 ring-emerald-500/40">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                Playing
                            </span>
                        </template>
                        <template x-if="set.status === 'coming_up'">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-semibold text-amber-300 ring-1 ring-amber-500/40">
                                Coming Up
                            </span>
                        </template>
                        <template x-if="set.status === 'postponed'">
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/20 px-2 py-0.5 text-xs font-semibold text-rose-300 ring-1 ring-rose-500/40">
                                <x-heroicon-m-x-circle class="h-3.5 w-3.5" aria-hidden="true" />
                                Postponed
                            </span>
                        </template>
                        <template x-if="set.status === 'finished'">
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-500/20 px-2 py-0.5 text-xs font-semibold text-slate-300 ring-1 ring-slate-500/40">
                                <x-heroicon-m-check-circle class="h-3.5 w-3.5" aria-hidden="true" />
                                Finished
                            </span>
                        </template>
                    </div>

                    <div class="flex gap-3 p-4">
                        {{-- Main content --}}
                        <div class="min-w-0 flex-1 pr-24">
                            <div class="mb-1 flex flex-wrap items-baseline gap-2">
                                <h3 class="truncate text-base font-semibold text-slate-100" x-text="set.name"></h3>
                                <span class="text-xs text-slate-400" x-text="set.owner ? `by ${set.owner}` : ''"></span>
                            </div>

                            {{-- Duration & health bar --}}
                            <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                <span x-show="set.duration_seconds > 0" x-cloak>
                                    ~<span x-text="formatDuration(set.duration_seconds)"></span>
                                </span>
                                <span x-show="set.total_slots > 0">
                                    <span x-text="set.filled_slots"></span>/<span x-text="set.total_slots"></span> slots filled
                                </span>
                                <span x-show="set.total_slots === 0" x-cloak class="italic">No slots defined</span>
                            </div>

                            {{-- Health bar --}}
                            <div x-show="set.total_slots > 0 && set.status !== 'finished' && set.status !== 'postponed'" class="mb-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-700/50">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :style="`width: ${set.health}%; background: ${healthColor(set.health)};`"
                                ></div>
                            </div>

                            {{-- Songs list --}}
                            <ul class="space-y-1">
                                <template x-for="song in set.songs" :key="song.id">
                                    <li class="flex flex-wrap items-baseline gap-x-2 text-xs">
                                        <span class="font-medium text-slate-300" x-text="`${song.artist} – ${song.title}`"></span>
                                        <span x-show="song.duration" x-cloak class="text-slate-500" x-text="song.duration ? formatDuration(song.duration) : ''"></span>
                                        <span class="flex flex-wrap gap-1">
                                            <template x-for="slot in song.slots" :key="slot.id">
                                                <span
                                                    class="inline-block rounded px-1 py-0.5 text-[10px]"
                                                    :class="slot.filled ? 'bg-emerald-900/60 text-emerald-300' : 'bg-slate-700/60 text-slate-400'"
                                                    x-text="slot.user_name ? `${slot.name}: ${slot.user_name}` : slot.name"
                                                ></span>
                                            </template>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Action buttons --}}
                        <div class="flex shrink-0 flex-col gap-1.5">
                            {{-- Bring Forward --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now'"
                                @click="bringForward(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-600 bg-slate-800/60 text-slate-400 transition hover:border-amber-400 hover:text-amber-300 active:scale-95"
                                title="Bring Forward"
                            >
                                <x-heroicon-m-chevron-up class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Start --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now' && set.status !== 'finished'"
                                @click="startSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-700 bg-emerald-900/60 text-emerald-400 transition hover:border-emerald-400 hover:text-emerald-300 active:scale-95"
                                title="Start"
                            >
                                <x-heroicon-m-play class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Finish --}}
                            <button
                                type="button"
                                x-show="set.status === 'playing_now'"
                                @click="finishSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-sky-700 bg-sky-900/60 text-sky-400 transition hover:border-sky-400 hover:text-sky-300 active:scale-95"
                                title="Finish"
                            >
                                <x-heroicon-m-stop class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Postpone --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now' && set.status !== 'finished' && set.status !== 'postponed'"
                                @click="postponeSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-rose-800 bg-rose-900/40 text-rose-400 transition hover:border-rose-500 hover:text-rose-300 active:scale-95"
                                title="Postpone"
                            >
                                <x-heroicon-m-no-symbol class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Push Back --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now'"
                                @click="pushBack(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-600 bg-slate-800/60 text-slate-400 transition hover:border-amber-400 hover:text-amber-300 active:scale-95"
                                title="Push Back"
                            >
                                <x-heroicon-m-chevron-down class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="orderedSets.length === 0 && !loading" x-cloak class="rounded-xl border border-slate-800 bg-slate-900/50 px-6 py-12 text-center text-slate-400">
                No sets found for this session.
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function liveJamManage(config) {
        return {
            sets: [],
            loading: true,
            saveBusy: false,
            saveSuccess: false,
            saveError: '',
            clearBusy: false,
            lastUpdated: '',
            pollTimer: null,

            init() {
                this.fetchData();
                this.pollTimer = setInterval(() => this.fetchData(), 5000);
            },

            async fetchData() {
                try {
                    const resp = await fetch(config.dataUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!resp.ok) { return; }
                    const payload = await resp.json();

                    // Only update from server if we have no local state yet
                    if (this.sets.length === 0) {
                        this.sets = payload.sets.map(s => ({ ...s }));
                    } else {
                        // Merge health/slot data from server but preserve local order/status edits
                        payload.sets.forEach(serverSet => {
                            const local = this.sets.find(s => s.id === serverSet.id);
                            if (local) {
                                local.health = serverSet.health;
                                local.total_slots = serverSet.total_slots;
                                local.filled_slots = serverSet.filled_slots;
                                local.duration_seconds = serverSet.duration_seconds;
                                local.songs = serverSet.songs;
                            }
                        });
                    }

                    if (payload.updated_at) {
                        this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();
                    }
                } catch (e) {
                    // Silently fail polling
                } finally {
                    this.loading = false;
                }
            },

            get orderedSets() {
                const active = this.sets.filter(s => s.status !== 'postponed' && s.status !== 'finished');
                const postponed = this.sets.filter(s => s.status === 'postponed');
                const finished = this.sets.filter(s => s.status === 'finished');

                const sortActive = [...active].sort((a, b) => {
                    // playing_now always first
                    if (a.status === 'playing_now') { return -1; }
                    if (b.status === 'playing_now') { return 1; }
                    return a.order - b.order;
                });

                // Assign coming_up to the first non-playing_now active set
                const playingIndex = sortActive.findIndex(s => s.status === 'playing_now');
                sortActive.forEach((s, i) => {
                    if (s.status === 'playing_now') { return; }
                    if (playingIndex !== -1 && i === playingIndex + 1) {
                        s.status = s.status === 'pending' ? 'coming_up' : s.status;
                    } else if (s.status === 'coming_up') {
                        s.status = 'pending';
                    }
                });

                return [...sortActive, ...postponed, ...finished];
            },

            bringForward(set) {
                const active = this.sets.filter(s => s.status !== 'postponed' && s.status !== 'finished' && s.status !== 'playing_now');
                const idx = active.findIndex(s => s.id === set.id);
                if (idx <= 0) { return; }
                const prev = active[idx - 1];
                const tempOrder = set.order;
                set.order = prev.order;
                prev.order = tempOrder;
                if (set.status === 'postponed') {
                    set.status = 'pending';
                }
            },

            pushBack(set) {
                const active = this.sets.filter(s => s.status !== 'postponed' && s.status !== 'finished' && s.status !== 'playing_now');
                const idx = active.findIndex(s => s.id === set.id);
                if (idx >= active.length - 1) { return; }
                const next = active[idx + 1];
                const tempOrder = set.order;
                set.order = next.order;
                next.order = tempOrder;
            },

            startSet(set) {
                // Clear any existing playing_now
                this.sets.forEach(s => {
                    if (s.status === 'playing_now') { s.status = 'pending'; }
                });
                set.status = 'playing_now';
                set.order = -1;
            },

            finishSet(set) {
                set.status = 'finished';
                set.order = 9999 + set.id;
            },

            postponeSet(set) {
                set.status = 'postponed';
            },

            async saveState() {
                this.saveBusy = true;
                this.saveSuccess = false;
                this.saveError = '';

                try {
                    const payload = {
                        sets: this.sets.map((s, i) => ({
                            set_id: s.id,
                            status: s.status,
                            order: s.order,
                        })),
                    };

                    const resp = await fetch(config.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!resp.ok) { throw new Error('Save failed'); }

                    this.saveSuccess = true;
                    this.lastUpdated = new Date().toLocaleTimeString();
                    setTimeout(() => { this.saveSuccess = false; }, 2500);
                } catch (e) {
                    this.saveError = 'Could not save. Please try again.';
                    setTimeout(() => { this.saveError = ''; }, 4000);
                } finally {
                    this.saveBusy = false;
                }
            },

            async clearState() {
                if (!confirm('Reset the live state? This cannot be undone.')) { return; }
                this.clearBusy = true;
                try {
                    await fetch(config.clearUrl, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                    });
                    this.sets = [];
                    this.loading = true;
                    await this.fetchData();
                } finally {
                    this.clearBusy = false;
                }
            },

            setCardClasses(set) {
                if (set.status === 'finished') {
                    return 'border-slate-700 bg-slate-800/40 opacity-60';
                }
                if (set.status === 'postponed') {
                    return 'border-rose-900 bg-rose-950/30 opacity-70';
                }
                if (set.status === 'playing_now') {
                    return 'border-emerald-500 shadow-lg shadow-emerald-900/30';
                }
                if (set.status === 'coming_up') {
                    return 'border-amber-600/60';
                }
                return 'border-slate-700';
            },

            setCardStyle(set) {
                if (set.status === 'finished' || set.status === 'postponed') {
                    return '';
                }
                const h = set.health / 100;
                // Interpolate red (0%) → amber (50%) → green (100%)
                const r = Math.round(239 - h * (239 - 34));
                const g = Math.round(68 + h * (197 - 68));
                const b = Math.round(68 - h * (68 - 94));
                return `background: linear-gradient(135deg, rgba(${r},${g},${b},0.06) 0%, rgba(15,23,42,0.0) 60%);`;
            },

            healthColor(health) {
                const h = health / 100;
                const r = Math.round(239 - h * (239 - 34));
                const g = Math.round(68 + h * (197 - 68));
                const b = Math.round(68 - h * (68 - 94));
                return `rgb(${r},${g},${b})`;
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
    @endpush
</x-app-layout>
