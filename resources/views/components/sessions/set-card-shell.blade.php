@props([
    'set',
    'sessions',
    'users',
    'templates',
    'slotOptions',
    'jamSessionClosed' => false,
])

@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->is_admin;
    $isSetOwner = $set->owner_id === auth()->id();
    $isCollaborator = ! $isSetOwner && $set->isCollaborator($currentUser);
    $canManageSet = $isAdmin || $isSetOwner || $isCollaborator;
    $canEditSet = $isAdmin || $isSetOwner;
    $canManageCollaborators = $isAdmin || $isSetOwner;
    $setLocked = $set->performed;
    $sessionLocked = (bool) ($set->session?->is_closed ?? false);
    $canRequestSong = ! $sessionLocked && ! $setLocked && $set->song_requests && ! $isSetOwner && ! $isCollaborator;
    $totalSlots = $set->songs->sum(fn ($song) => $song->slots->count());
    $filledSlots = $set->songs->sum(fn ($song) => $song->slots->filter(fn ($slot) => $slot->user_id !== null || filled($slot->manual_performer_name))->count());
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
    $isAdminManagingOtherSet = $isAdmin && ! $isSetOwner && ! $isCollaborator;
    $setManageMenuItemClass = $isAdminManagingOtherSet
        ? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
        : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
    $setCardClass = match (true) {
        $set->feature_set && $set->is_hidden => 'border-sky-400 bg-amber-50/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]',
        $set->feature_set => 'border-amber-400 bg-amber-50/95 shadow-sm',
        $set->is_hidden => 'border-sky-400 bg-slate-50/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]',
        default => 'border-slate-200 bg-slate-50/95 shadow-sm',
    };
@endphp

