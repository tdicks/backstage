<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Signups') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p class="text-gray-700">
                    These are the sets where you have a confirmed slot assignment.
                </p>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">Slot proposals for you</h3>
                    <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                        {{ $slotProposals->count() }} pending
                    </span>
                </div>

                @if ($slotProposals->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No pending slot proposals right now.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($slotProposals as $proposal)
                            @php
                                $set = $proposal->slot->song->set;
                                $session = $set->session;
                            @endphp
                            <article class="rounded-md border border-indigo-100 bg-indigo-50/50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            {{ $proposal->slot->song->artist }} - {{ $proposal->slot->song->title }}
                                        </p>
                                        <p class="text-sm text-gray-700">
                                            {{ $session->name }} · {{ $session->date->format('D, M j, Y') }} · {{ $set->name }}
                                        </p>
                                        <p class="mt-1 text-sm text-gray-700">
                                            Slot: {{ ucfirst(str_replace('_', ' ', $proposal->slot->name)) }} · Proposed by {{ $proposal->actor->name }}
                                        </p>
                                        @if ($proposal->message)
                                            <p class="mt-2 text-sm text-gray-600">{{ $proposal->message }}</p>
                                        @endif
                                    </div>

                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('slot-assignments.respond', $proposal) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="accepted">
                                            <x-primary-button>Accept</x-primary-button>
                                        </form>
                                        <form method="POST" action="{{ route('slot-assignments.respond', $proposal) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <x-secondary-button>Decline</x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @forelse ($signedUpSets as $group)
                @php
                    $set = $group['set'];
                    $isPerformed = $set->performed;
                    $cardClasses = $isPerformed
                        ? 'border-gray-200 bg-gray-100 text-gray-500'
                        : 'border-gray-200 bg-white text-gray-900';
                @endphp

                <section class="rounded-lg border p-6 shadow-sm {{ $cardClasses }}">
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
                        @foreach ($group['songs'] as $songGroup)
                            @php
                                $song = $songGroup['song'];
                            @endphp

                            <article class="rounded-md border border-gray-200 bg-white/60 p-4">
                                <h4 class="font-semibold text-gray-900">{{ $song->artist }} - {{ $song->title }}</h4>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($songGroup['slots'] as $slot)
                                        <div class="inline-flex items-center gap-2 rounded-full bg-slate-900/90 px-3 py-1 text-xs font-medium text-white">
                                            <span class="uppercase tracking-wide text-white/70">Slot</span>
                                            <span>{{ ucfirst(str_replace('_', ' ', $slot->name)) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    You have not signed up for any slots yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
