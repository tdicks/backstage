<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Notification Preferences') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Choose which notifications you receive in Backstage, and how they are delivered.
        </p>
        <p class="mt-1 text-xs text-slate-500">
            If push notifications are enabled here but are not arriving, check your browser site permission settings for notifications.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')
        <input type="hidden" name="name" value="{{ $user->name }}">
        <input type="hidden" name="email" value="{{ $user->email }}">

        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3" x-data="{ pushPermissionResult: null }">
            <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Browser Push Permission</p>
                    <p class="text-xs text-slate-600">Grant browser permission to receive push notifications on this device.</p>
                </div>

                <button
                    type="button"
                    x-show="$store.notifications.canRequestPushPermission()"
                    x-cloak
                    @click="pushPermissionResult = await $store.notifications.requestPushPermissionAndSync()"
                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    Enable Push Notifications
                </button>
            </div>

            <p x-show="$store.notifications.pushPermissionState() === 'granted'" x-cloak class="mt-2 text-xs text-emerald-700">
                Push notifications are enabled for this browser.
            </p>
            <p x-show="$store.notifications.pushPermissionState() === 'denied'" x-cloak class="mt-2 text-xs text-amber-700">
                Push notifications are blocked by this browser. Update browser site permissions to enable them.
            </p>
            <p x-show="pushPermissionResult === 'default'" x-cloak class="mt-2 text-xs text-slate-600">
                Push notification permission was dismissed. You can try again anytime.
            </p>
        </div>

        <div class="space-y-6">
            @forelse ($notificationOptions as $group)
                @php($isAdminNotificationGroup = $group['category'] === 'admin')
                <div
                    class="overflow-hidden rounded-xl border {{ $isAdminNotificationGroup ? 'border-sky-400' : 'border-slate-200' }}"
                    x-data="{
                        applyToColumn(channel, value) {
                            const selector = 'input[type=checkbox][data-notification-target=true][data-notification-channel=' + channel + ']';

                            this.$refs.optionRows
                                .querySelectorAll(selector)
                                .forEach((input) => {
                                    if (input.disabled) {
                                        return;
                                    }

                                    input.checked = value;
                                    input.dispatchEvent(new Event('change', { bubbles: true }));
                                });
                        },
                    }"
                >
                    <div class="border-b {{ $isAdminNotificationGroup ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3">
                        <h3 class="flex items-center gap-2 font-semibold {{ $isAdminNotificationGroup ? 'text-sky-900' : 'text-slate-900' }}">
                            @if ($isAdminNotificationGroup)
                                <x-admin-shield-icon class="h-4 w-4 text-sky-500" aria-hidden="true" />
                            @endif
                            {{ $group['label'] }}
                        </h3>
                    </div>
                    <div class="border-b border-slate-200 bg-white">
                        <div class="grid grid-cols-[minmax(0,1fr)_5rem_5rem_5rem_5rem] gap-3 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <div>Notification</div>
                            <div class="text-center">Enabled</div>
                            <div class="text-center">Popup</div>
                            <div class="text-center">Email</div>
                            <div class="text-center">Push</div>
                        </div>
                        <div class="grid grid-cols-[minmax(0,1fr)_5rem_5rem_5rem_5rem] gap-3 border-t border-slate-200 bg-slate-50/80 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <div>Apply to all</div>
                            @foreach (['enabled', 'popup', 'email', 'push'] as $channel)
                                <div class="flex items-center justify-center">
                                    <input
                                        type="checkbox"
                                        @change="applyToColumn(@js($channel), $event.target.checked)"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        aria-label="Apply {{ $channel }} to all {{ strtolower($group['label']) }} notifications"
                                    >
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div x-ref="optionRows" class="divide-y divide-slate-200 bg-white">
                        @forelse ($group['options'] as $option)
                            <div class="grid grid-cols-[minmax(0,1fr)_5rem_5rem_5rem_5rem] gap-3 px-4 py-4 text-sm text-slate-700">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $option['label'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $option['description'] }}</p>
                                </div>
                                <div class="flex items-center justify-center">
                                    <input type="hidden" name="notification_preferences[{{ $option['type'] }}][enabled]" value="0">
                                    <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][enabled]" value="1" data-notification-target="true" data-notification-channel="enabled" @checked(old('notification_preferences.'.$option['type'].'.enabled', $option['enabled'])) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                </div>
                                <div class="flex items-center justify-center">
                                    @if ($option['popup_available'])
                                        <input type="hidden" name="notification_preferences[{{ $option['type'] }}][popup]" value="0">
                                    @endif
                                    <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][popup]" value="1" data-notification-target="true" data-notification-channel="popup" @checked(old('notification_preferences.'.$option['type'].'.popup', $option['popup'])) @disabled(! $option['popup_available']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                </div>
                                <div class="flex items-center justify-center">
                                    @if ($option['email_available'])
                                        <input type="hidden" name="notification_preferences[{{ $option['type'] }}][email]" value="0">
                                    @endif
                                    <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][email]" value="1" data-notification-target="true" data-notification-channel="email" @checked(old('notification_preferences.'.$option['type'].'.email', $option['email'])) @disabled(! $option['email_available']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                </div>
                                <div class="flex items-center justify-center">
                                    @if ($option['push_available'])
                                        <input type="hidden" name="notification_preferences[{{ $option['type'] }}][push]" value="0">
                                    @endif
                                    <input type="checkbox" name="notification_preferences[{{ $option['type'] }}][push]" value="1" data-notification-target="true" data-notification-channel="push" @checked(old('notification_preferences.'.$option['type'].'.push', $option['push'])) @disabled(! $option['push_available']) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-5 text-sm text-slate-500">
                                No notifications in this category.
                            </div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">
                    No notification types are currently available.
                </div>
            @endforelse
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Preferences') }}</x-primary-button>

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
