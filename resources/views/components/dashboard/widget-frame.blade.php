@props([
    'panelClasses' => 'border-slate-200 bg-white/95',
    'scrollTheme' => 'light',
    'iconFrameClasses' => 'border-slate-200 bg-slate-50 text-slate-700',
])

@php
    $scrollThemeClass = $scrollTheme === 'dark' ? 'dashboard-widget-scroll-dark' : 'dashboard-widget-scroll-light';
@endphp

<section {{ $attributes->merge(['class' => 'dashboard-widget-frame flex h-full min-h-0 flex-col overflow-hidden rounded-xl border p-4 shadow-sm '.$panelClasses]) }}>
    <header class="dashboard-widget-header flex items-start gap-3">
        @isset($icon)
            <span class="dashboard-widget-icon inline-flex shrink-0 items-center justify-center rounded-lg border {{ $iconFrameClasses }}">
                {{ $icon }}
            </span>
        @endisset

        <div class="min-w-0">
            @isset($title)
                <h3 class="dashboard-widget-title font-semibold text-slate-900">{{ $title }}</h3>
            @endisset

            @isset($description)
                <p class="dashboard-widget-description mt-1 text-sm text-slate-600">{{ $description }}</p>
            @elseif (isset($kicker))
                <p class="dashboard-widget-description mt-1 text-sm text-slate-600">{{ $kicker }}</p>
            @endisset
        </div>
    </header>

    <div class="dashboard-widget-body mt-4 min-h-0 flex-1 overflow-y-auto pr-1 dashboard-widget-scroll {{ $scrollThemeClass }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="dashboard-widget-footer mt-4 border-t border-slate-200/80 pt-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
