@props([
    'slotModel',
])

<span
    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm"
    x-bind:class="assignedToCurrentUser ? 'border-sky-200 bg-sky-50/90 text-sky-800' : (slotIsOpen ? 'border-amber-200 bg-amber-50/80 text-amber-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800')"
    x-bind:title="assignmentIsManual ? 'Manually assigned' : ''"
>
    <span x-text="assignedUserName">{{ $slotModel->user_id === auth()->id() ? 'You' : $slotModel->assignedPerformerName() }}</span>
    <template x-if="assignmentIsManual">
        <span class="ml-1 inline-flex items-center" aria-hidden="true">
            <x-heroicon-m-pencil-square class="h-3.5 w-3.5" />
        </span>
    </template>
</span>
