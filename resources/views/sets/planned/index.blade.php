<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Planned Sets</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    Draft sets you can shape now and schedule later.
                </p>
            </div>
            <x-primary-button type="button" onclick="window.dispatchEvent(new CustomEvent('open-planned-set-create'))" class="gap-1.5">
                <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                <span>Create Planned Set</span>
            </x-primary-button>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
        @open-planned-set-create.window="openCreateModal()"
        x-data="plannedSetsPage({
            csrfToken: @js(csrf_token()),
            currentUserId: @js(auth()->id()),
            currentUserIsAdmin: @js((bool) auth()->user()?->is_admin),
            initialSets: @js($initialPlannedSets),
            attendanceSessionOptions: @js($attendanceSessionOptions),
            scheduleSessionOptions: @js($scheduleSessionOptions),
            collaboratorOptions: @js($collaboratorOptions),
            templateOptions: @js($templateOptions),
            slotOptions: @js($slotOptions),
            slotConflicts: @js($slotConflicts),
            storeUrl: @js(route('planned-sets.store')),
            updateUrlTemplate: @js(route('planned-sets.update', '__SET_ID__')),
            songStoreUrlTemplate: @js(route('planned-sets.songs.store', '__SET_ID__')),
            songUpdateUrlTemplate: @js(route('planned-sets.songs.update', ['set' => '__SET_ID__', 'song' => '__SONG_ID__'])),
            slotStoreUrlTemplate: @js(route('planned-sets.slots.store', ['set' => '__SET_ID__', 'song' => '__SONG_ID__'])),
            slotTakeUrlTemplate: @js(route('planned-sets.slots.take', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            slotRequestUrlTemplate: @js(route('planned-sets.slots.request', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            slotProposeUrlTemplate: @js(route('planned-sets.slots.propose', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            slotReleaseUrlTemplate: @js(route('planned-sets.slots.release', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            slotClaimableUrlTemplate: @js(route('planned-sets.slots.claimable', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            songRequestRespondUrlTemplate: @js(route('planned-sets.song-requests.respond', ['set' => '__SET_ID__', 'songRequest' => '__SONG_REQUEST_ID__'])),
            slotAssignmentRespondUrlTemplate: @js(route('planned-sets.slot-assignments.respond', ['set' => '__SET_ID__', 'slotAssignment' => '__SLOT_ASSIGNMENT_ID__'])),
            artistLookupUrl: @js(route('lookups.deezer.artists')),
            titleLookupUrl: @js(route('lookups.deezer.tracks')),
            attendanceUpdateUrlTemplate: @js(route('sessions.attendance.update', '__SESSION_ID__')),
            scheduleUrlTemplate: @js(route('planned-sets.schedule', '__SET_ID__')),
        })"
    >
        <div x-show="statusMessage" x-cloak x-transition.opacity class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            <p x-text="statusMessage"></p>
        </div>

        <div x-show="errorMessage" x-cloak x-transition.opacity class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
            <p x-text="errorMessage"></p>
        </div>

        <div>
            <div class="mb-4 flex items-center justify-end">
                <span class="rounded-full border border-slate-200 bg-white/90 px-2.5 py-0.5 text-xs font-semibold text-slate-700" x-text="`${sets.length} draft${sets.length === 1 ? '' : 's'}`"></span>
            </div>

            <div class="space-y-4" x-show="sets.length > 0">
                <template x-for="set in sets" :key="set.id">
                    <article class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900" x-text="set.name"></h4>
                                    <span x-show="set.is_hidden" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Hidden</span>
                                </div>
                                <p class="mt-2 inline-flex items-center gap-1.5 text-sm text-slate-600" title="Set owner">
                                    <x-heroicon-m-user class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                    <span class="font-medium text-slate-700" x-text="set.owner?.name || 'Unknown'"></span>
                                </p>
                                <p class="mt-2 text-sm text-slate-600" x-show="set.description" x-text="set.description"></p>
                            </div>
                            <div class="relative" x-data="{ openSetActionMenu: false }">
                                <button
                                    type="button"
                                    @click="openSetActionMenu = !openSetActionMenu"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                    :aria-expanded="openSetActionMenu.toString()"
                                    aria-label="Set actions"
                                    title="Set actions"
                                >
                                    <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                                    <span class="sr-only">Set actions</span>
                                </button>

                                <div
                                    x-show="openSetActionMenu"
                                    x-cloak
                                    x-transition.origin.top.right
                                    @click.outside="openSetActionMenu = false"
                                    class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                                >
                                    <button
                                        type="button"
                                        @click="openSetActionMenu = false; openAddSongModal(set)"
                                        x-show="set.can_manage"
                                        x-bind:class="slotManageMenuItemClass(set)"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                                    >
                                        <x-heroicon-m-plus class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                        <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                        <span>Add Song</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openSetActionMenu = false; openScheduleModal(set)"
                                        x-show="canScheduleSet(set)"
                                        x-bind:class="slotManageMenuItemClass(set)"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                                    >
                                        <x-heroicon-m-clock class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                        <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                        <span>Schedule</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openSetActionMenu = false; openEditModal(set)"
                                        x-show="set.can_edit"
                                        x-bind:class="slotManageMenuItemClass(set)"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                                    >
                                        <x-heroicon-m-pencil-square class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                        <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2" x-show="(set.collaborators || []).length > 0">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collaborators</span>
                            <template x-for="collaborator in set.collaborators" :key="`set-${set.id}-collab-${collaborator.id}`">
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700" x-text="collaborator.name"></span>
                            </template>
                        </div>

                        <div class="mt-3 rounded-lg border border-slate-200 bg-white/90 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Songs &amp; Slots</p>
                            </div>

                            <div class="mt-3 space-y-3" x-show="set.can_manage && (set.pending_song_requests || []).length > 0">
                                <div class="rounded-md border border-amber-200 bg-amber-50/80 p-3">
                                    <p class="text-sm font-semibold text-amber-900">Song Requests</p>
                                    <p class="mt-1 text-xs text-amber-800" x-text="`${(set.pending_song_requests || []).length} pending`"></p>
                                </div>

                                <template x-for="songRequest in (set.pending_song_requests || [])" :key="`set-${set.id}-song-request-${songRequest.id}`">
                                    <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900" x-text="`${songRequest.artist} - ${songRequest.title}`"></p>
                                                <p class="text-xs text-slate-600" x-text="`Requested by ${Number(songRequest.requester_user_id) === Number(currentUserId) ? 'you' : songRequest.requester_name}`"></p>
                                                <p class="mt-1 text-xs text-slate-600" x-show="songRequest.notes" x-text="songRequest.notes"></p>
                                                <p class="mt-1 text-xs text-slate-600" x-show="(songRequest.requested_slot_labels || []).length > 0">
                                                    Can cover:
                                                    <span x-text="(songRequest.requested_slot_labels || []).join(', ')"></span>
                                                </p>
                                                <p class="mt-1 text-xs text-rose-700" x-show="songRequest.error" x-text="songRequest.error" x-cloak></p>
                                            </div>

                                            <div class="w-full max-w-sm space-y-2">
                                                <div x-show="!songRequest.jam_standard_song_id" x-cloak>
                                                    <x-input-label x-bind:for="`planned-song-request-template-${songRequest.id}`" value="Band Template (optional)" />
                                                    <select
                                                        x-bind:id="`planned-song-request-template-${songRequest.id}`"
                                                        x-model="songRequest.selected_band_template_id"
                                                        x-bind:disabled="songRequest.busy"
                                                        class="mt-1 block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                                    >
                                                        <option value="">Template: None</option>
                                                        <template x-for="template in templateOptions" :key="`planned-song-request-template-option-${songRequest.id}-${template.id}`">
                                                            <option :value="String(template.id)" x-text="template.name"></option>
                                                        </template>
                                                    </select>
                                                </div>

                                                <div class="rounded-md border border-slate-200 bg-white p-2" x-show="(songRequest.requested_slot_names || []).length > 0" x-cloak>
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Assign requester to slots (optional)</p>
                                                    <div class="mt-2 space-y-1.5">
                                                        <template x-for="slotName in (songRequest.requested_slot_names || [])" :key="`planned-song-request-slot-${songRequest.id}-${slotName}`">
                                                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                                                <input
                                                                    type="checkbox"
                                                                    :checked="songRequestSlotSelected(songRequest, slotName)"
                                                                    :disabled="songRequestSlotSelectionDisabled(songRequest, slotName)"
                                                                    @change="toggleApprovedSongRequestSlot(songRequest, slotName)"
                                                                    class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500"
                                                                >
                                                                <span x-text="slotOptions[slotName] || slotName"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </div>

                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        @click="respondSongRequest(set, songRequest, 'accepted')"
                                                        x-bind:disabled="songRequest.busy"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-50"
                                                        aria-label="Approve song request"
                                                        title="Approve"
                                                    >
                                                        <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="respondSongRequest(set, songRequest, 'rejected')"
                                                        x-bind:disabled="songRequest.busy"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-50"
                                                        aria-label="Reject song request"
                                                        title="Reject"
                                                    >
                                                        <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-2 space-y-2" x-show="(set.songs || []).length > 0">
                                <template x-for="song in (set.songs || [])" :key="`set-${set.id}-song-${song.id}`">
                                    <div class="rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-3 shadow-sm" x-data="{ openSongActionMenu: false }">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900" x-text="`${song.artist} - ${song.title}`"></p>
                                                <p class="mt-1 text-xs text-slate-600" x-show="song.notes" x-text="song.notes"></p>
                                            </div>
                                            <div class="relative" x-show="set.can_manage">
                                                <button
                                                    type="button"
                                                    @click="openSongActionMenu = !openSongActionMenu"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                                    :aria-expanded="openSongActionMenu.toString()"
                                                    aria-label="Song actions"
                                                    title="Song actions"
                                                >
                                                    <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                                                    <span class="sr-only">Song actions</span>
                                                </button>
                                                <div
                                                    x-show="openSongActionMenu"
                                                    x-cloak
                                                    x-transition.origin.top.right
                                                    @click.outside="openSongActionMenu = false"
                                                    class="absolute right-0 z-20 mt-2 w-36 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                                                >
                                                    <button
                                                        type="button"
                                                        @click="openSongActionMenu = false; openEditSongModal(set, song)"
                                                        x-bind:class="slotManageMenuItemClass(set)"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                                                    >
                                                        <x-heroicon-m-pencil-square class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                                        <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                                        <span>Edit Song</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="openSongActionMenu = false; openAddSlotModal(set, song)"
                                                        x-bind:class="slotManageMenuItemClass(set)"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                                                    >
                                                        <x-heroicon-m-plus class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                                        <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                                        <span>Add Slot</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-2" x-show="(song.slots || []).length > 0">
                                            <template x-for="slot in (song.slots || [])" :key="`song-${song.id}-slot-${slot.id}`">
                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-300"
                                                        @click="openSlotActions(set, song, slot)"
                                                        x-bind:title="slot.user_name ? `${slot.label}: ${slot.user_name}` : slot.label"
                                                        x-bind:aria-expanded="isSlotActionPopoverOpen(slot.id).toString()"
                                                    >
                                                        <span x-text="slot.label"></span>
                                                        <span
                                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                                            x-bind:class="slotAssigneeBadgeClass(slot)"
                                                            x-text="slotAssigneeName(slot)"
                                                        ></span>
                                                    </button>

                                                    <div
                                                        x-show="isSlotActionPopoverOpen(slot.id)"
                                                        x-cloak
                                                        x-transition.origin.top.left
                                                        @click.outside="closeSlotActions()"
                                                        class="absolute left-0 z-30 mt-2 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                                                    >
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slot Actions</p>
                                                        <p class="mt-1 text-sm font-medium text-slate-900" x-text="slot.label"></p>
                                                        <p class="text-xs text-slate-500" x-text="slot.is_open ? 'Open' : `Assigned to ${slotAssigneeName(slot)}`"></p>

                                                            <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-amber-700" x-show="set.free_for_all">
                                                                Free for all
                                                            </p>

                                                        <div class="mt-3 space-y-1.5">
                                                            <button
                                                                type="button"
                                                                x-show="canTakeActiveSlot()"
                                                                @click="takeActiveSlot()"
                                                                x-bind:disabled="slotActionBusy"
                                                                    x-bind:class="slotManageMenuItemClass(set)"
                                                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                    <x-heroicon-m-arrow-down-on-square class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                                                <span x-text="slotActionBusy ? 'Working...' : 'Take this slot'"></span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                x-show="canRequestActiveSlot()"
                                                                @click="openRequestSlotForm()"
                                                                x-bind:disabled="slotActionBusy"
                                                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                <x-heroicon-m-hand-raised class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                                                <span>Request slot</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                x-show="canRecommendActiveSlot()"
                                                                @click="openRecommendSlotForm()"
                                                                x-bind:disabled="slotActionBusy"
                                                                class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                <x-heroicon-m-user-plus class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                                                <span>Recommend someone else</span>
                                                            </button>

                                                                <button
                                                                    type="button"
                                                                    x-show="canReleaseActiveSlot()"
                                                                    @click="releaseActiveSlot()"
                                                                    x-bind:disabled="slotActionBusy"
                                                                    x-bind:class="slotManageMenuItemClass(set)"
                                                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                                                    <span>Release slot</span>
                                                                </button>

                                                                <button
                                                                    type="button"
                                                                    x-show="canToggleClaimableActiveSlot()"
                                                                    @click="toggleClaimableActiveSlot()"
                                                                    x-bind:disabled="slotActionBusy"
                                                                    x-bind:class="slotManageMenuItemClass(set)"
                                                                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    <x-heroicon-m-flag class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                                                    <span x-text="slot.is_claimable_manual ? 'Remove claimable status' : 'Mark slot claimable'"></span>
                                                                </button>
                                                        </div>

                                                        <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-2.5" x-show="activeSlotAction.mode === 'request'" x-cloak>
                                                            <x-input-label for="planned-slot-request-message-inline" value="Message (optional)" />
                                                            <x-textarea-input id="planned-slot-request-message-inline" x-model="activeSlotAction.message" rows="3" class="mt-1 w-full" />
                                                            <div class="mt-2 flex justify-end">
                                                                <x-modal-primary-button type="button" @click="requestActiveSlot()" x-bind:disabled="slotActionBusy" x-text="slotActionBusy ? 'Sending...' : 'Send Request'"></x-modal-primary-button>
                                                            </div>
                                                        </div>

                                                        <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-2.5" x-show="activeSlotAction.mode === 'recommend'" x-cloak>
                                                            <x-input-label for="planned-slot-recommend-user-inline" value="Recommend To" />
                                                            <select id="planned-slot-recommend-user-inline" x-model="activeSlotAction.target_user_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                                                <option value="">Choose someone</option>
                                                                <template x-for="user in recommendedUsersForActiveSlot()" :key="`slot-recommend-user-inline-${user.id}`">
                                                                    <option :value="String(user.id)" x-text="user.name"></option>
                                                                </template>
                                                            </select>
                                                            <div class="mt-2">
                                                                <x-input-label for="planned-slot-recommend-message-inline" value="Message (optional)" />
                                                                <x-textarea-input id="planned-slot-recommend-message-inline" x-model="activeSlotAction.message" rows="3" class="mt-1 w-full" />
                                                            </div>
                                                            <div class="mt-2 flex justify-end">
                                                                <x-modal-primary-button type="button" @click="recommendActiveSlot()" x-bind:disabled="slotActionBusy" x-text="slotActionBusy ? 'Sending...' : 'Send Recommendation'"></x-modal-primary-button>
                                                            </div>
                                                        </div>

                                                        <p class="mt-3 text-xs text-slate-500" x-show="!canTakeActiveSlot() && !canRequestActiveSlot() && !canRecommendActiveSlot() && !canReleaseActiveSlot() && !canToggleClaimableActiveSlot()">No actions available for this slot right now.</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <p class="mt-2 text-xs text-slate-500" x-show="(song.slots || []).length === 0">No slots yet.</p>
                                    </div>
                                </template>
                            </div>

                            <p class="mt-2 text-sm text-slate-500" x-show="(set.songs || []).length === 0">No songs yet.</p>

                            <div class="mt-3 space-y-3" x-show="set.can_manage && (set.pending_slot_requests || []).length > 0">
                                <div class="rounded-md border border-amber-200 bg-amber-50/80 p-3">
                                    <p class="text-sm font-semibold text-amber-900">Slot Requests &amp; Recommendations</p>
                                    <p class="mt-1 text-xs text-amber-800" x-text="slotRequestsSummary(set)"></p>
                                </div>

                                <template x-for="slotRequest in (set.pending_slot_requests || [])" :key="`set-${set.id}-slot-request-${slotRequest.id}`">
                                    <div class="rounded-lg border border-amber-200 bg-white/90 p-3 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900" x-text="slotRequest.slot_label"></p>
                                                <p class="text-xs text-slate-600" x-text="`${slotRequest.song_artist} - ${slotRequest.song_title}`"></p>
                                                <p class="mt-1 text-xs text-slate-700" x-show="slotRequest.type === 'request'" x-text="`${Number(slotRequest.actor_user_id) === Number(currentUserId) ? 'You' : slotRequest.actor_name} requested this slot.`"></p>
                                                <p class="mt-1 text-xs text-slate-700" x-show="slotRequest.type === 'proposal'" x-text="`${slotRequest.actor_name} recommended ${Number(slotRequest.target_user_id) === Number(currentUserId) ? 'you' : slotRequest.target_name} for this slot.`"></p>
                                                <p class="mt-1 text-xs text-slate-600" x-show="slotRequest.message" x-text="`"${slotRequest.message}"`"></p>
                                                <p class="mt-1 text-xs text-rose-700" x-show="slotRequest.error" x-text="slotRequest.error" x-cloak></p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="respondSlotRequest(set, slotRequest, 'accepted')"
                                                    x-bind:disabled="slotRequest.busy"
                                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-50"
                                                >
                                                    <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                                    <span>Approve</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="respondSlotRequest(set, slotRequest, 'rejected')"
                                                    x-bind:disabled="slotRequest.busy"
                                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-50"
                                                >
                                                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                                    <span>Reject</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="relative mt-3 rounded-lg border border-slate-200 bg-white/90 p-3 pr-12">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session Availability</p>
                            <button
                                type="button"
                                @click="openAvailabilityModal(set)"
                                class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                aria-label="Open attendance"
                                title="Attendance"
                            >
                                <x-heroicon-m-calendar-days class="h-4 w-4" aria-hidden="true" />
                            </button>

                            <p class="mt-1 text-sm font-medium text-slate-700" x-show="attendanceSessionsForSet(set).length > 0" x-text="plannedForLabel(set)"></p>
                            <div class="mt-2 space-y-2" x-show="attendanceSessionsForSet(set).length > 0">
                                <template x-for="session in attendanceSessionsForSet(set).slice(0, 3)" :key="`set-${set.id}-availability-${session.jam_session_id}`">
                                    <div class="rounded-md border border-slate-200 bg-slate-50/70 px-2.5 py-2 text-xs text-slate-700">
                                        <p>
                                            <span class="font-semibold text-slate-900" x-text="session.jam_session_name"></span>
                                            <span class="text-slate-500" x-text="` (${session.jam_session_date_label})`"></span>
                                            <span
                                                class="ms-2 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                                                x-show="set.can_vote"
                                                x-bind:class="attendanceStatusBadge(session.my_status)"
                                                x-text="attendanceStatusLabel(session.my_status)"
                                            ></span>
                                        </p>
                                        <p class="mt-1" x-text="`- Going: ${session.display_counts.going}, ${availabilityNamesList(session.going_names)}`"></p>
                                        <p x-text="`- Not Going: ${session.display_counts.not_going}, ${availabilityNamesList(session.not_going_names)}`"></p>
                                        <p x-text="`- Not specified: ${session.display_counts.not_specified}, ${availabilityNamesList(session.not_specified_slot_names)}`"></p>
                                    </div>
                                </template>
                                <p class="pt-1 text-xs text-slate-500" x-show="attendanceSessionsForSet(set).length > 3">Open Attendance to view more candidate dates.</p>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div x-show="sets.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white/80 p-6 text-center text-sm text-slate-600">
                No planned sets yet. Create one to start collecting collaborators and availability.
            </div>
        </div>

        <x-modal name="planned-set-editor" maxWidth="3xl" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold" x-text="editor.id ? 'Edit Planned Set' : 'Create Planned Set'"></h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="planned-set-name" value="Set name" />
                        <x-text-input id="planned-set-name" x-model="editor.name" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <x-input-label for="planned-set-description" value="Notes" />
                        <x-textarea-input id="planned-set-description" x-model="editor.description" rows="4" class="mt-1 w-full" />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" x-model="editor.is_hidden" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                            <span>Hide this set from non-collaborators</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" x-model="editor.free_for_all" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Mark slots as free for all</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" x-model="editor.song_requests" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Accept song requests</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" x-model="editor.signups_open" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Accept sign ups</span>
                        </label>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-900">Collaborators</p>
                        <p class="mt-1 text-xs text-slate-500">Type a name to add collaborators.</p>
                        <div class="mt-2 space-y-3">
                            <div class="relative">
                                <x-text-input
                                    type="search"
                                    x-model="collaboratorQuery"
                                    @input="queueCollaboratorLookup()"
                                    @focus="showCollaboratorSuggestions = filteredCollaboratorSuggestions().length > 0"
                                    @keydown.escape.stop="showCollaboratorSuggestions = false"
                                    class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-200"
                                    placeholder="Search by name..."
                                    autocomplete="off"
                                />
                                <ul
                                    x-show="showCollaboratorSuggestions && filteredCollaboratorSuggestions().length > 0"
                                    x-cloak
                                    class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                    @click.outside="showCollaboratorSuggestions = false"
                                >
                                    <template x-for="candidate in filteredCollaboratorSuggestions()" :key="`collab-option-${candidate.id}`">
                                        <li>
                                            <button
                                                type="button"
                                                @click="addCollaborator(candidate)"
                                                class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                                x-text="candidate.name"
                                            ></button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div class="min-h-[2.5rem] rounded-md border border-slate-200 bg-slate-50 p-3">
                                <template x-if="selectedCollaborators().length === 0">
                                    <p class="text-sm text-slate-500">No collaborators added yet.</p>
                                </template>

                                <div class="flex flex-wrap gap-2" x-show="selectedCollaborators().length > 0">
                                    <template x-for="collaborator in selectedCollaborators()" :key="`selected-collab-${collaborator.id}`">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-800 shadow-sm">
                                            <span x-text="collaborator.name"></span>
                                            <button
                                                type="button"
                                                @click="removeCollaborator(collaborator.id)"
                                                class="inline-flex h-4 w-4 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-100 hover:text-rose-600 focus:outline-none focus:ring-1 focus:ring-rose-400"
                                                x-bind:aria-label="`Remove ${collaborator.name}`"
                                            >
                                                <x-heroicon-m-x-mark class="h-3 w-3" aria-hidden="true" />
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-900">Candidate Jam Sessions</p>
                        <p class="mt-1 text-xs text-slate-500">Pick the sessions this set could be published to.</p>
                        <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-3">
                            <template x-if="(attendanceSessionOptions || []).length === 0">
                                <p class="text-sm text-slate-500">No upcoming jam sessions available yet.</p>
                            </template>
                            <div class="space-y-2" x-show="(attendanceSessionOptions || []).length > 0">
                                <template x-for="sessionOption in attendanceSessionOptions" :key="`candidate-session-${sessionOption.id}`">
                                    <label class="flex items-start gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            :checked="isCandidateSessionSelected(sessionOption.id)"
                                            @change="toggleCandidateSession(sessionOption.id)"
                                            class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        >
                                        <span>
                                            <span x-text="`${sessionOption.name} (${sessionOption.date_label})`"></span>
                                            <span class="text-slate-500" x-show="sessionOption.is_closed"> - closed</span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-editor' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="saveEditor()" x-bind:disabled="editorBusy" x-text="editorBusy ? 'Saving...' : 'Save'"></x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-add-song" maxWidth="2xl" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Add Song</h3>
                <p class="mt-1 text-sm text-slate-600">Add a song to this planned set and optionally add slots now.</p>

                <div class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="relative">
                            <x-input-label for="planned-set-song-artist" value="Artist" />
                            <x-text-input
                                id="planned-set-song-artist"
                                x-model="songArtistQuery"
                                @input="queueSongArtistLookup()"
                                @focus="showSongArtistSuggestions = songArtistSuggestions.length > 0"
                                @keydown.escape.stop="showSongArtistSuggestions = false"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Start typing an artist..."
                            />
                            <ul
                                x-show="showSongArtistSuggestions && songArtistSuggestions.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showSongArtistSuggestions = false"
                            >
                                <template x-for="artist in songArtistSuggestions" :key="`planned-artist-${artist}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectSongArtistSuggestion(artist)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="artist"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-1 text-xs text-slate-500" x-show="songArtistLookupBusy">Looking up artists...</p>
                            <p class="mt-1 text-xs text-rose-600" x-show="songArtistLookupError" x-text="songArtistLookupError"></p>
                        </div>
                        <div class="relative">
                            <x-input-label for="planned-set-song-title" value="Title" />
                            <x-text-input
                                id="planned-set-song-title"
                                x-model="songTitleQuery"
                                @input="queueSongTitleLookup()"
                                @focus="showSongTitleSuggestions = songTitleSuggestions.length > 0"
                                @keydown.escape.stop="showSongTitleSuggestions = false"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Start typing a song title..."
                            />
                            <ul
                                x-show="showSongTitleSuggestions && songTitleSuggestions.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showSongTitleSuggestions = false"
                            >
                                <template x-for="suggestion in songTitleSuggestions" :key="`planned-title-${suggestion.title}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectSongTitleSuggestion(suggestion.title, suggestion.duration)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                        >
                                            <span class="block font-medium" x-text="suggestion.title"></span>
                                            <span class="block text-xs text-slate-500" x-text="suggestion.album || ''"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-1 text-xs text-slate-500" x-show="songTitleLookupBusy">Looking up songs...</p>
                            <p class="mt-1 text-xs text-rose-600" x-show="songTitleLookupError" x-text="songTitleLookupError"></p>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="planned-set-song-notes" value="Notes" />
                        <x-textarea-input id="planned-set-song-notes" x-model="songEditor.notes" rows="3" class="mt-1 w-full" />
                    </div>

                    <div class="space-y-2">
                        <span class="block text-sm font-medium text-slate-700">Add slots by</span>
                        <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" value="template" x-model="songEditor.song_slot_addition_mode" class="border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                Band template
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" value="manual" x-model="songEditor.song_slot_addition_mode" class="border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                Choose slots manually
                            </label>
                        </div>
                    </div>

                    <div x-show="songEditor.song_slot_addition_mode === 'template'" x-cloak>
                        <x-input-label for="planned-set-song-template" value="Band Template" />
                        <select id="planned-set-song-template" x-model="songEditor.band_template_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Choose a band template</option>
                            <template x-for="template in templateOptions" :key="`planned-template-${template.id}`">
                                <option :value="String(template.id)" x-text="template.name"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="songEditor.song_slot_addition_mode === 'manual'" x-cloak>
                        <p class="text-sm font-medium text-slate-700">Choose slots manually</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <template x-for="(slotLabel, slotKey) in slotOptions" :key="`planned-slot-option-${slotKey}`">
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        :checked="songEditor.slot_names.includes(slotKey)"
                                        @change="toggleSongSlotName(slotKey)"
                                    >
                                    <span x-text="slotLabel"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-song' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="saveSong()" x-bind:disabled="songBusy" x-text="songBusy ? 'Adding...' : 'Add Song'"></x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-add-slot" maxWidth="md" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Add Slot</h3>
                <p class="mt-1 text-sm text-slate-600">Add one slot or apply a band template to this song.</p>

                <div class="mt-4 space-y-4">
                    <div class="space-y-2">
                        <span class="block text-sm font-medium text-slate-700">Add slots by</span>
                        <div class="flex gap-4 text-sm text-slate-700">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" value="individual" x-model="slotEditor.addition_mode" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                                Individual slot
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" value="template" x-model="slotEditor.addition_mode" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                                Band template
                            </label>
                        </div>
                    </div>

                    <div x-show="slotEditor.addition_mode === 'individual'" x-cloak>
                        <x-input-label for="planned-set-slot-name" value="Slot Name" />
                        <select id="planned-set-slot-name" x-model="slotEditor.name" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <template x-for="(slotLabel, slotKey) in slotOptions" :key="`planned-slot-${slotKey}`">
                                <option :value="slotKey" x-text="slotLabel"></option>
                            </template>
                        </select>

                        <div class="mt-3">
                            <x-input-label for="planned-set-slot-notes" value="Notes (optional)" />
                            <x-textarea-input id="planned-set-slot-notes" x-model="slotEditor.notes" rows="3" class="mt-1 w-full" />
                        </div>
                    </div>

                    <div x-show="slotEditor.addition_mode === 'template'" x-cloak>
                        <x-input-label for="planned-set-slot-template" value="Band Template" />
                        <select id="planned-set-slot-template" x-model="slotEditor.band_template_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Choose a band template</option>
                            <template x-for="template in templateOptions" :key="`planned-slot-template-${template.id}`">
                                <option :value="String(template.id)" x-text="template.name"></option>
                            </template>
                        </select>
                        <p class="mt-2 text-xs text-slate-600">Existing slots stay in place. Duplicate slot types are skipped.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-slot' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="saveSlot()" x-bind:disabled="slotBusy" x-text="slotBusy ? 'Adding...' : 'Add Slot'"></x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-edit-song" maxWidth="xl" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Edit Song</h3>
                <div class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="relative">
                            <x-input-label for="planned-set-edit-song-artist" value="Artist" />
                            <x-text-input
                                id="planned-set-edit-song-artist"
                                x-model="songEditArtistQuery"
                                @input="queueSongEditArtistLookup()"
                                @focus="showSongEditArtistSuggestions = songEditArtistSuggestions.length > 0"
                                @keydown.escape.stop="showSongEditArtistSuggestions = false"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Start typing an artist..."
                            />
                            <ul
                                x-show="showSongEditArtistSuggestions && songEditArtistSuggestions.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showSongEditArtistSuggestions = false"
                            >
                                <template x-for="artist in songEditArtistSuggestions" :key="`planned-edit-artist-${artist}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectSongEditArtistSuggestion(artist)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                            x-text="artist"
                                        ></button>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-1 text-xs text-slate-500" x-show="songEditArtistLookupBusy">Looking up artists...</p>
                            <p class="mt-1 text-xs text-rose-600" x-show="songEditArtistLookupError" x-text="songEditArtistLookupError"></p>
                        </div>
                        <div class="relative">
                            <x-input-label for="planned-set-edit-song-title" value="Title" />
                            <x-text-input
                                id="planned-set-edit-song-title"
                                x-model="songEditTitleQuery"
                                @input="queueSongEditTitleLookup()"
                                @focus="showSongEditTitleSuggestions = songEditTitleSuggestions.length > 0"
                                @keydown.escape.stop="showSongEditTitleSuggestions = false"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                placeholder="Start typing a song title..."
                            />
                            <ul
                                x-show="showSongEditTitleSuggestions && songEditTitleSuggestions.length > 0"
                                x-cloak
                                class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                                @click.outside="showSongEditTitleSuggestions = false"
                            >
                                <template x-for="suggestion in songEditTitleSuggestions" :key="`planned-edit-title-${suggestion.title}`">
                                    <li>
                                        <button
                                            type="button"
                                            @click="selectSongEditTitleSuggestion(suggestion.title, suggestion.duration)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                        >
                                            <span class="block font-medium" x-text="suggestion.title"></span>
                                            <span class="block text-xs text-slate-500" x-text="suggestion.album || ''"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-1 text-xs text-slate-500" x-show="songEditTitleLookupBusy">Looking up songs...</p>
                            <p class="mt-1 text-xs text-rose-600" x-show="songEditTitleLookupError" x-text="songEditTitleLookupError"></p>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="planned-set-edit-song-notes" value="Notes" />
                        <x-textarea-input id="planned-set-edit-song-notes" x-model="songEditEditor.notes" rows="3" class="mt-1 w-full" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-edit-song' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="saveSongEdit()" x-bind:disabled="songEditBusy" x-text="songEditBusy ? 'Saving...' : 'Save'"></x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-availability" maxWidth="2xl" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Set Availability</h3>
                <p class="mt-1 text-sm text-slate-600">This uses your jam attendance. Going means available, and Not Going means unavailable.</p>

                <div class="mt-4 space-y-3">
                    <template x-if="!currentAvailabilityHasCandidateSessions()">
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">No candidate jam sessions selected for this set yet.</p>
                    </template>

                    <template x-if="currentAvailabilityHasCandidateSessions() && currentAvailabilitySessions().length === 0">
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">No upcoming candidate jam sessions available right now.</p>
                    </template>

                    <template x-for="session in currentAvailabilitySessions()" :key="`availability-row-${session.jam_session_id}`">
                        <div class="rounded-lg border border-slate-200 bg-slate-50/95 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900" x-text="session.jam_session_name"></p>
                                    <p class="mt-1 text-xs text-slate-500" x-text="session.jam_session_date_label"></p>
                                    <p class="mt-2 text-xs text-slate-600" x-text="`Set team: Going ${session.counts.going}, Not Going ${session.counts.not_going}, Not specified ${session.counts.maybe}`"></p>
                                </div>
                                <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide" x-bind:class="attendanceStatusBadge(session.my_status)" x-text="attendanceStatusLabel(session.my_status)"></span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" @click="setAttendanceStatus(session, 'going')" x-bind:disabled="session.is_closed || isAttendanceSaving(session.jam_session_id) || session.my_status === 'going'" class="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition enabled:hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50">Going</button>
                                <button type="button" @click="setAttendanceStatus(session, 'not_going')" x-bind:disabled="session.is_closed || isAttendanceSaving(session.jam_session_id) || session.my_status === 'not_going'" class="inline-flex items-center rounded-md border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm transition enabled:hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50">Not Going</button>
                                <button type="button" @click="setAttendanceStatus(session, 'maybe')" x-bind:disabled="session.is_closed || isAttendanceSaving(session.jam_session_id) || session.my_status === 'maybe'" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition enabled:hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">Not Specified</button>
                                <span class="text-xs text-slate-500" x-show="session.is_closed">Closed session</span>
                                <span class="text-xs text-slate-500" x-show="isAttendanceSaving(session.jam_session_id)">Saving...</span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-availability' }))">Close</x-modal-secondary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-dropout-choice" maxWidth="md" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Before you mark not going</h3>
                <p class="mt-1 text-sm text-slate-600">Choose what should happen to your current slots in <span class="font-semibold text-slate-900" x-text="dropoutPrompt.jam_session_name"></span>.</p>

                <div class="mt-4 space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                        <input type="radio" name="planned_set_dropout_action" value="keep_claimable" x-model="dropoutPrompt.action" class="mt-1 border-slate-300 text-amber-500 focus:ring-amber-500">
                        <span>Keep my slots assigned, but mark them claimable.</span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                        <input type="radio" name="planned_set_dropout_action" value="release_slots" x-model="dropoutPrompt.action" class="mt-1 border-slate-300 text-amber-500 focus:ring-amber-500">
                        <span>Release all of my assigned slots now.</span>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-dropout-choice' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="confirmDropoutChoice()">Confirm No</x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="planned-set-schedule" maxWidth="md" focusable>
            <div class="p-6 text-slate-900">
                <h3 class="text-lg font-semibold">Schedule Planned Set</h3>
                <p class="mt-1 text-sm text-slate-600">Move this draft into a specific jam session.</p>

                <div class="mt-4">
                    <x-input-label for="schedule-jam-session" value="Jam session" />
                    <select id="schedule-jam-session" x-model="scheduleForm.jam_session_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <template x-for="option in scheduleCandidateSessionOptions()" :key="`schedule-session-${option.id}`">
                            <option :value="String(option.id)" x-text="`${option.name} (${option.date_label})${option.is_closed ? ' - closed' : ''}`"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-xs text-rose-700" x-show="scheduleCandidateSessionOptions().length === 0" x-cloak>
                        Add candidate jam sessions in the set editor before publishing.
                    </p>
                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900" x-show="scheduleAvailabilityWarningMessage()" x-cloak x-text="scheduleAvailabilityWarningMessage()"></p>

                    <div class="mt-3 space-y-2" x-show="scheduleHasNotGoingParticipants()" x-cloak>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Participants Marked Not Going</p>
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <input type="radio" name="planned_set_not_going_slot_action" value="release_slots" x-model="scheduleForm.not_going_slot_action" class="mt-0.5 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Clear slots assigned to Not Going participants and leave those slots open after publishing.</span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <input type="radio" name="planned_set_not_going_slot_action" value="keep_claimable" x-model="scheduleForm.not_going_slot_action" class="mt-0.5 border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Keep those slot assignments, but mark each slot claimable after publishing.</span>
                        </label>
                        <p class="text-xs text-slate-500">Only slots assigned to users marked Not Going are affected.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-schedule' }))">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="saveSchedule()" x-bind:disabled="scheduleBusy" x-text="scheduleBusy ? 'Scheduling...' : 'Schedule Set'"></x-modal-primary-button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
