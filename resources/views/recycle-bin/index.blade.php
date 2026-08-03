<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Recycle Bin</h2>
                <p class="text-sm text-slate-400">Restore recently deleted sets and jam sessions.</p>
            </div>
            <span
                class="inline-flex items-center rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-200 ring-1 ring-inset ring-rose-400/40"
                x-data
                x-text="$store.recycleBin.count + ' item' + ($store.recycleBin.count === 1 ? '' : 's')"
            >{{ $initialCount }} items</span>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        x-data="recycleBinPage({
            listUrl: @js($listUrl),
            restoreSetUrlTemplate: @js($restoreSetUrlTemplate),
            restoreSessionUrlTemplate: @js($restoreSessionUrlTemplate),
            csrfToken: @js(csrf_token()),
        })"
        x-init="init()"
        @modal-closed.window="handleModalClosed($event.detail.name)"
    >
        <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Deleted items are kept here until you restore them.</p>
                <button
                    type="button"
                    @click="refresh()"
                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                >
                    <x-heroicon-m-arrow-path class="h-4 w-4" aria-hidden="true" />
                    Refresh
                </button>
            </div>

            <p
                x-show="error"
                x-text="error"
                class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                x-cloak
            ></p>

            <div x-show="loading" class="mt-5 space-y-3" x-cloak>
                <div class="h-12 animate-pulse rounded-lg bg-slate-200"></div>
                <div class="h-12 animate-pulse rounded-lg bg-slate-200"></div>
                <div class="h-12 animate-pulse rounded-lg bg-slate-200"></div>
            </div>

            <div x-show="!loading && !hasItems" class="mt-5 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500" x-cloak>
                No deleted items right now.
            </div>
        </section>

        <section x-show="!loading && sessions.length > 0" class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6" x-cloak>
            <h3 class="text-lg font-semibold text-slate-900">Jam Sessions</h3>
            <p x-show="hasSessionDeletedSets" class="mt-1 text-xs text-slate-600" x-cloak>
                Some deleted jam sessions include deleted sets. You can restore sets separately or restore selected sets with the jam session.
            </p>
            <div class="mt-3 space-y-3">
                <template x-for="session in sessions" :key="'session-' + session.id">
                    <article class="rounded-lg border border-slate-200 bg-white/90 p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="openOverview('session', session)" class="min-w-0 text-left transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-amber-400 rounded-md">
                                <p class="text-sm font-semibold text-slate-900" x-text="session.name"></p>
                                <p class="mt-1 text-xs text-slate-600">
                                    <span x-text="session.date"></span>
                                    <span class="mx-1 text-slate-400">•</span>
                                    <span x-text="'Deleted ' + session.deleted_ago"></span>
                                </p>
                            </button>
                            <button
                                type="button"
                                @click="openRestorePrompt('session', session)"
                                class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="isSessionRestoring(session.id)"
                            >
                                <x-heroicon-m-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />
                                <span x-show="!isSessionRestoring(session.id)">Restore Session</span>
                                <span x-show="isSessionRestoring(session.id)">Restoring...</span>
                            </button>
                        </div>

                        <div x-show="session.deleted_sets?.length > 0" class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3" x-cloak>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Deleted sets in this session</p>
                            <ul class="mt-2 space-y-1">
                                <template x-for="sessionSet in session.deleted_sets" :key="'session-set-' + sessionSet.id">
                                    <li class="text-xs text-slate-700">
                                        <span class="font-medium" x-text="sessionSet.name"></span>
                                        <span class="text-slate-400">•</span>
                                        <span x-text="sessionSet.owner_name"></span>
                                        <span class="text-slate-400">•</span>
                                        <span x-text="'Deleted ' + sessionSet.deleted_ago"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <section x-show="!loading && sets.length > 0" class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6" x-cloak>
            <h3 class="text-lg font-semibold text-slate-900">Sets</h3>
            <div class="mt-3 space-y-3">
                <template x-for="set in sets" :key="'set-' + set.id">
                    <article class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white/90 p-4 shadow-sm">
                        <button type="button" @click="openOverview('set', set)" class="min-w-0 text-left transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-amber-400 rounded-md">
                            <p class="text-sm font-semibold text-slate-900" x-text="set.name"></p>
                            <p class="mt-1 text-xs text-slate-600">
                                <span x-text="set.session_name"></span>
                                <span
                                    x-show="set.session_deleted"
                                    x-cloak
                                    class="ms-2 inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700"
                                >
                                    Deleted Session
                                </span>
                                <span class="mx-1 text-slate-400">•</span>
                                <span x-text="set.owner_name"></span>
                                <span class="mx-1 text-slate-400">•</span>
                                <span x-text="'Deleted ' + set.deleted_ago"></span>
                            </p>
                        </button>
                        <button
                            type="button"
                            @click="openRestorePrompt('set', set)"
                            class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isSetRestoring(set.id)"
                        >
                            <x-heroicon-m-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />
                            <span x-show="!isSetRestoring(set.id)">Restore Set</span>
                            <span x-show="isSetRestoring(set.id)">Restoring...</span>
                        </button>
                    </article>
                </template>
            </div>
        </section>

        <x-modal name="recycle-bin-restore-item" :show="false" focusable>
            <div class="flex max-h-[calc(100vh-4rem)] flex-col bg-gradient-to-b from-white to-slate-50 text-slate-900">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Restore Item</h3>
                    <p class="mt-1 text-sm text-slate-600">Choose how this item should be restored.</p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold" x-text="restorePromptItemName"></span>
                        <span x-text="restorePromptType === 'session' ? ' will be restored as a jam session.' : ' will be restored as a set.'"></span>
                    </p>

                    <div x-show="restorePromptType === 'set'" x-cloak class="mt-4">
                        <x-input-label value="Restore to Jam Session" />
                        <select x-model="restoreTargetSessionId" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            <option value="">Choose a jam session</option>
                            <template x-for="option in restoreSessionOptions" :key="'restore-session-option-' + option.id">
                                <option :value="String(option.id)" :disabled="option.disabled" x-text="option.label"></option>
                            </template>
                        </select>
                        <p class="mt-2 text-xs text-slate-500" x-show="restorePromptParentSessionName" x-cloak>
                            Deleted from <span class="font-medium" x-text="restorePromptParentSessionName"></span>.
                        </p>
                        <p class="mt-2 text-xs text-slate-500" x-show="!hasRestoreSessionOptions" x-cloak>
                            No eligible jam sessions are currently available for restoring this set.
                        </p>
                    </div>

                    <label class="mt-4 flex items-center gap-3 rounded-lg border border-sky-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 shadow-[inset_0_0_6px_rgb(125_211_252_/_0.45),inset_0_0_14px_rgb(186_230_253_/_0.35)]">
                        <input type="checkbox" x-model="restoreAsHidden" class="rounded border-slate-300 text-slate-600 shadow-sm focus:ring-slate-500">
                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                        <span>Restore as hidden</span>
                    </label>

                    <div x-show="hasSessionRestoreChoices" x-cloak class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Restore These Sets With The Session</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="setOption in currentRestoreSessionSets" :key="'restore-session-set-' + setOption.id">
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" x-model="restoreSelectedSetIds" :value="String(setOption.id)" class="mt-0.5 rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span>
                                        <span class="font-medium" x-text="setOption.name"></span>
                                        <span class="text-slate-400">•</span>
                                        <span x-text="setOption.owner_name"></span>
                                    </span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <label x-show="restorePromptType === 'set'" x-cloak class="mt-3 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" x-model="clearSlotAssignments" class="rounded border-slate-300 text-rose-500 shadow-sm focus:ring-rose-500">
                        <x-heroicon-m-x-circle class="h-4 w-4 text-rose-500" aria-hidden="true" />
                        <span>Clear slot assignments</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-6 py-4">
                    <x-modal-secondary-button type="button" @click="closeRestorePrompt()">
                        Cancel
                    </x-modal-secondary-button>
                    <x-modal-primary-button type="button" @click="confirmRestore()" x-bind:disabled="confirmRestoreDisabled">
                        <span x-text="restorePromptType === 'session' ? 'Restore Session' : 'Restore Set'"></span>
                    </x-modal-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="recycle-bin-item-overview" :show="false" focusable>
            <div class="flex max-h-[calc(100vh-4rem)] flex-col bg-gradient-to-b from-white to-slate-50 text-slate-900">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Item Overview</h3>
                    <p class="mt-1 text-sm text-slate-600">Review the deleted item contents before restoring it.</p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <div x-show="overviewItemType === 'session' && overviewItem" x-cloak class="space-y-4 text-slate-700">
                        <div>
                            <p class="text-base font-semibold text-slate-900" x-text="overviewItem?.name"></p>
                            <p class="mt-1 text-sm text-slate-600">
                                <span x-text="overviewItem?.date"></span>
                                <span class="mx-1 text-slate-400">•</span>
                                <span x-text="'Deleted ' + (overviewItem?.deleted_ago || '')"></span>
                            </p>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Sets and Songs</p>
                            <ul class="mt-3 space-y-3 text-sm text-slate-700">
                                <template x-for="detailSet in (overviewItem?.overview?.sets || [])" :key="'overview-session-set-' + detailSet.id">
                                    <li>
                                        <p class="font-medium text-slate-900">
                                            <span x-text="detailSet.name"></span>
                                            <span class="text-slate-400">·</span>
                                            <span class="text-slate-600" x-text="detailSet.owner_name"></span>
                                        </p>
                                        <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-slate-600">
                                            <template x-for="song in (detailSet.songs || [])" :key="'overview-session-song-' + song.id">
                                                <li>
                                                    <span x-text="song.artist + ' - ' + song.title"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="(overviewItem?.overview?.sets || []).length === 0" x-cloak class="mt-3 text-xs text-slate-500">No deleted sets in this jam session.</p>
                        </div>
                    </div>

                    <div x-show="overviewItemType === 'set' && overviewItem" x-cloak class="space-y-4 text-slate-700">
                        <div>
                            <p class="text-base font-semibold text-slate-900" x-text="overviewItem?.name"></p>
                            <p class="mt-1 text-sm text-slate-600">
                                <span x-text="overviewItem?.session_name"></span>
                                <span class="mx-1 text-slate-400">•</span>
                                <span x-text="overviewItem?.owner_name"></span>
                            </p>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Set Settings</p>
                            <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-slate-600">
                                <template x-for="setting in (overviewItem?.overview?.settings || [])" :key="'overview-setting-' + setting.label">
                                    <li>
                                        <span x-text="setting.label + ': ' + (setting.enabled ? 'On' : 'Off')"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Songs and Slots</p>
                            <ul class="mt-3 space-y-3 text-sm text-slate-700">
                                <template x-for="song in (overviewItem?.overview?.songs || [])" :key="'overview-set-song-' + song.id">
                                    <li>
                                        <p class="font-medium text-slate-900" x-text="song.artist + ' - ' + song.title"></p>
                                        <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-slate-600">
                                            <template x-for="slot in (song.slots || [])" :key="'overview-slot-' + slot.id">
                                                <li>
                                                    <span x-text="slot.label + ': ' + slot.performer_name"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="(overviewItem?.overview?.songs || []).length === 0" x-cloak class="mt-3 text-xs text-slate-500">No songs in this set.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                    <x-modal-secondary-button type="button" @click="closeOverview()">
                        Close
                    </x-modal-secondary-button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
