<section data-tour="profile-privacy-section">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Privacy') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Choose where other users can find you.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.privacy.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <ul data-tour="profile-privacy-toggles" class="space-y-3">
            <li>
                <label for="hide_from_directory" class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="hide_from_directory" value="0">
                    <input id="hide_from_directory" type="checkbox" name="hide_from_directory" value="1" @checked(old('hide_from_directory', $user->hide_from_directory)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>Hide me from the who’s who page</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('hide_from_directory')" />
            </li>

            <li>
                <label for="hide_from_slot_proposals" class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="hide_from_slot_proposals" value="0">
                    <input id="hide_from_slot_proposals" type="checkbox" name="hide_from_slot_proposals" value="1" @checked(old('hide_from_slot_proposals', $user->hide_from_slot_proposals)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>Hide me from the slot proposal user list</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('hide_from_slot_proposals')" />
            </li>
        </ul>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'Privacy updated.')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>