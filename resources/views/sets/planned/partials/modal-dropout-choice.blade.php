<x-modal name="planned-set-dropout-choice" maxWidth="md" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Before you mark not going</h3>
        <p class="mt-1 text-sm text-slate-600">Choose what should happen to your current slots in <span class="font-semibold text-slate-900" x-text="dropoutPrompt.jam_session_name"></span>.</p>

        <div class="mt-4 space-y-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                <input type="radio" name="planned_set_dropout_action" value="keep_claimable" x-model="dropoutPrompt.action" class="mt-1 border-slate-300 text-amber-500 focus:ring-amber-500">
                <span>Keep my slots assigned, but mark them claimable.</span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                <input type="radio" name="planned_set_dropout_action" value="release_slots" x-model="dropoutPrompt.action" class="mt-1 border-slate-300 text-amber-500 focus:ring-amber-500">
                <span>Release all of my assigned slots now.</span>
            </label>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-dropout-choice' }))">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="confirmDropoutChoice()">Confirm No</x-modal-primary-button>
        </div>
    </div>
</x-modal>
