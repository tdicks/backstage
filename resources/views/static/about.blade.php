@if (auth()->check())
    <x-app-layout>
        <x-slot name="header">
            <h2 class="text-xl font-semibold text-slate-100">About</h2>
        </x-slot>

        <div class="py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-6 text-sm leading-6 text-slate-300 shadow-sm">
                    <p>
                        Backstage helps players organise jam sessions, build sets, and keep track of who is playing each part.
                    </p>
                </div>
            </div>
        </div>
    </x-app-layout>
@else
    <x-guest-layout>
        <div class="space-y-4 text-sm leading-6 text-slate-700">
            <h1 class="text-xl font-semibold text-slate-950">About</h1>
            <p>
                Backstage helps players organise jam sessions, build sets, and keep track of who is playing each part.
            </p>
        </div>
    </x-guest-layout>
@endif