<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Planned Sets</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    Plan sets in advance without assigning them to a specific jam.
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
            jamStandardSongs: @js($jamStandardSongs),
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
            slotUpdateUrlTemplate: @js(route('planned-sets.slots.update', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            slotClaimableUrlTemplate: @js(route('planned-sets.slots.claimable', ['set' => '__SET_ID__', 'slot' => '__SLOT_ID__'])),
            songRequestStoreUrlTemplate: @js(route('song-requests.store', '__SET_ID__')),
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
            <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5" x-show="totalSetCount() > 0">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <label class="sr-only" for="planned-set-search">Search sets</label>
                    <x-text-input id="planned-set-search" x-model="filterQuery" placeholder="Search by set, owner, or song" class="block w-full" />

                    <div class="relative" @click.outside="filterMenuOpen = false">
                        <button type="button" @click="filterMenuOpen = !filterMenuOpen" :aria-expanded="filterMenuOpen.toString()" aria-haspopup="true" class="inline-flex w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition hover:border-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-64">
                            <span x-text="selectedFilterLabel()"></span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                        </button>

                        <div x-show="filterMenuOpen" x-cloak x-transition.origin.top.right class="absolute right-0 z-30 mt-1 w-full min-w-72 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl sm:w-80">
                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Ownership</p>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="my_sets" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-user class="h-4 w-4 text-slate-600" aria-hidden="true" />
                                <span>My sets</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="collaborating" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-user-group class="h-4 w-4 text-indigo-600" aria-hidden="true" />
                                <span>Sets I&apos;m collaborating on</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="performing_on" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-musical-note class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                                <span>Set&apos;s I&apos;m performing on</span>
                            </label>
                            <div class="mx-3 border-t border-slate-200"></div>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="planned" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-clock class="h-4 w-4 text-sky-600" aria-hidden="true" />
                                <span>Planned</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="performed" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                                <span>Performed</span>
                            </label>
                            <div class="mx-3 border-t border-slate-200"></div>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Sign ups</p>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="signups_open" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                                <span>Sign ups open</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="signups_closed" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                                <span>Sign ups closed</span>
                            </label>
                            <div class="mx-3 border-t border-slate-200"></div>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Visibility and mode</p>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="hidden" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                <span>Hidden</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="free_for_all" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                                <span>Free for all mode</span>
                            </label>
                            <div class="mx-3 border-t border-slate-200"></div>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Attachments</p>
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" value="has_attachments" x-model="selectedAttributeFilters" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <x-heroicon-m-paper-clip class="h-4 w-4 text-violet-600" aria-hidden="true" />
                                <span>Has attachments</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
                    <div class="flex items-center gap-3">
                        <span x-text="`${visibleSetCount()} of ${totalSetCount()} sets`"></span>
                        <button type="button" @click="clearFilters()" x-show="hasActiveFilters()" x-cloak class="text-xs font-semibold uppercase tracking-wide text-amber-700 transition hover:text-amber-900">Reset filters</button>
                    </div>
                </div>
            </section>

            <div class="mt-4 space-y-4" x-show="totalSetCount() > 0">
                <template x-for="set in filteredSets()" :key="set.id">
                    @include('sets.planned.partials.set-card')
                </template>
            </div>

            <div x-show="totalSetCount() > 0 && visibleSetCount() === 0" x-cloak class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                No sets match your current filters.
            </div>

            <div x-show="totalSetCount() === 0" class="rounded-lg border border-dashed border-slate-300 bg-white/80 p-6 text-center text-sm text-slate-600">
                No planned sets yet. Create one to start collecting collaborators and availability.
            </div>
        </div>

        @include('sets.planned.partials.modal-editor')
        @include('sets.planned.partials.modal-add-song')
        @include('sets.planned.partials.modal-add-slot')
        @include('sets.planned.partials.modal-edit-song')
        @include('sets.planned.partials.modal-edit-slot')
        @include('sets.planned.partials.modal-request-song')
        @include('sets.planned.partials.modal-availability')
        @include('sets.planned.partials.modal-dropout-choice')
        @include('sets.planned.partials.modal-schedule')
    </div>
</x-app-layout>
