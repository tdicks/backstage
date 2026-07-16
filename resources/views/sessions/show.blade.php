<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ openEditSession: false, openSet: false }" @keydown.escape.window="openEditSession = false; openSet = false">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $session->name }}</h2>
                <p class="text-sm text-gray-500">{{ $session->date->format('l, F j, Y') }}</p>
            </div>

            <div class="flex items-center gap-2">
                @can('update', $session)
                    <x-secondary-button @click="openEditSession = true">Edit Session</x-secondary-button>
                @endcan
                <x-secondary-button @click="openSet = true">Create Set</x-secondary-button>
            </div>

            @can('update', $session)
                <div x-show="openEditSession" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSession = false"></div>
                <div x-show="openEditSession" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold">Edit Jam Session</h3>
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
            @endcan

            <div x-show="openSet" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSet = false"></div>
            <div x-show="openSet" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold">New Set for {{ $session->name }}</h3>
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
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if ($session->description)
                <div class="session-markdown rounded-lg bg-white p-6 shadow-sm">
                    {!! Illuminate\Support\Str::markdown($session->description) !!}
                </div>
            @endif

            @forelse ($session->sets as $set)
                <x-sessions.set-card
                    :set="$set"
                    :sessions="$sessions"
                    :users="$users"
                    :templates="$templates"
                    :slot-options="$slotOptions"
                />
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No sets for this jam session yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
