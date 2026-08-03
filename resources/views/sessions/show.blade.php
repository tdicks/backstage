<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ openEditSession: false, openSet: false, free_for_all_create: false, shareCopied: false, initialEditSessionClosed: @js((bool) $session->is_closed), initialEditSessionAllowCheckins: @js((bool) $session->allow_checkins), initialEditSessionLive: @js((bool) $session->is_live), editSessionClosed: @js((bool) $session->is_closed), editSessionAllowCheckins: @js((bool) $session->allow_checkins), editSessionLive: @js((bool) $session->is_live), openEditSessionModal() { this.editSessionClosed = this.initialEditSessionClosed; this.editSessionAllowCheckins = this.initialEditSessionAllowCheckins; this.editSessionLive = this.initialEditSessionLive; this.openEditSession = true; }, async copySessionShareLink() { await window.copyShareLink(@js(route('share.session', $session))); this.shareCopied = true; setTimeout(() => this.shareCopied = false, 1800); } }" @keydown.escape.window="openEditSession = false; openSet = false">
            <div>
                <h2 class="flex items-center gap-2 text-xl font-semibold text-slate-100">
                    <span>{{ $session->name }}</span>
                    @if ($session->is_live)
                        <x-live-status-icon size="h-6 w-6" title="This jam session is live" />
                    @endif
                    @if ($session->is_closed)
                        <x-heroicon-m-lock-closed class="h-6 w-6 text-amber-400" aria-hidden="true" title="This jam is closed to new sets" />
                    @endif
                    @if ($session->is_archived)
                        <x-heroicon-m-archive-box class="h-6 w-6 text-amber-700" aria-hidden="true" title="This jam is archived" />
                    @endif
                    @if ($session->is_hidden)
                        <x-heroicon-m-eye-slash class="h-6 w-6 text-sky-400" aria-hidden="true" title="This jam is hidden from non-admin users" />
                    @endif
                </h2>
                <p class="text-sm text-gray-500">{{ $session->date->format('l, F j, Y') }}</p>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <span class="relative inline-flex">
                    <button
                        type="button"
                        @click="copySessionShareLink()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-100 shadow-sm transition hover:border-amber-400 hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400"
                        x-bind:title="shareCopied ? 'Share link copied' : 'Copy share link'"
                        aria-label="Copy share link"
                    >
                        <x-heroicon-m-share class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only" x-text="shareCopied ? 'Share link copied' : 'Copy share link'">Copy share link</span>
                    </button>
                    <div
                        x-show="shareCopied"
                        x-transition.opacity.duration.150ms
                        x-cloak
                        role="status"
                        aria-live="polite"
                        class="absolute right-0 top-full z-[80] mt-2 whitespace-nowrap rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 shadow-lg"
                    >
                        Share link copied
                    </div>
                </span>
                <div
                    class="relative"
                    x-data="sessionAttendanceControl({
                        status: @js($attendanceStatus),
                        requiresDropoutAction: @js((bool) $attendanceRequiresDropoutAction),
                        sessionClosed: @js((bool) $session->is_closed),
                        isPastSession: @js($session->date->isBefore(today())),
                        isAdmin: @js((bool) auth()->user()?->is_admin),
                        currentUserId: @js((string) auth()->id()),
                        attendanceIndexUrl: @js(route('sessions.attendance.index', $session)),
                        attendanceUpdateUrl: @js(route('sessions.attendance.update', $session)),
                        csrfToken: @js(csrf_token()),
                    })"
                    @keydown.escape.window="attendanceModalOpen = false; openDropoutChoices = false; openAdminDropoutChoices = false"
                >
                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            @click="openAttendanceModal()"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 shadow-sm transition hover:border-amber-400 hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Open attendance"
                            title="Open attendance"
                        >
                            <x-heroicon-m-user-group class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Attendance</span>
                        </button>

                        <div x-show="hasVisibleStatusButtons()" x-cloak class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-900/85 p-1 shadow-sm">
                        <button
                            type="button"
                            @click="chooseStatus('not_going')"
                            x-show="shouldShowStatusButton('not_going')"
                            :disabled="isSaving || sessionClosed || isPastSession"
                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="status === 'not_going' ? 'border border-rose-700 bg-rose-950/70 text-rose-300' : 'border border-transparent text-slate-300 enabled:hover:border-rose-600 enabled:hover:bg-rose-950/60 enabled:hover:text-rose-300'"
                            :title="statusButtonLabel('not_going')"
                            :aria-label="statusButtonLabel('not_going')"
                        >
                            <x-heroicon-m-x-circle class="h-4 w-4" aria-hidden="true" />
                            <span class="ml-1 hidden sm:inline" x-text="statusButtonLabel('not_going')">Not going</span>
                        </button>
                        <button
                            type="button"
                            @click="chooseStatus('maybe')"
                            x-show="shouldShowStatusButton('maybe')"
                            :disabled="isSaving || sessionClosed || isPastSession"
                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="status === 'maybe' ? 'border border-slate-500 bg-slate-800 text-slate-100' : 'border border-transparent text-slate-300 enabled:hover:border-slate-500 enabled:hover:bg-slate-800 enabled:hover:text-slate-100'"
                            :title="statusButtonLabel('maybe')"
                            :aria-label="statusButtonLabel('maybe')"
                        >
                            <x-heroicon-m-question-mark-circle class="h-4 w-4" aria-hidden="true" />
                            <span class="ml-1 hidden sm:inline" x-text="statusButtonLabel('maybe')">Maybe</span>
                        </button>
                        <button
                            type="button"
                            @click="chooseStatus('going')"
                            x-show="shouldShowStatusButton('going')"
                            :disabled="isSaving || sessionClosed || isPastSession"
                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="status === 'going' ? 'border border-emerald-700 bg-emerald-950/70 text-emerald-300' : 'border border-transparent text-slate-300 enabled:hover:border-emerald-700 enabled:hover:bg-emerald-950/60 enabled:hover:text-emerald-300'"
                            :title="statusButtonLabel('going')"
                            :aria-label="statusButtonLabel('going')"
                        >
                            <x-heroicon-m-check-circle class="h-4 w-4" aria-hidden="true" />
                            <span class="ml-1 hidden sm:inline" x-text="statusButtonLabel('going')">Going</span>
                        </button>
                        </div>
                    </div>

                    <template x-teleport="body">
                        <div x-cloak>
                            <div x-show="attendanceModalOpen" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="attendanceModalOpen = false"></div>
                            <div x-show="attendanceModalOpen" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                                <section class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-left text-slate-900 shadow-2xl sm:max-h-[calc(100vh-4rem)]" role="dialog" aria-modal="true" aria-label="Attendance list" @click.stop>
                            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
                                <div>
                                    <h4 class="text-lg font-semibold text-slate-900">Attendance</h4>
                                    <p class="mt-1 text-sm text-slate-600" x-text="isPastSession ? 'Showing users who attended or did not attend.' : 'Showing users marked Going or Not going.'">Showing users marked Going or Not going.</p>
                                </div>
                                <button type="button" @click="attendanceModalOpen = false" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400" aria-label="Close attendance modal" title="Close">
                                    <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </header>

                            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                <p x-show="attendanceListLoading" x-cloak class="text-sm text-slate-600">Loading attendance...</p>
                                <p x-show="attendanceListError" x-cloak x-text="attendanceListError" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

                                <template x-if="!attendanceListLoading && attendanceUsers.length === 0">
                                    <p class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-600" x-text="isPastSession ? 'No attendance history recorded yet.' : 'No users currently marked Going or Not going.'">No users currently marked Going or Not going.</p>
                                </template>

                                <div x-show="attendanceUsers.length > 0" x-cloak class="space-y-2">
                                    <template x-for="user in attendanceUsers" :key="user.id">
                                        <article class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900" x-text="user.name"></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium uppercase tracking-wide" :class="badgeClasses(user.status)" x-text="modalStatusLabel(user.status)"></span>
                                                <template x-if="canManageAttendanceForUser(user)">
                                                    <div class="inline-flex items-center gap-1">
                                                        <button type="button" @click="setUserStatus(user.id, 'not_going')" :disabled="isSaving || sessionClosed || user.status === 'not_going'" class="inline-flex items-center rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 shadow-sm transition enabled:hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Set not going" title="Set not going"><x-heroicon-m-x-circle class="h-4 w-4" aria-hidden="true" /><span class="ml-1 hidden sm:inline">Not Going</span></button>
                                                        <button type="button" @click="setUserStatus(user.id, 'maybe')" :disabled="isSaving || sessionClosed || user.status === 'maybe'" class="inline-flex items-center rounded-md border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm transition enabled:hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Set maybe" title="Set maybe"><x-heroicon-m-question-mark-circle class="h-4 w-4" aria-hidden="true" /><span class="ml-1 hidden sm:inline">Maybe</span></button>
                                                        <button type="button" @click="setUserStatus(user.id, 'going')" :disabled="isSaving || sessionClosed || user.status === 'going'" class="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-sm transition enabled:hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Set going" title="Set going"><x-heroicon-m-check-circle class="h-4 w-4" aria-hidden="true" /><span class="ml-1 hidden sm:inline">Going</span></button>
                                                    </div>
                                                </template>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </div>

                            <footer class="flex items-center justify-end gap-2 border-t border-slate-200 px-6 py-4">
                                <x-modal-secondary-button type="button" @click="attendanceModalOpen = false">Close</x-modal-secondary-button>
                            </footer>
                                </section>
                            </div>
                        </div>
                    </template>

                    <x-prompt-modal
                        open="openDropoutChoices"
                        title="Before you mark not going"
                        description="Choose what should happen to your current slots in this session."
                        close-action="openDropoutChoices = false"
                    >
                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                <input type="radio" name="dropout_action" value="keep_claimable" x-model="dropoutAction" class="mt-0.5 border-slate-500 text-amber-500 focus:ring-amber-500">
                                <span>Keep my slots assigned, but mark them claimable so others can take over.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                <input type="radio" name="dropout_action" value="release_slots" x-model="dropoutAction" class="mt-0.5 border-slate-500 text-amber-500 focus:ring-amber-500">
                                <span>Release all of my assigned slots now.</span>
                            </label>
                        </div>

                        <div class="mt-4 rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-200">Your sets</p>
                            <p class="mt-1 text-xs text-slate-300">Marking not going does not move your sets. If another jam is available, you can move your sets there.</p>
                        </div>

                        <x-slot name="actions">
                            <button type="button" @click="openDropoutChoices = false" class="rounded-md border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800">Cancel</button>
                            <button type="button" @click="confirmDropout()" :disabled="isSaving" class="rounded-md border border-rose-500 bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white transition enabled:hover:bg-rose-400 disabled:cursor-not-allowed disabled:opacity-50">Confirm not going</button>
                        </x-slot>
                    </x-prompt-modal>

                    <x-prompt-modal
                        open="openAdminDropoutChoices"
                        title="Set Not Going"
                        description="Choose what should happen to the user's assigned slots in this session."
                        close-action="openAdminDropoutChoices = false"
                    >
                        <p class="mb-3 text-xs text-slate-300">
                            <span class="font-semibold" x-text="adminDropoutUserName"></span>
                            will be marked as not going.
                        </p>

                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                <input type="radio" name="admin_dropout_action" value="keep_claimable" x-model="adminDropoutAction" class="mt-0.5 border-slate-500 text-amber-500 focus:ring-amber-500">
                                <span>Keep their slots assigned, but mark them claimable so others can take over.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                <input type="radio" name="admin_dropout_action" value="release_slots" x-model="adminDropoutAction" class="mt-0.5 border-slate-500 text-amber-500 focus:ring-amber-500">
                                <span>Release all of their assigned slots now.</span>
                            </label>
                        </div>

                        <div class="mt-4 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-200">User sets</p>
                            <p class="mt-1 text-xs text-slate-300">Marking this user not going does not move their sets. If another jam is available, you may want to move their sets there.</p>
                        </div>

                        <x-slot name="actions">
                            <button type="button" @click="openAdminDropoutChoices = false" class="rounded-md border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800">Cancel</button>
                            <button type="button" @click="confirmAdminDropout()" :disabled="isSaving" class="rounded-md border border-rose-500 bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white transition enabled:hover:bg-rose-400 disabled:cursor-not-allowed disabled:opacity-50">Confirm not going</button>
                        </x-slot>
                    </x-prompt-modal>
                </div>
                @can('update', $session)
                    <x-secondary-button @click="openEditSessionModal()" title="Edit Session" aria-label="Edit Session" class="gap-1.5">
                        <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Edit Session</span>
                    </x-secondary-button>
                    @if ($session->is_live)
                        <a href="{{ route('sessions.live.manage', $session) }}" class="inline-flex items-center gap-1.5 rounded-md border border-emerald-700 bg-emerald-900/40 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-emerald-300 shadow-sm transition ease-in-out duration-150 hover:border-emerald-500 hover:text-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-slate-900 sm:px-4" title="Open Live Admin" aria-label="Open Live Admin">
                            <x-live-status-icon size="h-4 w-4" title="Open Live Admin" />
                            <span class="hidden sm:inline">Live Admin</span>
                        </a>
                    @endif
                @endcan
                @if (! $session->is_archived && (auth()->user()->is_admin || ! $session->is_closed))
                    <x-primary-button @click="openSet = true" title="Create Set" aria-label="Create Set" class="gap-1.5">
                        <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Create Set</span>
                    </x-primary-button>
                @endif
            </div>

            @can('update', $session)
                <template x-teleport="body">
                    <div x-cloak>
                        <div x-show="openEditSession" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openEditSession = false"></div>
                        <div x-show="openEditSession" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                            <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                                <div class="px-6 pt-6">
                                <h3 class="text-lg font-semibold text-slate-900">Edit Jam Session</h3>
                                </div>
                                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                <form id="edit_session_form_{{ $session->id }}" method="POST" action="{{ route('sessions.update', $session) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <x-input-label for="session_name" value="Name" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <x-text-input id="session_name" name="name" :value="$session->name" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                    </div>
                                    <div>
                                        <x-input-label for="session_date" value="Date" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <x-text-input id="session_date" type="date" name="date" :value="$session->date->toDateString()" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <x-input-label for="session_description" value="Description (Markdown)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                            <x-markdown-help modal-name="session-edit-markdown-help" title="Session Description Markdown Help" />
                                        </div>
                                        <x-textarea-input id="session_description" name="description" rows="6" class="mt-2 w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $session->description }}</x-textarea-input>
                                    </div>
                                    <div>
                                        <input type="hidden" name="is_closed" value="0">
                                        <label for="session_is_closed" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                id="session_is_closed"
                                                type="checkbox"
                                                name="is_closed"
                                                value="1"
                                                x-model="editSessionClosed"
                                                @change="if (editSessionClosed) { editSessionLive = false; editSessionAllowCheckins = false; }"
                                                class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                                @checked($session->is_closed)
                                            >
                                            <span>Close this jam session (prevent new sets)</span>
                                        </label>
                                    </div>
                                    <div>
                                        <input type="hidden" name="is_live" value="0">
                                        <label for="session_is_live" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                id="session_is_live"
                                                type="checkbox"
                                                name="is_live"
                                                value="1"
                                                x-model="editSessionLive"
                                                @change="if (!editSessionLive) { editSessionAllowCheckins = false; }"
                                                x-bind:disabled="editSessionClosed"
                                                class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                                @checked($session->is_live)
                                            >
                                            <span>Enable Live Mode</span>
                                        </label>
                                    </div>
                                    <div>
                                        <input type="hidden" name="allow_checkins" value="0">
                                        <label for="session_allow_checkins" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                id="session_allow_checkins"
                                                type="checkbox"
                                                name="allow_checkins"
                                                value="1"
                                                x-model="editSessionAllowCheckins"
                                                x-bind:disabled="editSessionClosed || !editSessionLive"
                                                class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                @checked($session->allow_checkins)
                                            >
                                            <span>Allow user sign-ins for this session</span>
                                        </label>
                                        <p x-show="initialEditSessionAllowCheckins && !editSessionAllowCheckins" x-cloak class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                            This action will sign out all attendees from this session.
                                        </p>
                                    </div>
                                    <div>
                                        <input type="hidden" name="is_hidden" value="0">
                                        <label for="session_is_hidden" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                id="session_is_hidden"
                                                type="checkbox"
                                                name="is_hidden"
                                                value="1"
                                                class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                                @checked($session->is_hidden)
                                            >
                                            <span>Hide this jam session from non-admin users</span>
                                        </label>
                                    </div>
                                    <div>
                                        <input type="hidden" name="is_archived" value="0">
                                        <label for="session_is_archived" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                id="session_is_archived"
                                                type="checkbox"
                                                name="is_archived"
                                                value="1"
                                                class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                                                @checked($session->is_archived)
                                            >
                                            <span>Archive this jam session</span>
                                        </label>
                                    </div>
                                </form>
                                </div>
                                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4">
                                    <form method="POST" action="{{ route('sessions.destroy', $session) }}" onsubmit="return confirm('Delete this jam session? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-danger-button type="submit">Delete Session</x-danger-button>
                                    </form>
                                    <div class="flex justify-end gap-2">
                                        <x-modal-secondary-button type="button" @click="openEditSession = false">Cancel</x-modal-secondary-button>
                                        <x-modal-primary-button type="submit" form="edit_session_form_{{ $session->id }}">Save</x-modal-primary-button>
                                    </div>
                                    </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endcan

            @if (! $session->is_archived && (auth()->user()->is_admin || ! $session->is_closed))
                <template x-teleport="body">
                    <div x-cloak>
                        <div x-show="openSet" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openSet = false"></div>
                        <div x-show="openSet" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                                <h3 class="text-lg font-semibold text-slate-900">New Set for {{ $session->name }}</h3>
                                <form method="POST" action="{{ route('sets.store', $session) }}" class="mt-4 space-y-4">
                                    @csrf
                                    <div>
                                        <x-input-label for="set_name" value="Set Name" />
                                        <x-text-input id="set_name" name="name" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="set_description" value="Description" />
                                        <x-textarea-input id="set_description" name="description" rows="4" class="mt-1 w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" />
                                    </div>
                                    <label class="flex items-center gap-3 rounded-lg border border-sky-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 shadow-[inset_0_0_6px_rgb(125_211_252_/_0.45),inset_0_0_14px_rgb(186_230_253_/_0.35)]">
                                        <input type="hidden" name="is_hidden" value="0">
                                        <input type="checkbox" name="is_hidden" value="1" class="rounded border-slate-300 text-slate-600 shadow-sm focus:ring-slate-500">
                                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                        Hide this set from other users.
                                    </label>
                                    <p class="-mt-1 text-xs text-slate-600">Only collaborators and admins will see the set.</p>
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                        <input type="hidden" name="free_for_all" value="0">
                                        <input type="checkbox" name="free_for_all" value="1" x-model="free_for_all_create" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                        <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                                        Free for all mode.
                                    </label>
                                    <p x-show="free_for_all_create" x-cloak class="text-xs text-amber-700">
                                        In free for all mode, any unclaimed slots can be taken without requiring any approvals.
                                    </p>
                                    <div class="flex justify-end gap-3">
                                        <x-modal-secondary-button type="button" @click="openSet = false">Cancel</x-modal-secondary-button>
                                        <x-modal-primary-button>Create Set</x-modal-primary-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div
            class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
            x-data="lazySessionSets('{{ route('sessions.sets', $session) }}', '{{ route('sessions.activity', $session) }}', { currentUserId: @js((string) auth()->id()) })"
            @refresh-session-sets.window="refresh({ preserveSetIds: $event.detail && $event.detail.setId ? [String($event.detail.setId)] : [], forceOpenSetIds: $event.detail && $event.detail.setId ? [String($event.detail.setId)] : [] })"
            x-on:refresh-session-activity.window="$store.approvals.refresh()"
            x-on:session-song-opened.window="$store.approvals.refresh()"
        >
            @if ($session->is_live && ! auth()->user()?->is_admin)
                <section class="grid w-full grid-cols-[1fr_auto_1fr] items-center rounded-md border border-emerald-700 bg-emerald-900/40 px-4 py-3 text-emerald-300 shadow-sm">
                    <x-live-status-icon size="h-5 w-5" title="This jam session is live" class="justify-self-start" />
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-widest">This jam session is now in live mode</p>
                        <p class="mt-1 text-xs text-emerald-200">Let the jam manager know about any changes to your sets.</p>
                        @if ($session->allow_checkins)
                            <p class="mt-1 text-xs text-emerald-200">Sign ins are live! Look for a QR code or ask the jam organisers for assistance.</p>
                        @endif
                    </div>
                    <span class="h-5 w-5 justify-self-end" aria-hidden="true"></span>
                </section>
            @endif

            @if ($session->sets_count > 0)
                <p x-show="error" x-text="error" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700 shadow-sm" x-cloak></p>
            @endif

            @if ($session->description)
                <div class="session-markdown rounded-lg bg-slate-50 p-6 shadow-sm">
                    {!! Illuminate\Support\Str::markdown($session->description) !!}
                </div>
            @endif

            @if ($session->sets_count > 0)
                <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <label class="sr-only" for="session-set-search">Search sets</label>
                        <x-text-input id="session-set-search" x-model="filterQuery" @input.debounce.250ms="applyFilters()" placeholder="Search by set, owner, or song" class="block w-full" />

                        <div class="relative" @click.outside="filterMenuOpen = false">
                            <button type="button" @click="filterMenuOpen = !filterMenuOpen" :aria-expanded="filterMenuOpen.toString()" aria-haspopup="true" class="inline-flex w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition hover:border-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-64">
                                <span x-text="selectedFilterLabel()"></span>
                                <x-heroicon-m-chevron-down class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                            </button>

                            <div x-show="filterMenuOpen" x-cloak x-transition.origin.top.right class="absolute right-0 z-30 mt-1 w-full min-w-72 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl sm:w-80">
                                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Ownership</p>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="my_sets" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-user class="h-4 w-4 text-slate-600" aria-hidden="true" />
                                    <span>My sets</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="collaborating" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-user-group class="h-4 w-4 text-indigo-600" aria-hidden="true" />
                                    <span>Sets I&apos;m collaborating on</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="performing_on" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-musical-note class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                                    <span>Set&apos;s I&apos;m performing on</span>
                                </label>
                                <div class="mx-3 border-t border-slate-200"></div>

                                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="planned" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-clock class="h-4 w-4 text-sky-600" aria-hidden="true" />
                                    <span>Planned</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="performed" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                                    <span>Performed</span>
                                </label>
                                <div class="mx-3 border-t border-slate-200"></div>

                                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Sign ups</p>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="signups_open" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                                    <span>Sign ups open</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="signups_closed" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                                    <span>Sign ups closed</span>
                                </label>
                                <div class="mx-3 border-t border-slate-200"></div>

                                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Visibility and mode</p>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="hidden" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span>Hidden</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="free_for_all" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                                    <span>Free for all mode</span>
                                </label>
                                <div class="mx-3 border-t border-slate-200"></div>

                                <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Attachments</p>
                                <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" value="has_attachments" x-model="selectedAttributeFilters" @change="applyFilters()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <x-heroicon-m-paper-clip class="h-4 w-4 text-violet-600" aria-hidden="true" />
                                    <span>Has attachments</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
                        <div class="flex items-center gap-3">
                            <span x-show="summarySearchLoading" x-cloak class="text-xs text-slate-500">Searching set songs...</span>
                            <span x-text="`${visibleSetCount} of ${totalSetCount} sets`"></span>
                            <button type="button" @click="clearFilters()" x-show="hasActiveFilters()" x-cloak class="text-xs font-semibold uppercase tracking-wide text-amber-700 transition hover:text-amber-900">Reset filters</button>
                        </div>
                    </div>
                </section>
            @endif

            @if ($session->sets_count > 0)
                <div class="space-y-4" x-show="!loaded && !error" x-cloak>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-amber-400"></div>
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $loadingOneLiner }}</p>
                            </div>
                        </div>
                    </div>

                    @for ($i = 0; $i < min($session->sets_count, 3); $i++)
                        <div class="animate-pulse rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                            <div class="h-5 w-48 rounded bg-slate-200"></div>
                            <div class="mt-3 h-4 w-80 rounded bg-slate-200"></div>
                            <div class="mt-6 space-y-3">
                                <div class="h-10 rounded bg-slate-200"></div>
                                <div class="h-10 rounded bg-slate-200"></div>
                                <div class="h-10 rounded bg-slate-200"></div>
                            </div>
                        </div>
                    @endfor
                </div>
                <div x-ref="setsContainer" x-show="loaded" x-cloak x-bind:class="refreshing ? 'cursor-wait' : ''"></div>
                <div x-show="loaded && totalSetCount > 0 && visibleSetCount === 0" x-cloak class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    No sets match your current filters.
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No sets for this jam session yet.
                </div>
            @endif
        </div>
    </div>

</x-app-layout>
