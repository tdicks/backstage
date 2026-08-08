@props([
    'tag' => 'article',
    'wrapperClass' => 'rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-3 shadow-sm transition-all duration-300 ease-out',
])

<{{ $tag }} {{ $attributes->class($wrapperClass) }}>
    {{ $slot }}
</{{ $tag }}>
