<x-guest-layout>
    @if ($socialLoginsEnabled && ($googleSocialLoginsEnabled || $facebookSocialLoginsEnabled))
        <div class="space-y-3">
            @if ($googleSocialLoginsEnabled)
                @push('scripts')
                <script src="https://accounts.google.com/gsi/client" async></script>
                @endpush

                <div id="g_id_onload"
                    data-client_id="{{ config('services.google.client_id') }}"
                    data-login_uri="{{ route('social.callback', 'google') }}"
                    data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                    data-type="standard"
                    data-size="large"
                    data-theme="outline"
                    data-text="signup_with"
                    data-shape="rectangular"
                    data-logo_alignment="left">
                </div>
            @endif
            @if ($facebookSocialLoginsEnabled)
                <a href="{{ route('social.redirect', 'facebook') }}" class="flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('Register with Facebook') }}
                </a>
            @endif
        </div>

        <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wide text-slate-500">
            <div class="h-px flex-1 bg-slate-200"></div>
            <span>{{ __('or') }}</span>
            <div class="h-px flex-1 bg-slate-200"></div>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        @if ($returnTo)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
