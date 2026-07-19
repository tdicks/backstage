@if (auth()->check())
    <x-app-layout>
        <x-slot name="header">
            <h2 class="text-xl font-semibold text-slate-100">Privacy Policy</h2>
        </x-slot>

        <div class="py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/70 p-6 text-sm leading-6 text-slate-300 shadow-sm">
                    <p>
                        Backstage stores account and session information so organisers and players can coordinate jam sessions.
                    </p>
                    <p>
                        Personal information should only be used for running the sessions and supporting the people taking part.
                    </p>
                </div>
            </div>
        </div>
    </x-app-layout>
@else
    <x-guest-layout>
        <div class="space-y-4 text-sm leading-6 text-slate-700">
            <h1 class="text-xl font-semibold text-slate-950">Privacy Policy</h1>
            <p>
                Backstage stores account and session information so organisers and players can coordinate jam sessions.
            </p>
            <p>
                Personal information should only be used for running the sessions and supporting the people taking part.
            </p>
        </div>
    </x-guest-layout>
@endif