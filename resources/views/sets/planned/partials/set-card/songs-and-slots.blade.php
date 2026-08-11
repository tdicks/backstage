<x-sets.presentational.section-panel heading="Songs & Slots" class="mt-3 transition hover:border-slate-400 hover:shadow-md">

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
            <x-sets.presentational.song-card x-data="{ openSongActionMenu: false }" title-expr="`${song.artist} - ${song.title}`" notes-expr="song.notes" notes-show-expr="song.notes">
                <x-slot:titleSuffix>
                    <button
                        type="button"
                        @click="openAttachmentsForEntity('song', song.id, `Song: ${song.artist} - ${song.title}`, `song-${song.id}`, song.attachments_count)"
                        x-bind:class="attachmentIconClasses(`song-${song.id}`, song.attachments_count)"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                        aria-label="Song attachments"
                        title="Song attachments"
                    >
                        <x-heroicon-m-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </x-slot:titleSuffix>

                <x-slot:actions>
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
                                @click="openSongActionMenu = false; openAddSlotModal(set, song)"
                                x-bind:class="slotManageMenuItemClass(set)"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none"
                            >
                                <x-heroicon-m-plus class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                <x-admin-shield-icon x-show="isAdminManagingOtherSet(set)" x-cloak class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                <span>Add Slot</span>
                            </button>
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
                                @click="openSongActionMenu = false; openAttachmentsForEntity('song', song.id, `Song: ${song.artist} - ${song.title}`, `song-${song.id}`, song.attachments_count)"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                            >
                                <x-heroicon-m-paper-clip class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                <span>Attachments</span>
                            </button>

                        </div>
                    </div>
                </x-slot:actions>

                <div class="mt-2 flex flex-wrap gap-2" x-show="(song.slots || []).length > 0">
                    <template x-for="slot in (song.slots || [])" :key="`song-${song.id}-slot-${slot.id}`">
                        <div class="relative">
                            <x-sets.presentational.slot-chip
                                tag="button"
                                type="button"
                                @click="openSlotActions(set, song, slot)"
                                label-expr="slot.label"
                                title-expr="slot.user_name ? `${slot.label}: ${slot.user_name}` : slot.label"
                                expanded-expr="isSlotActionPopoverOpen(slot.id).toString()"
                            >
                                <x-slot:badge>
                                    <div class="inline-flex items-center gap-1">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            x-bind:class="slotAssigneeBadgeClass(slot)"
                                            x-bind:title="slot.manual_performer_name ? 'Manually assigned' : ''"
                                        >
                                            <span x-text="slotAssigneeName(slot)"></span>
                                            <template x-if="slot.manual_performer_name">
                                                <span class="inline-flex items-center" aria-hidden="true">
                                                    <x-heroicon-m-pencil-square class="h-3.5 w-3.5" />
                                                </span>
                                            </template>
                                        </span>

                                        <template x-if="slot.notes">
                                            <span class="inline-flex items-center  p-1 text-slate-600" aria-hidden="true" x-bind:title="'Has notes'">
                                                <x-heroicon-m-chat-bubble-left-ellipsis class="h-3.5 w-3.5" />
                                            </span>
                                        </template>
                                    </div>
                                </x-slot:badge>

                            </x-sets.presentational.slot-chip>

                            <div
                                x-show="isSlotActionPopoverOpen(slot.id)"
                                x-cloak
                                x-transition.origin.top.left
                                @click.outside="closeSlotActions()"
                                class="absolute left-0 z-30 mt-2 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                            >
                                <p class="mb-1 text-sm font-medium text-slate-900" x-text="slot.label"></p>
                                <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    <span x-text="slot.is_open ? 'Open' : `Assigned to ${slotAssigneeName(slot)}`"></span>
                                    <template x-if="slot.manual_performer_name">
                                        <span class="inline-flex items-center" aria-hidden="true" x-bind:title="'Manually assigned'">
                                            <x-heroicon-m-pencil-square class="h-3.5 w-3.5" />
                                        </span>
                                    </template>
                                </div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-show="slot.notes" x-cloak>Slot Notes</p>
                                <div class="mt-2 mb-2 rounded-md border border-amber-100 bg-amber-50/70 px-3 py-2 text-sm text-amber-900" x-show="slot.notes" x-cloak>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-amber-950" x-text="slot.notes"></p>
                                </div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slot Actions</p>

                                <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-amber-700" x-show="set.free_for_all">
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

                                    <button
                                        type="button"
                                        x-show="canEditActiveSlot()"
                                        @click="openEditSlotModal()"
                                        x-bind:disabled="slotActionBusy"
                                        x-bind:class="slotManageMenuItemClass(set)"
                                        class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <x-heroicon-m-pencil-square class="h-4 w-4" x-bind:class="slotAdminIconClass(set)" aria-hidden="true" />
                                        <span>Edit slot</span>
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

                                <p class="mt-3 text-xs text-slate-500" x-show="!canTakeActiveSlot() && !canRequestActiveSlot() && !canRecommendActiveSlot() && !canReleaseActiveSlot() && !canToggleClaimableActiveSlot() && !canEditActiveSlot()">No actions available for this slot right now.</p>
                            </div>
                        </div>
                    </template>
                </div>

                <p class="mt-2 text-xs text-slate-500" x-show="(song.slots || []).length === 0">No slots yet.</p>
            </x-sets.presentational.song-card>
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
</x-sets.presentational.section-panel>
