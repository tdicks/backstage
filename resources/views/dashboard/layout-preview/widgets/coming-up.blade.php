<div class="flex items-center justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700">
            <x-heroicon-m-musical-note class="h-6 w-6" aria-hidden="true" />
        </span>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Coming up</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Next jam prep</h3>
        </div>
    </div>
    @if ($nextNonLiveSession)
        <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ $nextNonLiveSession->date->format('D j M') }}</span>
    @endif
</div>

@if ($nextNonLiveSession)
    <div class="mt-4 rounded-lg border border-sky-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold text-slate-900">{{ $nextNonLiveSession->name }}</p>
            <a href="{{ route('sessions.show', $nextNonLiveSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800">
                Open session
                <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" />
            </a>
        </div>

        <div class="mt-3 grid gap-2">
            @foreach ($nextNonLiveSets as $set)
                <article class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-900">{{ $set->name }}</p>
                    <ul class="mt-1 space-y-1 text-xs text-slate-600">
                        @foreach ($set->songs as $song)
                            @php
                                $slotNames = $song->slots->pluck('name')
                                    ->filter()
                                    ->map(fn ($slotName) => $slotLabels[$slotName] ?? str((string) $slotName)->replace(['_', '-'], ' ')->title()->toString())
                                    ->join(', ');
                            @endphp
                            <li>{{ $slotNames !== '' ? $slotNames.' on ' : '' }}{{ $song->artist }} - {{ $song->title }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
@else
    <div class="mt-4 rounded-lg border border-dashed border-sky-200 bg-white px-4 py-6 text-center text-sm text-slate-600">
        Nothing lined up yet for the next non-live jam.
    </div>
@endif
