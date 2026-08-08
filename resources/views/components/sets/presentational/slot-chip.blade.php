@props([
    'tag' => 'button',
    'type' => null,
    'labelText' => null,
    'labelExpr' => null,
    'titleExpr' => null,
    'expandedExpr' => null,
    'chipClass' => 'inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-300',
])

<{{ $tag }}
    @if ($type) type="{{ $type }}" @endif
    {{ $attributes->class($chipClass) }}
    @if ($titleExpr) x-bind:title="{{ $titleExpr }}" @endif
    @if ($expandedExpr) x-bind:aria-expanded="{{ $expandedExpr }}" @endif
>
    <span class="truncate font-semibold text-slate-900">
        @if ($labelExpr)
            <span x-text="{{ $labelExpr }}"></span>
        @else
            {{ $labelText }}
        @endif
    </span>

    @isset($badge)
        {{ $badge }}
    @endisset
</{{ $tag }}>