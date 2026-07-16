<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data="{ openCreate: false }">
            <h2 class="text-xl font-semibold text-gray-900">Band Templates</h2>
            @can('create', App\Models\BandTemplate::class)
                <x-secondary-button @click="openCreate = true">New Template</x-secondary-button>

                <div x-show="openCreate" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openCreate = false"></div>
                <div x-show="openCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold">Create Band Template</h3>
                        <form method="POST" action="{{ route('band-templates.store') }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label :value="'Template Name'" />
                                <x-text-input name="name" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Slots</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @foreach ($slotOptions as $slotValue => $slotLabel)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}">
                                            {{ $slotLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <x-secondary-button type="button" @click="openCreate = false">Cancel</x-secondary-button>
                                <x-primary-button>Create</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @forelse ($templates as $template)
                <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" x-data="{ openEdit: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $template->name }}</h3>
                            <p class="text-sm text-gray-600">
                                {{ $template->slots->map(fn ($slot) => $slotOptions[$slot->name] ?? $slot->name)->join(', ') }}
                            </p>
                        </div>

                        @can('update', $template)
                            <x-secondary-button @click="openEdit = true">Edit</x-secondary-button>
                        @endcan
                    </div>

                    @can('update', $template)
                        <div x-show="openEdit" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEdit = false"></div>
                        <div x-show="openEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                                <h4 class="text-lg font-semibold">Edit Template</h4>
                                <form method="POST" action="{{ route('band-templates.update', $template) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <x-input-label :value="'Template Name'" />
                                        <x-text-input name="name" :value="$template->name" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">Slots</p>
                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" @checked($template->slots->contains('name', $slotValue))>
                                                    {{ $slotLabel }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-secondary-button type="button" @click="openEdit = false">Cancel</x-secondary-button>
                                        <x-primary-button>Save</x-primary-button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('band-templates.destroy', $template) }}" class="mt-4">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button type="submit">Delete Template</x-danger-button>
                                </form>
                            </div>
                        </div>
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
