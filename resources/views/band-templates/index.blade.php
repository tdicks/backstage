<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data="{ openCreate: false }">
            <h2 class="text-xl font-semibold text-slate-100">Band Templates</h2>
            @can('create', App\Models\BandTemplate::class)
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.slot-conflicts.index') }}" class="inline-flex items-center rounded-md border border-slate-600 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 shadow-sm transition hover:border-slate-500 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                        Slot Conflicts
                    </a>
                    <x-primary-button @click="openCreate = true">New Template</x-primary-button>
                </div>

                <template x-teleport="body">
                    <div x-show="openCreate" x-cloak @keydown.escape.window="openCreate = false">
                        <div class="fixed inset-0 z-40 bg-black/40" @click="openCreate = false"></div>
                        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                            <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                                <div class="px-6 pt-6">
                                    <h3 class="text-lg font-semibold text-slate-900">Create Band Template</h3>
                                </div>
                                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                    <form method="POST" action="{{ route('band-templates.store') }}" class="space-y-4">
                                        @csrf
                                        <div>
                                            <x-input-label :value="'Template Name'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                            <x-text-input name="name" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-700">Slots</p>
                                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                @foreach ($slotOptions as $slotValue => $slotLabel)
                                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                                                        <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                        {{ $slotLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <x-modal-secondary-button type="button" @click="openCreate = false">Cancel</x-modal-secondary-button>
                                            <x-modal-primary-button>Create</x-modal-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @forelse ($templates as $template)
                <article class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm" x-data="{ openEdit: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $template->name }}</h3>
                            <p class="text-sm text-slate-600">
                                {{ $template->slots->map(fn ($slot) => $slotOptions[$slot->name] ?? $slot->name)->join(', ') }}
                            </p>
                        </div>

                        @can('update', $template)
                            <button
                                type="button"
                                @click="openEdit = true"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                aria-label="Edit template"
                                title="Edit template"
                            >
                                <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                                <span class="sr-only">Edit</span>
                            </button>
                        @endcan
                    </div>

                    @can('update', $template)
                        <template x-teleport="body">
                            <div x-show="openEdit" x-cloak @keydown.escape.window="openEdit = false">
                                <div class="fixed inset-0 z-40 bg-black/40" @click="openEdit = false"></div>
                                <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4">
                                    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                                        <div class="px-6 pt-6">
                                            <h4 class="text-lg font-semibold text-slate-900">Edit Template</h4>
                                        </div>
                                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                                            <form id="edit_template_form_{{ $template->id }}" method="POST" action="{{ route('band-templates.update', $template) }}" class="space-y-4">
                                                @csrf
                                                @method('PATCH')
                                                <div>
                                                    <x-input-label :value="'Template Name'" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                                    <x-text-input name="name" :value="$template->name" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-slate-700">Slots</p>
                                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                        @foreach ($slotOptions as $slotValue => $slotLabel)
                                                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                                                                <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" @checked($template->slots->contains('name', $slotValue)) class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                                {{ $slotLabel }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4">
                                            <form method="POST" action="{{ route('band-templates.destroy', $template) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-danger-button type="submit">Delete Template</x-danger-button>
                                            </form>
                                            <div class="flex justify-end gap-2">
                                                <x-modal-secondary-button type="button" @click="openEdit = false">Cancel</x-modal-secondary-button>
                                                <x-modal-primary-button form="edit_template_form_{{ $template->id }}">Save</x-modal-primary-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    @endcan
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No templates yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
