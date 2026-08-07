<div class="flex items-center justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-slate-200">
            <x-live-status-icon size="h-6 w-6" title="Live jam session" />
        </span>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Right now</p>
            <h3 class="mt-1 text-lg font-semibold text-white" x-text="liveSessionName || 'Live jam parts'"></h3>
        </div>
    </div>
    <span class="rounded-full border border-slate-600 bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-100" x-text="liveSessionIsLive ? 'Live now' : 'Standby'"></span>
</div>

<p class="mt-2 text-sm text-slate-400" x-show="liveSetPanelsLoading && !liveSetPanelsLoaded" x-cloak>Loading live parts...</p>
<p class="mt-2 text-sm text-rose-300" x-show="liveSetPanelsError" x-cloak x-text="liveSetPanelsError"></p>
<p class="mt-2 text-sm text-slate-400" x-show="liveSetPanelsLoaded && liveSetPanels.length === 0" x-cloak>No active parts for you right now.</p>

<div class="mt-3 space-y-3" x-show="liveSetPanels.length > 0" x-cloak>
    <template x-for="setPanel in liveSetPanels" :key="`live-set-${setPanel.setId}`">
        <div class="rounded-lg border p-3" :class="setCardClasses(setPanel.status, setPanel.isFeatureSet)">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-1.5">
                    <div class="flex min-w-0 items-center gap-2">
                        <p class="truncate font-semibold" :class="textClasses(setPanel.status)" x-text="setPanel.setName"></p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center p-0.5 transition focus:outline-none focus:ring-2"
                        :class="[attachmentIconClasses(setPanel.setAttachmentKey, setPanel.setAttachmentCount), attachmentButtonClasses(setPanel.status)]"
                        @click="openAttachmentsForEntity('set', setPanel.setId, `Set: ${setPanel.setName}`, setPanel.setAttachmentKey, setPanel.setAttachmentCount)"
                        title="Set attachments"
                    >
                        <x-heroicon-s-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">
                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="statusClasses(setPanel.status)" x-text="setPanel.statusLabel"></span>
                    <template x-if="setPanel.isFeatureSet">
                        <x-feature-set-icon size="h-3.5 w-3.5" title="Feature set" />
                    </template>
                </div>
            </div>

            <div class="mt-2 space-y-2">
                <template x-for="songPanel in setPanel.songs" :key="`live-song-${songPanel.songId}`">
                    <div class="rounded-md p-2.5" :class="songCardClasses(setPanel.status)">
                        <div class="flex min-w-0 items-center gap-1.5">
                            <p class="truncate text-sm font-semibold" :class="textClasses(setPanel.status)" x-text="songPanel.songName"></p>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center p-0.5 transition focus:outline-none focus:ring-2"
                                :class="[attachmentIconClasses(songPanel.songAttachmentKey, songPanel.songAttachmentCount), attachmentButtonClasses(setPanel.status)]"
                                @click="openAttachmentsForEntity('song', songPanel.songId, `Song: ${songPanel.songName}`, songPanel.songAttachmentKey, songPanel.songAttachmentCount)"
                                title="Song attachments"
                            >
                                <x-heroicon-s-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                            </button>
                        </div>

                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="slotPanel in songPanel.slots" :key="`live-slot-${slotPanel.slotId}`">
                                <li class="inline-flex min-w-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs" :class="slotChipClasses(setPanel.status)">
                                    <span class="truncate" x-text="slotPanel.slotName"></span>
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center p-0.5 transition focus:outline-none focus:ring-2"
                                        :class="[attachmentIconClasses(slotPanel.slotAttachmentKey, slotPanel.slotAttachmentCount), attachmentButtonClasses(setPanel.status)]"
                                        @click="openAttachmentsForEntity('slot', slotPanel.slotId, `Part: ${slotPanel.slotName} on ${songPanel.songName}`, slotPanel.slotAttachmentKey, slotPanel.slotAttachmentCount)"
                                        title="Part attachments"
                                    >
                                        <x-heroicon-s-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>

<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        <a x-show="liveSessionDashboardUrl" x-cloak :href="liveSessionDashboardUrl" class="inline-flex items-center gap-1.5 rounded-md border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm font-semibold text-slate-100">Open live board</a>
        <a href="{{ route('practice-plan.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm font-semibold text-slate-100">Open practice</a>
    </div>
    <p class="text-xs text-slate-400" x-show="liveLastUpdated" x-cloak x-text="`Updated ${liveLastUpdated}`"></p>
</div>
