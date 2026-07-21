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
            currentUserId: @js($currentUserId),
            initialJamManager: @js($jamManager?->only(['id', 'name'])),
            csrfToken: @js(csrf_token()),
        })"
        x-init="init()"
    >
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-900/85 p-4 text-slate-100 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="text-center lg:text-left">
                    <div class="flex items-center justify-center gap-2 text-sm text-slate-300 lg:justify-start">
                        <x-heroicon-m-microphone class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span x-text="jamManagerName || 'No jam manager assigned yet'"></span>
                    </div>
                    <div class="mt-1 text-sm text-slate-400">
                        <span x-show="saveSuccess" x-cloak x-transition.opacity class="font-medium text-emerald-300">Saved</span>
                        <span x-show="saveError" x-cloak x-transition.opacity class="font-medium text-rose-300" x-text="saveError"></span>
                        <span x-show="!saveSuccess && !saveError && lastUpdated" x-cloak>Last saved <span x-text="lastUpdated"></span></span>
                        <span x-show="!saveSuccess && !saveError && !lastUpdated" x-cloak>Arrange sets, then update the live display.</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
                    <button
                        type="button"
                        x-show="canManageLiveJam"
                        x-cloak
                        @click="releaseManager()"
                        :disabled="managerBusy"
                        class="inline-flex items-center gap-1.5 rounded-md border border-rose-800 bg-rose-950/60 px-3 py-2 text-sm font-medium text-rose-300 transition hover:border-rose-600 hover:bg-rose-900/70 disabled:opacity-50"
                    >
                        <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Exit jam manager mode</span>
                    </button>
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
                    <div
                        x-show="canManageLiveJam"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 translate-x-4"
                        class="flex flex-wrap items-center justify-center gap-2"
                    >
                        <button
                            type="button"
                            @click="clearState()"
                            :disabled="clearBusy"
                            class="inline-flex items-center gap-1.5 rounded-md border border-rose-800 bg-rose-950/60 px-3 py-2 text-sm font-medium text-rose-300 transition hover:border-rose-600 hover:bg-rose-900/70 disabled:opacity-50"
                        >
                            <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                            Reset
                        </button>
                        <button
                            type="button"
                            @click="openAddSetModal()"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-700"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                            Add Set
                        </button>
                        <button
                            type="button"
                            @click="saveState()"
                            :disabled="saveBusy || !hasChanges"
                            class="inline-flex items-center gap-1.5 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-400 disabled:opacity-50"
                        >
                            <x-heroicon-m-arrow-up-tray class="h-4 w-4" aria-hidden="true" />
                            <span x-show="!saveBusy">Update</span>
                            <span x-show="saveBusy" x-cloak>Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Set Modal --}}
        <template x-teleport="body">
            <div x-show="addSetModalOpen" x-cloak @keydown.escape.window="closeAddSetModal()">
                <div x-show="addSetModalOpen" x-transition.opacity.duration.150ms class="fixed inset-0 z-40 bg-black/40" @click="closeAddSetModal()"></div>
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div x-show="addSetModalOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" @click.stop class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                        <h3 class="text-lg font-semibold">Add Live Set</h3>
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
                    :class="[setCardClasses(set), { 'opacity-70': draggingSetId === set.id, 'ring-2 ring-sky-400 shadow-[0_0_28px_rgba(56,189,248,0.45)]': set.highlighted }]"
                    data-live-set-card
                    :data-live-set-id="set.id"
                    :data-live-set-status="set.status"
                    @mouseenter="markSetBrowsed(set)"
                    @focusin="markSetBrowsed(set)"
                    x-bind:draggable="canDragSet(set) ? 'true' : 'false'"
                    @dragstart="onSetDragStart($event, set)"
                    @dragover="onSetDragOver($event, set)"
                    @drop="onSetDrop($event)"
                    @dragend="onSetDragEnd()"
                >
                    <div class="flex min-h-40">
                        {{-- Main content --}}
                        <div class="min-w-0 flex-1 p-5 text-slate-100">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="flex items-center gap-2 text-xl font-semibold text-slate-100">
                                        <span class="min-w-0 truncate" x-text="set.name"></span>
                                        <template x-if="set.feature_set">
                                            <x-feature-set-icon />
                                        </template>
                                    </h3>
                                    <p class="mt-1 truncate text-sm text-slate-400" x-show="set.owner" x-text="`by ${set.owner}`"></p>
                                </div>
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold" :class="statusBadgeClasses(set)" x-text="statusLabel(set.status)"></span>
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
                            <ul x-show="set.songs.length > 0" class="mt-4 divide-y divide-slate-700 overflow-hidden rounded-lg border border-slate-700 bg-slate-950/40">
                                <template x-for="song in set.songs" :key="song.id">
                                    <li class="px-3 py-2 text-sm">
                                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                            <span class="font-semibold text-slate-100" x-text="`${song.artist} – ${song.title}`"></span>
                                            <span x-show="song.duration" x-cloak class="text-slate-500" x-text="song.duration ? formatDuration(song.duration) : ''"></span>
                                        </div>
                                        <span class="mt-1.5 flex flex-wrap gap-1">
                                            <template x-for="slot in song.slots" :key="slot.id">
                                                <span
                                                    class="inline-block rounded-md px-2 py-1 text-xs transition"
                                                    :class="{
                                                        'bg-emerald-900/80 text-emerald-50 ring-1 ring-emerald-400/80': slot.filled && slot.checked_in,
                                                        'bg-emerald-950/60 text-emerald-300 ring-1 ring-emerald-800': slot.filled && !slot.checked_in,
                                                        'bg-slate-800 text-slate-500': !slot.filled
                                                    }"
                                                    :title="slot.checked_in ? 'Checked in' : 'Not checked in'"
                                                >
                                                    <span x-text="slot.user_name ? `${slot.name}: ${slot.user_name}` : slot.name"></span>
                                                    <x-checked-in-dot x-show="slot.checked_in" x-cloak class="ml-1" />
                                                </span>
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
                                <x-heroicon-m-stop class="h-4 w-4" aria-hidden="true" />
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
            lastCheckedAt: null,
            pollTimer: null,
            addSetModalOpen: false,
            editSetModalOpen: false,
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
            // Track highlighted set IDs so they remain highlighted until the user hovers over or focuses on them.
            setHighlights: new Set(),
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

            persistLastCheckedAt(timestamp) {
                if (!timestamp) {
                    return;
                }

                this.lastCheckedAt = timestamp;
                localStorage.setItem(this.lastCheckedKey(), timestamp);
            },

            lastCheckedKey() {
                return `live-jam-last-checked:${config.dataUrl}`;
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

            shouldHighlightSet(set) {
                if (!set.created_at || !this.lastCheckedAt) {
                    return false;
                }

                return new Date(set.created_at) > new Date(this.lastCheckedAt);
            },

            highlightSet(setOrId) {
                const set = typeof setOrId === 'object'
                    ? setOrId
                    : this.sets.find(item => String(item.id) === String(setOrId));
                if (!set) {
                    return;
                }

                set.highlighted = true;
                this.setHighlights.add(String(set.id));
            },

            clearSetHighlight(setOrId) {
                const set = typeof setOrId === 'object'
                    ? setOrId
                    : this.sets.find(item => String(item.id) === String(setOrId));
                if (!set) {
                    return;
                }

                set.highlighted = false;
                this.setHighlights.delete(String(set.id));
            },

            markSetBrowsed(setOrId) {
                this.clearSetHighlight(setOrId);
            },

            applyHighlightIfNeeded(set) {
                if (this.shouldHighlightSet(set)) {
                    set.highlighted = true;
                    this.setHighlights.add(String(set.id));
                }

                return set;
            },

            stateSnapshot(set) {
                const snapshot = { id: set.id, status: set.status, order: set.order };

                if (set.isLiveSet) {
                    snapshot.name = set.name || '';
                    snapshot.owner = set.owner || '';
                    snapshot.participants = set.participants || '';
                    snapshot.details = set.details || '';
                }

                return snapshot;
            },

            init() {
                this.lastCheckedAt = localStorage.getItem(this.lastCheckedKey());
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
                    const localIds = new Set(this.sets.map(set => String(set.id)));
                    const isInitialLoad = this.sets.length === 0;

                    // On initial load, apply highlights to the server snapshot.
                    // On subsequent polls, preserve local reordering/editing and only append truly new sets.
                    if (isInitialLoad) {
                        this.sets = serverSets.map(serverSet => this.applyHighlightIfNeeded({ ...serverSet }));
                    } else {
                        const newSets = serverSets
                            .filter(serverSet => !localIds.has(String(serverSet.id)))
                            .map(serverSet => this.applyHighlightIfNeeded({ ...serverSet }));

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

                        this.sets = [...this.sets, ...newSets];
                    }

                    if (!hadLocalChanges) {
                        this.originalSets = this.sets.map(s => this.stateSnapshot(s));
                    }

                    this.lastCacheUpdate = payload.updated_at || this.lastCacheUpdate;
                    if (payload.updated_at) {
                        this.persistLastCheckedAt(payload.updated_at);
                        this.lastUpdated = new Date(payload.updated_at).toLocaleTimeString();
                    }

                    if (!this.lastCheckedAt) {
                        this.persistLastCheckedAt(new Date().toISOString());
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
                this.highlightSet(newSet.id);
                this.closeAddSetModal();
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
                this.animateSetMovement(previousRects);
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
                }

                this.onSetDragEnd();
            },

            syncDraggedSetOrder() {
                const draggedSet = this.sets.find(s => s.id === this.dragSetId);
                if (!draggedSet) { return; }

                const orderedIds = Array.from(this.$refs.setsContainer.querySelectorAll(`[data-live-set-card][data-live-set-status='${draggedSet.status}']`))
                    .map(el => el.dataset.liveSetId);

                this.applyOrderedIdsForStatus(draggedSet.status, orderedIds);
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
                this.animateSetMovement(previousRects);
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
                this.animateSetMovement(previousRects);
            },

            startSet(set) {
                if (!this.canManageLiveJam) {
                    return;
                }

                const previousRects = this.captureSetPositions();
                this.sets = this.sets.map(currentSet => {
                    if (String(currentSet.id) === String(set.id)) {
                        return { ...currentSet, status: 'playing_now', order: 0 };
                    }

                    if (currentSet.status === 'playing_now') {
                        return { ...currentSet, status: 'pending' };
                    }

                    return currentSet;
                });
                this.animateSetMovement(previousRects);
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

            async saveState() {
                if (!this.canManageLiveJam) {
                    return;
                }

                this.saveBusy = true;
                this.saveSuccess = false;
                this.saveError = '';

                try {
                    const payload = {
                        sets: this.sets.map((s, i) => ({
                            set_id: s.id,
                            status: s.status,
                            order: s.order,
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
                    this.originalSets = this.sets.map(s => this.stateSnapshot(s));
                    setTimeout(() => { this.saveSuccess = false; }, 2500);
                } catch (e) {
                    this.saveError = 'Could not save. Please try again.';
                    setTimeout(() => { this.saveError = ''; }, 4000);
                } finally {
                    this.saveBusy = false;
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
                if (set.status === 'finished') {
                    return 'border-slate-700 bg-slate-900/75 opacity-75';
                }
                if (set.status === 'postponed') {
                    return 'border-rose-900 bg-rose-950/70';
                }
                if (set.status === 'playing_now') {
                    return 'border-emerald-600 bg-emerald-950/70 shadow-[0_0_24px_rgba(16,185,129,0.18)]';
                }
                if (set.status === 'coming_up') {
                    return 'border-amber-700 bg-amber-950/70 shadow-[0_0_20px_rgba(245,158,11,0.14)]';
                }
                return 'border-slate-700 bg-slate-900/85';
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
