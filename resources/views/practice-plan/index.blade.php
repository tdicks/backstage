<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-100">Practice Plan</h2>
                <p class="mt-1 text-sm text-slate-300">Your current songs and slots to focus on.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10" x-data="practicePlanPage({ csrfToken: @js(csrf_token()) })" @keydown.escape.window="closeAttachmentsModal()">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if ($practiceSets->isEmpty())
                <section class="rounded-lg border border-dashed border-slate-300 bg-white/80 p-8 text-center text-sm text-slate-600">
                    No practice items yet. Take a slot in a scheduled set and it will show up here.
                </section>
            @else
                @php
                    $slotOptions = \App\Models\Slot::options();
                    $practiceSetsBySession = $practiceSets->groupBy(fn (array $group): string => (string) ($group['set']->session?->id ?? 'unscheduled'));
                @endphp
                @foreach ($practiceSetsBySession as $sessionSets)
                    @php
                        $session = $sessionSets->first()['set']->session;
                    @endphp
                    <section class="space-y-3">
                        <header class="sticky top-0 z-10 border-b border-slate-700/70 bg-slate-950/85 px-3 py-2 shadow-sm backdrop-blur-sm">
                            <div class="border-l-2 border-sky-400/70 pl-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-100">{{ $session?->name ?? 'Planned set' }}</h3>
                            @if ($session?->date)
                                <p class="mt-0.5 text-xs text-slate-300">{{ $session->date->format('M j, Y') }}</p>
                            @endif
                            </div>
                        </header>

                        @foreach ($sessionSets as $group)
                            @php
                                $set = $group['set'];
                            @endphp
                            <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                                            <span>{{ $set->name }}</span>
                                        </h4>
                                        <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                                            <span class="inline-flex items-center gap-1.5" title="Set owner">
                                                <x-heroicon-m-user class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                                <span class="sr-only">Set owner</span>
                                                <span>{{ $set->owner?->name ?? 'Unknown' }}</span>
                                            </span>
                                            <x-sets.status-strip
                                                status="scheduled"
                                                :show-signups="true"
                                                :signups-open="$set->signups_open"
                                                :show-song-requests="true"
                                                :song-requests-open="$set->song_requests"
                                                :show-free-for-all="true"
                                                :free-for-all="$set->free_for_all"
                                                :show-hidden="true"
                                                :hidden="$set->is_hidden"
                                                :show-attachments="true"
                                                :attachment-button="true"
                                                :has-attachments="(int) ($set->attachments_count ?? 0) > 0"
                                                attachment-click="openAttachmentsFor($el)"
                                                attachment-title="Set attachments"
                                                attachment-button-class="inline-flex items-center rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400"
                                                attachment-button-class-expr="hasAttachments('set-{{ $set->id }}', {{ (int) ($set->attachments_count ?? 0) }}) ? 'opacity-100' : 'opacity-35'"
                                                attachment-context="Set: {{ $set->name }}"
                                                attachment-key="set-{{ $set->id }}"
                                                :attachment-count="(int) ($set->attachments_count ?? 0)"
                                                :attachment-list-url="route('sets.attachments.index', $set)"
                                                :attachment-store-url="route('sets.attachments.store', $set)"
                                            />
                                        </div>
                                    </div>
                                    @if ($set->session)
                                        <a href="{{ route('sessions.show', $set->session) }}#set-{{ $set->id }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                            Open set
                                            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                                        </a>
                                    @endif
                                </div>

                                <div class="mt-3 space-y-3">
                                    @foreach ($group['songs'] as $songGroup)
                                        @php
                                            $song = $songGroup['song'];
                                        @endphp
                                        <article class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                            <div class="flex items-start justify-between gap-3">
                                                <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                                    <span>{{ $song->artist }} - {{ $song->title }}</span>
                                                    <button
                                                        type="button"
                                                        @click="openAttachmentsFor($el)"
                                                        data-attachment-context="Song: {{ $song->artist }} - {{ $song->title }}"
                                                        data-attachment-key="song-{{ $song->id }}"
                                                        data-attachment-count="{{ (int) ($song->attachments_count ?? 0) }}"
                                                        data-attachment-list-url="{{ route('songs.attachments.index', $song) }}"
                                                        data-attachment-store-url="{{ route('songs.attachments.store', $song) }}"
                                                        :class="hasAttachments('song-{{ $song->id }}', {{ (int) ($song->attachments_count ?? 0) }}) ? 'opacity-100' : 'opacity-35'"
                                                        class="inline-flex items-center rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400"
                                                        aria-label="Open song attachments"
                                                        title="Song attachments"
                                                    >
                                                        <x-heroicon-m-paper-clip class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                </p>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                                @foreach ($songGroup['mySlots'] as $slot)
                                                    @php
                                                        $slotLabel = $slotOptions[$slot->name] ?? str((string) $slot->name)->replace('_', ' ')->title()->toString();
                                                    @endphp
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50/90 px-2.5 py-1 text-sky-800">
                                                        <span>{{ $slotLabel }}</span>
                                                        <button
                                                            type="button"
                                                            @click="openAttachmentsFor($el)"
                                                            data-attachment-context="Slot: {{ $slotLabel }} ({{ $song->artist }} - {{ $song->title }})"
                                                            data-attachment-key="slot-{{ $slot->id }}"
                                                            data-attachment-count="{{ (int) ($slot->attachments_count ?? 0) }}"
                                                            data-attachment-list-url="{{ route('slots.attachments.index', $slot) }}"
                                                            data-attachment-store-url="{{ route('slots.attachments.store', $slot) }}"
                                                            :class="hasAttachments('slot-{{ $slot->id }}', {{ (int) ($slot->attachments_count ?? 0) }}) ? 'opacity-100' : 'opacity-35'"
                                                            class="inline-flex items-center rounded-md p-0.5 text-slate-500 transition hover:bg-white hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400"
                                                            aria-label="Open slot attachments"
                                                            title="Slot attachments"
                                                        >
                                                            <x-heroicon-m-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </section>
                @endforeach
            @endif
        </div>

        @include('components.sessions.attachments-modal')
    </div>
</x-app-layout>
