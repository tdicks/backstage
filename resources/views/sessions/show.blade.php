<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ openEditSession: false, openSet: false }" @keydown.escape.window="openEditSession = false; openSet = false">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">{{ $session->name }}</h2>
                <p class="text-sm text-gray-500">{{ $session->date->format('l, F j, Y') }}</p>
            </div>

            <div class="ml-auto flex items-center gap-2">
                @can('update', $session)
                    <x-secondary-button @click="openEditSession = true">Edit Session</x-secondary-button>
                    <x-secondary-button @click="$dispatch('open-who-is-here')">Who's Here</x-secondary-button>
                @endcan
                <x-secondary-button @click="openSet = true">Create Set</x-secondary-button>
            </div>

            @can('update', $session)
                <template x-teleport="body">
                    <div x-show="openEditSession" x-cloak>
                        <div class="fixed inset-0 z-40 bg-black/40" @click="openEditSession = false"></div>
                        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-xl rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                                <h3 class="text-lg font-semibold text-slate-900">Edit Jam Session</h3>
                                <form method="POST" action="{{ route('sessions.update', $session) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <x-input-label for="session_name" value="Name" />
                                        <x-text-input id="session_name" name="name" :value="$session->name" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="session_date" value="Date" />
                                        <x-text-input id="session_date" type="date" name="date" :value="$session->date->toDateString()" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="session_description" value="Description (Markdown)" />
                                        <textarea id="session_description" name="description" rows="6" class="mt-1 w-full rounded-md border-gray-300">{{ $session->description }}</textarea>
                                    </div>
                                    <div class="flex justify-end gap-3">
                                        <x-secondary-button type="button" @click="openEditSession = false">Cancel</x-secondary-button>
                                        <x-primary-button>Save</x-primary-button>
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
                                    <x-secondary-button type="button" @click="openSet = false">Cancel</x-secondary-button>
                                    <x-primary-button>Create Set</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
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
