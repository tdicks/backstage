<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-300">Backstage</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-100">Welcome back, {{ auth()->user()->name }}</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
                <div class="space-y-6">
                    @if ($showGetStartedQuest)
                        <section
                            id="get-started-quest"
                            x-data="getStartedQuest()"
                            x-show="visible"
                            x-transition.opacity.duration.300ms
                            aria-labelledby="get-started-heading"
                            class="rounded-xl border-2 {{ $allGetStartedItemsCompleted ? 'border-emerald-200' : 'border-amber-200' }} bg-white/95 p-5 shadow-sm transition duration-300 ease-out sm:p-6"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 id="get-started-heading" class="text-xl font-semibold text-slate-900">Get started</h2>
                                    <p class="mt-1 text-sm text-slate-600">Here's three things you can do to get stuck in!</p>
                                </div>
                                <form method="POST" action="{{ route('dashboard.get-started.dismiss') }}" class="get-started-dismiss-form" x-on:submit.prevent="dismiss($event)">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400 {{ $allGetStartedItemsCompleted ? 'get-started-dismiss-glow border-emerald-300 text-emerald-600' : '' }}"
                                        aria-label="Dismiss get started guide"
                                    >
                                        <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </form>
                            </div>

                            <ul class="mt-4 space-y-2 text-sm text-slate-700">
                                @foreach ($getStartedItems as $item)
                                    <li class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                                        @if ($item['completed'])
                                            <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
                                        @else
                                            <x-heroicon-m-arrow-right-circle class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ $item['href'] }}" class="font-medium text-slate-900 hover:text-sky-700">{{ $item['label'] }}</a>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $item['description'] }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($allGetStartedItemsCompleted)
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
                                    <p class="font-medium">You're all set.</p>
                                    <p class="mt-1 text-emerald-700/90">Happy jamming! You're welcome to close this window.</p>
                                </div>
                            @endif
                        </section>
                    @endif

                    <section aria-labelledby="next-jam-heading" class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 id="next-jam-heading" class="text-xl font-semibold text-slate-900">Your commitments</h2>
                            </div>
                            <a href="{{ route('my-sets.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                My Sets
                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>

                    @if ($nextSession)
                        <div class="mt-5 rounded-xl border border-sky-300 bg-sky-100 p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-800">{{ $nextSession->date->format('l, F j') }}</p>
                                    <h3 data-next-session-name class="mt-2 text-xl font-semibold text-slate-900">{{ $nextSession->name }}</h3>
                                </div>
                                <a href="{{ route('sessions.show', $nextSession) }}" class="inline-flex items-center gap-1.5 rounded-md border border-sky-400 bg-white px-3 py-2 text-sm font-semibold text-sky-900 transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                    Open session
                                    <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
                                </a>
                            </div>

                            <div class="mt-6 grid gap-3">
                                @foreach ($nextSessionSets as $set)
                                    <a href="{{ route('sessions.show', $nextSession).'#set-'.$set->id }}" class="group flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3 transition hover:border-sky-400 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                        <div class="min-w-0">
                                            <h4 class="truncate font-semibold text-slate-900">{{ $set->name }}</h4>
                                            <ul class="mt-1 list-none space-y-1 text-sm text-slate-600">
                                                @foreach ($set->songs as $song)
                                                    @php
                                                        $slotNames = $song->slots->pluck('name')
                                                            ->filter()
                                                            ->map(fn ($slotName) => $slotLabels[$slotName] ?? str((string) $slotName)->replace(['_', '-'], ' ')->title()->toString())
                                                            ->join(', ');
                                                    @endphp
                                                    <li>{{ $slotNames !== '' ? $slotNames.' on ' : '' }}{{ $song->artist }} - {{ $song->title }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <x-heroicon-m-chevron-right class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:text-sky-700" aria-hidden="true" />
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center">
                            <x-heroicon-m-musical-note class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" />
                            <p class="mt-4 text-lg font-semibold text-slate-800">No upcoming slots yet</p>
                            <p class="mt-2 text-sm text-slate-600">Browse the jam sessions to find a set that needs you.</p>
                            <a href="{{ route('sessions.index') }}" class="mt-5 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                Browse jam sessions
                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    @endif
                </section>
                </div>

                <aside class="self-start rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">Quick links</h3>

                    <nav class="mt-4 space-y-2" aria-label="Dashboard quick links">
                        <a href="{{ route('sessions.index') }}" class="group flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-400">
                            <span>All jam sessions</span>
                            <x-heroicon-m-arrow-right class="h-4 w-4 text-slate-400 transition group-hover:text-sky-700" aria-hidden="true" />
                        </a>

                        <a href="{{ route('my-sets.index') }}" class="group flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-400">
                            <span>My sets</span>
                            <x-heroicon-m-arrow-right class="h-4 w-4 text-slate-400 transition group-hover:text-sky-700" aria-hidden="true" />
                        </a>

                        <a href="{{ route('directory.index') }}" class="group flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-400">
                            <span>Who's who</span>
                            <x-heroicon-m-arrow-right class="h-4 w-4 text-slate-400 transition group-hover:text-sky-700" aria-hidden="true" />
                        </a>

                        <a href="{{ route('profile.edit') }}" class="group flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-400">
                            <span>Profile settings</span>
                            <x-heroicon-m-arrow-right class="h-4 w-4 text-slate-400 transition group-hover:text-sky-700" aria-hidden="true" />
                        </a>
                    </nav>
                </aside>
            </div>
        </div>
    </div>
    <style>
        .get-started-dismiss-glow {
            animation: get-started-glow 2s ease-in-out infinite;
        }

        [x-cloak] {
            display: none !important;
        }

        @keyframes get-started-glow {
            0%, 100% {
                box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.35), 0 0 0 8px rgba(74, 222, 128, 0.15);
            }

            50% {
                box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.45), 0 0 0 10px rgba(74, 222, 128, 0.25);
            }
        }
    </style>
</x-app-layout>