<div class="relative mt-3 rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-3 pr-12 shadow-sm transition hover:border-slate-400 hover:shadow-md">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session Availability</p>
    <button
        x-show="attendanceSessionsForSet(set).length !== 0"
        type="button"
        @click="openAvailabilityModal(set)"
        class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
        aria-label="Open attendance"
        title="Attendance"
    >
        <x-heroicon-m-calendar-days class="h-4 w-4" aria-hidden="true" />
    </button>

    <p class="mt-1 text-sm font-medium text-slate-700" x-show="attendanceSessionsForSet(set).length === 0">No planned session yet.</p>
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
                <ul class="mt-1">
                    <li x-text="`- Going: ${session.display_counts.going}, ${availabilityNamesList(session.going_names)}`"></li>
                    <li x-text="`- Not Going: ${session.display_counts.not_going}, ${availabilityNamesList(session.not_going_names)}`"></li>
                    <li x-text="`- Not specified: ${session.display_counts.not_specified}, ${availabilityNamesList(session.not_specified_slot_names)}`"></li>
                </ul>
            </div>
        </template>
        <p class="pt-1 text-xs text-slate-500" x-show="attendanceSessionsForSet(set).length > 3">Open Attendance to view more candidate dates.</p>
    </div>
</div>