<article
    id="set-{{ $set->id }}"
    data-session-set-card
    data-set-id="{{ $set->id }}"
    data-set-body-url="{{ route('sessions.sets.body', [$set->session, $set]) }}"
    class="rounded-xl border {{ $setCardClass }} p-6"
    x-bind:data-set-open="(!setCollapsed).toString()"
    x-data="sessionSetCard(@js([
        'setId' => $set->id,
        'setName' => $set->name,
        'ownerName' => $set->owner->name,
        'sessionDate' => $set->session->date->format('M j, Y'),
        'setBodyUrl' => route('sessions.sets.body', [$set->session, $set]),
        'initialSongRequestsPendingCount' => $set->songRequests->where('status', 'pending')->count(),
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
        'initialFreeForAll' => (bool) $set->free_for_all,
        'freeForAllDraft' => (bool) $set->free_for_all,
        'shareSetUrl' => route('share.set', $set),
        'setDirectUrl' => route('sessions.show', $set->session).'#set-'.$set->id,
        'songsReorderUrl' => route('songs.reorder', $set),
        'songStoreUrl' => route('songs.store', $set),
        'songRequestStoreUrl' => route('song-requests.store', $set),
        'setSummaryUrl' => route('sets.summary', $set),
        'attachmentsListUrl' => route('sets.attachments.index', $set),
        'attachmentsStoreUrl' => route('sets.attachments.store', $set),
        'canManageAttachments' => $canManageSet,
        'initialAttachmentCount' => $set->attachments_count ?? 0,
        'collaboratorsUrl' => ($isAdmin || $isSetOwner) ? route('sets.collaborators.update', $set) : null,
        'collaboratorsUsersUrl' => ($isAdmin || $isSetOwner) ? route('sets.collaborators.users', $set) : null,
        'initialCollaborators' => $users->whereIn('id', $set->collaboratorUserIds())->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
        'csrfToken' => csrf_token(),
    ]))"
    x-init="setCollapsed = localStorage.getItem(setKey) === '1'; songRequestsCollapsed = localStorage.getItem(songRequestsKey) === '1'; initLazySetCard($el)"
    x-effect="localStorage.setItem(setKey, setCollapsed ? '1' : '0'); localStorage.setItem(songRequestsKey, songRequestsCollapsed ? '1' : '0')"
    x-on:mobile-song-move.window="if ($event.detail.setId === {{ $set->id }}) moveSong($event.detail.songId, $event.detail.direction)"
    x-on:session-song-request-processed.window="onSongRequestProcessed($event.detail)"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    @scroll.window="repositionActionMenu()"
    @resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <div
        class="flex cursor-pointer items-start justify-between gap-3"
        x-bind:aria-expanded="(!setCollapsed).toString()"
        x-bind:title="setCollapsed ? 'Click to show set songs and assignments' : 'Click to hide set songs and assignments'"
        aria-label="Toggle set details"
        role="button"
        tabindex="0"
        @click.stop="setCollapsed = !setCollapsed; if (!setCollapsed) loadSetBody()"
        @keydown.enter.prevent="setCollapsed = !setCollapsed; if (!setCollapsed) loadSetBody()"
        @keydown.space.prevent="setCollapsed = !setCollapsed; if (!setCollapsed) loadSetBody()"
    >
        <div class="min-w-0 flex-1">
            <h3 class="flex items-center gap-2 text-lg font-semibold {{ $setTitleTextClass }}">
                {{ $set->name }}
                <span class="inline-flex shrink-0 items-center">
                    <x-heroicon-m-chevron-up x-show="!setCollapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                    <x-heroicon-m-chevron-down x-show="setCollapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                </span>
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
                    <span>
                        {{ $set->owner->name }}@if ($set->collaboratorUserIds())<span class="md:hidden" x-show="collaboratorNames.length > 0" x-cloak> and collaborators</span><span class="hidden md:inline" x-show="collaboratorNames.length > 0" x-text="', ' + collaboratorNames.slice(0, 2).join(', ')" x-cloak></span>@endif
                    </span>
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

                @if (! $set->performed && ! $sessionLocked)
                    <span class="inline-flex items-center" title="Sign ups {{ $set->signups_open ? 'open' : 'closed' }}">
                        @if ($set->signups_open)
                            <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                            <span class="sr-only">Sign ups open</span>
                        @else
                            <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                            <span class="sr-only">Sign ups closed</span>
                        @endif
                    </span>
                    @if ($set->free_for_all && $set->signups_open)
                        <span class="inline-flex items-center" title="Free for all mode">
                            <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                            <span class="sr-only">Free for all mode</span>
                        </span>
                    @endif
                @endif

                @if ($set->is_hidden)
                    <span class="inline-flex items-center" title="Hidden set">
                        <x-heroicon-m-eye-slash class="h-4 w-4 {{ $setHiddenIconClass }}" aria-hidden="true" />
                        <span class="sr-only">Hidden set</span>
                    </span>
                @endif

                <button
                    type="button"
                    x-show="attachmentCount > 0"
                    x-cloak
                    @click.stop="openAttachmentsModal()"
                    class="inline-flex items-center rounded-sm text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Open set attachments"
                    title="Open set attachments"
                >
                    <x-heroicon-m-paper-clip class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    <span class="sr-only">Set has attachments</span>
                </button>

                @if ($isAdmin && ! $set->performed && ! $sessionLocked)
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

        <div class="shrink-0" @click.stop>
            <div class="relative flex h-8 w-8 items-center justify-center">
                <span
                    x-show="contentLoading && !contentLoaded"
                    x-cloak
                    class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-amber-400"
                    aria-hidden="true"
                ></span>
                <button
                    type="button"
                    x-ref="actionMenuButton"
                    x-show="contentLoaded || contentLoadError"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-rotate-12 scale-75 opacity-0"
                    x-transition:enter-end="rotate-0 scale-100 opacity-100"
                    @click="toggleActionMenu()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-bind:aria-expanded="openActionMenu.toString()"
                    aria-label="Set actions"
                    title="Set actions"
                >
                    <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Set actions</span>
                </button>
                <template x-teleport="body">
                    <div
                        x-ref="actionMenu"
                        x-show="openActionMenu"
                        x-cloak
                        x-transition.origin.top.right
                        @click.outside="openActionMenu = false"
                        x-bind:style="actionMenuStyle"
                        data-session-action-menu
                        class="z-[80] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                    >
                    @if ($canManageSet && ! $setLocked && ! $isAdminManagingOtherSet)
                        <button
                            type="button"
                            @disabled($sessionLocked && !$isAdmin)
                            @click="openActionMenu = false; openAddSongModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 {{ $setManageMenuItemClass }}"
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
                    @elseif ($canRequestSong)
                        <button
                            type="button"
                            @click="openActionMenu = false; openSongRequestModal()"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                        >
                            <x-heroicon-m-hand-raised class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <span>Request Song</span>
                        </button>
                    @endif
                    @if ($canEditSet)
                        <button
                            type="button"
                            @click="openActionMenu = false; openSetEditModal()"
                            @disabled($sessionLocked && !$isAdmin)
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $setManageMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
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
                    @if ($canManageCollaborators)
                        <button
                            type="button"
                            @click="openActionMenu = false; openCollaboratorsModal()"
                            @disabled($sessionLocked && !$isAdmin)
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $setManageMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-heroicon-m-user-group class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Manage Collaborators
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
                        @click="openActionMenu = false; openSnapshotModal()"
                        x-bind:disabled="summaryImageBusy"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-wait disabled:opacity-50"
                    >
                        <x-heroicon-m-photo class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span x-text="summaryImageBusy ? 'Creating snapshot...' : 'Set Snapshot'">Set Snapshot</span>
                    </button>
                    <button
                        type="button"
                        @click="openActionMenu = false; openAttachmentsModal()"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    >
                        <x-heroicon-m-paper-clip class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span>Attachments</span>
                    </button>
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
                </template>
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
                <p x-show="summaryImageError" x-text="summaryImageError" x-cloak class="absolute right-0 top-full z-[80] mt-2 w-64 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800 shadow-lg"></p>
            </div>
        </div>
    </div>

    @if ($canRequestSong)
        <x-sessions.song-request-modal :set="$set" />
    @endif

    <p x-show="contentLoadError" x-text="contentLoadError" class="mt-4 text-sm text-rose-700" x-cloak></p>
    <div x-show="!setCollapsed && !contentLoaded && !contentLoadError" x-cloak x-transition.opacity.duration.150ms class="mt-5">
        <div class="rounded-xl border border-dashed border-slate-300 bg-white/80 p-4 text-slate-500" aria-hidden="true">
            <p class="text-sm">Loading songs...</p>

            <div class="mt-3 rounded-lg border border-slate-200 bg-white/90 p-3 animate-pulse">
                <div class="h-4 w-56 rounded bg-slate-200/70"></div>
                <div class="mt-2 h-3 w-36 rounded bg-slate-100"></div>

                <div class="mt-3 space-y-2">
                    <div class="grid grid-cols-[90px_minmax(0,1fr)] items-center gap-3">
                        <div class="h-6 w-20 rounded-full bg-amber-100"></div>
                        <div class="h-6 rounded bg-slate-100"></div>
                    </div>
                    <div class="grid grid-cols-[90px_minmax(0,1fr)] items-center gap-3">
                        <div class="h-6 w-20 rounded-full bg-amber-100"></div>
                        <div class="h-6 rounded bg-slate-100"></div>
                    </div>
                    <div class="grid grid-cols-[90px_minmax(0,1fr)] items-center gap-3">
                        <div class="h-6 w-20 rounded-full bg-amber-100"></div>
                        <div class="h-6 rounded bg-slate-100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div x-ref="setBodyContainer" x-show="contentLoaded" x-cloak></div>

    <x-sessions.set-snapshot-modal />
    <x-sessions.attachments-modal />
</article>
