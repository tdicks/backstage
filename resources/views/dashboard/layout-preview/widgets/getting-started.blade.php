<section
    data-tour="dashboard-get-started-quest"
    id="get-started-quest"
    x-data="getStartedQuest()"
    x-show="visible"
    x-transition.opacity.duration.300ms
    aria-labelledby="preview-get-started-heading"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 id="preview-get-started-heading" class="text-lg font-semibold text-slate-900">Get started</h3>
            <p class="mt-1 text-sm text-slate-600">Here's three things you can do to get stuck in!</p>
        </div>
        <form method="POST" action="{{ route('dashboard.get-started.dismiss') }}" class="get-started-dismiss-form" x-on:submit.prevent="dismiss($event)">
            @csrf
            <button
                type="submit"
                class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400 {{ $allGetStartedItemsCompleted ? 'get-started-dismiss-glow border-emerald-300 text-emerald-600' : '' }}"
                aria-label="Dismiss get started guide"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
            </button>
        </form>
    </div>

    <ul class="mt-4 space-y-2 text-sm text-slate-700">
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
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
            <p class="font-medium">You're all set.</p>
            <p class="mt-1 text-emerald-700/90">Happy jamming! Feel free to close this window.</p>
        </div>
    @endif
</section>
