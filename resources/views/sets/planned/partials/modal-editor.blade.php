<x-modal name="planned-set-editor" maxWidth="3xl" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold" x-text="editor.id ? 'Edit Planned Set' : 'Create Planned Set'"></h3>
        <div class="mt-4 space-y-4">
            <div>
                <x-input-label for="planned-set-name" value="Set name" />
                <x-text-input id="planned-set-name" x-model="editor.name" class="mt-1 block w-full" />
            </div>

            <div>
                <x-input-label for="planned-set-description" value="Notes" />
                <x-textarea-input id="planned-set-description" x-model="editor.description" rows="4" class="mt-1 w-full" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="editor.is_hidden" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                    <span>Hide this set from non-collaborators</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="editor.free_for_all" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    <span>Mark slots as free for all</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="editor.song_requests" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    <span>Accept song requests</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="editor.signups_open" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    <span>Accept sign ups</span>
                </label>
            </div>

            <div>
                <p class="text-sm font-semibold text-slate-900">Collaborators</p>
                <p class="mt-1 text-xs text-slate-500">Type a name to add collaborators.</p>
                <div class="mt-2 space-y-3">
                    <div class="relative">
                        <x-text-input
                            type="search"
                            x-model="collaboratorQuery"
                            @input="queueCollaboratorLookup()"
                            @focus="showCollaboratorSuggestions = filteredCollaboratorSuggestions().length > 0"
                            @keydown.escape.stop="showCollaboratorSuggestions = false"
                            class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-200"
                            placeholder="Search by name..."
                            autocomplete="off"
                        />
                        <ul
                            x-show="showCollaboratorSuggestions && filteredCollaboratorSuggestions().length > 0"
                            x-cloak
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                            @click.outside="showCollaboratorSuggestions = false"
                        >
                            <template x-for="candidate in filteredCollaboratorSuggestions()" :key="`collab-option-${candidate.id}`">
                                <li>
                                    <button
                                        type="button"
                                        @click="addCollaborator(candidate)"
                                        class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                        x-text="candidate.name"
                                    ></button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="min-h-[2.5rem] rounded-md border border-slate-200 bg-slate-50 p-3">
                        <template x-if="selectedCollaborators().length === 0">
                            <p class="text-sm text-slate-500">No collaborators added yet.</p>
                        </template>

                        <div class="flex flex-wrap gap-2" x-show="selectedCollaborators().length > 0">
                            <template x-for="collaborator in selectedCollaborators()" :key="`selected-collab-${collaborator.id}`">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-800 shadow-sm">
                                    <span x-text="collaborator.name"></span>
                                    <button
                                        type="button"
                                        @click="removeCollaborator(collaborator.id)"
                                        class="inline-flex h-4 w-4 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-100 hover:text-rose-600 focus:outline-none focus:ring-1 focus:ring-rose-400"
                                        x-bind:aria-label="`Remove ${collaborator.name}`"
                                    >
                                        <x-heroicon-m-x-mark class="h-3 w-3" aria-hidden="true" />
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-sm font-semibold text-slate-900">Candidate Jam Sessions</p>
                <p class="mt-1 text-xs text-slate-500">Pick the sessions this set could be published to.</p>
                <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-3">
                    <template x-if="(attendanceSessionOptions || []).length === 0">
                        <p class="text-sm text-slate-500">No upcoming jam sessions available yet.</p>
                    </template>
                    <div class="space-y-2" x-show="(attendanceSessionOptions || []).length > 0">
                        <template x-for="sessionOption in attendanceSessionOptions" :key="`candidate-session-${sessionOption.id}`">
                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    :checked="isCandidateSessionSelected(sessionOption.id)"
                                    @change="toggleCandidateSession(sessionOption.id)"
                                    class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                >
                                <span>
                                    <span x-text="`${sessionOption.name} (${sessionOption.date_label})`"></span>
                                    <span class="text-slate-500" x-show="sessionOption.is_closed"> - closed</span>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-editor' }))">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="saveEditor()" x-bind:disabled="editorBusy" x-text="editorBusy ? 'Saving...' : 'Save'"></x-modal-primary-button>
        </div>
    </div>
</x-modal>
