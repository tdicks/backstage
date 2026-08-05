@props([
    'set',
    'templates',
    'slotOptions',
    'slotConflicts' => [],
    'canManageSet' => false,
    'isSetOwner' => false,
    'setLocked' => false,
])

@php
    $templateSlotNamesByTemplateId = $templates
        ->mapWithKeys(fn ($template) => [
            (string) $template->id => $template->slots->pluck('name')->values()->all(),
        ])
        ->all();
    $templateNamesById = $templates
        ->mapWithKeys(fn ($template) => [
            (string) $template->id => $template->name,
        ])
        ->all();
@endphp

@if ($set->song_requests && $set->songRequests->where('status', 'pending')->isNotEmpty())
    <div class="rounded-md border border-amber-200 bg-amber-50/80 p-4" x-show="songRequestsPendingCount > 0" x-transition>
        <div
            class="flex cursor-pointer items-center justify-between gap-2"
            role="button"
            tabindex="0"
            @click="songRequestsCollapsed = !songRequestsCollapsed"
            @keydown.enter.prevent="songRequestsCollapsed = !songRequestsCollapsed"
            @keydown.space.prevent="songRequestsCollapsed = !songRequestsCollapsed"
            x-bind:aria-expanded="(!songRequestsCollapsed).toString()"
            x-bind:title="songRequestsCollapsed ? 'Click to show song requests' : 'Click to hide song requests'"
        >
            <h4 class="text-sm font-semibold text-amber-900">Song requests</h4>
            <x-heroicon-m-chevron-down class="h-4 w-4 text-amber-700 transition" x-bind:class="songRequestsCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
        </div>
        <div class="mt-3 space-y-3" x-show="!songRequestsCollapsed" x-transition>
            @foreach ($set->songRequests->where('status', 'pending') as $songRequest)
                <div
                    class="rounded-lg border border-amber-200 bg-white/90 p-4 shadow-sm"
                    data-song-request-id="{{ $songRequest->id }}"
                    x-data="sessionSongRequestRow(@js([
                        'respondUrl' => route('song-requests.respond', $songRequest),
                        'setId' => $set->id,
                        'initialBandTemplateId' => $songRequest->band_template_id,
                        'canChooseBandTemplate' => blank($songRequest->jam_standard_song_id),
                        'requestedSlotNames' => $songRequest->requested_slot_names ?? [],
                        'slotConflicts' => $slotConflicts,
                        'templateSlotNamesByTemplateId' => $templateSlotNamesByTemplateId,
                        'templateNamesById' => $templateNamesById,
                        'decrementApprovalsCounter' => $isSetOwner,
                        'csrfToken' => csrf_token(),
                    ]))"
                    x-show="!hidden"
                    x-transition
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $songRequest->artist }} - {{ $songRequest->title }}</p>
                            <p class="text-sm text-slate-600">Requested by {{ $songRequest->requester_user_id === auth()->id() ? 'you' : $songRequest->requester->name }}</p>
                            @if ($songRequest->bandTemplate)
                                <p class="text-sm text-slate-600">Requested template: {{ $songRequest->bandTemplate->name }}</p>
                            @endif
                            @if (! empty($songRequest->requested_slot_names))
                                <p class="text-sm text-slate-600">
                                    Can cover:
                                    {{ collect($songRequest->requested_slot_names)->map(fn ($slotName) => $slotOptions[$slotName] ?? str($slotName)->replace('_', ' ')->title())->join(', ') }}
                                </p>
                            @endif
                            @if ($songRequest->notes)
                                <p class="mt-1 text-sm text-slate-700">{{ $songRequest->notes }}</p>
                            @endif
                            <p x-show="error" x-text="error" class="mt-1 text-sm text-rose-700" x-cloak></p>
                        </div>

                        <div class="w-full sm:w-auto">
                            @if ($canManageSet && ! $setLocked)
                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    @if (blank($songRequest->jam_standard_song_id))
                                        <label class="sr-only" for="band_template_id_{{ $songRequest->id }}">Band template for approval</label>
                                        <x-select id="band_template_id_{{ $songRequest->id }}" x-model="bandTemplateId" x-bind:disabled="busy" class="w-52 py-1.5 text-sm disabled:opacity-60">
                                            <option value="">Template: None</option>
                                            @foreach ($templates as $template)
                                                <option value="{{ $template->id }}" @selected($songRequest->band_template_id === $template->id)>{{ $template->name }}</option>
                                            @endforeach
                                        </x-select>
                                    @endif
                                    @if (! empty($songRequest->requested_slot_names))
                                        <div class="w-52 rounded-lg border border-slate-300 bg-white p-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Assign requester to slots (optional)</p>
                                            <div class="mt-2 space-y-1.5">
                                                @foreach ($songRequest->requested_slot_names as $requestedSlotName)
                                                    <label class="flex items-center gap-2 text-xs text-slate-700">
                                                        <input type="checkbox" value="{{ $requestedSlotName }}" x-model="approvedSlotNames" x-bind:disabled="slotSelectionDisabled('{{ $requestedSlotName }}')" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                        <span>
                                                            {{ $slotOptions[$requestedSlotName] ?? str($requestedSlotName)->replace('_', ' ')->title() }}
                                                            <span x-show="slotNeedsTemplateAddition('{{ $requestedSlotName }}')" class="text-rose-700" x-cloak>*</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <p x-show="hasAnyTemplateAdditions()" x-text="templateAdditionHelperText()" class="mt-2 text-xs text-rose-700" x-cloak></p>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        @click="respond('accepted')"
                                        x-bind:disabled="busy"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                        aria-label="Approve song request"
                                        title="Approve"
                                    >
                                        <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                                        <span class="sr-only">Approve</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="respond('rejected')"
                                        x-bind:disabled="busy"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400"
                                        aria-label="Reject song request"
                                        title="Reject"
                                    >
                                        <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                                        <span class="sr-only">Reject</span>
                                    </button>
                                </div>
                            @endif

                            @if ($songRequest->requester_user_id === auth()->id())
                                <div class="mt-2 flex justify-end">
                                    <button
                                        type="button"
                                        @click="removeOwnSongRequest()"
                                        x-bind:disabled="busy"
                                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                                        aria-label="Remove your song request"
                                        title="Remove your song request"
                                    >
                                        <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span>Remove</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
