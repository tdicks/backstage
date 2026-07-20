@php
    $mySetsApprovalCount = \App\Http\Controllers\MySetsController::pendingApprovalCount(Auth::user());
@endphp

<nav
    x-data="{
        open: false,
        notificationsOpen: false,
        notificationObserver: null,
        notificationTimers: {},
        toggleNotifications() {
            this.notificationsOpen = ! this.notificationsOpen;

            if (this.notificationsOpen) {
                this.$store.notifications.refresh({ showPopups: false });
                this.syncNotificationObserver();
            } else {
                this.teardownNotificationObserver();
            }
        },
        closeNotifications() {
            this.notificationsOpen = false;
            this.teardownNotificationObserver();
        },
        syncNotificationObserver() {
            this.$nextTick(() => {
                this.teardownNotificationObserver();

                if (! this.notificationsOpen || ! this.$refs.notificationList) {
                    return;
                }

                this.notificationObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        const id = entry.target.dataset.notificationId;

                        if (! id) {
                            return;
                        }

                        if (entry.isIntersecting) {
                            if (this.notificationTimers[id]) {
                                return;
                            }

                            this.notificationTimers[id] = window.setTimeout(() => {
                                this.$store.notifications.markSeen(id);
                                delete this.notificationTimers[id];
                            }, 3000);

                            return;
                        }

                        if (this.notificationTimers[id]) {
                            window.clearTimeout(this.notificationTimers[id]);
                            delete this.notificationTimers[id];
                        }
                    });
                }, {
                    root: this.$refs.notificationList,
                    threshold: 0.75,
                });

                this.$refs.notificationList.querySelectorAll('[data-notification-id]').forEach((element) => {
                    this.notificationObserver.observe(element);
                });
            });
        },
        teardownNotificationObserver() {
            Object.values(this.notificationTimers).forEach((timer) => window.clearTimeout(timer));
            this.notificationTimers = {};

            if (this.notificationObserver) {
                this.notificationObserver.disconnect();
                this.notificationObserver = null;
            }
        },
    }"
    x-init="$store.approvals.init({ count: @js($mySetsApprovalCount), url: @js(route('my-sets.count')) }); $store.notifications.init({ items: @js($navNotificationFeed['notifications']), unreadCount: @js($navNotificationFeed['unread_count']), indexUrl: @js(route('notifications.index')), seenUrlTemplate: @js(route('notifications.seen', '__NOTIFICATION_ID__')), dismissUrlTemplate: @js(route('notifications.dismiss', '__NOTIFICATION_ID__')) })"
    @visibilitychange.window="$store.approvals.refresh(); $store.notifications.refresh({ showPopups: false })"
    @notifications-updated.window="if (notificationsOpen) { syncNotificationObserver() }"
    @target-consent-processed.window="$store.approvals.decrement()"
    @pending-approval-processed.window="$store.approvals.decrement()"
    @keydown.escape.window="closeNotifications()"
    class="border-b border-slate-800 bg-slate-950"
