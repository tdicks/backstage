@props(['disabled' => false])

@php
	$inputType = (string) $attributes->get('type', 'text');
	$defaults = [
		'class' => 'bg-white px-3 py-2 text-gray-900 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm',
	];

	if ($inputType === 'search') {
		$defaults['autocomplete'] = 'off';
		$defaults['autocorrect'] = 'off';
		$defaults['autocapitalize'] = 'off';
		$defaults['spellcheck'] = 'false';
	}
@endphp

<input @disabled($disabled) {{ $attributes->merge($defaults) }}>
