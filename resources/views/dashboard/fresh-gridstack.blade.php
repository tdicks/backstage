<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-slate-100">Fresh GridStack Dashboard</h2>
        </div>
    </x-slot>

    <div class="py-10" data-dashboard-fresh-gridstack x-data="dashboardFreshGridstackPersistence()" x-init="init()" x-bind:data-initial-layout-json="initialLayoutJson">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600" data-fresh-gridstack-summary>Layout locked: click Unlock layout to move placeholders.</p>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                        data-fresh-gridstack-toggle
                    >Unlock layout</button>
                </div>
            </section>

            <section class="grid-stack fresh-gridstack" data-fresh-gridstack-canvas x-ignore>
                <div class="grid-stack-item" gs-id="placeholder-1" gs-x="0" gs-y="0" gs-w="4" gs-h="2">
                    <div class="grid-stack-item-content">
                        <div class="fresh-widget">Placeholder 1</div>
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="placeholder-2" gs-x="4" gs-y="0" gs-w="4" gs-h="2">
                    <div class="grid-stack-item-content">
                        <div class="fresh-widget">Placeholder 2</div>
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="placeholder-3" gs-x="8" gs-y="0" gs-w="4" gs-h="2">
                    <div class="grid-stack-item-content">
                        <div class="fresh-widget">Placeholder 3</div>
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="placeholder-4" gs-x="0" gs-y="2" gs-w="6" gs-h="2">
                    <div class="grid-stack-item-content">
                        <div class="fresh-widget">Placeholder 4</div>
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="placeholder-5" gs-x="6" gs-y="2" gs-w="6" gs-h="2">
                    <div class="grid-stack-item-content">
                        <div class="fresh-widget">Placeholder 5</div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
