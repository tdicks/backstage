<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-100">
                Slot Conflicts
            </h2>
            <p class="mt-1 text-sm text-slate-400">Control which slot types cannot be assigned to the same player within a song.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm"
                x-data="{
                    busyKey: '',
                    message: '',
                    error: '',
                    messageTimer: null,
                    async toggleConflict(sourceId, targetId, enabled, url) {
                        const key = `${sourceId}:${targetId}`;
                        this.busyKey = key;
                        this.message = '';
                        this.error = '';
                        clearTimeout(this.messageTimer);

                        try {
                            const response = await fetch(url, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': @js(csrf_token()),
                                },
                                body: JSON.stringify({
                                    conflict_id: targetId,
                                    enabled,
                                }),
                            });

                            const payload = await response.json();

                            if (!response.ok) {
                                throw new Error(payload.message || 'Could not update this conflict.');
                            }

                            this.setConflictState(sourceId, targetId, payload.enabled);
                            this.setConflictState(targetId, sourceId, payload.enabled);
                            this.message = payload.message || 'Conflict updated.';
                            this.messageTimer = setTimeout(() => this.message = '', 2500);
                        } catch (e) {
                            this.setConflictState(sourceId, targetId, !enabled);
                            this.setConflictState(targetId, sourceId, !enabled);
                            this.error = e.message || 'Could not update this conflict.';
                        } finally {
                            this.busyKey = '';
                        }
                    },
                    setConflictState(sourceId, targetId, enabled) {
                        document.querySelectorAll(`[data-conflict-source='${sourceId}'][data-conflict-target='${targetId}']`).forEach((checkbox) => {
                            checkbox.checked = enabled;
                        });
                    },
                }"
            >
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Conflict Matrix</h3>
                    <p class="mt-1 text-sm text-slate-600">Changes are saved immediately and mirrored on the matching conflict.</p>
                </div>

                <div class="border-b border-slate-200 px-6 py-3">
                    <p x-show="message" x-transition.opacity.duration.200ms x-text="message" class="text-sm text-emerald-700" x-cloak></p>
                    <p x-show="error" x-text="error" class="text-sm text-rose-700" x-cloak></p>
                    <p x-show="!message && !error" class="text-sm text-slate-500">Tick any pair that should be impossible for one player within a set.</p>
                </div>

                @if ($slotTypes->isEmpty())
                    <div class="px-6 py-8 text-sm text-slate-600">
                        No slot types have been configured yet.
                    </div>
                @else
                    <div class="divide-y divide-slate-200">
                        @foreach ($slotTypes as $slotType)
                            @php
                                $conflictIds = $slotType->conflicts->pluck('id')->map(fn ($id) => (int) $id);
                            @endphp

                            <div class="grid gap-5 px-6 py-5 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">{{ $slotType->name }}</h4>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $slotType->key }}</p>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($slotTypes as $candidate)
                                        @php
                                            $isSelf = $candidate->is($slotType);
                                            $busyKey = $slotType->id.':'.$candidate->id;
                                        @endphp

                                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm {{ $isSelf ? 'opacity-50' : '' }}">
                                            <input
                                                type="checkbox"
                                                name="conflict_ids[]"
                                                value="{{ $candidate->id }}"
                                                data-conflict-source="{{ $slotType->id }}"
                                                data-conflict-target="{{ $candidate->id }}"
                                                @change="toggleConflict({{ $slotType->id }}, {{ $candidate->id }}, $event.target.checked, @js(route('admin.slot-conflicts.update', $slotType)))"
                                                :disabled="@js($isSelf) || busyKey === @js($busyKey)"
                                                @checked($conflictIds->contains($candidate->id))
                                                class="rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500 disabled:cursor-not-allowed"
                                            >
                                            <span>{{ $candidate->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>