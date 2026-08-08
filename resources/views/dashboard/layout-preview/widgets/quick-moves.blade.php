<div class="flex items-start gap-3">
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700">
        <x-heroicon-m-arrow-right-circle class="h-6 w-6" aria-hidden="true" />
    </span>
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Quick moves</p>
        <h3 class="mt-1 text-lg font-semibold text-slate-900">Shortcuts</h3>
    </div>
</div>
<div class="mt-4 grid gap-2">
    <a href="{{ route('jam-standards.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Start a set</a>
    <a href="{{ route('planned-sets.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Plan a set</a>
    <a href="{{ route('slot-finder.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Find a slot</a>
    <a href="{{ route('practice-plan.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Practice plan</a>
</div>
