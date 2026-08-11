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
                @foreach ($practiceSets as $group)
                    @php
                        $set = $group['set'];
                        $slotOptions = \App\Models\Slot::options();
                    @endphp
                    <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                                    <span>{{ $set->name }}</span>
                                    <button
                                        type="button"
                                        @click="openAttachmentsFor($el)"
                                        data-attachment-context="Set: {{ $set->name }}"
                                        data-attachment-key="set-{{ $set->id }}"
                                        data-attachment-count="{{ (int) ($set->attachments_count ?? 0) }}"
                                        data-attachment-list-url="{{ route('sets.attachments.index', $set) }}"
                                        data-attachment-store-url="{{ route('sets.attachments.store', $set) }}"
                                        :class="hasAttachments('set-{{ $set->id }}', {{ (int) ($set->attachments_count ?? 0) }}) ? 'opacity-100' : 'opacity-35'"
                                        class="inline-flex items-center rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400"
                                        aria-label="Open set attachments"
                                        title="Set attachments"
                                    >
                                        <x-heroicon-m-paper-clip class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $set->session?->name ?? 'Planned set' }}</p>
                            </div>
                            @if ($set->session)
                                <a href="{{ route('sessions.show', $set->session) }}#set-{{ $set->id }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                    Open set
                                    <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                                </a>
                            @endif
                        </div>

                        <div class="mt-3 space-y-2">
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
            @endif
        </div>

        @include('components.sessions.attachments-modal')
    </div>
</x-app-layout>
