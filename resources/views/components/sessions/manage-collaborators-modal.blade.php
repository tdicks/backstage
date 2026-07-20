@props([
    'set',
])

<div x-show="openCollaborators" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openCollaborators = false; resetCollaboratorModal()"></div>
<div x-show="openCollaborators" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
        <h4 class="text-lg font-semibold text-slate-900">Manage Collaborators</h4>
        <p class="mt-1 text-sm text-slate-500">Collaborators can manage songs and slots but cannot edit this set's settings.</p>

        <div class="mt-4 space-y-4">
            <div class="relative">
                <x-input-label :value="'Add collaborator'" />
                <x-text-input
                    type="search"
                    x-model="collaboratorQuery"
                    @input="queueCollaboratorLookup()"
                    @focus="showCollaboratorSuggestions = collaboratorSuggestions.length > 0"
                    @keydown.escape.stop="showCollaboratorSuggestions = false"
                    class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                    placeholder="Search by name…"
                    autocomplete="off"
                />
                <div x-show="collaboratorLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Searching…</div>
                <ul
                    x-show="showCollaboratorSuggestions && filteredCollaboratorSuggestions().length > 0"
                    x-cloak
                    class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                    @click.outside="showCollaboratorSuggestions = false"
                >
                    <template x-for="user in filteredCollaboratorSuggestions()" :key="'collab-suggest-' + user.id">
                        <li>
                            <button
                                type="button"
                                @click="addCollaborator(user)"
                                class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                x-text="user.name"
                            ></button>
                        </li>
                    </template>
                </ul>
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700">Current collaborators</p>
                <div class="mt-2 min-h-[2.5rem]">
                    <template x-if="collaboratorsList.length === 0">
                        <p class="text-sm text-slate-400">No collaborators added yet.</p>
                    </template>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="collab in collaboratorsList" :key="'collab-pill-' + collab.id">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-800 shadow-sm">
                                <span x-text="collab.name"></span>
                                <button
                                    type="button"
                                    @click="removeCollaborator(collab.id)"
                                    class="inline-flex h-4 w-4 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-100 hover:text-rose-600 focus:outline-none focus:ring-1 focus:ring-rose-400"
                                    x-bind:aria-label="'Remove ' + collab.name"
                                >
                                    <x-heroicon-m-x-mark class="h-3 w-3" aria-hidden="true" />
                                </button>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <p x-show="collaboratorSaveError" x-text="collaboratorSaveError" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p>
        </div>

        <div class="mt-4 flex justify-end gap-2 border-t border-slate-200 pt-4">
            <x-modal-secondary-button type="button" @click="openCollaborators = false; resetCollaboratorModal()">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="saveCollaborators()" x-bind:disabled="collaboratorSaveBusy">Save</x-modal-primary-button>
        </div>
    </div>
</div>
