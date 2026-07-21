@props([
    'size' => 'h-2 w-2',
    'title' => 'Checked in',
])

<span {{ $attributes->merge(['class' => "inline-block {$size} animate-pulse rounded-full bg-emerald-400"]) }} title="{{ $title }}"></span>
