<section data-tour="profile-information-section">
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
            <x-input-label for="mobile_number" :value="__('Mobile Number')" />
            <p class="mt-1 text-xs text-gray-500">Used for future text or WhatsApp notifications if you opt in later.</p>
            <x-text-input id="mobile_number" name="mobile_number" type="text" class="mt-1 block w-full" :value="old('mobile_number', $user->mobile_number)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('mobile_number')" />
        </div>

        <div>
            <x-input-label for="bio" :value="__('Bio')" />
            <p class="mt-1 text-xs text-gray-500">Describe yourself, instruments you play, and any bands or projects you're involved in.</p>
            <x-textarea-input id="bio" name="bio" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ old('bio', $user->bio) }}</x-textarea-input>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        @if ($slotOptions)
            <div data-tour="profile-slot-coverage-section">
                <x-input-label :value="__('Slot Coverage')" />
                <input type="hidden" name="slot_coverage_present" value="1">
                <p class="mt-1 text-xs text-gray-500">Choose which slots you want to see in the Slot Finder. Green ticked slots will be given priority, while red crossed slots won't be shown at all.</p>
                @php $coverageStates = \App\Models\User::slotCoverageStates(); @endphp
                <div data-tour="profile-slot-coverage-chips" class="mt-2 flex flex-wrap gap-2">
                    @foreach ($slotOptions as $key => $name)
                        @php $coverageState = old('slot_coverage.'.$key, $user->slotCoverageState($key)); @endphp
                        <div
                            x-data="{
                                state: @js($coverageState),
                                states: @js(array_keys($coverageStates)),
                                labels: @js($coverageStates),
                                symbols: {
                                    can: '✓',
                                    unspecified: '•',
                                    wont_cover: '✕',
                                },
                                cycle() {
                                    const currentIndex = this.states.indexOf(this.state);
                                    const nextIndex = (currentIndex + 1) % this.states.length;

                                    this.state = this.states[nextIndex];
                                },
                                stateClasses() {
                                    if (this.state === '{{ \App\Models\User::SLOT_COVERAGE_CAN }}') {
                                        return 'border-emerald-300 bg-emerald-50 text-emerald-700';
                                    }

                                    if (this.state === '{{ \App\Models\User::SLOT_COVERAGE_WONT }}') {
                                        return 'border-rose-300 bg-rose-50 text-rose-700';
                                    }

                                    return 'border-slate-200 bg-white text-slate-600 hover:border-slate-300';
                                },
                            }"
                            class="shrink-0"
                        >
                            <input type="hidden" name="slot_coverage[{{ $key }}]" x-bind:value="state">
                            <button
                                type="button"
                                @click="cycle()"
                                class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"
                                x-bind:class="stateClasses()"
                                x-bind:aria-label="`${labels[state]} for {{ $name }}`"
                            >
                                <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_CAN }}'" x-cloak aria-hidden="true" class="shrink-0">
                                    <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                                </span>
                                <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_UNSPECIFIED }}'" x-cloak aria-hidden="true" class="shrink-0">
                                    <x-heroicon-m-question-mark-circle class="h-4 w-4 text-slate-400" aria-hidden="true" />
                                </span>
                                <span x-show="state === '{{ \App\Models\User::SLOT_COVERAGE_WONT }}'" x-cloak aria-hidden="true" class="shrink-0">
                                    <x-heroicon-m-x-circle class="h-4 w-4 text-rose-600" aria-hidden="true" />
                                </span>
                                <span class="truncate">{{ $name }}</span>
                            </button>
                        </div>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('slot_coverage')" />
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button data-tour="profile-slot-coverage-save">{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'Profile updated.')
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
