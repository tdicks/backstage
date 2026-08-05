<x-modal name="planned-set-edit-slot" maxWidth="lg" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Edit Slot</h3>
        <p class="mt-1 text-sm text-slate-600">Update the slot assignment, slot name, or notes.</p>

        <div class="mt-4 space-y-4">
            <div>
                <x-input-label for="planned-set-edit-slot-name" value="Slot Name" />
                <x-select id="planned-set-edit-slot-name" x-model="slotEditEditor.name" class="focus:border-amber-500 focus:ring-amber-500">
                    <template x-for="(slotLabel, slotKey) in slotOptions" :key="`planned-slot-edit-name-${slotKey}`">
                        <option :value="slotKey" x-text="slotLabel"></option>
                    </template>
                </x-select>
            </div>

            <div>
                <x-input-label for="planned-set-edit-slot-assignee" value="Assigned User or Manual Name" />
                <div class="relative">
                    <x-text-input
                        id="planned-set-edit-slot-assignee"
                        type="search"
                        x-model="editAssignedUserQuery"
                        @input="updateEditUserQuery()"
                        @focus="showEditUserSuggestions = editAssignedUserQuery.trim() !== ''"
                        @keydown.escape="showEditUserSuggestions = false"
                        class="mt-1 block w-full"
                        autocomplete="off"
                    />
                    <div
                        x-show="showEditUserSuggestions && (groupedEditUsers().available.length > 0 || groupedEditUsers().notAttending.length > 0)"
                        x-cloak
                        class="absolute z-[120] mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                        @click.outside="showEditUserSuggestions = false"
                    >
                        <template x-if="groupedEditUsers().available.length > 0">
                            <div>
                                <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Available</p>
                                <template x-for="user in groupedEditUsers().available" :key="`planned-slot-edit-available-${user.id}`">
                                    <button
                                        type="button"
                                        @click="selectEditUser(user)"
                                        class="w-full px-3 py-2 text-left text-sm text-slate-800 transition hover:bg-amber-50 focus:bg-amber-50 focus:outline-none"
                                        x-text="user.name"
                                    ></button>
                                </template>
                            </div>
                        </template>
                        <template x-if="groupedEditUsers().notAttending.length > 0">
                            <div class="border-t border-slate-200">
                                <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Not attending</p>
                                <p class="px-3 pb-2 text-[11px] text-slate-500">These users marked not attending for this session.</p>
                                <template x-for="user in groupedEditUsers().notAttending" :key="`planned-slot-edit-not-attending-${user.id}`">
                                    <button
                                        type="button"
                                        @click="if (canSelectUser(user)) { selectEditUser(user); }"
                                        :disabled="!canSelectUser(user)"
                                        class="w-full px-3 py-2 text-left text-sm transition focus:outline-none"
                                        :class="canSelectUser(user) ? 'text-slate-800 hover:bg-amber-50 focus:bg-amber-50' : 'cursor-not-allowed text-slate-400'"
                                        x-text="user.name"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                <p x-show="shouldShowAssigneeWarning()" x-cloak class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    We&apos;ll save this as a manual assignment unless you choose a user from the list.
                </p>
                <p x-show="assignmentConflictMessage" x-text="assignmentConflictMessage" x-cloak class="mt-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900"></p>
                <p class="mt-1 text-xs text-slate-500">Free typing will keep this as a manual performer name.</p>
            </div>

            <div>
                <x-input-label for="planned-set-edit-slot-notes" value="Notes (optional)" />
                <x-textarea-input id="planned-set-edit-slot-notes" x-model="slotEditEditor.notes" rows="3" class="mt-1 w-full" />
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between gap-3">
            <x-danger-button type="button" @click="clearEditedSlot()" x-bind:disabled="slotEditBusy">Clear Slot</x-danger-button>
            <div class="flex justify-end gap-3">
                <x-modal-secondary-button type="button" @click="closeEditSlotModal()">Cancel</x-modal-secondary-button>
                <x-modal-primary-button type="button" @click="submitEditSlot()" x-bind:disabled="slotEditBusy || assignmentConflictCooldown" x-text="slotEditBusy ? 'Saving...' : 'Save'"></x-modal-primary-button>
            </div>
        </div>
    </div>
</x-modal>
