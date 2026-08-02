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

            <div
                x-data="adminManualSlotTransfer({
                    dataUrl: @js($manualSlotTransferDataUrl),
                    applyUrl: @js($manualSlotTransferApplyUrl),
                    csrfToken: @js(csrf_token()),
                })"
                @keydown.escape.window="if (open) { closeModal(); }"
                class="rounded-lg border border-slate-200 bg-slate-50/95 p-6 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Manual Slot Assignments</h3>
                        <p class="mt-1 text-sm text-slate-600">Reassign typed performer names to registered users on current open jam sessions.</p>
                    </div>
                    <button
                        type="button"
                        @click="openModal()"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    >
                        <x-heroicon-m-arrow-path class="h-4 w-4" aria-hidden="true" />
                        <span>Reassign Manual Slots</span>
                    </button>
                </div>

                <template x-teleport="body">
                    <div x-cloak>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="closeModal()"></div>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                            <section class="flex max-h-[calc(100vh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-left text-slate-900 shadow-2xl sm:max-h-[calc(100vh-4rem)]" role="dialog" aria-modal="true" aria-label="Manual slot assignment transfer" @click.stop>
                                <header class="border-b border-slate-200 px-6 py-4">
                                    <h4 class="text-lg font-semibold text-slate-900">Reassign Manual Slots</h4>
                                    <p class="mt-1 text-sm text-slate-600">Pick users for manually typed slot names. Suggested matches are listed first for each slot.</p>
                                </header>

                                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                    <p x-show="loading" x-cloak class="text-sm text-slate-600">Loading manual slot assignments...</p>
                                    <p x-show="error" x-cloak x-text="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>
                                    <p x-show="feedback" x-cloak x-text="feedback" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></p>

                                    <template x-if="!loading && slots.length === 0">
                                        <p class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-600">No manual slot assignments were found for current open jam sessions.</p>
                                    </template>

                                    <div x-show="slots.length > 0" x-cloak class="space-y-3">
                                        <template x-for="slot in slots" :key="slot.slotId">
                                            <article class="rounded-lg border border-slate-200 bg-white p-4">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-semibold text-slate-900" x-text="slot.manualPerformerName"></p>
                                                        <p class="mt-1 text-xs text-slate-600">
                                                            <span x-text="slot.slotLabel"></span>
                                                            <span class="mx-1">-</span>
                                                            <span x-text="slot.songArtist"></span>
                                                            <span> - </span>
                                                            <span x-text="slot.songTitle"></span>
                                                        </p>
                                                        <p class="text-xs text-slate-500">
                                                            <span x-text="slot.setName"></span>
                                                            <span class="mx-1">-</span>
                                                            <a class="underline decoration-slate-300 underline-offset-2 hover:text-slate-700" :href="slot.sessionUrl" x-text="`${slot.sessionName} (${slot.sessionDateLabel})`"></a>
                                                        </p>
                                                    </div>
                                                    <div class="w-full max-w-xs">
                                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Assign To</label>
                                                        <select x-model="slot.selectedUserId" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                                            <option value="">No change</option>
                                                            <template x-for="user in slot.userOptions" :key="`slot-${slot.slotId}-user-${user.id}`">
                                                                <option :value="String(user.id)" x-text="user.name"></option>
                                                            </template>
                                                        </select>
                                                        <p x-show="slot.status === 'error'" x-cloak x-text="slot.message" class="mt-2 text-xs text-rose-700"></p>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>

                                    <div x-show="completed.length > 0" x-cloak class="mt-5 space-y-2 border-t border-slate-200 pt-4">
                                        <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Completed Transfers</h5>
                                        <template x-for="slot in completed" :key="`completed-${slot.slotId}`">
                                            <article class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                                <p>
                                                    <span class="font-semibold" x-text="slot.manualPerformerName"></span>
                                                    <span> transferred to </span>
                                                    <span class="font-semibold" x-text="slot.assignedUserName"></span>
                                                </p>
                                                <span class="text-xs" x-text="slot.message"></span>
                                            </article>
                                        </template>
                                    </div>
                                </div>

                                <footer class="flex items-center justify-between gap-2 border-t border-slate-200 px-6 py-4">
                                    <button
                                        type="button"
                                        @click="loadSlots()"
                                        :disabled="loading || saving"
                                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Refresh
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <x-modal-secondary-button type="button" @click="closeModal()">Close</x-modal-secondary-button>
                                        <x-modal-primary-button type="button" @click="submit()" x-bind:disabled="saving || loading || !hasChanges()" class="disabled:cursor-not-allowed disabled:opacity-40">Apply Changes</x-modal-primary-button>
                                    </div>
                                </footer>
                            </section>
                        </div>
                    </div>
                </template>
            </div>

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
                    <table class="w-full table-fixed divide-y divide-slate-200 md:table-auto">
                        <thead class="bg-slate-100/70">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                @php
                                    $query = request()->except('page');
                                    $nextDirection = $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="w-[28%] px-3 py-3 md:w-auto md:px-6">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'name', 'direction' => $nextDirection])) }}">{{ __('Name') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'email' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="w-[34%] px-3 py-3 md:w-auto md:px-6">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'email', 'direction' => $nextDirection])) }}">{{ __('Email') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'is_admin' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="w-[20%] px-3 py-3 md:w-auto md:px-6">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'is_admin', 'direction' => $nextDirection])) }}">{{ __('Role') }}</a>
                                </th>
                                @php
                                    $nextDirection = $sort === 'created_at' && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th class="hidden px-6 py-3 md:table-cell">
                                    <a href="{{ route('admin.users.index', array_merge($query, ['sort' => 'created_at', 'direction' => $nextDirection])) }}">{{ __('Joined') }}</a>
                                </th>
                                <th class="w-[18%] px-3 py-3 md:w-auto md:px-6">{{ __('Actions') }}</th>
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
                                    <td class="break-words px-3 py-4 md:px-6">
                                        <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                        @if ($user->id === auth()->id())
                                            <span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ __('You') }}</span>
                                        @endif
                                    </td>
                                    <td class="break-all px-3 py-4 text-sm text-slate-700 md:px-6">
                                        {{ $user->email }}
                                    </td>
                                    <td class="px-3 py-4 md:px-6">
                                        @if ($user->is_admin)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800" @if($user->id === auth()->id()) title="You cannot change your own role" @endif>{{ __('Admin') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" @if($user->id === auth()->id()) title="You cannot change your own role" @endif>{{ __('User') }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden whitespace-nowrap px-6 py-4 text-sm text-slate-600 md:table-cell">
                                        {{ $user->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="px-3 py-4 md:px-6">
                                        @if ($user->id !== auth()->id())
                                            <div class="flex items-center gap-1 md:gap-2">
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
                                                    <div x-show="openEditUser" x-cloak x-transition.opacity.duration.150ms data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openEditUser = false"></div>
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
                                                                        Hide me from directories (Who's Who and Jam Standards search)
                                                                    </label>

                                                                    <input type="hidden" name="hide_from_slot_proposals" value="0">
                                                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                                                        <input type="checkbox" name="hide_from_slot_proposals" value="1" @checked($user->hide_from_slot_proposals) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                                                        Hide me from slot recommendations and assignments
                                                                    </label>

                                                                    @if ($slotOptions)
                                                                        <div>
                                                                            <x-input-label :value="'Slot Coverage'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                                            <p class="mt-1 text-xs text-slate-500">Choose whether this user can cover each slot, is unsure, or should be hidden from Find a Slot results.</p>
                                                                            @php $coverageStates = \App\Models\User::slotCoverageStates(); @endphp
                                                                            <div class="mt-2 flex flex-wrap gap-2">
                                                                                @foreach ($slotOptions as $key => $name)
                                                                                    @php $coverageState = old('slot_coverage.'.$key, $user->slotCoverageState($key)); @endphp
                                                                                    <div
                                                                                        x-data="{
                                                                                            state: @js($coverageState),
                                                                                            states: @js(array_keys($coverageStates)),
                                                                                            labels: @js($coverageStates),
                                                                                                symbols: {
                                                                                                    can: '✓',
                                                                                                    unspecified: '•',
                                                                                                    wont_cover: '✕',
                                                                                                },
                                                                                            cycle() {
                                                                                                const currentIndex = this.states.indexOf(this.state);
                                                                                                const nextIndex = (currentIndex + 1) % this.states.length;

                                                                                                this.state = this.states[nextIndex];
                                                                                            },
                                                                                            stateClasses() {
                                                                                                if (this.state === '{{ \App\Models\User::SLOT_COVERAGE_CAN }}') {
                                                                                                    return 'border-emerald-300 bg-emerald-50 text-emerald-700';
                                                                                                }

                                                                                                if (this.state === '{{ \App\Models\User::SLOT_COVERAGE_WONT }}') {
                                                                                                    return 'border-rose-300 bg-rose-50 text-rose-700';
                                                                                                }

                                                                                                return 'border-slate-200 bg-white text-slate-600 hover:border-slate-300';
                                                                                            },
                                                                                        }"
                                                                                        class="shrink-0"
                                                                                    >
                                                                                        <input type="hidden" name="slot_coverage[{{ $key }}]" x-bind:value="state">
                                                                                        <button
                                                                                            type="button"
                                                                                            @click="cycle()"
                                                                                            class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                                                                                            x-bind:class="stateClasses()"
                                                                                            x-bind:aria-label="`${labels[state]} for {{ $name }}`"
                                                                                        >
                                                                                            <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_CAN }}'" x-cloak aria-hidden="true" class="shrink-0">
                                                                                                <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                                                                                            </span>
                                                                                            <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_UNSPECIFIED }}'" x-cloak aria-hidden="true" class="shrink-0">
                                                                                                <x-heroicon-m-question-mark-circle class="h-4 w-4 text-slate-400" aria-hidden="true" />
                                                                                            </span>
                                                                                            <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_WONT }}'" x-cloak aria-hidden="true" class="shrink-0">
                                                                                                <x-heroicon-m-x-circle class="h-4 w-4 text-rose-600" aria-hidden="true" />
                                                                                            </span>
                                                                                            <span class="truncate">{{ $name }}</span>
                                                                                        </button>
                                                                                    </div>
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