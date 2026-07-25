<x-guest-layout>
    <div
        x-data="{
            seconds: 5,
            redirectUrl: @js(route('home')),
            startCountdown() {
                window.setInterval(() => {
                    this.seconds -= 1;

                    if (this.seconds <= 0) {
                        window.location.assign(this.redirectUrl);
                    }
                }, 1000);
            },
        }"
        x-init="startCountdown()"
        class="py-4 text-center"
    >
        <x-heroicon-m-check-circle class="mx-auto h-12 w-12 text-emerald-600" aria-hidden="true" />
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">You&apos;ve logged out</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">You&apos;ll be redirected to the homepage in <span class="font-semibold text-slate-900" x-text="seconds">5</span> seconds.</p>
        <a href="{{ route('home') }}" class="mt-6 inline-flex items-center gap-2 rounded-md bg-amber-400 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
            Go to the homepage now
            <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
        </a>
    </div>
</x-guest-layout>