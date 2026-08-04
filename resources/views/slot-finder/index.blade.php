<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="mt-1 text-2xl font-semibold text-slate-100">Find a Slot</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                Find a free slot quickly, without scrolling through the listings!
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="space-y-4">
                @forelse ($sessionGroups as $sessionGroup)
                    @php
                        $session = $sessionGroup['session'];
                        $sessionKey = 'backstage:u'.auth()->id().':slot-finder:session:'.$session->id;
                        $hiddenCardClass = 'border-sky-400 bg-white/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]';
                        $sessionCardClass = $session->is_hidden
                            ? $hiddenCardClass
                            : 'border-slate-200 bg-slate-50/95 shadow-sm';
                    @endphp
                    <article
                        x-data="{
                            collapsed: false,
                            sessionKey: @js($sessionKey),
                            toggle() {
                                this.collapsed = ! this.collapsed;
                            },
                        }"
                        x-init="collapsed = localStorage.getItem(sessionKey) === '1'"
                        x-effect="localStorage.setItem(sessionKey, collapsed ? '1' : '0')"
                        class="rounded-xl border {{ $sessionCardClass }} p-5 sm:p-6"
                    >
                        <div
                            class="flex cursor-pointer items-start justify-between gap-4"
                            x-bind:aria-expanded="(!collapsed).toString()"
                            aria-label="Toggle session details"
                            role="button"
                            @click="toggle()"
                            @keydown.enter.prevent="toggle()"
                            @keydown.space.prevent="toggle()"
                        >
                            <div class="min-w-0 flex-1">
                                <h3 class="flex items-center gap-2 text-xl font-semibold text-slate-900">
                                    <span class="truncate">{{ $session->name }}</span>
                                    <span class="inline-flex shrink-0 items-center text-slate-500">
                                        <x-heroicon-m-chevron-up x-show="!collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                                        <x-heroicon-m-chevron-down x-show="collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    @if ($session->is_hidden)
                                        <span class="inline-flex shrink-0 items-center" title="Jam session is hidden from non-admin users">
                                            <x-heroicon-m-eye-slash class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                            <span class="sr-only">Hidden jam session</span>
                                        </span>
                                    @endif
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $session->date->format('l, F j, Y') }}</p>
                            </div>
                            <a
                                href="{{ route('sessions.show', $session) }}"
                                @click.stop
                                class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                aria-label="Go to session"
                                title="Go to session"
                            >
                                <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>

                        <div x-show="!collapsed" x-cloak x-transition class="mt-5 space-y-4">
                            @foreach ($sessionGroup['sets'] as $setGroup)
                                @php
                                    $set = $setGroup['set'];
                                    $setKey = 'backstage:u'.auth()->id().':slot-finder:set:'.$set->id;
                                    $setCardClass = $set->is_hidden
                                        ? $hiddenCardClass
                                        : 'border-slate-200 bg-white/95 shadow-sm';
                                @endphp
                                <article
                                    data-tour="find-a-slot-card"
                                    x-data="{
                                        collapsed: false,
                                        setKey: @js($setKey),
                                        remainingSongs: @js($setGroup['songs']->count()),
                                        removed: false,
                                        removing: false,
                                        toggle() {
                                            this.collapsed = ! this.collapsed;
                                        },
                                        removeSet() {
                                            if (this.removed || this.removing) {
                                                return;
                                            }

                                            this.removing = true;
                                            window.setTimeout(() => {
                                                this.removed = true;
                                            }, 280);
                                        },
                                    }"
                                    x-init="collapsed = localStorage.getItem(setKey) === '1'"
                                    x-effect="localStorage.setItem(setKey, collapsed ? '1' : '0')"
                                    x-on:slot-finder-song-removed.window="if ($event.detail.setKey === setKey) { remainingSongs -= 1; if (remainingSongs <= 0) { removeSet() } }"
                                    x-show="!removed"
                                    x-bind:class="removing ? 'opacity-0 translate-y-2 scale-[0.98] pointer-events-none' : ''"
                                    x-transition.opacity.duration.200ms
                                    class="rounded-xl border {{ $setCardClass }} p-4 transition-all duration-300 ease-out"
                                >
                                    <div
                                        class="flex cursor-pointer items-start justify-between gap-3"
                                        x-bind:aria-expanded="(!collapsed).toString()"
                                        aria-label="Toggle set details"
                                        role="button"
                                        tabindex="0"
                                        @click="toggle()"
                                        @keydown.enter.prevent="toggle()"
                                        @keydown.space.prevent="toggle()"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <h4 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                                                <span class="truncate">{{ $set->name }}</span>
                                                <span class="inline-flex shrink-0 items-center text-slate-500">
                                                    <x-heroicon-m-chevron-up x-show="!collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                                                    <x-heroicon-m-chevron-down x-show="collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                                                </span>
                                                @if ($set->free_for_all)
                                                    <span class="inline-flex items-center" title="Free for all mode">
                                                        <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                                                        <span class="sr-only">Free for all mode</span>
                                                    </span>
                                                @endif
                                                @if ($set->is_hidden)
                                                    <span class="inline-flex shrink-0 items-center" title="Hidden set">
                                                        <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                                        <span class="sr-only">Hidden set</span>
                                                    </span>
                                                @endif
                                            </h4>
                                            <p class="mt-1 text-sm text-slate-600">By {{ $set->owner->name }}</p>
                                        </div>

                                        <a
                                            href="{{ route('sessions.show', $session).'#set-'.$set->id }}"
                                            @click.stop
                                            class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                            aria-label="Go to set"
                                            title="Go to set"
                                        >
                                            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                                        </a>
                                    </div>

                                    <div x-show="!collapsed" x-cloak x-transition class="mt-4 space-y-3">
                                        @foreach ($setGroup['songs'] as $songGroup)
                                            @php
                                                $song = $songGroup['song'];
                                                $songKey = 'backstage:u'.auth()->id().':slot-finder:song:'.$song->id;
                                            @endphp
                                            <article
                                                x-data="slotFinderSongCard(@js([
                                                    'songKey' => $songKey,
                                                    'setKey' => $setKey,
                                                ]))"
                                                x-show="!removed"
                                                x-bind:class="removing ? 'opacity-0 translate-y-2 scale-[0.98] pointer-events-none' : ''"
                                                x-transition.opacity.duration.200ms
                                                class="relative rounded-lg border border-slate-200 bg-slate-50/80 p-3 transition-all duration-300 ease-out"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <h5 class="text-sm font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</h5>
                                                </div>

                                                <div class="mt-3 flex flex-wrap gap-2" data-tour="find-a-slot-slots">
                                                    @foreach ($songGroup['slots'] as $slot)
                                                        @php
                                                            $slotLabel = $slotOptions[$slot->name] ?? str($slot->name)->replace('_', ' ')->title()->toString();
                                                        @endphp
                                                        @php($pendingRequestUrl = $slot->pending_request_id ? route('slot-assignments.respond', $slot->pending_request_id) : null)
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
                                                            class="inline-flex w-fit max-w-full cursor-pointer select-none items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-xs transition-all duration-300 ease-out hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400"
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

                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="truncate font-semibold text-slate-900">{{ $slotLabel }}</span>
                                                                </div>
                                                            </div>

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
                                                        </article>
                                                    @endforeach
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center shadow-sm">
                        <x-heroicon-m-musical-note class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" />
                        <h3 class="mt-4 text-lg font-semibold text-slate-800">No open slots right now</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Try again later, or update your slot coverage so matching parts are easier to spot.
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            <a href="{{ route('sessions.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                Browse jam sessions
                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                            </a>
                            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                Update slot coverage
                                <x-heroicon-m-adjustments-horizontal class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>