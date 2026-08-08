<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-100">My Sets</h2>
            </div>
            <a href="{{ route('planned-sets.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                Planned Sets
                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
            </a>
        </div>
    </x-slot>

    <div class="py-10" x-data="mySetsLibrary()" @keydown.escape.window="closeSetPopover()">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                <x-text-input x-model="query" placeholder="Search by set, owner, collaborator, or session" class="block w-full" />
            </section>

            <section class="space-y-4">
                <h3 class="text-xl font-semibold text-white">Upcoming</h3>

                @if ($upcomingPlanned->isEmpty() && $upcomingSessionGroups->isEmpty())
                    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                        No upcoming sets yet.
                    </div>
                @endif

                @if ($upcomingPlanned->isNotEmpty())
                    <article class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Planned</h4>
                            <span class="text-xs text-slate-500">{{ $upcomingPlanned->count() }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ($upcomingPlanned as $card)
                                @php
                                    $set = $card['set'];
                                    $searchText = collect([
                                        $set->name,
                                        $set->owner?->name,
                                        $card['collaboratorNames']->implode(' '),
                                        'Planned',
                                    ])->filter()->implode(' ');
                                    $openUrl = route('planned-sets.index').'#set-'.$set->id;
                                    $manageUrl = route('planned-sets.index').'#set-'.$set->id;
                                    $lifecycleLabel = $card['lifecycle'] === \App\Models\Set::LIFECYCLE_DRAFT ? 'Draft' : 'Scheduled';
                                @endphp
                                <button
                                    type="button"
                                    x-show="matchesSetCard($el)"
                                    @click="openSetPopover($el)"
                                    data-search="{{ mb_strtolower($searchText) }}"
                                    data-name="{{ $set->name }}"
                                    data-owner="{{ $set->owner?->name ?? 'Unknown' }}"
                                    data-session="Planned"
                                    data-date="Not scheduled"
                                    data-songs="{{ $set->songs_count }}"
                                    data-lifecycle-label="{{ $lifecycleLabel }}"
                                    data-owned="{{ $card['isOwned'] ? '1' : '0' }}"
                                    data-collaborator="{{ $card['isCollaborator'] ? '1' : '0' }}"
                                    data-has-my-slots="{{ $card['hasMySlots'] ? '1' : '0' }}"
                                    data-collaborators="{{ $card['collaboratorNames']->implode('|') }}"
                                    data-open-url="{{ $openUrl }}"
                                    data-manage-url="{{ $manageUrl }}"
                                    data-card-id="planned-{{ $set->id }}"
                                    :aria-expanded="popoverVisible && popoverCardId === 'planned-{{ $set->id }}'"
                                    class="group relative aspect-square overflow-hidden rounded-xl border border-slate-300 bg-slate-100 p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"
                                >
                                    <x-my-sets.artwork-grid :tiles="$card['artworkTiles']" :song-count="$set->songs_count" :seed="$set->id" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-900/35 to-transparent"></div>
                                    <div class="relative z-10 flex h-full flex-col justify-between">
                                        <p class="line-clamp-2 w-fit max-w-full rounded-md border border-white/20 bg-black/40 px-2 py-1 text-sm font-semibold text-white backdrop-blur-[2px]">{{ $set->name }}</p>
                                        <div>
                                            <div class="inline-flex flex-col rounded-md border border-white/20 bg-black/35 px-2 py-1 backdrop-blur-[2px]">
                                                <p class="text-[11px] text-slate-100">{{ $set->owner?->name ?? 'Unknown' }}</p>
                                                <p class="mt-1 text-[11px] font-medium text-slate-200">{{ $set->songs_count }} songs</p>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </article>
                @endif

                @foreach ($upcomingSessionGroups as $group)
                    @php
                        $session = $group['session'];
                    @endphp
                    <article class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ $session->name }}</h4>
                                <p class="text-xs text-slate-500">{{ $session->date?->format('D, M j, Y') }}</p>
                            </div>
                            <span class="text-xs text-slate-500">{{ $group['sets']->count() }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ($group['sets'] as $card)
                                @php
                                    $set = $card['set'];
                                    $searchText = collect([
                                        $set->name,
                                        $set->owner?->name,
                                        $card['collaboratorNames']->implode(' '),
                                        $session->name,
                                    ])->filter()->implode(' ');
                                    $openUrl = route('sessions.show', $session).'#set-'.$set->id;
                                    $manageUrl = $card['lifecycle'] === \App\Models\Set::LIFECYCLE_DRAFT
                                        ? route('planned-sets.index').'#set-'.$set->id
                                        : '';
                                    $lifecycleLabel = $card['lifecycle'] === \App\Models\Set::LIFECYCLE_DRAFT ? 'Draft' : 'Scheduled';
                                @endphp
                                <button
                                    type="button"
                                    x-show="matchesSetCard($el)"
                                    @click="openSetPopover($el)"
                                    data-search="{{ mb_strtolower($searchText) }}"
                                    data-name="{{ $set->name }}"
                                    data-owner="{{ $set->owner?->name ?? 'Unknown' }}"
                                    data-session="{{ $session->name }}"
                                    data-date="{{ $session->date?->format('D, M j, Y') }}"
                                    data-songs="{{ $set->songs_count }}"
                                    data-lifecycle-label="{{ $lifecycleLabel }}"
                                    data-owned="{{ $card['isOwned'] ? '1' : '0' }}"
                                    data-collaborator="{{ $card['isCollaborator'] ? '1' : '0' }}"
                                    data-has-my-slots="{{ $card['hasMySlots'] ? '1' : '0' }}"
                                    data-collaborators="{{ $card['collaboratorNames']->implode('|') }}"
                                    data-open-url="{{ $openUrl }}"
                                    data-manage-url="{{ $manageUrl }}"
                                    data-card-id="upcoming-{{ $set->id }}"
                                    :aria-expanded="popoverVisible && popoverCardId === 'upcoming-{{ $set->id }}'"
                                    class="group relative aspect-square overflow-hidden rounded-xl border border-slate-300 bg-slate-100 p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"
                                >
                                    <x-my-sets.artwork-grid :tiles="$card['artworkTiles']" :song-count="$set->songs_count" :seed="$set->id" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-900/35 to-transparent"></div>
                                    <div class="relative z-10 flex h-full flex-col justify-between">
                                        <p class="line-clamp-2 w-fit max-w-full rounded-md border border-white/20 bg-black/40 px-2 py-1 text-sm font-semibold text-white backdrop-blur-[2px]">{{ $set->name }}</p>
                                        <div>
                                            <div class="inline-flex flex-col rounded-md border border-white/20 bg-black/35 px-2 py-1 backdrop-blur-[2px]">
                                                <p class="text-[11px] text-slate-100">{{ $set->owner?->name ?? 'Unknown' }}</p>
                                                <p class="mt-1 text-[11px] font-medium text-slate-200">{{ $set->songs_count }} songs</p>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="space-y-4">
                <h3 class="text-xl font-semibold text-white">Performed</h3>

                @if ($performedSessionGroups->isEmpty())
                    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                        No performed sets yet.
                    </div>
                @endif

                @foreach ($performedSessionGroups as $group)
                    @php
                        $session = $group['session'];
                    @endphp
                    <article class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ $session?->name ?? 'Unknown Session' }}</h4>
                                <p class="text-xs text-slate-500">{{ $session?->date?->format('D, M j, Y') ?? 'No date' }}</p>
                            </div>
                            <span class="text-xs text-slate-500">{{ $group['sets']->count() }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ($group['sets'] as $card)
                                @php
                                    $set = $card['set'];
                                    $searchText = collect([
                                        $set->name,
                                        $set->owner?->name,
                                        $card['collaboratorNames']->implode(' '),
                                        $session?->name,
                                    ])->filter()->implode(' ');
                                    $openUrl = $session ? route('sessions.show', $session).'#set-'.$set->id : route('my-sets.index');
                                @endphp
                                <button
                                    type="button"
                                    x-show="matchesSetCard($el)"
                                    @click="openSetPopover($el)"
                                    data-search="{{ mb_strtolower($searchText) }}"
                                    data-name="{{ $set->name }}"
                                    data-owner="{{ $set->owner?->name ?? 'Unknown' }}"
                                    data-session="{{ $session?->name ?? 'Unknown Session' }}"
                                    data-date="{{ $session?->date?->format('D, M j, Y') ?? 'No date' }}"
                                    data-songs="{{ $set->songs_count }}"
                                    data-lifecycle-label="Performed"
                                    data-owned="{{ $card['isOwned'] ? '1' : '0' }}"
                                    data-collaborator="{{ $card['isCollaborator'] ? '1' : '0' }}"
                                    data-has-my-slots="{{ $card['hasMySlots'] ? '1' : '0' }}"
                                    data-collaborators="{{ $card['collaboratorNames']->implode('|') }}"
                                    data-open-url="{{ $openUrl }}"
                                    data-manage-url=""
                                    data-card-id="performed-{{ $set->id }}"
                                    :aria-expanded="popoverVisible && popoverCardId === 'performed-{{ $set->id }}'"
                                    class="group relative aspect-square overflow-hidden rounded-xl border border-slate-300 bg-slate-100 p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"
                                >
                                    <x-my-sets.artwork-grid :tiles="$card['artworkTiles']" :song-count="$set->songs_count" :seed="$set->id" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-900/35 to-transparent"></div>
                                    <div class="relative z-10 flex h-full flex-col justify-between">
                                        <p class="line-clamp-2 w-fit max-w-full rounded-md border border-white/20 bg-black/40 px-2 py-1 text-sm font-semibold text-white backdrop-blur-[2px]">{{ $set->name }}</p>
                                        <div>
                                            <div class="inline-flex flex-col rounded-md border border-white/20 bg-black/35 px-2 py-1 backdrop-blur-[2px]">
                                                <p class="text-[11px] text-slate-100">{{ $set->owner?->name ?? 'Unknown' }}</p>
                                                <p class="mt-1 text-[11px] font-medium text-slate-200">{{ $set->songs_count }} songs</p>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </section>
        </div>

        <div
            x-show="popoverVisible && selectedSet"
            x-cloak
            x-transition.opacity.duration.150ms
            @click.outside="closeSetPopover()"
            class="absolute z-40"
            :style="popoverStyle"
            role="dialog"
            aria-label="Set details popover"
        >
            <div x-ref="setPopoverPanel" class="relative rounded-xl border border-slate-300 bg-white p-4 shadow-2xl">
                <div x-show="popoverPlacement === 'bottom'" class="absolute -top-2 left-1/2 h-4 w-4 -translate-x-1/2 rotate-45 border-s border-t border-slate-300 bg-white"></div>
                <div x-show="popoverPlacement === 'top'" class="absolute -bottom-2 left-1/2 h-4 w-4 -translate-x-1/2 rotate-45 border-e border-b border-slate-300 bg-white"></div>

                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900" x-text="selectedSet?.name || 'Set details'"></h3>
                    <button
                        type="button"
                        @click="closeSetPopover()"
                        class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400"
                        aria-label="Close details"
                    >
                        <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>

                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">Owner:</span> <span x-text="selectedSet?.owner"></span></p>
                    <p><span class="font-semibold text-slate-900">Session:</span> <span x-text="selectedSet?.session"></span></p>
                    <p><span class="font-semibold text-slate-900">Date:</span> <span x-text="selectedSet?.date"></span></p>
                    <p><span class="font-semibold text-slate-900">Songs:</span> <span x-text="selectedSet?.songs"></span></p>
                    <p><span class="font-semibold text-slate-900">Status:</span> <span x-text="selectedSet?.lifecycle"></span></p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <span x-show="selectedSet?.isOwned" x-cloak class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Owned by you</span>
                        <span x-show="selectedSet?.isCollaborator" x-cloak class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">Collaborator</span>
                        <span x-show="selectedSet?.hasMySlots" x-cloak class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">You have slots</span>
                    </div>

                    <div x-show="selectedSet?.collaborators?.length" x-cloak>
                        <p class="font-semibold text-slate-900">Collaborators</p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template x-for="name in (selectedSet?.collaborators || [])" :key="`collaborator-${name}`">
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600" x-text="name"></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <a x-show="selectedSet?.manageUrl" x-cloak :href="selectedSet?.manageUrl || '#'" class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 shadow-sm transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400">Manage draft</a>
                    <a x-show="selectedSet?.openUrl" x-cloak :href="selectedSet?.openUrl || '#'" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">Open set</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
