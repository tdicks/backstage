@props(['approvalSessions', 'bandTemplates', 'slotOptions'])

@foreach ($approvalSessions as $approvalSession)
    @php
        $session = $approvalSession['session'];
    @endphp
    <section data-approval-session-id="{{ $session->id }}" class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jam session</p>
                <h4 class="mt-1 text-lg font-semibold text-slate-900">
                    <a href="{{ route('sessions.show', $session) }}" target="_blank" rel="noopener noreferrer" class="underline decoration-slate-300 underline-offset-2 hover:decoration-slate-600">{{ $session->name }}</a>
                </h4>
                <p class="mt-1 text-sm text-slate-600">{{ $session->date->format('D, M j, Y') }}</p>
            </div>
        </div>

        <div class="space-y-4 p-4">
            @foreach ($approvalSession['sets'] as $approvalSet)
                @php
                    $set = $approvalSet['set'];
                @endphp
                <section data-approval-set-id="{{ $set->id }}" class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50/80">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h5 class="text-base font-semibold text-slate-900">
                            <a href="{{ route('sessions.show', $set->session) }}#set-{{ $set->id }}" target="_blank" rel="noopener noreferrer" class="underline decoration-slate-300 underline-offset-2 hover:decoration-slate-600">{{ $set->name }}</a>
                        </h5>
                        <p class="mt-1 text-sm text-slate-600">{{ $set->owner->name }}</p>
                    </div>

                    <div class="space-y-3 p-3">
                        @foreach ($approvalSet['songs'] as $approvalSong)
                            @php
                                $song = $approvalSong['song'];
                            @endphp
                            <section data-approval-song-id="{{ $song->id }}" class="rounded-lg border border-slate-300 bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-heroicon-m-musical-note class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                    <h6 class="font-semibold text-slate-900">
                                        <a href="{{ route('sessions.show', $set->session) }}#song-{{ $song->id }}" target="_blank" rel="noopener noreferrer" class="underline decoration-slate-300 underline-offset-2 hover:decoration-slate-600">{{ $song->artist }} - {{ $song->title }}</a>
                                    </h6>
                                </div>
                                <div class="mt-3 divide-y divide-slate-200">
                                    @foreach ($approvalSong['items'] as $item)
                                        @php
                                            $approval = $item['approval'];
                                            $slotLabel = $slotOptions[$approval->slot->name] ?? str($approval->slot->name)->replace('_', ' ')->title();
                                            $isTargetConsent = $item['type'] === 'target_consent';
                                            $isRecommendation = $approval->type === \App\Models\SlotAssignment::TYPE_PROPOSAL;
                                            $conflictingSlot = \App\Services\SlotCompatibility::conflictingSlotForSlot($approval->target_user_id, $approval->slot);
                                            $conflictingSlotLabel = $conflictingSlot ? ($slotOptions[$conflictingSlot->name] ?? str($conflictingSlot->name)->replace('_', ' ')->title()) : null;
                                        @endphp
                                        <article
                                            class="py-3 first:pt-0 last:pb-0"
                                            x-data="{
                                                hidden: false, busy: false, error: '',
                                                async respond(status) {
                                                    this.busy = true; this.error = '';
                                                    try {
                                                        const response = await fetch('{{ route('slot-assignments.respond', $approval) }}', {
                                                            method: 'POST',
                                                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                                            body: JSON.stringify({ _method: 'PATCH', status }),
                                                        });
                                                        if (!response.ok) { throw new Error('Could not update approval. Try again.'); }
                                                        this.hidden = true;
                                                        window.dispatchEvent(new CustomEvent('{{ $isTargetConsent ? 'target-consent-processed' : 'pending-approval-processed' }}'));
                                                    } catch (e) { this.error = e.message || 'Could not update approval. Try again.'; } finally { this.busy = false; }
                                                },
                                            }"
                                            x-show="!hidden"
                                            x-transition
                                        >
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{{ $isTargetConsent ? 'Recommendation for you' : ($isRecommendation ? 'Recommendation' : 'Slot request') }}</span>
                                                        <p class="text-sm font-semibold text-slate-900">{{ $slotLabel }}</p>
                                                    </div>
                                                    <p class="mt-1 text-sm text-slate-700">
                                                        @if ($isTargetConsent)
                                                            {{ $approval->actor->name }} recommended you for {{ $slotLabel }}.
                                                        @elseif ($isRecommendation)
                                                            {{ $approval->actor->name }} recommended {{ $approval->target->name }} for {{ $slotLabel }}.
                                                        @else
                                                            {{ $approval->actor->name }} requested {{ $slotLabel }}.
                                                        @endif
                                                    </p>
                                                    @if ($approval->message)
                                                        <p class="mt-1 text-sm text-slate-600">{{ $approval->message }}</p>
                                                    @endif
                                                    @if ($conflictingSlot)
                                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">
                                                            @if ($isTargetConsent)
                                                                Accepting this will move you from {{ $conflictingSlotLabel }} to {{ $slotLabel }} on this song.
                                                            @else
                                                                Approving this will move {{ $approval->target->name }} from {{ $conflictingSlotLabel }} to {{ $slotLabel }} on this song.
                                                            @endif
                                                        </p>
                                                    @endif
                                                    <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-700"></p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="button" @click="respond('accepted')" x-bind:disabled="busy" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40" title="Approve"><x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" /><span>{{ $isTargetConsent ? 'Accept' : 'Approve' }}</span></button>
                                                    <button type="button" @click="respond('rejected')" x-bind:disabled="busy" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40" title="Reject"><x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" /><span>Reject</span></button>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        @foreach ($approvalSet['songRequests'] as $item)
                            @php
                                $songRequest = $item['approval'];
                            @endphp
                            <article
                                class="rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-4"
                                x-data="{
                                    hidden: false, busy: false, error: '', bandTemplateId: '',
                                    async respond(status) {
                                        this.busy = true; this.error = '';
                                        try {
                                            const response = await fetch('{{ route('song-requests.respond', $songRequest) }}', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                                body: JSON.stringify({ _method: 'PATCH', status, ...(status === 'accepted' && this.bandTemplateId !== '' ? { band_template_id: Number(this.bandTemplateId) } : {}) }),
                                            });
                                            if (!response.ok) { throw new Error('Could not update song request. Try again.'); }
                                            this.hidden = true; window.dispatchEvent(new CustomEvent('pending-approval-processed'));
                                        } catch (e) { this.error = e.message || 'Could not update song request. Try again.'; } finally { this.busy = false; }
                                    },
                                }"
                                x-show="!hidden"
                                x-transition
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-heroicon-m-plus-circle class="h-4 w-4 text-amber-700" aria-hidden="true" />
                                            <h6 class="font-semibold text-slate-900">{{ $songRequest->artist }} - {{ $songRequest->title }}</h6>
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Song request</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-700">Requested by {{ $songRequest->requester->name }}.</p>
                                        @if ($songRequest->notes)
                                            <p class="mt-1 text-sm text-slate-600">{{ $songRequest->notes }}</p>
                                        @endif
                                        <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-700"></p>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:items-end">
                                        <label class="space-y-1 text-xs font-semibold uppercase tracking-wide text-amber-800">
                                            <span>Band template (optional)</span>
                                            <select name="band_template_id" x-model="bandTemplateId" x-bind:disabled="busy" class="block w-52 rounded-md border-amber-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 shadow-sm focus:border-amber-400 focus:ring-amber-300">
                                                <option value="">No template</option>
                                                @foreach ($bandTemplates as $bandTemplate)
                                                    <option value="{{ $bandTemplate->id }}">{{ $bandTemplate->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <div class="flex gap-2"><button type="button" @click="respond('accepted')" x-bind:disabled="busy" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"><x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" /><span>Approve</span></button><button type="button" @click="respond('rejected')" x-bind:disabled="busy" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"><x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" /><span>Reject</span></button></div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </section>
@endforeach