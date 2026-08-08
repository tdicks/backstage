@php
    $set = $setGroup['set'];
    $setKey = 'backstage:u'.auth()->id().':slot-finder:set:'.$set->id;
    $setCardClass = $set->is_hidden
        ? $hiddenCardClass
        : 'border-slate-200 bg-slate-50/95 shadow-sm';
    $songCountLabel = $setGroup['song_count'].' '.str('song')->plural($setGroup['song_count']);
    $setCardData = sprintf(
        '{ collapsed: false, setKey: %s, remainingSongs: %d, removed: false, removing: false, toggle() { this.collapsed = !this.collapsed; }, removeSet() { if (this.removed || this.removing) { return; } this.removing = true; window.setTimeout(() => { this.removed = true; }, 280); } }',
        json_encode($setKey),
        $setGroup['songs']->count(),
    );
@endphp

<x-sets.presentational.set-card
    data-tour="find-a-slot-card"
    x-data='{{ $setCardData }}'
    x-init="collapsed = localStorage.getItem(setKey) === '1'"
    x-effect="localStorage.setItem(setKey, collapsed ? '1' : '0')"
    x-on:slot-finder-song-removed.window="if ($event.detail.setKey === setKey) { remainingSongs -= 1; if (remainingSongs <= 0) { removeSet() } }"
    x-show="!removed"
    x-bind:class="removing ? 'opacity-0 translate-y-2 scale-[0.98] pointer-events-none' : ''"
    x-transition.opacity.duration.200ms
    class="rounded-xl border {{ $setCardClass }} p-4 transition-all duration-300 ease-out"
>
    <div
        class="flex cursor-pointer items-start justify-between gap-3"
        x-bind:aria-expanded="(!collapsed).toString()"
        aria-label="Toggle set details"
        role="button"
        tabindex="0"
        @click="toggle()"
        @keydown.enter.prevent="toggle()"
        @keydown.space.prevent="toggle()"
    >
        <div class="min-w-0 flex-1">
            <h4 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                <span class="truncate">{{ $set->name }}</span>
                <span class="inline-flex shrink-0 items-center text-slate-500">
                    <x-heroicon-m-chevron-up x-show="!collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                    <x-heroicon-m-chevron-down x-show="collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                </span>
            </h4>

            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <span class="inline-flex items-center gap-1.5" title="Set owner">
                    <x-heroicon-m-user class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    <span class="sr-only">Set owner</span>
                    <span>{{ $set->owner->name }}</span>
                </span>

                <span class="inline-flex items-center" title="Sign ups open">
                    <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                    <span class="sr-only">Sign ups open</span>
                </span>

                <span class="inline-flex items-center" title="{{ $set->song_requests ? 'Song requests open' : 'Song requests closed' }}">
                    <x-heroicon-m-musical-note class="h-4 w-4 {{ $set->song_requests ? 'text-emerald-700' : 'text-slate-400' }}" aria-hidden="true" />
                    <span class="sr-only">{{ $set->song_requests ? 'Song requests open' : 'Song requests closed' }}</span>
                </span>

                @if ($set->free_for_all)
                    <span class="inline-flex items-center" title="Free for all mode">
                        <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                        <span class="sr-only">Free for all mode</span>
                    </span>
                @endif

                @if ($set->is_hidden)
                    <span class="inline-flex items-center" title="Hidden set">
                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                        <span class="sr-only">Hidden set</span>
                    </span>
                @endif
            </div>

            @if ($set->description)
                <p class="mt-2 text-sm text-slate-700">{{ $set->description }}</p>
            @endif
        </div>

        <a
            href="{{ route('sessions.show', $session).'#set-'.$set->id }}"
            @click.stop
            class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
            aria-label="Go to set"
            title="Go to set"
        >
            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
        </a>
    </div>

    <div x-show="!collapsed" x-cloak x-transition class="mt-4">
        <x-sets.presentational.section-panel heading="Songs & Slots">
            <x-slot:meta>
                <p class="text-xs text-slate-500">{{ $songCountLabel }}</p>
            </x-slot:meta>

            <div class="mt-3 space-y-3">
                @foreach ($setGroup['songs'] as $songGroup)
                    @include('slot-finder.partials.song-card', ['set' => $set, 'setKey' => $setKey, 'songGroup' => $songGroup, 'slotOptions' => $slotOptions])
                @endforeach
            </div>
        </x-sets.presentational.section-panel>
    </div>
</x-sets.presentational.set-card>