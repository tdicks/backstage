@props(['iconClass' => 'h-4 w-4 text-sky-400'])

<svg {{ $attributes->except('class') }} class="{{ $attributes->get('class', $iconClass) }}" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M10.339 2.083a.75.75 0 0 0-.678 0L3.93 4.78a.75.75 0 0 0-.43.677v3.938a9.026 9.026 0 0 0 4.228 7.67l1.86 1.163a.75.75 0 0 0 .794 0l1.86-1.163A9.026 9.026 0 0 0 16.5 9.395V5.457a.75.75 0 0 0-.43-.677L10.34 2.083Z" clip-rule="evenodd" />
</svg>