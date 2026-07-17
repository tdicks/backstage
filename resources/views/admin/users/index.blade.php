<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ __('User Administration') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Search users, update email addresses, and send password reset emails.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end">
                    <div>
                        <label for="q" class="block text-sm font-medium text-gray-700">{{ __('Search') }}</label>
                        <input id="q" name="q" value="{{ $search }}" placeholder="Name or email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700">{{ __('Sort by') }}</label>
                        <select id="sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="name" @selected($sort === 'name')>{{ __('Name') }}</option>
                            <option value="email" @selected($sort === 'email')>{{ __('Email') }}</option>
                            <option value="is_admin" @selected($sort === 'is_admin')>{{ __('Role') }}</option>
                            <option value="created_at" @selected($sort === 'created_at')>{{ __('Joined') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="direction" class="block text-sm font-medium text-gray-700">{{ __('Direction') }}</label>
                        <select id="direction" name="direction" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="asc" @selected($direction === 'asc')>{{ __('Ascending') }}</option>
                            <option value="desc" @selected($direction === 'desc')>{{ __('Descending') }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Apply') }}</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <p class="text-sm text-gray-600">
                        {{ $users->total() }} {{ __('users found') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                @php
                                    $query = request()->except('page');
                                    $nextDirection = $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="px-6 py-3">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'name', 'direction' => $nextDirection])) }}">{{ __('Name') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'email' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="px-6 py-3">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'email', 'direction' => $nextDirection])) }}">{{ __('Email') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'is_admin' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="px-6 py-3">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'is_admin', 'direction' => $nextDirection])) }}">{{ __('Role') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'created_at' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="px-6 py-3">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'created_at', 'direction' => $nextDirection])) }}">{{ __('Joined') }}</a>
                                </th>
                                <th class="px-6 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                        @if ($user->id === auth()->id())
                                            <span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ __('You') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                            @csrf
                                            @method('PATCH')
                                            <input name="email" type="email" value="{{ $user->email }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-md">
                                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                                        </form>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if ($user->id === auth()->id())
                                            @if ($user->is_admin)
                                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800" title="You cannot change your own role">{{ __('Admin') }}</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700" title="You cannot change your own role">{{ __('User') }}</span>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" onsubmit="return confirm('{{ $user->is_admin ? 'Change this user to regular User?' : 'Grant admin access to this user?' }}')">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="email" value="{{ $user->email }}">
                                                <input type="hidden" name="is_admin" value="{{ $user->is_admin ? 0 : 1 }}">
                                                @if ($user->is_admin)
                                                    <button type="submit" class="inline-flex cursor-pointer rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1" title="Tap or click to set as User">{{ __('Admin') }}</button>
                                                @else
                                                    <button type="submit" class="inline-flex cursor-pointer rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1" title="Tap or click to set as Admin">{{ __('User') }}</button>
                                                @endif
                                            </form>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                                            @csrf
                                            <x-secondary-button type="submit">{{ __('Send reset email') }}</x-secondary-button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                        {{ __('No users matched your search.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-6 py-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>