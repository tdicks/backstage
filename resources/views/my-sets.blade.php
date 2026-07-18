<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-100 leading-tight">
            {{ __('My Sets') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                <p class="text-slate-700">
                    These are the sets you currently own and any pending slot approvals for them.
                </p>
            </div>

            <section
                class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm"
                x-data="{ pendingCount: {{ $pendingSlotApprovals->count() }} }"
                @pending-approval-processed.window="pendingCount = Math.max(0, pendingCount - 1)"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">Pending slot approvals</h3>
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800" x-text="`${pendingCount} pending`">
                        {{ $pendingSlotApprovals->count() }} pending
                    </span>
                </div>

                @if ($pendingSlotApprovals->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No pending slot approvals right now.</p>
                @else
                    <div class="mt-4 space-y-3" x-show="pendingCount > 0">
                        @foreach ($pendingSlotApprovals as $approval)
                            @php
                                $set = $approval->slot->song->set;
                                $session = $set->session;
                                $slotLabel = ucfirst(str_replace('_', ' ', $approval->slot->name));
                                $targetName = $approval->target->name;
                                $actorName = $approval->actor->name;
                            @endphp

                            <article
                                class="rounded-md border border-amber-100 bg-amber-50/50 p-4"
                                x-data="{
                                    hidden: false,
                                    busy: false,
                                    error: '',
                                    async respond(status) {
                                        this.busy = true;
                                        this.error = '';

                                        try {
                                            const response = await fetch('{{ route('slot-assignments.respond', $approval) }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                },
                                                body: JSON.stringify({
                                                    _method: 'PATCH',
                                                    status,
                                                }),
                                            });

                                            if (!response.ok) {
                                                throw new Error('Request failed');
                                            }

                                            this.hidden = true;
                                            window.dispatchEvent(new CustomEvent('pending-approval-processed'));
                                        } catch (e) {
                                            this.error = 'Could not update approval. Try again.';
                                        } finally {
                                            this.busy = false;
                                        }
                                    },
                                }"
                                x-show="!hidden"
                                x-transition
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            {{ $approval->slot->song->artist }} - {{ $approval->slot->song->title }}
                                        </p>
                                        <p class="text-sm text-gray-700">
                                            {{ $session->name }} · {{ $session->date->format('D, M j, Y') }} · {{ $set->name }}
                                        </p>
                                        <p class="mt-1 text-sm text-gray-700">Slot: {{ $slotLabel }}</p>

                                        @if ($approval->type === \App\Models\SlotAssignment::TYPE_REQUEST)
                                            <p class="mt-1 text-sm text-gray-700">{{ $actorName }} requested this slot.</p>
                                        @else
                                            <p class="mt-1 text-sm text-gray-700">{{ $actorName }} recommended {{ $targetName }} for this slot.</p>
                                        @endif

                                        @if ($approval->message)
                                            <p class="mt-2 text-sm text-gray-600">{{ $approval->message }}</p>
                                        @endif

                                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-700"></p>
                                    </div>

                                    <div class="flex gap-2">
                                        <x-primary-button type="button" @click="respond('accepted')" x-bind:disabled="busy">Approve</x-primary-button>
                                        <x-secondary-button type="button" @click="respond('rejected')" x-bind:disabled="busy">Reject</x-secondary-button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm text-gray-500" x-show="pendingCount === 0" x-cloak>No pending slot approvals right now.</p>
                @endif
            </section>

            @forelse ($ownedSets as $set)
                @php
                    $isPerformed = $set->performed;
                    $cardClasses = $isPerformed
                        ? 'border-slate-200 bg-slate-100/95 text-slate-500'
                        : 'border-slate-200 bg-slate-50/95 text-slate-900';
                @endphp

                <section class="rounded-xl border p-6 shadow-sm {{ $cardClasses }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold">{{ $set->name }}</h3>
                                @if ($isPerformed)
                                    <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Performed</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Upcoming</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm {{ $isPerformed ? 'text-gray-500' : 'text-gray-600' }}">
                                {{ $set->session->name }} · {{ $set->session->date->format('D, M j, Y') }}
                            </p>
                            @if ($set->description)
                                <p class="mt-2 text-sm {{ $isPerformed ? 'text-gray-500' : 'text-gray-700' }}">{{ $set->description }}</p>
                            @endif
                        </div>

                        <a href="{{ route('sessions.show', $set->session) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                            View session
                        </a>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($set->songs as $song)
                            <article class="rounded-md border border-gray-200 bg-white/60 p-4">
                                <h4 class="font-semibold text-gray-900">{{ $song->artist }} - {{ $song->title }}</h4>
                                @if ($song->notes)
                                    <p class="mt-1 text-sm text-gray-600">{{ $song->notes }}</p>
                                @endif
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($song->slots as $slot)
                                        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold shadow-sm {{ $slot->user_id === auth()->id() ? 'border-sky-200 bg-sky-50/90 text-sky-800' : ($slot->isOpen() ? 'border-amber-200 bg-amber-50/80 text-amber-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800') }}">
                                            <span>{{ ucfirst(str_replace('_', ' ', $slot->name)) }}</span>
                                            <span>-</span>
                                            <span>{{ $slot->assignedPerformerName() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">No songs in this set yet.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    You do not own any sets yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
