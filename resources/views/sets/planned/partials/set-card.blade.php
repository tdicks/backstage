<article
    class="rounded-xl border p-4"
    x-bind:class="set.is_hidden
        ? 'border-sky-400 bg-slate-50/95 shadow-[0_1px_2px_0_rgb(0_0_0_/_0.05),inset_0_0_8px_rgb(125_211_252_/_0.65),inset_0_0_20px_rgb(186_230_253_/_0.55)]'
        : 'border-slate-200 bg-slate-50/95 shadow-sm'"
>
    @include('sets.planned.partials.set-card.header')
    @include('sets.planned.partials.set-card.songs-and-slots')
    @include('sets.planned.partials.set-card.availability')
</article>
