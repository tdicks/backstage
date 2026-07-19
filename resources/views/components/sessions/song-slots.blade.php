@props([
    'song',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
])

@php
    $setLocked = $set->performed;
@endphp

<tr x-ref="slotDropPlaceholder" class="hidden">
    <td colspan="3" class="px-3 py-3">
        <div data-slot-drop-label class="rounded-xl border-2 border-dashed border-sky-400 bg-sky-50/70 p-4 text-sm font-medium text-sky-700 shadow-sm">Drop slot here</div>
    </td>
</tr>
@forelse ($song->slots as $slot)
    <x-sessions.slot-row
        :slot-model="$slot"
        :set="$set"
        :users="$users"
        :slot-options="$slotOptions"
        :is-set-owner="$isSetOwner"
        :can-manage-set="$canManageSet"
        :can-reorder-slots="$canManageSet && ! $setLocked"
    />
@empty
    <tr>
        <td colspan="3" class="px-3 py-4 text-sm text-slate-500">No slots yet.</td>
    </tr>
@endforelse