>
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('sessions.index') }}" class="inline-flex items-center gap-2">
                        <x-application-logo class="block h-9 w-9 text-slate-100" />
                        <span class="hidden text-sm font-semibold tracking-wide text-slate-100 sm:inline">Backstage</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <div class="relative inline-flex items-centernllteenter" x-data="{ openJamSessions: false }" @click.outside="openJamSessions = false">
                        <button
                            @click="openJamSessions = !openJamSessions"
                            class="inline-flex items-center border-b-2 bg-transparent px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out appearance-none {{ request()->routeIs('sessions.*') ? 'border-amber-400 text-slate-100 focus:border-amber-300' : 'border-transparent text-slate-300 hover:text-slate-100 hover:border-slate-500 focus:text-slate-100 focus:border-slate-500' }} focus:outline-none"
                        >
                            <span>{{ __('Jam Sessions') }}</span>
                            <x-heroicon-m-chevron-down class="ms-1 h-4 w-4" aria-hidden="true" />
                        </button>

                        <div
                            x-show="openJamSessions"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute start-0 top-full z-50 mt-2 w-72 origin-top-left rounded-md border border-slate-800 bg-slate-900 shadow-2xl ring-1 ring-black/40"
                            style="display: none;"
                            @click="openJamSessions = false"
                        >
                            <div class="max-h-80 overflow-y-auto py-1">
                                <x-dropdown-link :href="route('sessions.index')">
                                    {{ __('All Jam Sessions') }}
                                </x-dropdown-link>

                                <div class="my-1 border-t border-slate-800"></div>

                                @forelse ($navJamSessions as $navSession)
                                    <x-dropdown-link :href="route('sessions.show', $navSession)">
                                        <span class="block truncate">{{ $navSession->name }}</span>
                                        <span class="mt-0.5 block text-xs text-gray-500">{{ $navSession->date->format('M j, Y') }}</span>
                                    </x-dropdown-link>
                                @empty
                                    <span class="block px-4 py-2 text-sm text-gray-500">No jam sessions yet.</span>
                                @endforelse

                            </div>
                        </div>
                    </div>
                    <x-nav-link :href="route('my-sets.index')" :active="request()->routeIs('my-sets.*')">
                        <span>{{ __('My Sets') }}</span>
                        <span
                            class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold leading-none text-amber-800"
                            x-show="$store.approvals.count > 0"
                            x-text="$store.approvals.count"
                            x-cloak
                        >{{ $mySetsApprovalCount }}</span>
                    </x-nav-link>
                    <x-nav-link :href="route('directory.index')" :active="request()->routeIs('directory.*')">
                        {{ __("Who's Who") }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:gap-2">
                <button
                    type="button"
                    @click="toggleNotifications()"
                    class="relative inline-flex h-10 w-10 items-center justify-center border-b-2 text-slate-100 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-400 {{ request()->routeIs('notifications.*') ? 'border-sky-400' : 'border-transparent hover:border-slate-500' }}"
                    title="Notifications"
                    aria-label="Notifications"
                >
                    <x-heroicon-m-bell class="h-5 w-5" aria-hidden="true" />
                    <span
                        class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-500 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-slate-950"
                        x-show="$store.notifications.unreadCount > 0"
                        x-text="$store.notifications.unreadCount"
                        x-cloak
                    >{{ $navNotificationFeed['unread_count'] }}</span>
                </button>
                <a
                    href="{{ route('help') }}"
                    class="inline-flex h-10 w-10 items-center justify-center border-b-2 text-slate-100 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400 {{ request()->routeIs('help') ? 'border-amber-400' : 'border-transparent hover:border-slate-500' }}"
                    title="Help"
                    aria-label="Help"
                >
                    <x-heroicon-m-question-mark-circle class="h-5 w-5" aria-hidden="true" />
                </a>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-md border border-slate-800 bg-slate-900 px-3 py-2 text-sm font-medium leading-4 text-slate-100 transition ease-in-out duration-150 hover:border-slate-700 hover:bg-slate-800 hover:text-white focus:outline-none">
                            <div>
                                {{ Auth::user()->name }}
                                @if (Auth::user()->is_admin)
                                    <span class="ms-1 inline-flex items-center align-middle" title="Admin">
                                        <x-admin-shield-icon class="h-4 w-4 text-sky-400" aria-hidden="true" />
                                        <span class="sr-only">Admin</span>
                                    </span>
                                @endif
                            </div>

                            <div class="ms-1">
                                <x-heroicon-m-chevron-down class="h-4 w-4" aria-hidden="true" />
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>

                        @if (Auth::user()->is_admin)
                            <div class="mx-2 my-2 rounded-md border border-sky-500/40 bg-sky-500/5 py-1">
                                <div class="px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <span class="inline-flex items-center gap-1">
                                        <x-admin-shield-icon class="h-3.5 w-3.5 text-sky-400" aria-hidden="true" />
                                        <span>Admin</span>
                                    </span>
                                </div>
                                <x-dropdown-link :href="route('admin.users.index')">
                                    {{ __('Users') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.settings.index')">
                                    {{ __('Settings') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('band-templates.index')">
                                    {{ __('Band Templates') }}
                                </x-dropdown-link>
                            </div>
                        @endif




                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center gap-2 sm:hidden">
                @if (Auth::user()->is_admin)
                    <span
                        class="inline-flex items-center justify-center text-sky-400"
                        x-show="! open"
                        x-cloak
                        title="Admin"
                        aria-label="Admin"
                    >
                        <x-admin-shield-icon class="h-4 w-4 text-sky-400" aria-hidden="true" />
                    </span>
                @endif
                <a
                    href="{{ route('my-sets.index') }}"
                    class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold leading-none text-amber-800 shadow-sm transition hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-950"
                    x-show="! open && $store.approvals.count > 0"
                    x-text="$store.approvals.count"
                    x-cloak
                    aria-label="View pending My Sets approvals"
                >{{ $mySetsApprovalCount }}</a>
                <a
                    href="#"
                    @click.prevent="toggleNotifications()"
                    class="relative inline-flex h-10 w-10 items-center justify-center border-b-2 text-slate-100 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-400 border-transparent hover:border-slate-500"
                    title="Notifications"
                    aria-label="Notifications"
                >
                    <x-heroicon-m-bell class="h-5 w-5" aria-hidden="true" />
                    <span
                        class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-500 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-slate-950"
                        x-show="$store.notifications.unreadCount > 0"
                        x-text="$store.notifications.unreadCount"
                        x-cloak
                    >{{ $navNotificationFeed['unread_count'] }}</span>
                </a>
                <a
                    href="{{ route('help') }}"
                    class="inline-flex h-10 w-10 items-center justify-center border-b-2 text-slate-100 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400 {{ request()->routeIs('help') ? 'border-amber-400' : 'border-transparent hover:border-slate-500' }}"
                    title="Help"
                    aria-label="Help"
                >
                    <x-heroicon-m-question-mark-circle class="h-5 w-5" aria-hidden="true" />
                </a>
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <x-heroicon-m-bars-3 :class="{'hidden': open, 'inline-flex': ! open }" class="h-6 w-6 inline-flex" aria-hidden="true" />
                    <x-heroicon-m-x-mark :class="{'hidden': ! open, 'inline-flex': open }" class="h-6 w-6 hidden" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('sessions.index')" :active="request()->routeIs('sessions.*')">
                {{ __('All Jam Sessions') }}
            </x-responsive-nav-link>
            <div class="mx-4 my-1 border-t border-slate-800"></div>
            @foreach ($navJamSessions as $navSession)
                <x-responsive-nav-link :href="route('sessions.show', $navSession)" :active="request()->routeIs('sessions.show') && request()->route('jamSession')?->id === $navSession->id">
                    {{ $navSession->name }}
                </x-responsive-nav-link>
            @endforeach
            <div class="mx-4 my-1 border-t border-slate-800"></div>
            <x-responsive-nav-link :href="route('my-sets.index')" :active="request()->routeIs('my-sets.*')">
                <span>{{ __('My Sets') }}</span>
                <span
                    class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold leading-none text-amber-800"
                    x-show="$store.approvals.count > 0"
                    x-text="$store.approvals.count"
                    x-cloak
                >{{ $mySetsApprovalCount }}</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('directory.index')" :active="request()->routeIs('directory.*')">
                {{ __("Who's Who") }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-slate-800 pt-4 pb-1">
            <div class="px-4">
                <div class="font-medium text-base text-slate-100">{{ Auth::user()->name }}
                    @if (Auth::user()->is_admin)
                        <span class="ms-1 inline-flex items-center align-middle" title="Admin">
                            <x-admin-shield-icon class="h-4 w-4 text-sky-400" aria-hidden="true" />
                            <span class="sr-only">Admin</span>
                        </span>
                    @endif
                </div>
                <div class="font-medium text-sm text-slate-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>

                @if (Auth::user()->is_admin)
                    <div class="mx-2 my-2 rounded-md border border-sky-500/40 bg-sky-500/5 py-1">
                        <div class="px-2 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <x-admin-shield-icon class="h-3.5 w-3.5 text-sky-400" aria-hidden="true" />
                                <span>Admin</span>
                            </span>
                        </div>
                        <x-responsive-nav-link :href="route('admin.users.index')">
                            {{ __('Users') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.settings.index')">
                            {{ __('Settings') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('band-templates.index')">
                            {{ __('Band Templates') }}
                        </x-responsive-nav-link>
                    </div>
                @endif


            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div
            x-show="$store.notifications.activeToast"
            x-cloak
            x-transition.opacity.duration.200ms
            class="fixed inset-x-0 top-4 z-[220] flex justify-center px-4"
            role="status"
        >
            <div class="w-full max-w-md rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold" x-text="$store.notifications.activeToast?.title"></p>
                        <p class="mt-1 text-sky-900" x-text="$store.notifications.activeToast?.body"></p>
                        <a
                            x-show="$store.notifications.activeToast?.action_url"
                            x-bind:href="$store.notifications.activeToast?.action_url"
                            @click="closeNotifications(); $store.notifications.closeToast()"
                            class="mt-3 inline-flex text-xs font-semibold uppercase tracking-wide text-sky-700 underline underline-offset-2"
                            x-text="$store.notifications.activeToast?.action_label || 'Open'"
                        ></a>
                    </div>
                    <button type="button" @click="$store.notifications.closeToast()" class="text-sky-700 transition hover:text-sky-900" aria-label="Dismiss notification popup">
                        <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="notificationsOpen" x-cloak class="fixed inset-0 z-[210]">
            <div class="absolute inset-0 bg-slate-950/60 sm:bg-transparent" @click="closeNotifications()"></div>
            <div
                x-show="notificationsOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-6 sm:translate-y-2"
                x-transition:enter-end="opacity-100 translate-x-0 sm:translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-x-0 sm:translate-y-0"
                x-transition:leave-end="opacity-0 translate-x-6 sm:translate-y-2"
                class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col border-l border-slate-800 bg-slate-950 shadow-2xl sm:right-4 sm:top-20 sm:h-auto sm:max-h-[70vh] sm:w-[26rem] sm:rounded-2xl sm:border sm:border-slate-800"
            >
                <div class="flex items-center justify-between border-b border-slate-800 px-4 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Notifications</h3>
                        <p class="mt-1 text-xs text-slate-400" x-text="$store.notifications.unreadCount > 0 ? $store.notifications.unreadCount + ' unread' : 'All caught up'"></p>
                    </div>
                    <button type="button" @click="closeNotifications()" class="rounded-full p-1 text-slate-400 transition hover:bg-slate-800 hover:text-slate-100" aria-label="Close notifications">
                        <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
                <div x-ref="notificationList" class="min-h-0 flex-1 overflow-y-auto">
                    <template x-if="$store.notifications.items.length === 0">
                        <div class="px-4 py-6 text-sm text-slate-400">
                            You have no notifications right now.
                        </div>
                    </template>

                    <div class="divide-y divide-slate-800" x-show="$store.notifications.items.length > 0">
                        <template x-for="notification in $store.notifications.items" :key="notification.id">
                            <div
                                class="flex gap-3 px-4 py-4"
                                x-bind:class="notification.seen ? 'bg-slate-950 text-slate-400' : 'bg-sky-500/8 text-slate-100'"
                                x-bind:data-notification-id="notification.id"
                            >
                                <a
                                    class="min-w-0 flex-1"
                                    x-bind:href="notification.action_url || '#'"
                                    @click.prevent="if (notification.action_url) { closeNotifications(); window.location = notification.action_url; }"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold" x-text="notification.title"></p>
                                            <p class="mt-1 text-sm" x-text="notification.body"></p>
                                            <p class="mt-2 text-xs text-slate-500" x-text="notification.created_at_human"></p>
                                        </div>
                                    </div>
                                </a>
                                <button type="button" @click.prevent="$store.notifications.dismiss(notification.id)" class="shrink-0 rounded-full p-1 text-slate-500 transition hover:bg-slate-800 hover:text-slate-100" aria-label="Dismiss notification">
                                    <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</nav>
