@props([
    'size' => 'h-4 w-4',
    'title' => 'Feature set',
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center']) }} title="{{ $title }}">
    <x-heroicon-m-star class="{{ $size }} text-amber-400" aria-hidden="true" />
    <span class="sr-only">{{ $title }}</span>
</span>
