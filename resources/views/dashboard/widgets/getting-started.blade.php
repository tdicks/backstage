<x-dashboard.widget-frame
    panel-classes="border-amber-200 bg-white/95"
    icon-frame-classes="border-amber-200 bg-amber-50 text-amber-700"
    data-tour="dashboard-get-started-quest"
    id="get-started-quest"
    x-data="getStartedQuest()"
    x-show="visible"
    x-transition.opacity.duration.300ms
    aria-labelledby="preview-get-started-heading"
>
    <x-slot:icon>
        <x-heroicon-m-arrow-right-circle class="h-6 w-6" aria-hidden="true" />
    </x-slot:icon>
    <x-slot:title>Three quick steps</x-slot:title>
    <x-slot:description>Here's three things you can do to get stuck in.</x-slot:description>

    <ul class="space-y-2 text-sm text-slate-700">
        @foreach ($getStartedItems as $item)
            <li class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                @if ($item['completed'])
                    <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
                @else
                    <x-heroicon-m-arrow-right-circle class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                @endif
                <div class="min-w-0">
                    <a href="{{ $item['href'] }}" class="font-medium text-slate-900 hover:text-sky-700">{{ $item['label'] }}</a>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $item['description'] }}</p>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($allGetStartedItemsCompleted)
        <x-slot:footer>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
                <p class="font-medium">You're all set.</p>
                <p class="mt-1 text-emerald-700/90">Happy jamming! Feel free to close this window.</p>
            </div>
        </x-slot:footer>
    @else
        <x-slot:footer>
            <form method="POST" action="{{ route('dashboard.get-started.dismiss') }}" class="get-started-dismiss-form" x-on:submit.prevent="dismiss($event)">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400"
                >
                    Dismiss guide
                </button>
            </form>
        </x-slot:footer>
    @endif
</x-dashboard.widget-frame>

