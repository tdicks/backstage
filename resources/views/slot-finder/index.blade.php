<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="mt-1 text-2xl font-semibold text-slate-100">Find a Slot</h1>
            @if ($sessionGroups->isEmpty())
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    There are no open slots right now.
                </p>
            @else
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    These sets are looking for performers!
                </p>
            @endif
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="space-y-4">
                @forelse ($sessionGroups as $sessionGroup)
                    @include('slot-finder.partials.session-group', ['sessionGroup' => $sessionGroup, 'slotOptions' => $slotOptions])
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center shadow-sm">
                        <x-heroicon-m-musical-note class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" />
                        <h3 class="mt-4 text-lg font-semibold text-slate-800">No open slots right now</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Try again later, or update your slot coverage so matching parts are easier to spot.
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            <a href="{{ route('sessions.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-400">
                                Browse jam sessions
                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                            </a>
                            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                Update slot coverage
                                <x-heroicon-m-adjustments-horizontal class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>