<x-modal name="planned-set-add-slot" maxWidth="md" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Add Slot</h3>
        <p class="mt-1 text-sm text-slate-600">Add one slot or apply a band template to this song.</p>

        <div class="mt-4 space-y-4">
            <div class="space-y-2">
                <span class="block text-sm font-medium text-slate-700">Add slots by</span>
                <div class="flex gap-4 text-sm text-slate-700">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="individual" x-model="slotEditor.addition_mode" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                        Individual slot
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="template" x-model="slotEditor.addition_mode" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                        Band template
                    </label>
                </div>
            </div>

            <div x-show="slotEditor.addition_mode === 'individual'" x-cloak>
                <x-input-label for="planned-set-slot-name" value="Slot Name" />
                <x-select id="planned-set-slot-name" x-model="slotEditor.name" class="focus:border-amber-500 focus:ring-amber-500">
                    <template x-for="(slotLabel, slotKey) in slotOptions" :key="`planned-slot-${slotKey}`">
                        <option :value="slotKey" x-text="slotLabel"></option>
                    </template>
                </x-select>

                <div class="mt-3">
                    <x-input-label for="planned-set-slot-notes" value="Notes (optional)" />
                    <x-textarea-input id="planned-set-slot-notes" x-model="slotEditor.notes" rows="3" class="mt-1 w-full" />
                </div>
            </div>

            <div x-show="slotEditor.addition_mode === 'template'" x-cloak>
                <x-input-label for="planned-set-slot-template" value="Band Template" />
                <x-select id="planned-set-slot-template" x-model="slotEditor.band_template_id" class="focus:border-amber-500 focus:ring-amber-500">
                    <option value="">Choose a band template</option>
                    <template x-for="template in templateOptions" :key="`planned-slot-template-${template.id}`">
                        <option :value="String(template.id)" x-text="template.name"></option>
                    </template>
                </x-select>
                <p class="mt-2 text-xs text-slate-600">Existing slots stay in place. Duplicate slot types are skipped.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-slot' }))">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="saveSlot()" x-bind:disabled="slotBusy" x-text="slotBusy ? 'Adding...' : 'Add Slot'"></x-modal-primary-button>
        </div>
    </div>
</x-modal>
