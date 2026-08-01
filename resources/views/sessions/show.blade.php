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
                                        <x-input-label for="session_description" value="Description (Markdown)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
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
            @refresh-session-sets.window="refresh()"
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
