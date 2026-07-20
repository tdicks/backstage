<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Notification Preferences') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Choose which notifications you receive in Backstage, and how they are delivered.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')
        <input type="hidden" name="name" value="{{ $user->name }}">
        <input type="hidden" name="email" value="{{ $user->email }}">

        <div>
            <x-input-label for="mobile_number" :value="__('Mobile Number')" />
            <p class="mt-1 text-xs text-gray-500">Used for future text or WhatsApp notifications if you opt in later.</p>
            <x-text-input id="mobile_number" name="mobile_number" type="text" class="mt-1 block w-full" :value="old('mobile_number', $user->mobile_number)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('mobile_number')" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <div class="grid grid-cols-[minmax(0,1fr)_5rem_5rem_5rem] gap-3 bg-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">
                <div>Notification</div>
                <div class="text-center">Type</div>
                <div class="text-center">Popup</div>
                <div class="text-center">Email</div>
            </div>
            <div class="divide-y divide-slate-200 bg-white">
                @forelse ($notificationOptions as $option)
                    <div class="grid grid-cols-[minmax(0,1fr)_5rem_5rem_5rem] gap-3 px-4 py-4 text-sm text-slate-700">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $option['label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $option['description'] }}</p>
                        </div>
                        <div class="flex items-center justify-center">
                            <input type="hidden" name="notification_preferences[{{ $option['type'] }}][enabled]" value="0">
                            <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][enabled]" value="1" @checked(old('notification_preferences.'.$option['type'].'.enabled', $option['enabled'])) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        </div>
                        <div class="flex items-center justify-center">
                            @if ($option['popup_available'])
                                <input type="hidden" name="notification_preferences[{{ $option['type'] }}][popup]" value="0">
                            @endif
                            <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][popup]" value="1" @checked(old('notification_preferences.'.$option['type'].'.popup', $option['popup'])) @disabled(! $option['popup_available']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                        </div>
                        <div class="flex items-center justify-center">
                            @if ($option['email_available'])
                                <input type="hidden" name="notification_preferences[{{ $option['type'] }}][email]" value="0">
                            @endif
                            <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][email]" value="1" @checked(old('notification_preferences.'.$option['type'].'.email', $option['email'])) @disabled(! $option['email_available']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-5 text-sm text-slate-500">
                        No notification types are currently available.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Preferences') }}</x-primary-button>

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
