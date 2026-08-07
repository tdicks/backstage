@php
    $session = $sessionGroup['session'];
    $sessionKey = 'backstage:u'.auth()->id().':slot-finder:session:'.$session->id;
    $hiddenCardClass = 'border-sky-400 bg-white/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]';
    $sessionCardClass = $session->is_hidden
        ? $hiddenCardClass
        : 'border-slate-200 bg-slate-50/95 shadow-sm';
@endphp

<article
    x-data="{
        collapsed: false,
        sessionKey: @js($sessionKey),
        toggle() {
            this.collapsed = ! this.collapsed;
        },
    }"
    x-init="collapsed = localStorage.getItem(sessionKey) === '1'"
    x-effect="localStorage.setItem(sessionKey, collapsed ? '1' : '0')"
    class="rounded-xl border {{ $sessionCardClass }} p-5 sm:p-6"
>
    <div
        class="flex cursor-pointer items-start justify-between gap-4"
        x-bind:aria-expanded="(!collapsed).toString()"
        aria-label="Toggle session details"
        role="button"
        tabindex="0"
        @click="toggle()"
        @keydown.enter.prevent="toggle()"
        @keydown.space.prevent="toggle()"
    >
        <div class="min-w-0 flex-1">
            <h3 class="flex items-center gap-2 text-xl font-semibold text-slate-900">
                <span class="truncate">{{ $session->name }}</span>
                <span class="inline-flex shrink-0 items-center text-slate-500">
                    <x-heroicon-m-chevron-up x-show="!collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                    <x-heroicon-m-chevron-down x-show="collapsed" x-cloak class="h-4 w-4" aria-hidden="true" />
                </span>
                @if ($session->is_hidden)
                    <span class="inline-flex shrink-0 items-center" title="Jam session is hidden from non-admin users">
                        <x-heroicon-m-eye-slash class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span class="sr-only">Hidden jam session</span>
                    </span>
                @endif
            </h3>
            <p class="mt-1 text-sm text-slate-600">{{ $session->date->format('l, F j, Y') }}</p>
        </div>
        <a
            href="{{ route('sessions.show', $session) }}"
            @click.stop
            class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
            aria-label="Go to session"
            title="Go to session"
        >
            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" aria-hidden="true" />
        </a>
    </div>

    <div x-show="!collapsed" x-cloak x-transition class="mt-5 space-y-4">
        @foreach ($sessionGroup['sets'] as $setGroup)
            @include('slot-finder.partials.set-card', ['session' => $session, 'setGroup' => $setGroup, 'hiddenCardClass' => $hiddenCardClass, 'slotOptions' => $slotOptions])
        @endforeach
    </div>
</article>