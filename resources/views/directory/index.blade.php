<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">User Directory</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('directory.index') }}" class="flex flex-wrap gap-3">
                    <input
                        type="text"
                        name="q"
                        value="{{ $query }}"
                        placeholder="Search by name or bio"
                        class="min-w-64 flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <x-primary-button>Search</x-primary-button>
                    @if ($query !== '')
                        <a href="{{ route('directory.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            @forelse ($users as $user)
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $user->name }}</h3>
                        @if ($user->is_admin)
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-slate-700">Admin</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-gray-700">{{ $user->bio ?: 'No bio yet.' }}</p>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No users matched your search.
                </div>
            @endforelse

            <div>
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>