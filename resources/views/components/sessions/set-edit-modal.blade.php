@props(['set', 'sessions', 'users', 'isAdmin' => false, 'isAdminManagingOtherSet' => false])

<div x-show="openSetEdit" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openSetEdit = false"></div>
<div x-show="openSetEdit" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl" @click.stop>
        <div class="border-b border-slate-200 px-6 py-4">
            <h4 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">{{ $isAdminManagingOtherSet ? 'Edit '.$set->owner->name.'\'s Set' : 'Edit Set' }}</h4>
        </div>
        <form id="edit_set_form_{{ $set->id }}" method="POST" action="{{ route('sets.update', $set) }}" class="min-h-0 flex-1 overflow-y-auto space-y-4 px-6 py-4">
            @csrf
            @method('PATCH')
            <div>
                <x-input-label :value="'Set Name'" />
                <x-text-input name="name" :value="$set->name" class="mt-1 block w-full" required />
            </div>
            <div>
                <x-input-label :value="'Description'" />
                <x-textarea-input name="description" rows="4" class="mt-1 w-full">{{ $set->description }}</x-textarea-input>
            </div>
            <div>
                <x-input-label :value="'Jam Session'" />
                <select name="jam_session_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required>
                    @foreach ($sessions as $jamSessionOption)
                        @php
                            $isClosedSessionOption = (bool) ($jamSessionOption->is_closed ?? false);
                            $isCurrentSessionOption = (int) $set->jam_session_id === (int) $jamSessionOption->id;
                            $disableSessionOption = ! $isAdmin && $isClosedSessionOption && ! $isCurrentSessionOption;
                        @endphp
                        <option value="{{ $jamSessionOption->id }}" @selected($isCurrentSessionOption) @disabled($disableSessionOption)>{{ $jamSessionOption->name }} ({{ $jamSessionOption->date->format('M j, Y') }}){{ $isClosedSessionOption ? ' (Closed)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            @if ($isAdmin)
                <div>
                    <x-input-label :value="'Set Owner'" />
                    <select name="owner_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($set->owner_id === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <input type="checkbox" name="performed" value="1" x-model="performedDraft" @checked($set->performed) class="rounded border-slate-300 text-emerald-600">
                Mark as performed.
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <input type="hidden" name="signups_open" value="0">
                <input type="checkbox" name="signups_open" value="1" @checked($set->signups_open) class="rounded border-slate-300 text-emerald-600">
                Sign ups open.
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <input type="hidden" name="song_requests" value="0">
                <input type="checkbox" name="song_requests" value="1" x-model="songRequestsDraft" @checked($set->song_requests) class="rounded border-slate-300 text-emerald-600">
                Accept song requests.
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-sky-300 bg-slate-50 px-3 py-2 text-sm">
                <input type="hidden" name="is_hidden" value="0">
                <input type="checkbox" name="is_hidden" value="1" @checked($set->is_hidden) class="rounded border-slate-300 text-slate-600">
                Hide this set from other users.
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <input type="hidden" name="free_for_all" value="0">
                <input type="checkbox" name="free_for_all" value="1" x-model="freeForAllDraft" @checked($set->free_for_all) class="rounded border-slate-300 text-emerald-600">
                Free for all mode.
            </label>
            @if ($isAdmin)
                <label class="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
                    <input type="hidden" name="feature_set" value="0">
                    <input type="checkbox" name="feature_set" value="1" @checked($set->feature_set) class="rounded border-amber-400 text-amber-500">
                    Feature set (pinned to top of session).
                </label>
            @endif
        </form>
        <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4">
            <form method="POST" action="{{ route('sets.destroy', $set) }}" onsubmit="return confirm('Move this set to the Recycle Bin?');">
                @csrf
                @method('DELETE')
                <x-danger-button type="submit">Move Set to Recycle Bin</x-danger-button>
            </form>
            <div class="flex gap-2">
                <x-modal-secondary-button type="button" @click="openSetEdit = false">Cancel</x-modal-secondary-button>
                <x-modal-primary-button type="submit" form="edit_set_form_{{ $set->id }}">Save</x-modal-primary-button>
            </div>
        </div>
    </div>
</div>
