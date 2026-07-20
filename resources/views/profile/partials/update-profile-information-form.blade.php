<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="bio" :value="__('Bio')" />
            <p class="mt-1 text-xs text-gray-500">Describe yourself, instruments you play, and any bands or projects you're involved in.</p>
            <textarea id="bio" name="bio" rows="4" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div class="space-y-3">
            <label for="hide_from_directory" class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="hide_from_directory" value="0">
                <input id="hide_from_directory" type="checkbox" name="hide_from_directory" value="1" @checked(old('hide_from_directory', $user->hide_from_directory)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>Hide me from the who’s who page</span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('hide_from_directory')" />

            <label for="hide_from_slot_proposals" class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="hide_from_slot_proposals" value="0">
                <input id="hide_from_slot_proposals" type="checkbox" name="hide_from_slot_proposals" value="1" @checked(old('hide_from_slot_proposals', $user->hide_from_slot_proposals)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>Hide me from the slot proposal user list</span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('hide_from_slot_proposals')" />
        </div>

        @if ($slotOptions)
            <div>
                <x-input-label :value="__('Slot Coverage')" />
                <p class="mt-1 text-xs text-gray-500">Select the slot types you are able to cover.</p>
                <input type="hidden" name="slot_coverage_present" value="1">
                <p class="mt-1 text-xs text-gray-500">Select the slot types you are able to cover. This lets others see what you can play.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($slotOptions as $key => $name)
                        @php $checked = in_array($key, old('slot_coverage', $user->slot_coverage ?? []), true); @endphp
                        <label
                            x-data="{ selected: @js($checked) }"
                            x-bind:class="selected ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                        >
                            <input type="checkbox" name="slot_coverage[]" value="{{ $key }}" @checked($checked) class="sr-only" @change="selected = $event.target.checked">
                            <x-heroicon-m-check-circle
                                class="h-4 w-4 scale-75 text-indigo-600 opacity-0 transition"
                                x-bind:class="selected ? 'scale-100 opacity-100' : ''"
                                aria-hidden="true"
                            />
                            <span x-bind:class="selected ? 'text-indigo-700' : ''">{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('slot_coverage')" />
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
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
