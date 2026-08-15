<x-dashboard.widget-frame
    panel-classes="border-emerald-200 bg-emerald-50/95"
    icon-frame-classes="border-emerald-200 bg-emerald-100 text-emerald-800"
    x-data="dashboardLiveSession({ dataUrl: @js(route('sessions.live.data', $liveSession)) })"
    x-init="init()"
>
    <x-slot:icon>
        <x-live-status-icon size="h-6 w-6" title="Live session in progress" />
    </x-slot:icon>
    <x-slot:title>Live now</x-slot:title>
    <x-slot:description>{{ $liveSession->name }}</x-slot:description>

    <div class="space-y-3">
        <div x-show="loading && !playingNow && !comingUp" class="rounded-lg border border-emerald-200 bg-white/80 px-4 py-5 text-sm text-emerald-800">
            Loading live session...
        </div>

        <div x-show="errorMessage" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="errorMessage"></div>

        <div x-show="playingNow" x-cloak class="rounded-lg border border-emerald-300 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Playing now</p>
            <p class="mt-1 text-base font-semibold text-slate-900" x-text="playingNow?.name"></p>
            <p x-show="setDetail(playingNow)" class="mt-1 text-sm text-slate-600" x-text="setDetail(playingNow)"></p>
        </div>

        <div x-show="comingUp" x-cloak class="rounded-lg border border-amber-200 bg-amber-50/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Coming up</p>
            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="comingUp?.name"></p>
            <p x-show="setDetail(comingUp)" class="mt-1 text-sm text-slate-600" x-text="setDetail(comingUp)"></p>
        </div>

        <p x-show="!loading && !errorMessage && !playingNow && !comingUp" x-cloak class="rounded-lg border border-dashed border-emerald-200 bg-white/70 px-4 py-5 text-center text-sm text-slate-600">
            The live running order is being set up.
        </p>
    </div>

    <x-slot:footer>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sessions.live.dashboard', $liveSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100">
                Open live display
                <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" />
            </a>
            @can('update', $liveSession)
                <a href="{{ route('sessions.live.manage', $liveSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    Live control
                    <x-heroicon-m-arrow-right class="h-3.5 w-3.5" aria-hidden="true" />
                </a>
            @endcan
        </div>
    </x-slot:footer>
</x-dashboard.widget-frame>