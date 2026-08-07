<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0 flex-1">
        <h3 class="text-lg font-semibold text-slate-900" x-text="set.name"></h3>
        <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1.5" title="Set owner">
                <x-heroicon-m-user class="h-4 w-4 text-slate-500" aria-hidden="true" />
                <span class="sr-only">Set owner</span>
                <span>
                    <span x-text="set.owner?.name || 'Unknown'"></span><span class="md:hidden" x-show="(set.collaborators || []).length > 0" x-cloak> and collaborators</span><span class="hidden md:inline" x-show="(set.collaborators || []).length > 0" x-cloak x-text="', ' + (set.collaborators || []).slice(0, 2).map((collaborator) => collaborator.name).join(', ')"></span>
                </span>
            </span>

            <span class="inline-flex items-center" title="Planned">
                <x-heroicon-m-clock class="h-4 w-4 text-slate-400" aria-hidden="true" />
                <span class="sr-only">Planned</span>
            </span>

            <span class="inline-flex items-center" x-bind:title="`Sign ups ${set.signups_open ? 'open' : 'closed'}`">
                <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" x-show="set.signups_open" x-cloak aria-hidden="true" />
                <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" x-show="!set.signups_open" x-cloak aria-hidden="true" />
                <span class="sr-only" x-text="set.signups_open ? 'Sign ups open' : 'Sign ups closed'"></span>
            </span>

            <span class="inline-flex items-center" x-show="set.free_for_all" x-cloak title="Free for all mode">
                <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                <span class="sr-only">Free for all mode</span>
            </span>

            <span class="inline-flex items-center" x-bind:title="set.song_requests ? 'Song requests open' : 'Song requests closed'">
                <x-heroicon-m-musical-note class="h-4 w-4 text-emerald-700" x-show="set.song_requests" x-cloak aria-hidden="true" />
                <x-heroicon-m-musical-note class="h-4 w-4 text-slate-400" x-show="!set.song_requests" x-cloak aria-hidden="true" />
                <span class="sr-only" x-text="set.song_requests ? 'Song requests open' : 'Song requests closed'"></span>
            </span>

            <span class="inline-flex items-center" x-show="set.is_hidden" x-cloak title="Hidden set">
                <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                <span class="sr-only">Hidden set</span>
            </span>
        </div>
        <p class="mt-2 text-sm text-slate-700" x-show="set.description" x-text="set.description"></p>
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
                @click="openSetActionMenu = false; openSongRequestModal(set)"
                x-show="canRequestSongForSet(set)"
                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
            >
                <x-heroicon-m-hand-raised class="h-4 w-4 text-slate-500" aria-hidden="true" />
                <span>Request Song</span>
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
                <span>Edit Set</span>
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
        </div>
    </div>
</div>

<div class="mt-3 flex flex-wrap items-center gap-2" x-show="(set.collaborators || []).length > 0">
    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collaborators</span>
    <template x-for="collaborator in set.collaborators" :key="`set-${set.id}-collab-${collaborator.id}`">
        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700" x-text="collaborator.name"></span>
    </template>
</div>
