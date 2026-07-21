<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">{{ __('User Administration') }}</h2>
                <p class="mt-1 text-sm text-slate-400">Search users, edit account details, and send password reset emails.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div
            class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
            x-data="{
                toast: { visible: false, type: 'success', message: '' },
                toastTimer: null,
                showToast(type, message) {
                    this.toast = { visible: true, type, message };
                    if (this.toastTimer) {
                        clearTimeout(this.toastTimer);
                    }
                    this.toastTimer = setTimeout(() => {
                        this.toast.visible = false;
                    }, 3500);
                },
            }"
        >
            <template x-teleport="body">
                <div
                    x-show="toast.visible"
                    x-cloak
                    x-transition
                    class="fixed right-4 top-4 z-[120] max-w-sm rounded-lg border px-4 py-3 text-sm font-medium shadow-2xl"
                    x-bind:class="toast.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                    x-text="toast.message"
                ></div>
            </template>

            <div class="rounded-lg border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end">
                    <div>
                        <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Search') }}</label>
                        <input id="q" name="q" value="{{ $search }}" placeholder="Name or email" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                    </div>

                    <div>
                        <label for="sort" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Sort by') }}</label>
                        <select id="sort" name="sort" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                            <option value="name" @selected($sort === 'name')>{{ __('Name') }}</option>
                            <option value="email" @selected($sort === 'email')>{{ __('Email') }}</option>
                            <option value="is_admin" @selected($sort === 'is_admin')>{{ __('Role') }}</option>
                            <option value="created_at" @selected($sort === 'created_at')>{{ __('Joined') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="direction" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Direction') }}</label>
                        <select id="direction" name="direction" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                            <option value="asc" @selected($direction === 'asc')>{{ __('Ascending') }}</option>
                            <option value="desc" @selected($direction === 'desc')>{{ __('Descending') }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Apply') }}</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <p class="text-sm text-slate-600">
                        {{ $users->total() }} {{ __('users found') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-100/70">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
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
                        <tbody class="divide-y divide-slate-200 bg-white/95">
                            @forelse ($users as $user)
                                <tr
                                    x-data="{
                                        openEditUser: false,
                                        resetBusy: false,
                                        async sendPasswordReset() {
                                            this.resetBusy = true;

                                            try {
                                                const response = await fetch(@js(route('admin.users.password-reset', $user)), {
                                                    method: 'POST',
                                                    headers: {
                                                        'Accept': 'application/json',
                                                        'X-Requested-With': 'XMLHttpRequest',
                                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    },
                                                });

                                                const payload = await response.json().catch(() => ({}));

                                                if (!response.ok) {
                                                    throw new Error(payload.message || 'Could not send password reset email.');
                                                }

                                                showToast('success', payload.message || 'Password reset email sent.');
                                            } catch (e) {
                                                showToast('error', e.message || 'Could not send password reset email.');
                                            } finally {
                                                this.resetBusy = false;
                                            }
                                        },
                                    }"
                                >
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                        @if ($user->id === auth()->id())
                                            <span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ __('You') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        {{ $user->email }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if ($user->is_admin)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800" @if($user->id === auth()->id()) title="You cannot change your own role" @endif>{{ __('Admin') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" @if($user->id === auth()->id()) title="You cannot change your own role" @endif>{{ __('User') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if ($user->id !== auth()->id())
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="openEditUser = true"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                                    aria-label="Edit user"
                                                    title="Edit user"
                                                >
                                                    <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                                                    <span class="sr-only">Edit user</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="sendPasswordReset()"
                                                    x-bind:disabled="resetBusy"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                                                    aria-label="Send reset email"
                                                    title="Send reset email"
                                                >
                                                    <x-heroicon-m-envelope class="h-4 w-4" aria-hidden="true" />
                                                    <span class="sr-only">Send reset email</span>
                                                </button>
                                            </div>

                                            <template x-teleport="body">
                                                <div x-cloak @keydown.escape.window="openEditUser = false">
                                                    <div x-show="openEditUser" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-40 bg-black/40" @click="openEditUser = false"></div>
                                                    <div x-show="openEditUser" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                                                        <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                                                            <div class="px-6 pt-6">
                                                                <h3 class="text-lg font-semibold text-slate-900">Edit User</h3>
                                                            </div>
                                                            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                                                <form id="edit_user_form_{{ $user->id }}" method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                                                                    @csrf
                                                                    @method('PATCH')

                                                                    <div>
                                                                        <x-input-label for="edit_user_name_{{ $user->id }}" :value="'Name'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                                        <x-text-input id="edit_user_name_{{ $user->id }}" name="name" :value="$user->name" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                                                    </div>

                                                                    <div>
                                                                        <x-input-label for="edit_user_email_{{ $user->id }}" :value="'Email'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                                        <x-text-input id="edit_user_email_{{ $user->id }}" name="email" type="email" :value="$user->email" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                                                    </div>

                                                                    <div>
                                                                        <x-input-label for="edit_user_bio_{{ $user->id }}" :value="'Bio'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                                        <x-textarea-input id="edit_user_bio_{{ $user->id }}" name="bio" rows="4" class="mt-2 w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $user->bio }}</x-textarea-input>
                                                                    </div>

                                                                    <input type="hidden" name="hide_from_directory" value="0">
                                                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                                                        <input type="checkbox" name="hide_from_directory" value="1" @checked($user->hide_from_directory) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                                                        Hide from Who's Who
                                                                    </label>

                                                                    <input type="hidden" name="hide_from_slot_proposals" value="0">
                                                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                                                        <input type="checkbox" name="hide_from_slot_proposals" value="1" @checked($user->hide_from_slot_proposals) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                                                        Hide from set proposal options
                                                                    </label>

                                                                    @if ($slotOptions)
                                                                        <div>
                                                                            <x-input-label :value="'Slot Coverage'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                                            <div class="mt-2 flex flex-wrap gap-2">
                                                                                @foreach ($slotOptions as $key => $name)
                                                                                    @php $checked = in_array($key, $user->slot_coverage ?? [], true); @endphp
                                                                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition {{ $checked ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}">
                                                                                        <input type="checkbox" name="slot_coverage[]" value="{{ $key }}" @checked($checked) class="sr-only">
                                                                                        {{ $name }}
                                                                                    </label>
                                                                                @endforeach
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    <input type="hidden" name="is_admin" value="0">
                                                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                                                        <input type="checkbox" name="is_admin" value="1" @checked($user->is_admin) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                                                        Admin user
                                                                    </label>
                                                                </form>
                                                            </div>
                                                            <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-6 py-4">
                                                                <x-modal-secondary-button type="button" @click="openEditUser = false">Cancel</x-modal-secondary-button>
                                                                <x-modal-primary-button type="submit" form="edit_user_form_{{ $user->id }}">Save</x-modal-primary-button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                        {{ __('No users matched your search.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>