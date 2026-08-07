<div class="flex items-center justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-purple-200 bg-purple-50 text-purple-700">
            <x-heroicon-m-user class="h-6 w-6" aria-hidden="true" />
        </span>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Looking around</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Sets that need players</h3>
        </div>
    </div>
    <a href="{{ route('my-sets.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Open my sets</a>
</div>

<div class="mt-4 grid gap-3 md:grid-cols-2">
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
