<div x-show="openSnapshot" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-slate-950/55" @click="closeSnapshotModal()"></div>
<div x-show="openSnapshot" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6">
    <section class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100dvh-3rem)]" role="dialog" aria-modal="true" aria-label="Set snapshot" @click.stop>
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6">
            <div>
                <h4 class="text-lg font-semibold text-slate-900">Set Snapshot</h4>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">This is an image of the set for sharing outside of the app!</p>
            </div>
            <button type="button" @click="closeSnapshotModal()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400" aria-label="Close set snapshot" title="Close">
                <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm">
                <img :src="snapshotImageUrl" alt="Set snapshot" class="block h-auto w-full" />
            </div>
            <p x-show="summaryImageError" x-text="summaryImageError" x-cloak class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"></p>
            <p x-show="summaryImageCopied" x-cloak class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">Snapshot copied to your clipboard.</p>
        </div>

        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white/80 px-4 py-3 sm:px-6">
            <button type="button" @click="copySnapshotImage()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <x-heroicon-m-clipboard-document class="h-4 w-4" aria-hidden="true" />
                Copy image
            </button>
            <button type="button" @click="downloadSnapshotImage()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <x-heroicon-m-arrow-down-tray class="h-4 w-4" aria-hidden="true" />
                Save image
            </button>
            <button type="button" x-show="snapshotCanShare" x-cloak @click="shareSnapshotImage()" class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2">
                <x-heroicon-m-share class="h-4 w-4" aria-hidden="true" />
                Share
            </button>
        </footer>
    </section>
</div>
