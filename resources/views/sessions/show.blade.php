<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ openEditSession: false, openSet: false }" @keydown.escape.window="openEditSession = false; openSet = false">
            <div>
                <h2 class="flex items-center gap-2 text-xl font-semibold text-slate-100">
                    <span>{{ $session->name }}</span>
                    @if ($session->is_closed)
                        <x-heroicon-m-lock-closed class="h-6 w-6 text-amber-400" aria-hidden="true" title="This jam is closed to new sets" />
                    @endif
                    @if ($session->is_hidden)
                        <x-heroicon-m-eye-slash class="h-6 w-6 text-sky-400" aria-hidden="true" title="This jam is hidden from non-admin users" />
                    @endif
                </h2>
                <p class="text-sm text-gray-500">{{ $session->date->format('l, F j, Y') }}</p>
            </div>

            <div class="ml-auto flex items-center gap-2">
                @can('update', $session)
                    <x-secondary-button @click="openEditSession = true">Edit Session</x-secondary-button>
                    <x-secondary-button @click="$dispatch('open-who-is-here')">Who's Here</x-secondary-button>
                @endcan
                @if (auth()->user()->is_admin || ! $session->is_closed)
                    <x-primary-button @click="openSet = true">Create Set</x-primary-button>
                @endif
            </div>

            @can('update', $session)
                <template x-teleport="body">
                    <div x-show="openEditSession" x-cloak>
                        <div class="fixed inset-0 z-40 bg-black/40" @click="openEditSession = false"></div>
                        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                                <h3 class="text-lg font-semibold text-slate-900">Edit Jam Session</h3>
                                <form method="POST" action="{{ route('sessions.update', $session) }}" class="mt-5 space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                        <x-input-label for="session_name" value="Name" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <x-text-input id="session_name" name="name" :value="$session->name" class="mt-2 block w-full border-slate-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500" required />
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                        <x-input-label for="session_date" value="Date" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <x-text-input id="session_date" type="date" name="date" :value="$session->date->toDateString()" class="mt-2 block w-full border-slate-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500" required />
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                        <x-input-label for="session_description" value="Description (Markdown)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <textarea id="session_description" name="description" rows="6" class="mt-2 w-full rounded-md border-slate-300 bg-white shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ $session->description }}</textarea>
                                    </div>
                                    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-4">
                                        <input type="hidden" name="is_closed" value="0">
                                        <label for="session_is_closed" class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                id="session_is_closed"
                                                type="checkbox"
                                                name="is_closed"
                                                value="1"
                                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                                @checked($session->is_closed)
                                            >
                                            <span>Close this jam session (prevent new sets)</span>
                                        </label>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                        <input type="hidden" name="is_hidden" value="0">
                                        <label for="session_is_hidden" class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                id="session_is_hidden"
                                                type="checkbox"
                                                name="is_hidden"
                                                value="1"
                                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                                @checked($session->is_hidden)
                                            >
                                            <span>Hide this jam session from non-admin users</span>
                                        </label>
                                    </div>
                                    <div class="flex justify-end gap-3 pt-1">
                                        <x-modal-secondary-button type="button" @click="openEditSession = false">Cancel</x-modal-secondary-button>
                                        <x-modal-primary-button>Save</x-modal-primary-button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('sessions.destroy', $session) }}" class="mt-4">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button type="submit">Delete Session</x-danger-button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            @endcan

            @if (auth()->user()->is_admin || ! $session->is_closed)
                <template x-teleport="body">
                    <div x-show="openSet" x-cloak>
                        <div class="fixed inset-0 z-40 bg-black/40" @click="openSet = false"></div>
                        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                                <h3 class="text-lg font-semibold text-slate-900">New Set for {{ $session->name }}</h3>
                                <form method="POST" action="{{ route('sets.store', $session) }}" class="mt-4 space-y-4">
                                    @csrf
                                    <div>
                                        <x-input-label for="set_name" value="Set Name" />
                                        <x-text-input id="set_name" name="name" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="set_description" value="Description" />
                                        <textarea id="set_description" name="description" rows="4" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-3">
                                        <x-modal-secondary-button type="button" @click="openSet = false">Cancel</x-modal-secondary-button>
                                        <x-modal-primary-button>Create Set</x-modal-primary-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div
            class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
            x-data="lazySessionSets('{{ route('sessions.sets', $session) }}')"
            @refresh-session-sets.window="refresh()"
        >
            @if ($session->description)
                <div class="session-markdown rounded-lg bg-slate-50 p-6 shadow-sm">
                    {!! Illuminate\Support\Str::markdown($session->description) !!}
                </div>
            @endif

            @if ($session->sets_count > 0)
                <div class="space-y-4" x-show="!loaded" x-cloak>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-amber-400"></div>
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $loadingOneLiner }}</p>
                            </div>
                        </div>
                    </div>

                    @for ($i = 0; $i < min($session->sets_count, 3); $i++)
                        <div class="animate-pulse rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                            <div class="h-5 w-48 rounded bg-slate-200"></div>
                            <div class="mt-3 h-4 w-80 rounded bg-slate-200"></div>
                            <div class="mt-6 space-y-3">
                                <div class="h-10 rounded bg-slate-200"></div>
                                <div class="h-10 rounded bg-slate-200"></div>
                                <div class="h-10 rounded bg-slate-200"></div>
                            </div>
                        </div>
                    @endfor
                </div>
                <div x-show="refreshing && loaded" x-cloak class="rounded-lg border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-600 shadow-sm">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-amber-400"></span>
                        Updating session content...
                    </span>
                </div>
                <p x-show="error" x-text="error" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" x-cloak></p>
                <div x-ref="setsContainer" x-show="loaded" x-cloak></div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No sets for this jam session yet.
                </div>
            @endif
        </div>
    </div>

    @can('update', $session)
        <x-sessions.who-is-here-modal :session="$session" />
    @endcan
</x-app-layout>
