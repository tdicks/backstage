<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data="{ openCreate: false }">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                Heavy Jams Sessions
            </h2>
            @can('create', App\Models\JamSession::class)
                <x-secondary-button @click="openCreate = true">New Session</x-secondary-button>
                <template x-teleport="body">
                    <div x-show="openCreate" x-cloak>
                        <div class="fixed inset-0 z-40 bg-black/40" @click="openCreate = false"></div>
                        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                                <h3 class="text-lg font-semibold">Create Jam Session</h3>
                                <form method="POST" action="{{ route('sessions.store') }}" class="mt-4 space-y-4">
                                    @csrf
                                    <div>
                                        <x-input-label for="name" value="Name" />
                                        <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="date" value="Date" />
                                        <x-text-input id="date" type="date" name="date" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="description" value="Description (Markdown)" />
                                        <textarea id="description" name="description" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                                    </div>
                                    <div>
                                        <input type="hidden" name="is_hidden" value="0">
                                        <label for="is_hidden" class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                id="is_hidden"
                                                type="checkbox"
                                                name="is_hidden"
                                                value="1"
                                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                            >
                                            <span>Hide this jam session from non-admin users</span>
                                        </label>
                                    </div>
                                    <div class="flex justify-end gap-3">
                                        <x-modal-secondary-button type="button" @click="openCreate = false">Cancel</x-modal-secondary-button>
                                        <x-modal-primary-button>Create</x-modal-primary-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid gap-4">
                @forelse ($sessions as $session)
                    <a href="{{ route('sessions.show', $session) }}" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $session->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $session->date->format('D, M j, Y') }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                {{ $session->sets_count }} sets
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                        No jam sessions yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
