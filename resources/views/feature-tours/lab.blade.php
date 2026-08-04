<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-100">Feature Tour Lab</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Fallback Layout Test</h3>
                <p class="mt-2 text-sm text-slate-600">
                    This page intentionally uses oversized anchors to exercise tour fallback placement. Access it directly at
                    <span class="font-mono text-xs text-slate-700">/feature-tours/lab</span>.
                </p>
                <div class="mt-4">
                    <x-primary-button type="button" data-feature-tour-start="feature-tour-fallback-lab" data-feature-tour-force="1">
                        Start Fallback Tour
                    </x-primary-button>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h4 class="text-base font-semibold text-slate-900">Control anchors (small)</h4>
                <p class="mt-2 text-sm text-slate-600">These are intentionally compact targets to verify normal callout placement before oversized tests.</p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span data-tour="tour-lab-control-1" class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Control A</span>
                    <span data-tour="tour-lab-control-2" class="inline-flex items-center rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Control B</span>
                    <span data-tour="tour-lab-control-3" class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Control C</span>
                </div>
            </section>

            <section
                data-tour="tour-lab-large-single"
                class="min-h-[62vh] rounded-xl border-2 border-dashed border-amber-300 bg-amber-50/70 p-6"
            >
                <h4 class="text-base font-semibold text-amber-900">Single oversized anchor</h4>
                <p class="mt-2 max-w-2xl text-sm text-amber-800">
                    This region intentionally occupies most of the viewport height so side placement can fail.
                </p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h4 class="text-base font-semibold text-slate-900">Multiple small anchors</h4>
                <p class="mt-2 text-sm text-slate-600">Three compact targets sharing one marker so we can verify multiple-anchor highlight styling.</p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span data-tour="tour-lab-small-multiple" class="inline-flex items-center rounded-full border border-violet-300 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Small Multi A</span>
                    <span data-tour="tour-lab-small-multiple" class="inline-flex items-center rounded-full border border-violet-300 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Small Multi B</span>
                    <span data-tour="tour-lab-small-multiple" class="inline-flex items-center rounded-full border border-violet-300 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Small Multi C</span>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <article
                    data-tour="tour-lab-large-multiple"
                    class="min-h-[42vh] rounded-xl border-2 border-dashed border-sky-300 bg-sky-50/70 p-6"
                >
                    <h4 class="text-base font-semibold text-sky-900">Multiple oversized anchor A</h4>
                </article>
                <article
                    data-tour="tour-lab-large-multiple"
                    class="min-h-[42vh] rounded-xl border-2 border-dashed border-sky-300 bg-sky-50/70 p-6"
                >
                    <h4 class="text-base font-semibold text-sky-900">Multiple oversized anchor B</h4>
                </article>
            </section>
        </div>
    </div>
</x-app-layout>
