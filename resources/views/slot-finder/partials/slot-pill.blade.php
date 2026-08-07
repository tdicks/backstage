@php
    $slotLabel = $slotOptions[$slot->name] ?? str($slot->name)->replace('_', ' ')->title()->toString();
    $pendingRequestUrl = $slot->pending_request_id ? route('slot-assignments.respond', $slot->pending_request_id) : null;
@endphp

<article
    x-data="slotFinderSlotCard(@js([
        'csrfToken' => csrf_token(),
        'takeUrl' => route('slots.take', $slot),
        'requestUrl' => route('slot-assignments.request', $slot),
        'slotId' => $slot->id,
        'pendingRequestUrl' => $pendingRequestUrl,
        'freeForAll' => $set->free_for_all,
        'songKey' => $songKey,
        'requested' => (int) $slot->pending_request_count > 0,
    ]))"
    x-show="!removed"
    x-bind:class="removing ? 'opacity-0 translate-y-2 scale-[0.98] pointer-events-none' : ''"
    x-transition.opacity.duration.200ms
    class="relative inline-flex w-fit max-w-full cursor-pointer select-none items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-xs shadow-sm transition-all duration-300 ease-out hover:border-amber-300 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-400"
    role="button"
    tabindex="0"
    @click="activate()"
    @keydown.enter.prevent="activate()"
    @keydown.space.prevent="activate()"
    x-bind:aria-disabled="(busy || removed || (!freeForAll && requested)).toString()"
>
    <div
        x-show="freeForAll && (feedback || error)"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
        class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center px-2"
    >
        <div
            class="max-w-full rounded-md border px-3 py-1.5 text-center text-xs font-medium shadow-sm shadow-slate-200/70 backdrop-blur-sm"
            x-bind:class="error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
        >
            <span x-text="error || feedback"></span>
        </div>
    </div>

    <x-sets.presentational.slot-chip tag="div" :label-text="$slotLabel" chip-class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50">
        <x-slot:badge>
            <span
                x-show="freeForAll"
                x-cloak
                class="inline-flex shrink-0 items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-700"
            >
                Take
            </span>

            <template x-if="!freeForAll && requested">
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                    <span>Requested</span>
                    <button
                        type="button"
                        @click.stop="cancelRequest()"
                        x-bind:disabled="busy"
                        class="inline-flex items-center justify-center rounded-full p-0.5 text-rose-700 transition hover:bg-rose-100 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Cancel request"
                        title="Cancel request"
                    >
                        <x-heroicon-m-x-mark class="h-3 w-3" aria-hidden="true" />
                    </button>
                </span>
            </template>

            <span
                x-show="!freeForAll && !requested"
                x-cloak
                class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
            >
                Request
            </span>
        </x-slot:badge>
    </x-sets.presentational.slot-chip>
</article>