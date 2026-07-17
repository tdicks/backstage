@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-amber-400 text-start text-base font-medium text-slate-100 bg-slate-900 focus:outline-none focus:text-white focus:bg-slate-800 focus:border-amber-300 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-slate-300 hover:text-slate-100 hover:bg-slate-900 hover:border-slate-600 focus:outline-none focus:text-slate-100 focus:bg-slate-900 focus:border-slate-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
