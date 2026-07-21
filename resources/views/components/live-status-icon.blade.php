@props(['size' => 'h-5 w-5', 'title' => 'Jam session is live'])

<span {{ $attributes->merge(['class' => "relative inline-flex {$size} items-center justify-center text-emerald-400"]) }} title="{{ $title }}">
    <span class="absolute inline-flex h-full w-full animate-ping rounded-full border border-emerald-300/70 opacity-75"></span>
    <x-heroicon-m-signal class="relative {{ $size }}" aria-hidden="true" />
</span>