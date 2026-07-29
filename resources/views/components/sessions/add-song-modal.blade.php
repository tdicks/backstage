@props(['set', 'templates', 'slotOptions', 'isAdminManagingOtherSet' => false])

<div x-show="openSong" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openSong = false; resetSongAutocomplete()"></div>
<div x-show="openSong" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl" @click.stop>
        <div class="border-b border-slate-200 px-6 py-4">
            <h4 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">{{ $isAdminManagingOtherSet ? 'Add Song to '.$set->owner->name.'\'s Set' : 'Add Song to '.$set->name }}</h4>
        </div>
        <form method="POST" action="{{ route('songs.store', $set) }}" class="min-h-0 flex-1 overflow-y-auto space-y-4 px-6 py-4" x-data="{ songSlotAdditionMode: 'template' }" @submit.prevent="submitAddSong($event)">
            @csrf
            <p x-show="addSongError" x-text="addSongError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" x-cloak></p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="relative">
                    <x-input-label :value="'Artist'" />
                    <x-text-input name="artist" x-model="songArtistQuery" @input="queueArtistLookup()" @focus="showArtistSuggestions = artistSuggestions.length > 0" class="mt-1 block w-full" autocomplete="off" required />
                    <ul x-show="showArtistSuggestions" x-cloak class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg" @click.outside="showArtistSuggestions = false">
                        <template x-for="artist in artistSuggestions" :key="`artist-${artist}`">
                            <li><button type="button" @click="selectArtistSuggestion(artist)" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50" x-text="artist"></button></li>
                        </template>
                    </ul>
                </div>
                <div class="relative">
                    <x-input-label :value="'Title'" />
                    <x-text-input name="title" x-model="songTitleQuery" @input="queueTitleLookup()" @focus="showTitleSuggestions = titleSuggestions.length > 0" class="mt-1 block w-full" autocomplete="off" required />
                    <ul x-show="showTitleSuggestions" x-cloak class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg" @click.outside="showTitleSuggestions = false">
                        <template x-for="track in titleSuggestions" :key="`title-${track.title}`">
                            <li><button type="button" @click="selectTitleSuggestion(track.title, track.duration)" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50" x-text="track.title"></button></li>
                        </template>
                    </ul>
                </div>
            </div>
            <input type="hidden" name="duration" :value="deezerTitleSelected && selectedDeezerDuration ? selectedDeezerDuration : ''">
            <input type="hidden" name="source" :value="deezerTitleSelected ? 'deezer' : ''">
            <div>
                <x-input-label :value="'Notes'" />
                <x-textarea-input name="notes" rows="3" class="mt-1 w-full" />
            </div>
            <div class="space-y-2">
                <span class="block text-sm font-medium text-slate-700">Add slots by</span>
                <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                    <label class="inline-flex items-center gap-2"><input type="radio" value="template" x-model="songSlotAdditionMode"> Band template</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" value="manual" x-model="songSlotAdditionMode"> Choose slots manually</label>
                </div>
            </div>
            <div x-show="songSlotAdditionMode === 'template'" x-cloak>
                <x-input-label :value="'Band Template'" />
                <select name="band_template_id" x-bind:disabled="songSlotAdditionMode !== 'template'" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Choose a band template</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="songSlotAdditionMode === 'manual'" x-cloak>
                <p class="text-sm font-medium text-slate-700">Choose slots manually</p>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($slotOptions as $slotValue => $slotLabel)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700"><input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" x-bind:disabled="songSlotAdditionMode !== 'manual'"> {{ $slotLabel }}</label>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <x-modal-secondary-button type="button" @click="openSong = false; resetSongAutocomplete()">Cancel</x-modal-secondary-button>
                <x-modal-primary-button x-bind:disabled="addSongBusy">Add Song</x-modal-primary-button>
            </div>
        </form>
    </div>
</div>
