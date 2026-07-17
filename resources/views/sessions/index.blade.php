<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data="{ openCreate: false }">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Heavy Jams Sessions
            </h2>
            @can('create', App\Models\JamSession::class)
                <x-secondary-button @click="openCreate = true">New Session</x-secondary-button>

                <div x-show="openCreate" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openCreate = false"></div>
                <div x-show="openCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 shadow-2xl">
                        <h3 class="text-lg font-semibold text-slate-900">Create Jam Session</h3>
                        <form method="POST" action="{{ route('sessions.store') }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="name" value="Name" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                            </div>
                            <div>
                                <x-input-label for="date" value="Date" />
                                <x-text-input id="date" type="date" name="date" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                            </div>
                            <div>
                                <x-input-label for="description" value="Description (Markdown)" />
                                <textarea id="description" name="description" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <x-secondary-button type="button" @click="openCreate = false">Cancel</x-secondary-button>
                                <x-primary-button>Create</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
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
