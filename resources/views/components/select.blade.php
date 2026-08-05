@props([
    'class' => '',
])

<select {{ $attributes->class([
    'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200',
    $class,
]) }}>
    {{ $slot }}
</select>