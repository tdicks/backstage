@props([
    'setLocked' => false,
    'slotModel',
    'slotOptions',
    'proposalUsers',
    'isSetOwner' => false,
    'noProposableUsersMessage' => 'No users are currently available for slot proposals.',
])

@if (! $setLocked)
<div x-show="openPropose" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openPropose = false"></div>
<div x-show="openPropose" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-lg bg-white p-6 text-left text-slate-900 shadow-xl" @click.stop>
        <h6 class="text-base font-semibold text-slate-900">Recommend {{ $slotOptions[$slotModel->name] ?? $slotModel->name }} to someone</h6>
        <form @submit.prevent="submitProposal()" class="mt-4 space-y-4">
            @if ($proposalUsers->isNotEmpty())
                <div>
                    <p class="mb-3 text-xs leading-5 text-slate-500">Think someone would enjoy this slot? Recommend it to them!</p>
                    <div class="relative">
                        <x-input-label for="proposal_user_{{ $slotModel->id }}" :value="'Who?'" />
                        <x-text-input
                            id="proposal_user_{{ $slotModel->id }}"
                            type="search"
                            x-model="proposeTargetUserQuery"
                            @input="updateProposalUserQuery()"
                            @focus="showProposalUserSuggestions = proposeTargetUserQuery.trim() !== ''"
                            @keydown.escape="showProposalUserSuggestions = false"
                            class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                            autocomplete="off"
                            required
                        />
                        <div
                            x-show="showProposalUserSuggestions && hasProposalSuggestions()"
                            x-cloak
                            class="absolute z-[120] mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                            @click.outside="showProposalUserSuggestions = false"
                        >
                            <template x-if="groupedProposalUsers().available.length > 0">
                                <div>
                                    <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Available</p>
                                    <template x-for="user in groupedProposalUsers().available" :key="`available-${user.id}`">
                                        <button
                                            type="button"
                                            @click="selectProposalUser(user)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-800 transition hover:bg-amber-50 focus:bg-amber-50 focus:outline-none"
                                            x-text="user.name"
                                        ></button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="groupedProposalUsers().notAttending.length > 0">
                                <div class="border-t border-slate-200">
                                    <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Not attending</p>
                                    <p class="px-3 pb-2 text-[11px] text-slate-500">These users marked not attending for this session.</p>
                                    <template x-for="user in groupedProposalUsers().notAttending" :key="`not-attending-${user.id}`">
                                        <button
                                            type="button"
                                            @click="if (canSelectUser(user)) { selectProposalUser(user); }"
                                            :disabled="!canSelectUser(user)"
                                            class="w-full px-3 py-2 text-left text-sm transition focus:outline-none"
                                            :class="canSelectUser(user) ? 'text-slate-800 hover:bg-amber-50 focus:bg-amber-50' : 'cursor-not-allowed text-slate-400'"
                                            x-text="user.name"
                                        ></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <p x-show="showProposalUserSuggestions && proposeTargetUserQuery.trim() !== '' && !hasProposalSuggestions()" x-cloak class="mt-1 text-xs text-slate-500">
                            No matching users are available for recommendations.
                        </p>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-600">{{ $noProposableUsersMessage }}</p>
            @endif
            <div>
                <x-input-label :value="'Message (optional)'" />
                <x-textarea-input x-model="proposeMessage" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" />
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    @if ($isSetOwner)
                        They will get a chance to say yes before the slot changes.
                    @else
                        They will get a chance to say yes first, then the set organiser can give it the final nod.
                    @endif
                </p>
            </div>
            <div class="flex justify-end gap-2">
                <x-modal-secondary-button type="button" @click="openPropose = false">Cancel</x-modal-secondary-button>
                <x-modal-primary-button x-bind:disabled="busyAction || !proposeTargetUserId" class="disabled:cursor-not-allowed disabled:opacity-40">Send Proposal</x-modal-primary-button>
            </div>
        </form>
    </div>
</div>
@endif
