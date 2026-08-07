@props([
    'titleText' => null,
    'titleExpr' => null,
    'notesText' => null,
    'notesExpr' => null,
    'notesShowExpr' => null,
    'metaText' => null,
    'metaExpr' => null,
    'wrapperClass' => 'relative rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-3 shadow-sm transition-all duration-300 ease-out',
])

<div {{ $attributes->class($wrapperClass) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            @if ($titleExpr)
                <p class="text-sm font-semibold text-slate-900" x-text="{{ $titleExpr }}"></p>
            @else
                <p class="text-sm font-semibold text-slate-900">{{ $titleText }}</p>
            @endif

            @if ($notesExpr)
                <p class="mt-1 text-xs text-slate-600" @if ($notesShowExpr) x-show="{{ $notesShowExpr }}" x-cloak @endif x-text="{{ $notesExpr }}"></p>
            @elseif (filled($notesText))
                <p class="mt-1 text-xs text-slate-600">{{ $notesText }}</p>
            @endif
        </div>

        @isset($actions)
            {{ $actions }}
        @endisset

        @if ($metaExpr)
            <p class="shrink-0 text-xs text-slate-500" x-text="{{ $metaExpr }}"></p>
        @elseif (filled($metaText))
            <p class="shrink-0 text-xs text-slate-500">{{ $metaText }}</p>
        @endif
    </div>

    {{ $slot }}
</div>