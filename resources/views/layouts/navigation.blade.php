@php
    $mySetsApprovalCount = \App\Http\Controllers\MySetsController::pendingApprovalCount(Auth::user());
@endphp

<nav
    x-data="{
        open: false,
    }"
    x-init="$store.approvals.init({ count: @js($mySetsApprovalCount), url: @js(route('my-sets.count')) })"
    @visibilitychange.window="$store.approvals.refresh()"
    @target-consent-processed.window="$store.approvals.decrement()"
    @pending-approval-processed.window="$store.approvals.decrement()"
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
                <a
                    href="{{ route('help') }}"
                    class="inline-flex h-10 w-10 items-center justify-center border-b-2 bg-slate-900 text-slate-100 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400 {{ request()->routeIs('help') ? 'border-amber-400' : 'border-transparent hover:border-slate-500' }}"
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
</nav>
