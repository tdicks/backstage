<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ liveDisplayModalOpen: false }" @keydown.escape.window="liveDisplayModalOpen = false">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Live Control</h2>
                <p class="text-sm text-slate-400">{{ $session->name }} &middot; {{ $session->date->format('l, F j, Y') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('sessions.show', $session) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-amber-400 hover:text-amber-300"
                >
                    <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" />
                    Back to Session
                </a>
                <button
                    type="button"
                    @click="$dispatch('open-who-is-here')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-amber-400 hover:text-amber-300"
                >
                    <x-heroicon-m-user-group class="h-4 w-4" aria-hidden="true" />
                    Who's Here
                </button>
                <button
                    type="button"
                    @click="liveDisplayModalOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-300 transition hover:border-amber-400 hover:text-amber-300"
                >
                    <x-heroicon-m-tv class="h-4 w-4" aria-hidden="true" />
                    Live Display
                </button>
            </div>
            <template x-teleport="body">
                <div x-show="liveDisplayModalOpen" x-cloak @keydown.escape.window="liveDisplayModalOpen = false">
                    <div class="fixed inset-0 z-40 bg-black/50" @click="liveDisplayModalOpen = false"></div>
                    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div @click.stop class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 text-center text-slate-900 shadow-2xl">
                            <h3 class="text-lg font-semibold">Live Display</h3>
                            <p class="mt-1 text-sm text-slate-600">Scan to open the live dashboard.</p>
                            <div class="mt-5 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <img
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=12&data={{ urlencode(route('sessions.live.short', $session->live_code)) }}"
                                    alt="QR code for {{ route('sessions.live.short', $session->live_code) }}"
                                    class="mx-auto h-64 w-64"
                                >
                            </div>
                            <a href="{{ route('sessions.live.short', $session->live_code) }}" target="_blank" class="mt-4 block break-all text-sm font-medium text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                                {{ route('sessions.live.short', $session->live_code) }}
                            </a>
                            <div class="mt-5 flex justify-end">
                                <x-modal-secondary-button type="button" @click="liveDisplayModalOpen = false">Close</x-modal-secondary-button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-5xl px-4 py-8 sm:px-6"
        x-data="liveJamManage({
            dataUrl: @js(route('sessions.live.data', $session)),
            claimManagerUrl: @js(route('sessions.live.manager.claim', $session)),
            releaseManagerUrl: @js(route('sessions.live.manager.release', $session)),
            updateUrl: @js(route('sessions.live.update', $session)),
            clearUrl: @js(route('sessions.live.clear', $session)),
            assignmentUsers: @js($assignmentUsers),
            slotUpdateUrlTemplate: @js(route('slots.update', ['slot' => '__slot__'])),
            currentUserId: @js($currentUserId),
            initialJamManager: @js($jamManager?->only(['id', 'name'])),
            csrfToken: @js(csrf_token()),
        })"
    >
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-900/85 p-4 text-slate-100 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="text-center lg:text-left">
                    <div class="flex items-center justify-center gap-2 text-sm text-slate-300 lg:justify-start">
                        <x-heroicon-m-microphone
                            class="h-4 w-4"
                            aria-hidden="true"
                            x-bind:class="jamManagerId ? (canManageLiveJam ? 'text-emerald-400' : 'text-amber-400') : 'text-slate-500'"
                        />
                        <span x-text="jamManagerName || 'No jam manager assigned yet'"></span>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-2 lg:items-end">
                    <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
                        <button
                            type="button"
                            x-show="!canManageLiveJam"
                            x-cloak
                            @click="claimManager()"
                            :disabled="managerBusy"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold text-slate-950 transition disabled:opacity-50"
                            :class="jamManagerId ? 'border border-amber-700 bg-amber-500 hover:bg-amber-400' : 'border border-emerald-700 bg-emerald-500 hover:bg-emerald-400'"
                        >
                            <x-heroicon-m-microphone class="h-4 w-4" aria-hidden="true" />
                            Manage
                        </button>
                    </div>
                    <div
                        x-show="canManageLiveJam"
                        x-cloak
                        class="flex flex-wrap items-center justify-center gap-2"
                    >
                        <button
                            type="button"
                            @click="releaseManager()"
                            :disabled="managerBusy"
                            class="inline-flex items-center gap-1.5 rounded-md border border-amber-800 bg-amber-950/60 px-2 py-2 text-sm font-medium text-amber-300 transition hover:border-amber-600 hover:bg-amber-900/70 disabled:opacity-50 sm:px-3"
                            title="Release Manager"
                            aria-label="Release Manager"
                        >
                            <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Release</span>
                        </button>
                        <button
                            type="button"
                            @click="clearState()"
                            :disabled="clearBusy"
                            class="inline-flex items-center gap-1.5 rounded-md border border-rose-800 bg-rose-950/60 px-2 py-2 text-sm font-medium text-rose-300 transition hover:border-rose-600 hover:bg-rose-900/70 disabled:opacity-50 sm:px-3"
                            title="Reset"
                            aria-label="Reset"
                        >
                            <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Reset</span>
                        </button>
                        <button
                            type="button"
                            @click="openAddSetModal()"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-800 px-2 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-700 sm:px-3"
                            title="Add Set"
                            aria-label="Add Set"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                            <span class="hidden sm:inline">Add Set</span>
                        </button>
                        <span
                            class="text-xs font-medium text-slate-400"
                            x-show="saveBusy || saveError"
                            x-cloak
                            :class="saveError ? 'text-rose-400' : 'text-slate-400'"
                            x-text="saveError || 'Saving…'"
                        ></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Set Modal --}}
        <template x-teleport="body">
            <div x-show="addSetModalOpen" x-cloak @keydown.escape.window="closeAddSetModal()">
                <div x-show="addSetModalOpen" x-transition.opacity.duration.150ms class="fixed inset-0 z-40 bg-black/40" @click="closeAddSetModal()"></div>
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="addSetModalOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" @click.stop class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                        <h3 class="text-lg font-semibold text-slate-900">Add Live Set</h3>
                        <p class="mt-1 text-sm text-slate-600">Add a one-off set for tonight; it will disappear after the jam.</p>
                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label value="Set Name" />
                                <x-text-input type="text" x-model="addSetForm.name" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Organiser (optional)" />
                                <x-text-input type="text" x-model="addSetForm.organiser" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Participants (optional)" />
                                <x-text-input type="text" x-model="addSetForm.participants" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Details (optional)" />
                                <x-textarea-input x-model="addSetForm.details" rows="5" class="mt-1 w-full" />
                            </div>
                            <div class="flex justify-end gap-3">
                                <x-modal-secondary-button type="button" @click="closeAddSetModal()">Cancel</x-modal-secondary-button>
                                <x-modal-primary-button type="button" @click="saveNewSet()">Save Set</x-modal-primary-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Edit Set Modal --}}
        <template x-teleport="body">
            <div x-show="editSetModalOpen" x-cloak @keydown.escape.window="closeEditSetModal()">
                <div x-show="editSetModalOpen" x-transition.opacity.duration.150ms class="fixed inset-0 z-40 bg-black/40" @click="closeEditSetModal()"></div>
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="editSetModalOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" @click.stop class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                        <h3 class="text-lg font-semibold">Edit Live Set</h3>
                        <p class="mt-1 text-sm text-slate-600">This one-off set is only for tonight and will not persist after the jam.</p>
                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label value="Set Name" />
                                <x-text-input type="text" x-model="editSetForm.name" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Organiser (optional)" />
                                <x-text-input type="text" x-model="editSetForm.organiser" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Participants (optional)" />
                                <x-text-input type="text" x-model="editSetForm.participants" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Details (optional)" />
                                <x-textarea-input x-model="editSetForm.details" rows="5" class="mt-1 w-full" />
                            </div>
                            <div class="flex justify-end gap-3">
                                <x-modal-secondary-button type="button" @click="closeEditSetModal()">Cancel</x-modal-secondary-button>
                                <x-modal-primary-button type="button" @click="saveEditSet()">Save Changes</x-modal-primary-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <x-sessions.slot-edit-modal :slot-options="$slotOptions" :users="$assignmentUsers" live-dashboard />

        {{-- Loading state --}}
        <div x-show="loading" x-cloak class="flex items-center justify-center py-16 text-slate-400">
            <svg class="mr-2 h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading sets…
        </div>

        {{-- Set cards --}}
        <div x-show="!loading" class="space-y-4" x-ref="setsContainer" @dragover="onSetDragOver($event)" @drop="onSetDrop($event)">
            <template x-for="set in orderedSets" :key="set.id">
                <div
                    class="overflow-hidden rounded-xl border shadow-sm"
                    :class="setCardClasses(set)"
                    data-live-set-card
                    :data-live-set-id="set.id"
                    :data-live-set-status="set.status"
                    x-init="observeSetCard($el, set)"
                    x-bind:draggable="canDragSet(set) ? 'true' : 'false'"
                    @dragstart.self="onSetDragStart($event, set)"
                    @dragover="onSetDragOver($event, set)"
                    @drop="onSetDrop($event)"
                    @dragend.self="onSetDragEnd()"
                >
                    <div class="flex min-h-40">
                        {{-- Main content --}}
                        <div class="min-w-0 flex-1 p-5 text-slate-100">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <button
                                        type="button"
                                        x-show="set.songs.length > 0"
                                        @click="toggleSetSongs(set)"
                                        x-bind:aria-expanded="(!set.songsCollapsed).toString()"
                                        x-bind:title="set.songsCollapsed ? 'Show songs' : 'Hide songs'"
                                        class="flex min-w-0 items-center gap-2 text-left text-xl font-semibold text-slate-100 transition hover:text-white focus:outline-none"
                                    >
                                        <span class="min-w-0 truncate" x-text="set.name"></span>
                                        <template x-if="set.feature_set">
                                            <x-feature-set-icon />
                                        </template>
                                        <x-heroicon-m-chevron-down class="h-4 w-4 shrink-0 transition-transform" x-bind:class="set.songsCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
                                    </button>
                                    <h3 x-show="set.songs.length === 0" class="flex items-center gap-2 text-xl font-semibold text-slate-100">
                                        <span class="min-w-0 truncate" x-text="set.name"></span>
                                        <template x-if="set.feature_set">
                                            <x-feature-set-icon />
                                        </template>
                                    </h3>
                                    <p class="mt-1 truncate text-sm text-slate-400" x-show="set.owner" x-text="`by ${set.owner}`"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold" :class="statusBadgeClasses(set)" x-text="statusLabel(set.status)"></span>
                                </div>
                            </div>

                            {{-- Live set details --}}
                            <div x-show="set.isLiveSet && (set.participants || set.details)" x-cloak class="mt-4 rounded-lg border border-slate-700 bg-slate-950/40 px-3 py-2 text-sm text-slate-300">
                                <div x-show="set.participants" class="flex gap-2">
                                    <span class="shrink-0 font-semibold text-slate-400">Participants</span>
                                    <span x-text="set.participants"></span>
                                </div>
                                <div x-show="set.details" class="mt-1 flex gap-2">
                                    <span class="shrink-0 font-semibold text-slate-400">Details</span>
                                    <span class="whitespace-pre-line" x-text="set.details"></span>
                                </div>
                            </div>

                            {{-- Duration & health bar --}}
                            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                <span x-show="set.duration_seconds > 0" x-cloak class="inline-flex items-center rounded-full border border-slate-700 bg-slate-900 px-2 py-1">
                                    ~<span x-text="formatDuration(set.duration_seconds)"></span>
                                </span>
                                <span x-show="set.total_slots > 0" class="inline-flex items-center rounded-full border border-slate-700 bg-slate-900 px-2 py-1">
                                    <span x-text="set.filled_slots"></span>/<span x-text="set.total_slots"></span> slots filled
                                </span>
                                <span x-show="set.isLiveSet" x-cloak class="inline-flex items-center rounded-full border border-sky-800 bg-sky-950/60 px-2 py-1 text-sky-300">Live set</span>
                                <span x-show="!set.isLiveSet && set.total_slots === 0" x-cloak class="inline-flex items-center rounded-full border border-slate-700 bg-slate-900 px-2 py-1 italic">No slots defined</span>
                                <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-950/40 px-2 py-1 text-slate-500">#<span x-text="set.id"></span></span>
                                <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-950/40 px-2 py-1 text-slate-500" x-text="`[${set.status}:${set.order}]`"></span>
                            </div>

                            {{-- Health bar --}}
                            <div x-show="set.total_slots > 0 && set.status !== 'finished' && set.status !== 'postponed'" class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-950/60">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :style="`width: ${set.health}%; background: ${healthColor(set.health)};`"
                                ></div>
                            </div>

                            {{-- Songs list --}}
                            <ul x-show="set.songs.length > 0 && !set.songsCollapsed" x-transition.opacity.duration.150ms class="mt-4 divide-y divide-slate-700 overflow-hidden rounded-lg border border-slate-700 bg-slate-950/40">
                                <template x-for="song in set.songs" :key="song.id">
                                    <li class="px-3 py-2 text-sm">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="font-semibold" :class="song.completed ? 'text-slate-500 line-through' : 'text-slate-100'" x-text="`${song.artist} – ${song.title}`"></span>
                                            <span x-show="song.duration" x-cloak class="text-slate-500" x-text="song.duration ? formatDuration(song.duration) : ''"></span>
                                            <button
                                                type="button"
                                                x-show="canManageLiveJam"
                                                x-cloak
                                                x-transition:enter="transition ease-out duration-150"
                                                x-transition:enter-start="opacity-0 translate-x-2"
                                                x-transition:enter-end="opacity-100 translate-x-0"
                                                x-transition:leave="transition ease-in duration-100"
                                                x-transition:leave-start="opacity-100 translate-x-0"
                                                x-transition:leave-end="opacity-0 translate-x-2"
                                                @click="toggleSongCompleted(song)"
                                                class="ml-auto inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md border transition"
                                                :class="song.completed ? 'border-emerald-700 bg-emerald-950/70 text-emerald-300' : 'border-slate-700 bg-slate-900 text-slate-400 hover:border-emerald-700 hover:text-emerald-300'"
                                                :title="song.completed ? 'Mark song incomplete' : 'Mark song completed'"
                                                :aria-label="song.completed ? 'Mark song incomplete' : 'Mark song completed'"
                                            >
                                                <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                            </button>
                                        </div>
                                        <span class="mt-1.5 flex flex-wrap gap-1">
                                            <template x-for="slot in song.slots" :key="slot.id">
                                                <button
                                                    type="button"
                                                    @click="openEditSlotModal(set, song, slot)"
                                                    x-bind:disabled="!canManageLiveJam || set.status === 'finished' || song.completed"
                                                    class="inline-block rounded-md px-2 py-1 text-left text-xs transition disabled:cursor-default"
                                                    :class="{
                                                        'bg-emerald-900/80 text-emerald-50 ring-1 ring-emerald-400/80': slot.filled && slot.checked_in,
                                                        'bg-emerald-950/60 text-emerald-300 ring-1 ring-emerald-800': slot.filled && !slot.checked_in,
                                                        'bg-slate-800 text-slate-500': !slot.filled,
                                                        'opacity-50': song.completed,
                                                        'hover:ring-2 hover:ring-amber-400': canManageLiveJam && set.status !== 'finished' && !song.completed
                                                    }"
                                                    :title="canManageLiveJam && set.status !== 'finished' && !song.completed ? 'Edit assignment' : (slot.checked_in ? 'Checked in' : 'Not checked in')"
                                                >
                                                    <span x-text="slot.user_name ? `${slot.name}: ${slot.user_name}` : slot.name"></span>
                                                    <x-checked-in-dot x-show="slot.checked_in" x-cloak class="ml-1" />
                                                </button>
                                            </template>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Action buttons --}}
                        <div
                            x-show="canManageLiveJam"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-x-4"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 translate-x-4"
                            class="flex w-14 shrink-0 flex-col items-center gap-1.5 border-l border-slate-700 bg-slate-950/35 px-2 py-3"
                        >
                            {{-- Finish --}}
                            <button
                                type="button"
                                x-show="set.status === 'playing_now'"
                                @click="finishSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-sky-800 bg-sky-950/60 text-sky-300 transition hover:border-sky-600 hover:bg-sky-900/70 active:scale-95"
                                title="Finish"
                            >
                                <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Play --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now' && set.status !== 'finished'"
                                @click="startSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-emerald-800 bg-emerald-950/60 text-emerald-300 transition hover:border-emerald-600 hover:bg-emerald-900/70 active:scale-95"
                                title="Play"
                            >
                                <x-heroicon-m-play class="h-4 w-4" aria-hidden="true" />
                            </button>

                            <div class="my-1 h-px w-8 bg-slate-700/80"></div>

                            {{-- Public Song List --}}
                            <button
                                type="button"
                                x-show="set.songs.length > 0 && set.status !== 'finished' && set.status !== 'postponed'"
                                @click="togglePublicSetSongs(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border transition active:scale-95"
                                :class="set.songs_collapsed
                                    ? 'border-violet-600 bg-violet-950/70 text-violet-300 hover:border-violet-500 hover:bg-violet-900/70 hover:text-violet-100'
                                    : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100'"
                                title="Condensed view"
                                aria-label="Condensed view"
                            >
                                <x-heroicon-m-arrows-pointing-in class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Push Down --}}
                            <button
                                type="button"
                                x-show="set.status === 'playing_now' || set.status === 'coming_up'"
                                @click="pushDown(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100 active:scale-95"
                                title="Push Down"
                            >
                                <x-heroicon-m-arrow-down class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Coming Up --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now' && set.status !== 'coming_up' && set.status !== 'finished' && set.status !== 'postponed'"
                                @click="markComingUp(set)"
                                :disabled="comingUpSets.length >= 2"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-amber-800 bg-amber-950/60 text-amber-300 transition hover:border-amber-600 hover:bg-amber-900/70 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                                title="Coming Up"
                            >
                                <x-heroicon-m-arrow-up class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Postpone --}}
                            <button
                                type="button"
                                x-show="set.status !== 'playing_now' && set.status !== 'finished' && set.status !== 'postponed'"
                                @click="postponeSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-rose-800 bg-rose-950/60 text-rose-300 transition hover:border-rose-600 hover:bg-rose-900/70 active:scale-95"
                                title="Postpone"
                            >
                                <x-heroicon-m-no-symbol class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Edit (Live Sets Only) --}}
                            <button
                                type="button"
                                x-show="set.isLiveSet"
                                @click="openEditSetModal(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-sky-600 hover:bg-sky-950/60 hover:text-sky-300 active:scale-95"
                                title="Edit"
                            >
                                <x-heroicon-m-pencil class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Delete (Live Sets Only) --}}
                            <button
                                type="button"
                                x-show="set.isLiveSet"
                                @click="deleteSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-rose-600 hover:bg-rose-950/60 hover:text-rose-300 active:scale-95"
                                title="Delete"
                            >
                                <x-heroicon-m-trash class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Restore --}}
                            <button
                                type="button"
                                x-show="set.status === 'finished' || set.status === 'postponed'"
                                @click="restoreSet(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100 active:scale-95"
                                title="Restore"
                            >
                                <x-heroicon-m-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />
                            </button>

                            <div x-show="canMoveUp(set) || canMoveDown(set)" class="my-1 h-px w-8 bg-slate-700/80"></div>

                            {{-- Up --}}
                            <button
                                type="button"
                                x-show="canMoveUp(set)"
                                @click="moveUp(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100 active:scale-95"
                                title="Up"
                            >
                                <x-heroicon-m-chevron-up class="h-4 w-4" aria-hidden="true" />
                            </button>

                            {{-- Down --}}
                            <button
                                type="button"
                                x-show="canMoveDown(set)"
                                @click="moveDown(set)"
                                class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-slate-500 hover:bg-slate-800 hover:text-slate-100 active:scale-95"
                                title="Down"
                            >
                                <x-heroicon-m-chevron-down class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="orderedSets.length === 0 && !loading" x-cloak class="rounded-xl border border-slate-700 bg-slate-900/85 px-6 py-12 text-center text-slate-400 shadow-sm">
                No sets found for this session.
            </div>
        </div>
    </div>

    @can('update', $session)
        <x-sessions.who-is-here-modal :session="$session" />
    @endcan

    @push('scripts')
    <script>
    function liveJamManage(config) {
        return {
            sets: [],
            originalSets: [],
            loading: true,
            saveBusy: false,
            saveSuccess: false,
            saveError: '',
            clearBusy: false,
            managerBusy: false,
            lastUpdated: '',
            lastCacheUpdate: null,
            jamManagerId: config.initialJamManager?.id ?? null,
            jamManagerName: config.initialJamManager?.name ?? '',
            currentUserId: config.currentUserId,
            seenSetIds: new Set(),
            collapsedSetSongIds: new Set(),
            seenObserver: null,
            seenDwellTimers: new Map(),
            pollTimer: null,
            saveTimer: null,
            saveQueued: false,
            addSetModalOpen: false,
            editSetModalOpen: false,
            openEditSlot: false,
            assignmentSaveBusy: false,
            assignmentSaveError: '',
            assignmentConflictMessage: '',
            assignmentConflictPending: false,
            assignmentConflictCooldown: false,
            assignmentConflictTimer: null,
            editingAssignment: null,
            assignmentUsers: config.assignmentUsers ?? [],
            assignmentForm: {
                slotKey: '',
            },
            editAssignedUserId: '',
            editAssignedUserQuery: '',
            initialEditAssignedUserName: '',
            initialEditManualPerformerName: '',
            showEditUserSuggestions: false,
            editingSetId: null,
            dragSetId: null,
            draggingSetId: null,
            setDropPlaceholderEl: null,
            dragStartRects: null,
            addSetForm: {
                organiser: '',
                name: '',
                participants: '',
                details: '',
            },
            editSetForm: {
                organiser: '',
                name: '',
                participants: '',
                details: '',
            },

            get hasChanges() {
                return JSON.stringify(this.sets.map(s => this.stateSnapshot(s))) !== JSON.stringify(this.originalSets);
            },

            get canManageLiveJam() {
                return this.jamManagerId !== null && String(this.jamManagerId) === String(this.currentUserId);
            },

            seenSetIdsKey() {
                return `live-jam-seen-sets:${config.dataUrl}`;
            },

            collapsedSetSongsKey() {
                return `live-jam-collapsed-set-songs:${config.dataUrl}`;
            },

            persistSeenSetIds() {
                localStorage.setItem(this.seenSetIdsKey(), JSON.stringify([...this.seenSetIds]));
            },

            markSetSeen(setOrId) {
                const setId = typeof setOrId === 'object' ? setOrId.id : setOrId;

                this.seenSetIds.add(String(setId));
                this.persistSeenSetIds();
            },

            persistCollapsedSetSongs() {
                localStorage.setItem(this.collapsedSetSongsKey(), JSON.stringify([...this.collapsedSetSongIds]));
            },

            toggleSetSongs(set) {
                set.songsCollapsed = !set.songsCollapsed;

                if (set.songsCollapsed) {
                    this.collapsedSetSongIds.add(String(set.id));
                } else {
                    this.collapsedSetSongIds.delete(String(set.id));
                }

                this.persistCollapsedSetSongs();
            },

            observeSetCard(element, set) {
                if (!this.seenObserver || !set.highlighted) {
                    return;
                }

                this.seenObserver.observe(element);
            },

            clearSeenDwellTimer(setId) {
                const timer = this.seenDwellTimers.get(String(setId));
                if (timer) {
                    clearTimeout(timer);
                    this.seenDwellTimers.delete(String(setId));
                }
            },

            handleSetVisibility(entries) {
                entries.forEach((entry) => {
                    const setId = String(entry.target.dataset.liveSetId);
                    const set = this.sets.find(item => String(item.id) === setId);

                    if (!entry.isIntersecting || entry.intersectionRatio < 0.2 || !set?.highlighted) {
                        this.clearSeenDwellTimer(setId);
                        return;
                    }

                    if (this.seenDwellTimers.has(setId)) {
                        return;
                    }

                    this.seenDwellTimers.set(setId, setTimeout(() => {
                        this.fadeSetHighlight(set);
                        this.seenObserver?.unobserve(entry.target);
                    }, 1200));
                });
            },

            applyJamManager(payload) {
                this.jamManagerId = payload?.id ?? null;
                this.jamManagerName = payload?.name ?? '';
            },

            normalizeServerSet(serverSet) {
                if (serverSet.isLiveSet && serverSet.liveSetData) {
                    return {
                        id: serverSet.set_id ?? serverSet.id,
                        name: serverSet.liveSetData.name,
                        owner: serverSet.liveSetData.owner,
                        feature_set: serverSet.feature_set ?? false,
                        participants: serverSet.liveSetData.participants,
                        details: serverSet.liveSetData.details,
                        created_at: serverSet.liveSetData.created_at ?? serverSet.created_at ?? null,
                        isLiveSet: true,
                        status: serverSet.status,
                        order: serverSet.order,
                        songs: [],
                        health: 0,
                        total_slots: 0,
                        filled_slots: 0,
                        duration_seconds: 0,
                    };
                }

                return {
                    ...serverSet,
                    created_at: serverSet.created_at ?? null,
                };
            },

            clearSetHighlight(setOrId) {
                const set = typeof setOrId === 'object'
                    ? setOrId
                    : this.sets.find(item => String(item.id) === String(setOrId));
                if (!set) {
                    return;
                }

                set.highlighted = false;
                this.markSetSeen(set);
            },

            fadeSetHighlight(setOrId) {
                const set = typeof setOrId === 'object'
                    ? setOrId
                    : this.sets.find(item => String(item.id) === String(setOrId));
                if (!set) {
                    return;
                }

                set.highlighted = false;
                set.highlightFading = true;
                this.markSetSeen(set);

                setTimeout(() => {
                    set.highlightFading = false;
                }, 450);
            },

            applyHighlightIfNeeded(set) {
                set.highlighted = !this.seenSetIds.has(String(set.id));
                set.highlightFading = false;
                set.songsCollapsed = this.collapsedSetSongIds.has(String(set.id));

                return set;
            },

            stateSnapshot(set) {
                const snapshot = {
                    id: set.id,
                    status: set.status,
                    order: set.order,
                    songsCollapsed: Boolean(set.songs_collapsed),
                };

                if (!set.isLiveSet) {
                    snapshot.completedSongIds = set.songs
                        .filter(song => song.completed)
                        .map(song => song.id)
                        .sort((firstId, secondId) => Number(firstId) - Number(secondId));
                }

                if (set.isLiveSet) {
                    snapshot.name = set.name || '';
                    snapshot.owner = set.owner || '';
                    snapshot.participants = set.participants || '';
                    snapshot.details = set.details || '';
                }

                return snapshot;
            },

            init() {
                try {
                    this.seenSetIds = new Set(JSON.parse(localStorage.getItem(this.seenSetIdsKey()) || '[]'));
                } catch (error) {
                    this.seenSetIds = new Set();
                }
                try {
                    this.collapsedSetSongIds = new Set(JSON.parse(localStorage.getItem(this.collapsedSetSongsKey()) || '[]'));
                } catch (error) {
                    this.collapsedSetSongIds = new Set();
                }
                this.seenObserver = new IntersectionObserver(
                    (entries) => this.handleSetVisibility(entries),
                    { threshold: 0.2 },
                );
                this.fetchData();
                this.pollTimer = setInterval(() => this.fetchData(), 5000);
            },

            async fetchData() {
                try {
                    const hadLocalChanges = this.originalSets.length > 0 && this.hasChanges;
                    const resp = await fetch(config.dataUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!resp.ok) { return; }
                    const payload = await resp.json();
                    const serverSets = (payload.sets || []).map(serverSet => this.normalizeServerSet(serverSet));
                    const isInitialLoad = this.sets.length === 0;

                    // On initial load, preserve unseen cards so they can be highlighted.
                    // On subsequent polls, refresh server-owned set details while retaining unsaved manager edits.
                    if (isInitialLoad) {
                        this.sets = serverSets.map(serverSet => this.applyHighlightIfNeeded({ ...serverSet }));
                    } else {
                        const localSetsById = new Map(this.sets.map(set => [String(set.id), set]));
                        const serverSetIds = new Set(serverSets.map(set => String(set.id)));
                        const newSets = serverSets.filter(serverSet => !localSetsById.has(String(serverSet.id)));

                        if (newSets.length > 0) {
                            // Start from -1 so the first pending set becomes order 0, then append any new pending sets after
                            // the current pending stack. If there are no pending sets yet, the first new one still lands at 0.
                            const nextPendingOrder = this.sets
                                .filter(set => set.status === 'pending')
                                .reduce((max, set) => Math.max(max, Number(set.order) || 0), -1) + 1;

                            newSets.forEach((set, index) => {
                                if (set.status === 'pending') {
                                    set.order = nextPendingOrder + index;
                                }
                            });
                        }

                        const refreshedSets = serverSets.map((serverSet) => {
                            const localSet = localSetsById.get(String(serverSet.id));

                            if (!localSet) {
                                return this.applyHighlightIfNeeded({ ...serverSet });
                            }

                            return {
                                ...serverSet,
                                ...(hadLocalChanges ? {
                                    status: localSet.status,
                                    order: localSet.order,
                                    songs_collapsed: localSet.songs_collapsed,
                                } : {}),
                                ...(hadLocalChanges && localSet.isLiveSet ? {
                                    name: localSet.name,
                                    owner: localSet.owner,
                                    participants: localSet.participants,
                                    details: localSet.details,
                                } : {}),
                                ...(hadLocalChanges && !localSet.isLiveSet ? {
                                    songs: serverSet.songs.map(song => ({
                                        ...song,
                                        completed: localSet.songs.find(localSong => String(localSong.id) === String(song.id))?.completed ?? song.completed,
                                    })),
                                } : {}),
                                highlighted: !this.seenSetIds.has(String(serverSet.id)),
                                highlightFading: localSet.highlightFading ?? false,
                                songsCollapsed: localSet.songsCollapsed ?? this.collapsedSetSongIds.has(String(serverSet.id)),
                            };
                        });
                        const localOnlySets = this.sets.filter(set => !serverSetIds.has(String(set.id)));

                        this.sets = [...refreshedSets, ...localOnlySets];
                    }

                    if (!hadLocalChanges) {
                        this.originalSets = this.sets.map(s => this.stateSnapshot(s));
                    }

                    this.lastCacheUpdate = payload.updated_at || this.lastCacheUpdate;
                    if (payload.updated_at) {
                        this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();
                    }

                    this.applyJamManager(payload.jam_manager);
                } catch (e) {
                    // Silently fail polling
                } finally {
                    this.loading = false;
                }
            },

            get orderedSets() {
                // Sort within each status group, then display in group order
                const playingNow = this.sets.filter(s => s.status === 'playing_now').sort((a, b) => a.order - b.order);
                const comingUp = this.comingUpSets;
                const pending = this.sets.filter(s => s.status === 'pending').sort((a, b) => a.order - b.order);
                const postponed = this.sets.filter(s => s.status === 'postponed').sort((a, b) => a.order - b.order);
                const finished = this.sets.filter(s => s.status === 'finished').sort((a, b) => a.order - b.order);

                return [...playingNow, ...comingUp, ...pending, ...postponed, ...finished];
            },

            get comingUpSets() {
                return this.sets.filter(s => s.status === 'coming_up').sort((a, b) => a.order - b.order);
            },

            openAddSetModal() {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.addSetForm = { organiser: '', name: '', participants: '', details: '' };
                this.addSetModalOpen = true;
            },

            closeAddSetModal() {
                this.addSetModalOpen = false;
                this.resetAddSetForm();
            },

            saveNewSet() {
                if (!this.canManageLiveJam) {
                    return;
                }

                if (!this.addSetForm.name.trim()) {
                    alert('Please enter a set name');
                    return;
                }

                // Generate unique ID for live set (using timestamp + random)
                const liveSetId = 'live_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                // Get max order of pending sets
                const pendingOrders = this.sets
                    .filter(s => s.status === 'pending')
                    .map(s => s.order);
                const newOrder = pendingOrders.length > 0 ? Math.max(...pendingOrders) + 1 : 0;

                // Create live set object
                const newSet = {
                    id: liveSetId,
                    name: this.addSetForm.name.trim(),
                    owner: this.addSetForm.organiser.trim(),
                    participants: this.addSetForm.participants.trim(),
                    details: this.addSetForm.details.trim(),
                    created_at: new Date().toISOString(),
                    isLiveSet: true,
                    status: 'pending',
                    order: newOrder,
                    songs: [],
                    health: 0,
                    total_slots: 0,
                    filled_slots: 0,
                    duration_seconds: 0,
                    highlighted: true,
                };

                this.sets.push(newSet);
                this.closeAddSetModal();
                this.scheduleSave();
            },

            resetAddSetForm() {
                this.addSetForm = { organiser: '', name: '', participants: '', details: '' };
            },

            openEditSetModal(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.editingSetId = set.id;
                this.editSetForm = {
                    organiser: set.owner || '',
                    name: set.name || '',
                    participants: set.participants || '',
                    details: set.details || '',
                };
                this.editSetModalOpen = true;
            },

            closeEditSetModal() {
                this.editSetModalOpen = false;
                this.editingSetId = null;
                this.resetEditSetForm();
            },

            filteredEditUsers() {
                const query = this.editAssignedUserQuery.trim().toLowerCase();

                if (query === '') {
                    return this.assignmentUsers.slice(0, 8);
                }

                return this.assignmentUsers
                    .filter((user) => user.name.toLowerCase().includes(query))
                    .slice(0, 8);
            },

            updateEditUserQuery() {
                this.editAssignedUserId = '';
                this.showEditUserSuggestions = true;
                this.resetAssignmentConflict();
            },

            selectEditUser(user) {
                this.editAssignedUserId = String(user.id);
                this.editAssignedUserQuery = user.name;
                this.showEditUserSuggestions = false;
                this.resetAssignmentConflict();
            },

            resetAssignmentConflict() {
                this.assignmentConflictMessage = '';
                this.assignmentConflictPending = false;
                this.assignmentConflictCooldown = false;
                clearTimeout(this.assignmentConflictTimer);
                this.assignmentConflictTimer = null;
            },

            showAssignmentConflict(message) {
                this.assignmentConflictMessage = `${message} Click Save to move the assignment.`;
                this.assignmentConflictPending = true;
                this.assignmentConflictCooldown = true;
                clearTimeout(this.assignmentConflictTimer);
                this.assignmentConflictTimer = setTimeout(() => {
                    this.assignmentConflictCooldown = false;
                    this.assignmentConflictTimer = null;
                }, 2500);
            },

            shouldShowAssigneeWarning() {
                const query = this.editAssignedUserQuery.trim();

                return query !== '' && query !== this.initialEditAssignedUserName && query !== this.initialEditManualPerformerName;
            },

            resolveEditedSlotAssignment() {
                const selectedUser = this.assignmentUsers.find((user) => String(user.id) === String(this.editAssignedUserId));

                if (selectedUser) {
                    return { user_id: String(selectedUser.id), manual_performer_name: '' };
                }

                return { user_id: '', manual_performer_name: this.editAssignedUserQuery.trim() };
            },

            openEditSlotModal(set, song, slot) {
                if (!this.canManageLiveJam || set.isLiveSet) {
                    return;
                }

                this.editingAssignment = { set, song, slot };
                this.assignmentForm = { slotKey: slot.slot_key };
                this.editAssignedUserId = slot.user_id ? String(slot.user_id) : '';
                this.initialEditAssignedUserName = slot.user_id ? slot.user_name || '' : '';
                this.initialEditManualPerformerName = slot.manual_performer_name || '';
                this.editAssignedUserQuery = this.initialEditAssignedUserName || this.initialEditManualPerformerName;
                this.showEditUserSuggestions = false;
                this.assignmentSaveError = '';
                this.resetAssignmentConflict();
                this.openEditSlot = true;
            },

            closeEditSlotModal() {
                this.openEditSlot = false;
                this.assignmentSaveBusy = false;
                this.assignmentSaveError = '';
                this.resetAssignmentConflict();
                this.editingAssignment = null;
                this.assignmentForm = { slotKey: '' };
                this.editAssignedUserId = '';
                this.editAssignedUserQuery = '';
                this.initialEditAssignedUserName = '';
                this.initialEditManualPerformerName = '';
                this.showEditUserSuggestions = false;
            },

            async submitLiveSlotEdit() {
                if (!this.editingAssignment || this.assignmentSaveBusy) {
                    return;
                }

                this.assignmentSaveBusy = true;
                this.assignmentSaveError = '';

                try {
                    const { slot } = this.editingAssignment;
                    const assignment = this.resolveEditedSlotAssignment();
                    let response = await fetch(config.slotUpdateUrlTemplate.replace('__slot__', slot.id), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            name: this.assignmentForm.slotKey,
                            user_id: assignment.user_id || null,
                            manual_performer_name: assignment.manual_performer_name,
                            replace_conflicting_assignment: this.assignmentConflictPending,
                        }),
                    });

                    if (response.status === 409) {
                        const conflict = await response.json();
                        this.showAssignmentConflict(conflict.message);
                        return;
                    }

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || 'Could not save this assignment.');
                    }

                    const payload = await response.json();
                    const updatedSlot = payload.slot;
                    slot.slot_key = updatedSlot.name;
                    slot.name = updatedSlot.label;
                    slot.user_id = updatedSlot.user_id;
                    slot.user_name = updatedSlot.is_open ? null : updatedSlot.user_name;
                    slot.manual_performer_name = updatedSlot.manual_performer_name;
                    slot.filled = Boolean(updatedSlot.user_id || updatedSlot.manual_performer_name);
                    this.closeEditSlotModal();
                    await this.fetchData();
                } catch (error) {
                    this.assignmentSaveError = error.message || 'Could not save this assignment.';
                } finally {
                    this.assignmentSaveBusy = false;
                }
            },

            async clearLiveSlot() {
                if (!this.editingAssignment || this.assignmentSaveBusy) {
                    return;
                }

                this.editAssignedUserId = '';
                this.editAssignedUserQuery = '';
                await this.submitLiveSlotEdit();
            },

            saveEditSet() {
                if (!this.canManageLiveJam) {
                    return;
                }

                const set = this.sets.find(s => s.id === this.editingSetId);
                if (!set) { return; }

                set.owner = this.editSetForm.organiser.trim();
                set.name = this.editSetForm.name.trim();
                set.participants = this.editSetForm.participants.trim();
                set.details = this.editSetForm.details.trim();

                this.closeEditSetModal();
                this.scheduleSave();
            },

            toggleSongCompleted(song) {
                if (!this.canManageLiveJam) {
                    return;
                }

                song.completed = !song.completed;
                this.scheduleSave();
            },

            togglePublicSetSongs(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                set.songs_collapsed = !set.songs_collapsed;
                this.scheduleSave();
            },

            resetEditSetForm() {
                this.editSetForm = { organiser: '', name: '', participants: '', details: '' };
            },

            captureSetPositions() {
                return new Map(Array.from(this.$refs.setsContainer?.querySelectorAll('[data-live-set-card]') || [])
                    .map(el => [el.dataset.liveSetId, el.getBoundingClientRect()]));
            },

            animateSetMovement(previousRects) {
                if (!previousRects) { return; }

                const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 140 : 320;

                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        const movedElements = Array.from(this.$refs.setsContainer?.querySelectorAll('[data-live-set-card]') || [])
                            .map(el => {
                                const previousRect = previousRects.get(el.dataset.liveSetId);
                                if (!previousRect) { return null; }

                                const currentRect = el.getBoundingClientRect();
                                const deltaX = previousRect.left - currentRect.left;
                                const deltaY = previousRect.top - currentRect.top;

                                if (Math.abs(deltaX) < 1 && Math.abs(deltaY) < 1) { return null; }

                                return { el, deltaX, deltaY };
                            })
                            .filter(Boolean);

                        movedElements.forEach(({ el, deltaX, deltaY }) => {
                            el.style.transition = 'none';
                            el.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
                        });

                        this.$refs.setsContainer?.offsetHeight;

                        requestAnimationFrame(() => {
                            movedElements.forEach(({ el }) => {
                                el.style.transition = `transform ${duration}ms cubic-bezier(0.2, 0, 0, 1)`;
                                el.style.transform = 'translate(0, 0)';
                                el.addEventListener('transitionend', () => {
                                    el.style.transition = '';
                                    el.style.transform = '';
                                }, { once: true });
                            });
                        });
                    });
                });
            },

            replaceSetOrders(orderById) {
                this.sets = this.sets.map(set => orderById.has(String(set.id))
                    ? { ...set, order: orderById.get(String(set.id)) }
                    : set);
            },

            applyOrderedIdsForStatus(status, orderedIds) {
                const orderById = new Map(orderedIds.map((id, index) => [String(id), index]));
                this.replaceSetOrders(orderById);
            },

            normalizeSetOrders() {
                const orderById = new Map();

                ['playing_now', 'coming_up', 'pending', 'postponed', 'finished'].forEach(status => {
                    this.sets
                        .filter(set => set.status === status)
                        .sort((firstSet, secondSet) => Number(firstSet.order) - Number(secondSet.order) || String(firstSet.id).localeCompare(String(secondSet.id)))
                        .forEach((set, index) => orderById.set(String(set.id), index));
                });

                this.replaceSetOrders(orderById);
            },

            refreshSetOrderView() {
                this.sets = [...this.sets];
            },

            animateOrderChange(previousRects) {
                this.refreshSetOrderView();
                this.animateSetMovement(previousRects);
            },

            nextOrderForStatus(status, excludeId = null) {
                const orders = this.sets
                    .filter(s => s.status === status && String(s.id) !== String(excludeId))
                    .map(s => s.order);

                return orders.length > 0 ? Math.max(...orders) + 1 : 0;
            },

            replaceSetWithAnimation(setId, changes) {
                const previousRects = this.captureSetPositions();
                this.sets = this.sets.map(set => String(set.id) === String(setId)
                    ? { ...set, ...changes }
                    : set);
                this.normalizeSetOrders();
                this.animateSetMovement(previousRects);
                this.scheduleSave();
            },

            canDragSet(set) {
                return this.canManageLiveJam && this.movableSetsForStatus(set.status).some(s => s.id === set.id);
            },

            ensureSetDropPlaceholder(draggedEl) {
                if (!this.setDropPlaceholderEl) {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'rounded-xl border-2 border-dashed border-sky-500/80 bg-sky-950/40 p-4 text-sm font-medium text-sky-300 shadow-sm';
                    placeholder.textContent = 'Drop set here';
                    this.setDropPlaceholderEl = placeholder;
                }

                this.setDropPlaceholderEl.style.minHeight = `${draggedEl.offsetHeight}px`;

                return this.setDropPlaceholderEl;
            },

            clearSetDropPlaceholder() {
                if (this.setDropPlaceholderEl?.parentNode) {
                    this.setDropPlaceholderEl.parentNode.removeChild(this.setDropPlaceholderEl);
                }
            },

            onSetDragStart(event, set) {
                if (!this.canDragSet(set)) {
                    event.preventDefault();
                    return;
                }

                if (window.isInteractiveDragSource(event)) {
                    event.preventDefault();
                    return;
                }

                this.dragSetId = set.id;
                this.draggingSetId = set.id;
                this.dragStartRects = this.captureSetPositions();

                const draggedEl = this.$refs.setsContainer?.querySelector(`[data-live-set-id='${set.id}']`);

                if (draggedEl && event.dataTransfer) {
                    event.dataTransfer.setDragImage(draggedEl, 24, 16);
                }

                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(set.id));
            },

            onSetDragEnd() {
                this.dragSetId = null;
                this.draggingSetId = null;
                this.dragStartRects = null;
                this.clearSetDropPlaceholder();
            },

            onSetDragOver(event, targetSet = null) {
                if (this.dragSetId === null) { return; }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                if (!targetSet || targetSet.id === this.dragSetId) { return; }

                const draggedSet = this.sets.find(s => s.id === this.dragSetId);
                if (!draggedSet || draggedSet.status !== targetSet.status || !this.canDragSet(targetSet)) { return; }

                const setsContainer = this.$refs.setsContainer;
                const draggedEl = setsContainer.querySelector(`[data-live-set-id='${this.dragSetId}']`);
                const targetEl = setsContainer.querySelector(`[data-live-set-id='${targetSet.id}']`);

                if (!draggedEl || !targetEl) { return; }

                const targetRect = targetEl.getBoundingClientRect();
                const placeAfter = event.clientY > (targetRect.top + targetRect.height / 2);
                const insertionReference = placeAfter ? targetEl.nextElementSibling : targetEl;
                const setElements = Array.from(setsContainer.querySelectorAll('[data-live-set-card]'));
                const currentIndex = setElements.indexOf(draggedEl);
                const referenceIndex = insertionReference ? setElements.indexOf(insertionReference) : setElements.length;
                const prospectiveIndex = insertionReference
                    ? (referenceIndex > currentIndex ? referenceIndex - 1 : referenceIndex)
                    : setElements.length - 1;

                if (prospectiveIndex === currentIndex) {
                    this.clearSetDropPlaceholder();
                    return;
                }

                const placeholderEl = this.ensureSetDropPlaceholder(draggedEl);

                if (insertionReference !== placeholderEl) {
                    setsContainer.insertBefore(placeholderEl, insertionReference);
                }
            },

            onSetDrop(event) {
                event.preventDefault();

                if (this.dragSetId === null) {
                    this.clearSetDropPlaceholder();
                    return;
                }

                const setsContainer = this.$refs.setsContainer;
                const draggedEl = setsContainer.querySelector(`[data-live-set-id='${this.dragSetId}']`);

                if (draggedEl && this.setDropPlaceholderEl?.parentNode === setsContainer) {
                    setsContainer.insertBefore(draggedEl, this.setDropPlaceholderEl);
                    this.syncDraggedSetOrder();
                    this.animateOrderChange(this.dragStartRects);
                    this.scheduleSave();
                }

                this.onSetDragEnd();
            },

            syncDraggedSetOrder() {
                const draggedSet = this.sets.find(s => s.id === this.dragSetId);
                if (!draggedSet) { return; }

                const orderedIds = Array.from(this.$refs.setsContainer.querySelectorAll(`[data-live-set-card][data-live-set-status='${draggedSet.status}']`))
                    .map(el => el.dataset.liveSetId);

                this.applyOrderedIdsForStatus(draggedSet.status, orderedIds);
                this.normalizeSetOrders();
            },

            movableSetsForStatus(status) {
                if (!this.canManageLiveJam) {
                    return [];
                }

                return this.sets
                    .filter(s => s.status === status && status !== 'playing_now' && status !== 'finished' && status !== 'postponed')
                    .sort((a, b) => a.order - b.order);
            },

            canMoveUp(set) {
                if (!this.canManageLiveJam) {
                    return false;
                }

                const sameStatus = this.movableSetsForStatus(set.status);

                return sameStatus.findIndex(s => s.id === set.id) > 0;
            },

            canMoveDown(set) {
                if (!this.canManageLiveJam) {
                    return false;
                }

                const sameStatus = this.movableSetsForStatus(set.status);
                const idx = sameStatus.findIndex(s => s.id === set.id);

                return idx >= 0 && idx < sameStatus.length - 1;
            },

            moveUp(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                // Filter sets with the same status, sorted by order
                const sameStatus = this.sets
                    .filter(s => s.status === set.status)
                    .sort((a, b) => a.order - b.order);
                
                const idx = sameStatus.findIndex(s => s.id === set.id);
                if (idx <= 0) { return; }
                
                // Swap order with the set above
                const prev = sameStatus[idx - 1];
                const previousRects = this.captureSetPositions();
                this.replaceSetOrders(new Map([
                    [String(set.id), prev.order],
                    [String(prev.id), set.order],
                ]));
                this.normalizeSetOrders();
                this.animateSetMovement(previousRects);
                this.scheduleSave();
            },

            moveDown(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                // Filter sets with the same status, sorted by order
                const sameStatus = this.sets
                    .filter(s => s.status === set.status)
                    .sort((a, b) => a.order - b.order);
                
                const idx = sameStatus.findIndex(s => s.id === set.id);
                if (idx >= sameStatus.length - 1) { return; }
                
                // Swap order with the set below
                const next = sameStatus[idx + 1];
                const previousRects = this.captureSetPositions();
                this.replaceSetOrders(new Map([
                    [String(set.id), next.order],
                    [String(next.id), set.order],
                ]));
                this.normalizeSetOrders();
                this.animateSetMovement(previousRects);
                this.scheduleSave();
            },

            startSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                const previousRects = this.captureSetPositions();
                const nextPendingOrder = this.nextOrderForStatus('pending', set.id);
                this.sets = this.sets.map(currentSet => {
                    if (String(currentSet.id) === String(set.id)) {
                        return { ...currentSet, status: 'playing_now', order: 0 };
                    }

                    if (currentSet.status === 'playing_now') {
                        return { ...currentSet, status: 'pending', order: nextPendingOrder };
                    }

                    return currentSet;
                });
                this.normalizeSetOrders();
                this.animateSetMovement(previousRects);
                this.scheduleSave();
            },

            finishSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.replaceSetWithAnimation(set.id, {
                    status: 'finished',
                    order: this.nextOrderForStatus('finished', set.id),
                });
            },

            postponeSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.replaceSetWithAnimation(set.id, {
                    status: 'postponed',
                    order: this.nextOrderForStatus('postponed', set.id),
                });
            },

            restoreSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.replaceSetWithAnimation(set.id, {
                    status: 'pending',
                    order: this.nextOrderForStatus('pending', set.id),
                });
            },

            deleteSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                if (set.isLiveSet) {
                    if (!confirm(`Delete "${set.name}"? This will remove the live set from the run of show.`)) { return; }

                    this.sets = this.sets.filter(s => s.id !== set.id);
                    this.scheduleSave();
                }
            },

            markComingUp(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                if (this.comingUpSets.length >= 2) { return; }

                this.replaceSetWithAnimation(set.id, {
                    status: 'coming_up',
                    order: this.nextOrderForStatus('coming_up', set.id),
                });
            },

            pushDown(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.replaceSetWithAnimation(set.id, {
                    status: 'pending',
                    order: this.nextOrderForStatus('pending', set.id),
                });
            },

            scheduleSave() {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.saveQueued = true;
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.saveState(), 500);
            },

            async saveState() {
                if (!this.canManageLiveJam || !this.saveQueued) {
                    return;
                }

                if (this.saveBusy) {
                    return;
                }

                this.normalizeSetOrders();
                this.saveBusy = true;
                this.saveQueued = false;
                this.saveSuccess = false;
                this.saveError = '';

                try {
                    const savedState = this.sets.map(set => this.stateSnapshot(set));
                    const payload = {
                        sets: this.sets.map((s, i) => ({
                            set_id: s.id,
                            status: s.status,
                            order: s.order,
                            songs_collapsed: Boolean(s.songs_collapsed),
                            completed_song_ids: s.isLiveSet
                                ? []
                                : s.songs.filter(song => song.completed).map(song => song.id),
                            // Include live set data for cache
                            isLiveSet: s.isLiveSet || false,
                            liveSetData: s.isLiveSet ? {
                                name: s.name,
                                owner: s.owner,
                                participants: s.participants,
                                details: s.details,
                                created_at: s.created_at || new Date().toISOString(),
                            } : null,
                        })),
                    };

                    const resp = await fetch(config.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!resp.ok) { throw new Error('Save failed'); }

                    this.saveSuccess = true;
                    this.lastUpdated = new Date().toLocaleTimeString();
                    this.originalSets = savedState;
                    setTimeout(() => { this.saveSuccess = false; }, 2500);
                } catch (e) {
                    this.saveError = 'Could not save. Please try again.';
                    setTimeout(() => { this.saveError = ''; }, 4000);
                    this.saveQueued = true;
                    setTimeout(() => this.saveState(), 2000);
                } finally {
                    this.saveBusy = false;

                    if (this.saveQueued) {
                        this.saveState();
                    }
                }
            },

            async clearState() {
                if (!this.canManageLiveJam) {
                    return;
                }

                if (!confirm('Reset the live state? This cannot be undone.')) { return; }
                this.clearBusy = true;
                try {
                    await fetch(config.clearUrl, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                    });
                    this.sets = [];
                    this.originalSets = [];
                    this.lastCacheUpdate = null;
                    this.loading = true;
                    await this.fetchData();
                } finally {
                    this.clearBusy = false;
                }
            },

            async claimManager() {
                if (this.managerBusy || this.canManageLiveJam) {
                    return;
                }

                if (this.jamManagerId && !confirm(`Take over from ${this.jamManagerName}?`)) {
                    return;
                }

                this.managerBusy = true;
                try {
                    const response = await fetch(config.claimManagerUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Claim failed');
                    }

                    const payload = await response.json();
                    this.applyJamManager(payload.jam_manager);
                } finally {
                    this.managerBusy = false;
                }
            },

            async releaseManager() {
                if (this.managerBusy || !this.canManageLiveJam) {
                    return;
                }

                this.managerBusy = true;
                try {
                    const response = await fetch(config.releaseManagerUrl, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Release failed');
                    }

                    this.applyJamManager(null);
                } finally {
                    this.managerBusy = false;
                }
            },

            setCardClasses(set) {
                const stateClasses = [];

                if (this.draggingSetId === set.id) {
                    stateClasses.push('opacity-70');
                }

                if (set.highlighted) {
                    stateClasses.push('live-set-unseen');
                }

                if (set.highlightFading) {
                    stateClasses.push('live-set-unseen-exit');
                }

                if (set.status === 'finished') {
                    return ['border-slate-700 bg-slate-900/75 opacity-75', ...stateClasses].join(' ');
                }
                if (set.status === 'postponed') {
                    return ['border-rose-900 bg-rose-950/70', ...stateClasses].join(' ');
                }
                if (set.status === 'playing_now') {
                    return ['border-emerald-600 bg-emerald-950/70 shadow-[0_0_24px_rgba(16,185,129,0.18)]', ...stateClasses].join(' ');
                }
                if (set.status === 'coming_up') {
                    return ['border-amber-700 bg-amber-950/70 shadow-[0_0_20px_rgba(245,158,11,0.14)]', ...stateClasses].join(' ');
                }
                return ['border-slate-700 bg-slate-900/85', ...stateClasses].join(' ');
            },

            statusLabel(status) {
                return {
                    playing_now: 'Playing now',
                    coming_up: 'Coming up',
                    pending: 'Up later',
                    postponed: 'Postponed',
                    finished: 'Finished',
                }[status] || status;
            },

            statusBadgeClasses(set) {
                if (set.status === 'playing_now') {
                    return 'border-emerald-800 bg-emerald-950 text-emerald-300';
                }
                if (set.status === 'coming_up') {
                    return 'border-amber-800 bg-amber-950 text-amber-300';
                }
                if (set.status === 'postponed') {
                    return 'border-rose-800 bg-rose-950 text-rose-300';
                }
                if (set.status === 'finished') {
                    return 'border-slate-700 bg-slate-800 text-slate-300';
                }
                return 'border-slate-700 bg-slate-800 text-slate-300';
            },

            healthColor(health) {
                const h = health / 100;
                const r = Math.round(239 - h * (239 - 34));
                const g = Math.round(68 + h * (197 - 68));
                const b = Math.round(68 - h * (68 - 94));
                return `rgb(${r},${g},${b})`;
            },

            formatDuration(seconds) {
                if (!seconds) { return ''; }
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return m + ':' + String(s).padStart(2, '0');
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
