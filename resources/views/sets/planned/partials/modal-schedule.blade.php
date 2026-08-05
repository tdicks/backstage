<x-modal name="planned-set-schedule" maxWidth="md" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Schedule Planned Set</h3>
        <p class="mt-1 text-sm text-slate-600">Move this draft into a specific jam session.</p>

        <div class="mt-4">
            <x-input-label for="schedule-jam-session" value="Jam session" />
            <x-select id="schedule-jam-session" x-model="scheduleForm.jam_session_id" class="focus:border-emerald-500 focus:ring-emerald-500">
                <template x-for="option in scheduleCandidateSessionOptions()" :key="`schedule-session-${option.id}`">
                    <option :value="String(option.id)" x-text="`${option.name} (${option.date_label})${option.is_closed ? ' - closed' : ''}`"></option>
                </template>
            </x-select>
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
