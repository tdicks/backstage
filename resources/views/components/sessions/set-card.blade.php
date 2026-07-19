@props([
    'set',
    'sessions',
    'users',
    'templates',
    'slotOptions',
])

@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->is_admin;
    $canManageSet = $isAdmin || $set->owner_id === auth()->id();
    $isSetOwner = $set->owner_id === auth()->id();
    $setLocked = $set->performed;
    $sessionLocked = (bool) ($set->session?->is_closed ?? false);
    $totalSlots = $set->songs->sum(fn ($song) => $song->slots->count());
    $filledSlots = $set->songs->sum(fn ($song) => $song->slots->whereNotNull('user_id')->count());
    $healthRatio = $totalSlots > 0 ? $filledSlots / $totalSlots : 0;
    $healthDotClass = match (true) {
        $healthRatio >= 1 => 'bg-emerald-400',
        $healthRatio >= 0.75 => 'bg-lime-500',
        $healthRatio >= 0.5 => 'bg-amber-400',
        $healthRatio > 0 => 'bg-orange-500',
        default => 'bg-rose-600',
    };
    $setTitleTextClass = $set->feature_set ? 'text-amber-900' : 'text-slate-900';
    $setMetaTextClass = $set->feature_set ? 'text-amber-800' : 'text-slate-600';
    $setOwnerIconClass = $set->feature_set ? 'text-amber-700' : 'text-slate-500';
    $setDescriptionTextClass = $set->feature_set ? 'text-amber-900/90' : 'text-slate-700';
    $setHiddenIconClass = $set->feature_set ? 'text-amber-700' : 'text-slate-500';
    $isAdminManagingOtherSet = $isAdmin && ! $isSetOwner;
    $setManageMenuItemClass = $isAdminManagingOtherSet
        ? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
        : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
    $summarySlotNames = collect(array_keys($slotOptions))
        ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
        ->values();
@endphp

