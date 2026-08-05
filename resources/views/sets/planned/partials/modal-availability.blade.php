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
