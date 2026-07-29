@props(['set'])

<div x-show="openSummary" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="closeSummaryModal()"></div>
<div x-show="openSummary" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl" @click.stop>
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <h4 class="text-lg font-semibold text-slate-900">Set Summary: {{ $set->name }}</h4>
                <p class="mt-1 text-sm text-slate-600">Set owner: {{ $set->owner->name }}</p>
            </div>
            <x-modal-secondary-button type="button" @click="closeSummaryModal()">Close</x-modal-secondary-button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
            <p class="text-sm text-slate-600" x-show="summaryLoading && !summaryLoaded">Loading summary...</p>
            <p class="text-sm text-rose-600" x-show="summaryError" x-text="summaryError"></p>
            <template x-if="summaryLoaded && summaryData?.songs?.length === 0">
                <p class="text-sm text-slate-600">No songs in this set yet.</p>
            </template>
            <div class="space-y-3" x-show="summaryLoaded && summaryData?.songs?.length > 0">
                <template x-for="song in summaryData.songs" :key="song.id">
                    <article class="rounded-lg border border-slate-200 bg-white p-4">
                        <h5 class="font-semibold text-slate-900" x-text="`${song.artist} - ${song.title}`"></h5>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <template x-for="slot in summaryData.slot_names" :key="slot.name">
                                <div class="flex justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm">
                                    <span class="font-medium text-slate-700" x-text="slot.label"></span>
                                    <span class="text-slate-600" x-text="song.slot_map[slot.name]?.display || 'Open'"></span>
                                </div>
                            </template>
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </div>
</div>
