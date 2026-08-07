@props([
    'heading',
    'headingClass' => 'text-xs font-semibold uppercase tracking-wide text-slate-500',
    'panelClass' => 'rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-3 shadow-sm',
])

<div {{ $attributes->class($panelClass) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="{{ $headingClass }}">{{ $heading }}</p>
        @isset($meta)
            {{ $meta }}
        @endisset
    </div>

    {{ $slot }}
</div>