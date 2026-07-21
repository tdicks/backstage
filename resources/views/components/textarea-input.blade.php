@props(['disabled' => false])

<textarea @disabled($disabled) {{ $attributes->merge(['class' => 'bg-white px-3 py-2 text-gray-900 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>{{ $slot }}</textarea>