<section
    id="set-{{ $set->id }}"
    data-session-set-card
    class="rounded-xl border {{ $set->feature_set ? 'border-amber-400 bg-amber-50/95' : 'border-slate-200 bg-slate-50/95' }} p-6 shadow-sm"
    x-bind:data-set-open="(!setCollapsed).toString()"
    x-data="sessionSetCard(@js([
        'artistLookupUrl' => route('lookups.deezer.artists'),
        'titleLookupUrl' => route('lookups.deezer.tracks'),
        'setKey' => 'backstage:u'.auth()->id().':set:'.$set->id,
        'songRequestsKey' => 'backstage:u'.auth()->id().':set:'.$set->id.':song-requests',
        'canReorderSongs' => $isSetOwner && ! $setLocked,
        'setLocked' => $setLocked,
        'initialSetPerformed' => $setLocked,
        'performedDraft' => $setLocked,
        'initialSongRequestsEnabled' => (bool) $set->song_requests,
        'songRequestsDraft' => (bool) $set->song_requests,
        'shareSetUrl' => route('share.set', $set),
        'setDirectUrl' => route('sessions.show', $set->session).'#set-'.$set->id,
        'songsReorderUrl' => route('songs.reorder', $set),
        'songStoreUrl' => route('songs.store', $set),
        'setSummaryUrl' => route('sets.summary', $set),
        'csrfToken' => csrf_token(),
    ]))"
    x-init="setCollapsed = localStorage.getItem(setKey) === '1'; songRequestsCollapsed = localStorage.getItem(songRequestsKey) === '1'"
    x-effect="localStorage.setItem(setKey, setCollapsed ? '1' : '0'); localStorage.setItem(songRequestsKey, songRequestsCollapsed ? '1' : '0')"
    x-on:mobile-song-move.window="if ($event.detail.setId === {{ $set->id }}) moveSong($event.detail.songId, $event.detail.direction)"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <div
        class="flex cursor-pointer flex-wrap items-center justify-between gap-3"
        @click="setCollapsed = !setCollapsed"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="setCollapsed = !setCollapsed"
        @keydown.space.prevent="setCollapsed = !setCollapsed"
        x-bind:aria-expanded="(!setCollapsed).toString()"
        x-bind:title="setCollapsed ? 'Click to show set songs and assignments' : 'Click to hide set songs and assignments'"
        aria-label="Toggle set details"
    >
        <div>
            <h3 class="flex items-center gap-2 text-lg font-semibold {{ $setTitleTextClass }}">
                {{ $set->name }}
                @if ($set->feature_set)
                    <span title="Feature set" class="inline-flex items-center">
                        <x-heroicon-m-star class="h-4 w-4 text-amber-500" aria-hidden="true" />
                        <span class="sr-only">Feature set</span>
                    </span>
                @endif
            </h3>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm {{ $setMetaTextClass }}">
                <span class="inline-flex items-center gap-1.5" title="Set owner">
                    <x-heroicon-m-user class="h-4 w-4 {{ $setOwnerIconClass }}" aria-hidden="true" />
                    <span class="sr-only">Set owner</span>
                    <span>{{ $set->owner->name }}</span>
                </span>

                @if ($set->performed)
                    <span class="inline-flex items-center" title="Performed">
                        <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                        <span class="sr-only">Performed</span>
                    </span>
                @else
                    <span class="inline-flex items-center" title="Planned">
                        <x-heroicon-m-clock class="h-4 w-4 text-sky-600" aria-hidden="true" />
                        <span class="sr-only">Not performed yet</span>
                    </span>
                @endif

                @if (! $set->performed)
                    <span class="inline-flex items-center" title="Sign ups {{ $set->signups_open ? 'open' : 'closed' }}">
                        @if ($set->signups_open)
                            <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                            <span class="sr-only">Sign ups open</span>
                        @else
                            <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                            <span class="sr-only">Sign ups closed</span>
                        @endif
                    </span>
                @endif

                @if ($set->is_hidden)
                    <span class="inline-flex items-center" title="Hidden set">
                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                        <span class="sr-only">Hidden set</span>
                    </span>
                @endif

                @if ($isAdmin && ! $set->performed)
                    <span
                        class="inline-flex items-center"
                        title="Set health: {{ $filledSlots }}/{{ $totalSlots }} slots filled"
                    >
                        <span class="h-2.5 w-2.5 rounded-full {{ $healthDotClass }}"></span>
                        <span class="sr-only">Set health: {{ $filledSlots }}/{{ $totalSlots }} slots filled</span>
                    </span>
                @endif
            </div>
            @if ($set->description)
                <p class="mt-2 text-sm {{ $setDescriptionTextClass }}">{{ $set->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2" @click.stop>
            <div class="relative">
                <button
                    type="button"
                    @click="toggleActionMenu()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-bind:aria-expanded="openActionMenu.toString()"
                    aria-label="Set actions"
                    title="Set actions"
                >
                    <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Set actions</span>
                </button>
                <div
                    x-show="openActionMenu"
                    x-cloak
                    x-transition.origin.top.right
                    @click.outside="openActionMenu = false"
                    class="absolute right-0 top-full z-[80] mt-2 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                >
                    @if ($canManageSet && ! $setLocked)
                        <button
                            type="button"
                            @click="openActionMenu = false; openAddSongModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $setManageMenuItemClass }}"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Add Song
                            </span>
                        </button>
                    @elseif ($set->signups_open && $set->song_requests && ! $setLocked && ! $sessionLocked)
                        <button
                            type="button"
                            @click="openActionMenu = false; openSongRequestModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                        >
                            <x-heroicon-m-hand-raised class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <span>Request Song</span>
                        </button>
                    @endif
                    @if ($canManageSet)
                        <button
                            type="button"
                            @click="openActionMenu = false; openSetEditModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $setManageMenuItemClass }}"
                        >
                            <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Edit Set
                            </span>
                        </button>
                    @endif
                    @if (! $setLocked)
                        <button
                            type="button"
                            @click="openActionMenu = false; openSummaryModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                        >
                            <x-heroicon-m-queue-list class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <span>Live Summary</span>
                        </button>
                    @endif
                    <button
                        type="button"
                        @click="openActionMenu = false; copySetShareLink()"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    >
                        <x-heroicon-m-share class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span>Copy Share link</span>
                    </button>
                    <button
                        type="button"
                        @click="openActionMenu = false; copySetDirectLink()"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    >
                        <x-heroicon-m-link class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span>Copy Direct Link</span>
                    </button>
                </div>
                <div
                    x-show="shareCopied || directLinkCopied"
                    x-transition.opacity.duration.150ms
                    x-cloak
                    role="status"
                    aria-live="polite"
                    class="absolute right-0 top-full z-[80] mt-2 whitespace-nowrap rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 shadow-lg"
                >
                    <span x-text="directLinkCopied ? 'Direct link copied' : 'Share link copied'">Share link copied</span>
                </div>
            </div>
        </div>
    </div>

    <div x-show="openSummary" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="closeSummaryModal()"></div>
    <div x-show="openSummary" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center p-2 sm:items-center sm:p-4">
        <div class="flex w-full max-w-6xl max-h-[calc(100dvh-1rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100dvh-2rem)]">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
                <div>
                    <h4 class="text-lg font-semibold text-slate-900">Set Summary: {{ $set->name }}</h4>
                    <p class="mt-1 text-sm text-slate-600">Set owner: {{ $set->owner->name }}</p>
                </div>
                <x-modal-secondary-button type="button" @click="closeSummaryModal()">Close</x-modal-secondary-button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">

            <p class="mt-3 text-xs text-slate-600" x-show="summaryLoading && summaryLoaded">Refreshing live status...</p>
            <p class="mt-4 text-sm text-slate-600" x-show="summaryLoading && !summaryLoaded">Loading summary...</p>
            <p class="mt-4 text-sm text-rose-600" x-show="summaryError" x-text="summaryError"></p>

            <template x-if="summaryLoaded && summaryData && summaryData.songs && summaryData.songs.length === 0">
                <p class="mt-4 text-sm text-slate-600">No songs in this set yet.</p>
            </template>

            <template x-if="summaryLoaded && summaryData && summaryData.slot_names && summaryData.slot_names.length === 0 && summaryData.songs && summaryData.songs.length > 0">
                <p class="mt-4 text-sm text-slate-600">No slots have been created for this set yet.</p>
            </template>

            <div x-show="summaryLoaded && summaryData && summaryData.songs && summaryData.songs.length > 0 && summaryData.slot_names && summaryData.slot_names.length > 0" class="mt-4 space-y-3">
                <div class="space-y-3 md:hidden">
                    <template x-for="song in summaryData.songs" :key="`mobile-${song.id}`">
                        <article class="rounded-xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                            <h5 class="text-sm font-semibold text-slate-900" x-text="`${song.artist} - ${song.title}`"></h5>
                            <div class="mt-3 space-y-2">
                                <template x-for="slot in summaryData.slot_names" :key="`mobile-${song.id}-${slot.name}`">
                                    <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-600" x-text="slot.label"></span>
                                        <div class="text-right">
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'user'">
                                                <div class="space-y-1">
                                                    <span
                                                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm"
                                                        x-bind:class="song.slot_map[slot.name].is_current_user ? 'border-sky-200 bg-sky-50/90 text-sky-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800'"
                                                        x-bind:title="song.slot_map[slot.name].is_manual ? 'Manually assigned' : ''"
                                                    >
                                                        <template x-if="song.slot_map[slot.name].is_manual">
                                                            <span class="mr-1 inline-flex items-center" aria-hidden="true">
                                                                <x-heroicon-m-pencil-square class="h-3.5 w-3.5" />
                                                            </span>
                                                        </template>
                                                        <span x-text="song.slot_map[slot.name].display"></span>
                                                    </span>
                                                    <span
                                                        x-show="song.slot_map[slot.name].checked_in"
                                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                    >
                                                        Checked in
                                                    </span>
                                                </div>
                                            </template>
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'open'">
                                                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50/80 px-3 py-1 text-xs font-semibold text-amber-800 shadow-sm">Open</span>
                                            </template>
                                            <template x-if="!song.slot_map[slot.name] || song.slot_map[slot.name].state === 'empty'">
                                                <span class="text-sm text-slate-500">-</span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full border border-slate-200 text-sm text-slate-900">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="border border-slate-200 px-3 py-2 text-left font-semibold text-slate-700">Artist/Title</th>
                                <template x-for="slot in summaryData.slot_names" :key="slot.name">
                                    <th class="border border-slate-200 px-3 py-2 text-left font-semibold text-slate-700" x-text="slot.label"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="song in summaryData.songs" :key="song.id">
                                <tr class="align-top">
                                    <td class="border border-slate-200 px-3 py-2 font-medium text-slate-900">
                                        <span x-text="`${song.artist} - ${song.title}`"></span>
                                    </td>
                                    <template x-for="slot in summaryData.slot_names" :key="`${song.id}-${slot.name}`">
                                        <td class="border border-slate-200 px-3 py-2">
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'user'">
                                                <div class="space-y-1">
                                                    <span
                                                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm"
                                                        x-bind:class="song.slot_map[slot.name].is_current_user ? 'border-sky-200 bg-sky-50/90 text-sky-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800'"
                                                        x-bind:title="song.slot_map[slot.name].is_manual ? 'Manually assigned' : ''"
                                                    >
                                                        <template x-if="song.slot_map[slot.name].is_manual">
                                                            <span class="mr-1 inline-flex items-center" aria-hidden="true">
                                                                <x-heroicon-m-pencil-square class="h-3.5 w-3.5" />
                                                            </span>
                                                        </template>
                                                        <span x-text="song.slot_map[slot.name].display"></span>
                                                    </span>
                                                    <span
                                                        x-show="song.slot_map[slot.name].checked_in"
                                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                    >
                                                        Checked in
                                                    </span>
                                                </div>
                                            </template>
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'open'">
                                                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50/80 px-3 py-1 text-xs font-semibold text-amber-800 shadow-sm">Open</span>
                                            </template>
                                            <template x-if="!song.slot_map[slot.name] || song.slot_map[slot.name].state === 'empty'">
                                                <span class="text-xs text-slate-500">-</span>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            </div>

            <div class="shrink-0 border-t border-slate-200 bg-white/95 px-4 py-2 text-xs text-slate-500 backdrop-blur sm:px-6">
                <span x-show="summaryLastUpdated" x-cloak>
                    Table refreshes automatically, last updated <span x-text="summaryLastUpdated"></span>
                </span>
            </div>
        </div>
    </div>

    @if ($canManageSet)
        <div x-show="openSetEdit" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openSetEdit = false"></div>
        <div x-show="openSetEdit" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                <h4 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                    {{ $isAdminManagingOtherSet ? 'Edit '.$set->owner->name.'\'s Set' : 'Edit Set' }}
                </h4>
                <form id="edit_set_form_{{ $set->id }}" method="POST" action="{{ route('sets.update', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label :value="'Set Name'" />
                        <x-text-input name="name" :value="$set->name" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                    </div>
                    <div>
                        <x-input-label :value="'Description'" />
                        <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $set->description }}</textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Jam Session'" />
                        <select name="jam_session_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required>
                            @foreach ($sessions as $jamSessionOption)
                                <option value="{{ $jamSessionOption->id }}" @selected($set->jam_session_id === $jamSessionOption->id)>
                                    {{ $jamSessionOption->name }} ({{ $jamSessionOption->date->format('M j, Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if ($isAdmin)
                        <div>
                            <x-input-label :value="'Set Owner'" />
                            <select name="owner_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($set->owner_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="performed" value="1" x-model="performedDraft" @checked($set->performed) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Mark as performed.
                    </label>
                    <p
                        x-show="!initialSetPerformed && performedDraft"
                        x-cloak
                        class="-mt-1 text-xs text-amber-700"
                    >
                        Marking this set as performed will lock the set's song list and slots.
                    </p>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="signups_open" value="0">
                        <input type="checkbox" name="signups_open" value="1" @checked($set->signups_open) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Sign ups open.
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="song_requests" value="0">
                        <input type="checkbox" name="song_requests" value="1" x-model="songRequestsDraft" @checked($set->song_requests) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Accept song requests.
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="is_hidden" value="0">
                        <input type="checkbox" name="is_hidden" value="1" @checked($set->is_hidden) class="rounded border-slate-300 text-slate-600 shadow-sm focus:ring-slate-500">
                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                        Hide this set from other users (admins can still see it).
                    </label>
                    <p
                        x-show="initialSongRequestsEnabled && !songRequestsDraft"
                        x-cloak
                        class="-mt-1 text-xs text-amber-700"
                    >
                        Turning off song requests will reject any pending song requests for this set.
                    </p>
                    @if ($isAdmin)
                        <input type="hidden" name="feature_set" value="0">
                        <label class="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                            <input type="checkbox" name="feature_set" value="1" @checked($set->feature_set) class="rounded border-amber-400 text-amber-500 shadow-sm focus:ring-amber-400">
                            <x-heroicon-m-star class="h-4 w-4 text-amber-500" aria-hidden="true" />
                            Feature set (pinned to top of session).
                        </label>
                    @endif
                </form>
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <form method="POST" action="{{ route('sets.destroy', $set) }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Delete Set</x-danger-button>
                    </form>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openSetEdit = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button type="submit" form="edit_set_form_{{ $set->id }}">
                            @if ($isAdminManagingOtherSet)
                                <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                <span class="sr-only">Admin action: </span>
                            @endif
                            Save
                        </x-modal-primary-button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="openSong" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openSong = false; resetSongAutocomplete()"></div>
        <div x-show="openSong" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
            <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                <div class="px-6 pt-6">
                    <h4 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                        {{ $isAdminManagingOtherSet ? 'Add Song to '.$set->owner->name.'\'s Set' : 'Add Song to '.$set->name }}
                    </h4>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                <form method="POST" action="{{ route('songs.store', $set) }}" class="space-y-4" @submit.prevent="submitAddSong($event)">
                    @csrf
                    <p x-show="addSongError" x-text="addSongError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" x-cloak></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="relative">
                            <x-input-label :value="'Artist'" />
                            <x-text-input
                                name="artist"
                                x-model="songArtistQuery"
                                @input="queueArtistLookup()"
                                @focus="showArtistSuggestions = artistSuggestions.length > 0"
                                @keydown.escape="showArtistSuggestions = false"
                                class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                autocomplete="off"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">Start typing an artist to fetch Deezer suggestions.</p>
                            <div x-show="artistLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up artists...</div>
                            <div x-show="artistLookupError" x-cloak x-text="artistLookupError" class="mt-1 text-xs text-rose-600"></div>
                            <ul
                                x-show="showArtistSuggestions"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showArtistSuggestions = false"
                            >
                                <template x-for="artist in artistSuggestions" :key="`artist-${artist}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectArtistSuggestion(artist)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="artist"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="relative">
                            <x-input-label :value="'Title'" />
                            <x-text-input
                                name="title"
                                x-model="songTitleQuery"
                                @input="queueTitleLookup()"
                                @focus="showTitleSuggestions = titleSuggestions.length > 0"
                                @keydown.escape="showTitleSuggestions = false"
                                class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                autocomplete="off"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">Song suggestions are scoped to the selected artist.</p>
                            <div x-show="titleLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up songs...</div>
                            <div x-show="titleLookupError" x-cloak x-text="titleLookupError" class="mt-1 text-xs text-rose-600"></div>
                            <ul
                                x-show="showTitleSuggestions"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showTitleSuggestions = false"
                            >
                                <template x-for="title in titleSuggestions" :key="`title-${title}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectTitleSuggestion(title)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="title"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div>
                        <x-input-label :value="'Notes'" />
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Band Template (optional)'" />
                        <select name="band_template_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            <option value="">None</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-700">Or choose manual slots</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                                    <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                    {{ $slotLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-modal-secondary-button type="button" @click="openSong = false; resetSongAutocomplete()">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button x-bind:disabled="addSongBusy">
                            @if ($isAdminManagingOtherSet)
                                <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                <span class="sr-only">Admin action: </span>
                            @endif
                            Add Song
                        </x-modal-primary-button>
                    </div>
                </form>
                </div>
            </div>
        </div>
    @else
        <div x-show="openSongRequest" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openSongRequest = false; resetSongRequestAutocomplete()"></div>
        <div x-show="openSongRequest" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 shadow-2xl">
                <h4 class="text-lg font-semibold text-slate-900">Request a Song for {{ $set->name }}</h4>
                <form method="POST" action="{{ route('song-requests.store', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="relative">
                            <x-input-label for="request_artist_{{ $set->id }}" value="Artist" />
                            <x-text-input
                                id="request_artist_{{ $set->id }}"
                                name="artist"
                                x-model="requestArtistQuery"
                                @input="queueRequestArtistLookup()"
                                @focus="showRequestArtistSuggestions = requestArtistSuggestions.length > 0"
                                @keydown.escape="showRequestArtistSuggestions = false"
                                class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                autocomplete="off"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">Start typing an artist to fetch Deezer suggestions.</p>
                            <div x-show="requestArtistLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up artists...</div>
                            <div x-show="requestArtistLookupError" x-cloak x-text="requestArtistLookupError" class="mt-1 text-xs text-rose-600"></div>
                            <ul
                                x-show="showRequestArtistSuggestions"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showRequestArtistSuggestions = false"
                            >
                                <template x-for="artist in requestArtistSuggestions" :key="`request-artist-${artist}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectRequestArtistSuggestion(artist)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="artist"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="relative">
                            <x-input-label for="request_title_{{ $set->id }}" value="Title" />
                            <x-text-input
                                id="request_title_{{ $set->id }}"
                                name="title"
                                x-model="requestTitleQuery"
                                @input="queueRequestTitleLookup()"
                                @focus="showRequestTitleSuggestions = requestTitleSuggestions.length > 0"
                                @keydown.escape="showRequestTitleSuggestions = false"
                                class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                autocomplete="off"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">Song suggestions are scoped to the selected artist.</p>
                            <div x-show="requestTitleLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up songs...</div>
                            <div x-show="requestTitleLookupError" x-cloak x-text="requestTitleLookupError" class="mt-1 text-xs text-rose-600"></div>
                            <ul
                                x-show="showRequestTitleSuggestions"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showRequestTitleSuggestions = false"
                            >
                                <template x-for="title in requestTitleSuggestions" :key="`request-title-${title}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectRequestTitleSuggestion(title)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="title"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="request_notes_{{ $set->id }}" value="Notes" />
                        <textarea id="request_notes_{{ $set->id }}" name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-modal-secondary-button type="button" @click="openSongRequest = false; resetSongRequestAutocomplete()">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button>Send Request</x-modal-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-5 space-y-4" x-show="!setCollapsed" x-transition>
        <p x-show="reorderError" x-text="reorderError" class="text-sm text-red-700"></p>
        @if ($isSetOwner && ! $setLocked)
            <p class="text-xs text-slate-500">Tip: drag songs and slots to reorder them.</p>
        @endif

        <div class="space-y-4" x-ref="songsContainer" @dragover="onSongDragOver($event)" @drop="onSongDrop($event)">
            @forelse ($set->songs as $song)
                <x-sessions.song-card
                    :song="$song"
                    :set="$set"
                    :users="$users"
                    :slot-options="$slotOptions"
                    :is-set-owner="$isSetOwner"
                    :can-manage-set="$canManageSet"
                    :can-reorder-songs="$isSetOwner && ! $setLocked"
                    :can-move-song-up="! $loop->first"
                    :can-move-song-down="! $loop->last"
                />
            @empty
                <p class="rounded border border-dashed border-slate-300 bg-white/80 p-4 text-sm text-slate-500">No songs in this set yet.</p>
            @endforelse
        </div>

        @if ($set->song_requests && $set->songRequests->where('status', 'pending')->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50/80 p-4">
                <div
                    class="flex cursor-pointer items-center justify-between gap-2"
                    role="button"
                    tabindex="0"
                    @click="songRequestsCollapsed = !songRequestsCollapsed"
                    @keydown.enter.prevent="songRequestsCollapsed = !songRequestsCollapsed"
                    @keydown.space.prevent="songRequestsCollapsed = !songRequestsCollapsed"
                    x-bind:aria-expanded="(!songRequestsCollapsed).toString()"
                    x-bind:title="songRequestsCollapsed ? 'Click to show song requests' : 'Click to hide song requests'"
                >
                    <h4 class="text-sm font-semibold text-amber-900">Song requests</h4>
                    <x-heroicon-m-chevron-down class="h-4 w-4 text-amber-700 transition" x-bind:class="songRequestsCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
                </div>
                <div class="mt-3 space-y-3" x-show="!songRequestsCollapsed" x-transition>
                    @foreach ($set->songRequests->where('status', 'pending') as $songRequest)
                        <div class="rounded-lg border border-amber-200 bg-white/90 p-4 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $songRequest->artist }} - {{ $songRequest->title }}</p>
                                    <p class="text-sm text-slate-600">Requested by {{ $songRequest->requester->name }}</p>
                                    @if ($songRequest->bandTemplate)
                                        <p class="text-sm text-slate-600">Requested template: {{ $songRequest->bandTemplate->name }}</p>
                                    @endif
                                    @if ($songRequest->notes)
                                        <p class="mt-1 text-sm text-slate-700">{{ $songRequest->notes }}</p>
                                    @endif
                                </div>

                                <div class="w-full sm:w-auto">
                                    @if ($canManageSet && ! $setLocked)
                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <form method="POST" action="{{ route('song-requests.respond', $songRequest) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="sr-only" for="band_template_id_{{ $songRequest->id }}">Band template for approval</label>
                                            <select id="band_template_id_{{ $songRequest->id }}" name="band_template_id" class="w-52 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                                <option value="">Template: None</option>
                                                @foreach ($templates as $template)
                                                    <option value="{{ $template->id }}" @selected($songRequest->band_template_id === $template->id)>{{ $template->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="status" value="accepted">
                                            <button
                                                type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                                aria-label="Approve song request"
                                                title="Approve"
                                            >
                                                <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                                                <span class="sr-only">Approve</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('song-requests.respond', $songRequest) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button
                                                type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400"
                                                aria-label="Reject song request"
                                                title="Reject"
                                            >
                                                <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                                                <span class="sr-only">Reject</span>
                                            </button>
                                        </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
