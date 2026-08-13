<x-dashboard.widget-frame panel-classes="border-purple-200 bg-white/95" icon-frame-classes="border-purple-200 bg-purple-50 text-purple-700">
    <x-slot:icon>
        <x-heroicon-m-user class="h-6 w-6" aria-hidden="true" />
    </x-slot:icon>
    <x-slot:title>Sets that need players</x-slot:title>
    <x-slot:description>Browse open spots and find where you can jump in.</x-slot:description>

    <div class="grid gap-3 md:grid-cols-2">
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Evening Jam</p>
            <p class="mt-1 text-sm text-slate-600">Needs guitar and keys.</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">Open now</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">2 spots</span>
            </div>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Sunday Set</p>
            <p class="mt-1 text-sm text-slate-600">Good for a quick browse.</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">Fresh</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">Needs help</span>
            </div>
        </article>
    </div>

    <x-slot:footer>
        <a href="{{ route('my-sets.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Open my sets</a>
    </x-slot:footer>
</x-dashboard.widget-frame>

