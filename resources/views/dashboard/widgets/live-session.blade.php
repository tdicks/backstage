<x-dashboard.widget-frame
    panel-classes="border-slate-800 bg-slate-900/95"
    scroll-theme="dark"
    icon-frame-classes="border-emerald-700 bg-emerald-950 text-emerald-300"
    :x-data="'dashboardLiveSession({ dataUrl: '.json_encode(route('sessions.live.data', $liveSession)).' })'"
    x-init="init()"
>
    <x-slot:icon>
        <x-live-status-icon size="h-6 w-6" title="Live session in progress" />
    </x-slot:icon>
    <x-slot:title>Live now</x-slot:title>
    <x-slot:description>{{ $liveSession->name }}</x-slot:description>

    <div class="space-y-3">
        <div x-show="loading && sets.length === 0" class="rounded-lg border border-emerald-800 bg-emerald-950/50 px-4 py-5 text-sm text-emerald-100">
            Loading live session...
        </div>

        <div x-show="errorMessage" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="errorMessage"></div>

        <template x-for="status in ['playing_now', 'coming_up', 'pending']" :key="status">
            <section x-show="setsForStatus(status).length" x-cloak>
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide" :class="statusTextClasses(status)" x-text="statusLabel(status)"></h4>
                <div class="space-y-2">
                    <template x-for="set in setsForStatus(status)" :key="set.id">
                        <article class="rounded-lg border px-3 py-2.5" :class="setCardClasses(status)">
                            <p class="text-sm font-semibold" :class="status === 'playing_now' ? 'text-lg' : ''" x-text="set.name"></p>
                            <p x-show="set.owner" class="mt-1 text-xs opacity-80" x-text="set.owner"></p>
                            <p x-show="setDetail(set)" class="mt-1.5 text-sm opacity-90" x-text="setDetail(set)"></p>
                        </article>
                    </template>
                </div>
            </section>
        </template>

        <section x-show="setsForStatus('finished').length || setsForStatus('postponed').length" x-cloak>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Finished / Postponed</h4>
            <div class="grid gap-2 sm:grid-cols-2">
                <template x-for="status in ['postponed', 'finished']" :key="status">
                    <template x-for="set in setsForStatus(status)" :key="set.id">
                        <article class="rounded-lg border px-3 py-2" :class="setCardClasses(status)">
                            <p class="text-xs font-semibold uppercase tracking-wide" :class="statusTextClasses(status)" x-text="statusLabel(status)"></p>
                            <p class="mt-1 truncate text-sm font-semibold" x-text="set.name"></p>
                        </article>
                    </template>
                </template>
            </div>
        </section>

        <p x-show="!loading && !errorMessage && sets.length === 0" x-cloak class="rounded-lg border border-dashed border-slate-700 bg-slate-950 px-4 py-5 text-center text-sm text-slate-300">
            The live running order is being set up.
        </p>
    </div>

    <x-slot:footer>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sessions.live.dashboard', $liveSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-emerald-700 bg-emerald-950/60 px-3 py-1.5 text-xs font-semibold text-emerald-200 transition hover:border-emerald-600 hover:bg-emerald-900/70">
                Open live display
                <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" />
            </a>
            @can('update', $liveSession)
                <a href="{{ route('sessions.live.manage', $liveSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-700">
                    Live control
                    <x-heroicon-m-arrow-right class="h-3.5 w-3.5" aria-hidden="true" />
                </a>
            @endcan
        </div>
    </x-slot:footer>
</x-dashboard.widget-frame>