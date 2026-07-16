<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        $pendingSlotProposalCount = \App\Models\SlotAssignment::query()
            ->where('type', \App\Models\SlotAssignment::TYPE_PROPOSAL)
            ->where('status', \App\Models\SlotAssignment::STATUS_PENDING)
            ->where('target_user_id', Auth::id())
            ->count();
    @endphp

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <div class="relative" x-data="{ openJamSessions: false }" @click.outside="openJamSessions = false">
                        <button
                            @click="openJamSessions = !openJamSessions"
                            class="inline-flex items-center border-b-2 bg-transparent px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out appearance-none {{ request()->routeIs('sessions.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }} focus:outline-none"
                        >
                            <span>{{ __('Jam Sessions') }}</span>
                            <svg class="ms-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div
                            x-show="openJamSessions"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute start-0 z-50 mt-2 w-72 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
                            style="display: none;"
                            @click="openJamSessions = false"
                        >
                            <div class="max-h-80 overflow-y-auto py-1">
                                <x-dropdown-link :href="route('sessions.index')">
                                    {{ __('All Jam Sessions') }}
                                </x-dropdown-link>

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
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <span>{{ __('My Signups') }}</span>
                        @if ($pendingSlotProposalCount > 0)
                            <span class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold leading-none text-amber-800">
                                {{ $pendingSlotProposalCount }}
                            </span>
                        @endif
                    </x-nav-link>
                    <x-nav-link :href="route('my-sets.index')" :active="request()->routeIs('my-sets.*')">
                        {{ __('My Sets') }}
                    </x-nav-link>
                    <x-nav-link :href="route('directory.index')" :active="request()->routeIs('directory.*')">
                        {{ __('User Directory') }}
                    </x-nav-link>
                    @if (Auth::user()->is_admin)
                    <span class="inline-flex items-center self-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-slate-600">
                        Admin
                    </span>
                    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700">
                            {{ __('Users') }}
                        </span>
                    </x-nav-link>
                    <x-nav-link :href="route('band-templates.index')" :active="request()->routeIs('band-templates.*')">
                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700">
                            {{ __('Band Templates') }}
                        </span>
                    </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>
                                {{ Auth::user()->name }}
                            @if (Auth::user()->is_admin)
                                (Admin)
                            @endif
                            </div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
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
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
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
            @foreach ($navJamSessions as $navSession)
                <x-responsive-nav-link :href="route('sessions.show', $navSession)" :active="request()->routeIs('sessions.show') && request()->route('jamSession')?->id === $navSession->id">
                    {{ $navSession->name }}
                </x-responsive-nav-link>
            @endforeach
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <span>{{ __('My Signups') }}</span>
                @if ($pendingSlotProposalCount > 0)
                    <span class="ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold leading-none text-amber-800">
                        {{ $pendingSlotProposalCount }}
                    </span>
                @endif
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('my-sets.index')" :active="request()->routeIs('my-sets.*')">
                {{ __('My Sets') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('directory.index')" :active="request()->routeIs('directory.*')">
                {{ __('User Directory') }}
            </x-responsive-nav-link>
            @if (Auth::user()->is_admin)
            <div class="px-4 pt-2 text-xs font-medium uppercase tracking-wide text-slate-600">Admin</div>
            <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700">
                    {{ __('Users Admin') }}
                </span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('band-templates.index')" :active="request()->routeIs('band-templates.*')">
                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700">
                    {{ __('Band Templates') }}
                </span>
            </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
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
            </div>
        </div>
    </div>
</nav>
