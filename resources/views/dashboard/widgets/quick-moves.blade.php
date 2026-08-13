<x-dashboard.widget-frame panel-classes="border-emerald-200 bg-white/95" icon-frame-classes="border-emerald-200 bg-emerald-50 text-emerald-700">
    <x-slot:icon>
        <x-heroicon-m-arrow-right-circle class="h-6 w-6" aria-hidden="true" />
    </x-slot:icon>
    <x-slot:title>Shortcuts</x-slot:title>
    <x-slot:description>Jump straight into common actions.</x-slot:description>

    <div class="grid gap-2">
        <a href="{{ route('jam-standards.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Start a set</a>
        <a href="{{ route('planned-sets.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Plan a set</a>
        <a href="{{ route('slot-finder.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Find a slot</a>
        <a href="{{ route('practice-plan.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-400 hover:bg-sky-50">Practice plan</a>
    </div>
</x-dashboard.widget-frame>

