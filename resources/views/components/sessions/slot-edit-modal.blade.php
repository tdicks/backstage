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
                    <x-input-label :value="'Assigned User (optional)'" />
                    <select name="user_id" x-model="editAssignedUserId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                        <option value="">Open</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($slotModel->user_id === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <p x-show="shouldShowAssigneeWarning()" x-cloak class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        Make sure you let the assignee know they've been added.
                    </p>
                </div>
                <div>
                    <x-input-label :value="'Manual Performer Name (optional)'" />
                    <x-text-input name="manual_performer_name" :value="$slotModel->manual_performer_name" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                    <p class="mt-1 text-xs text-slate-500">Use this when the performer does not have an account. If an assigned user is selected, this value is ignored.</p>
                </div>
            </form>
            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <form method="POST" action="{{ route('slots.destroy', $slotModel) }}" @submit.prevent="deleteSlot($event)">
                    @csrf
                    @method('DELETE')
                    <x-danger-button type="submit" x-bind:disabled="busyAction">Delete Slot</x-danger-button>
                </form>
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
