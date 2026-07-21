@props([
    'canManageSet' => false,
    'setLocked' => false,
    'isAdminManagingOtherSet' => false,
    'set',
    'slotModel',
    'slotOptions',
    'users',
])

@if ($canManageSet && ! $setLocked)
    <div x-show="openEditSlot" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openEditSlot = false"></div>
    <div x-show="openEditSlot" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-left text-slate-900 shadow-2xl">
            <h6 class="text-base font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                {{ $isAdminManagingOtherSet ? 'Edit '.$set->owner->name.'\'s Slot' : 'Edit Slot' }}
            </h6>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                This updates the slot on the set itself, not just the live dashboard. You can pick a user from the list or type a manual name.
            </p>
            <form id="edit_slot_form_{{ $slotModel->id }}" method="POST" action="{{ route('slots.update', $slotModel) }}" class="mt-4 space-y-4" @submit.prevent="submitEditSlot($event)">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label :value="'Slot Name'" />
                    <select name="name" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                        @foreach ($slotOptions as $slotValue => $slotLabel)
                            <option value="{{ $slotValue }}" @selected($slotModel->name === $slotValue)>{{ $slotLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="'Assigned User or Manual Name'" />
                    <div class="relative">
                        <x-text-input
                            type="search"
                            x-model="editAssignedUserQuery"
                            @input="updateEditUserQuery()"
                            @focus="showEditUserSuggestions = editAssignedUserQuery.trim() !== ''"
                            @keydown.escape="showEditUserSuggestions = false"
                            name="manual_performer_name"
                            list="slot_users_{{ $slotModel->id }}"
                            class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                            autocomplete="off"
                        />
                        <input type="hidden" name="user_id" x-bind:value="editAssignedUserId">
                        <div
                            x-show="showEditUserSuggestions && filteredEditUsers().length > 0"
                            x-cloak
                            class="absolute z-[120] mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                            @click.outside="showEditUserSuggestions = false"
                        >
                            <template x-for="user in filteredEditUsers()" :key="user.id">
                                <button
                                    type="button"
                                    @click="selectEditUser(user)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-800 transition hover:bg-amber-50 focus:bg-amber-50 focus:outline-none"
                                    x-text="user.name"
                                ></button>
                            </template>
                        </div>
                    </div>
                    <p x-show="shouldShowAssigneeWarning()" x-cloak class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        We&apos;ll save this as a manual assignment unless you choose a user from the list.
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Free typing will keep this as a manual performer name.</p>
                    <datalist id="slot_users_{{ $slotModel->id }}">
                        @foreach ($users as $user)
                            <option value="{{ $user->name }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </form>
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <x-danger-button type="button" @click="clearSlot()" x-bind:disabled="busyAction">Clear Slot</x-danger-button>
                <div class="flex justify-end gap-2">
                    <x-modal-secondary-button type="button" @click="openEditSlot = false">Cancel</x-modal-secondary-button>
                    <x-modal-primary-button type="submit" form="edit_slot_form_{{ $slotModel->id }}" x-bind:disabled="busyAction">
                        @if ($isAdminManagingOtherSet)
                            <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                            <span class="sr-only">Admin action: </span>
                        @endif
                        Save
                    </x-modal-primary-button>
                </div>
            </div>
        </div>
    </div>
@endif
