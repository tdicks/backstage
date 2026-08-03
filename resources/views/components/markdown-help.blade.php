@props([
    'modalName',
    'title' => 'Markdown Help',
])

@php
    $examples = [
        '# Heading',
        '## Subheading',
        '**bold** and *italic*',
        "- Item one\n- Item two",
        "1. First\n2. Second",
        '[Backstage](https://backstage-v1.test)',
        '> Important note',
        '`inline code`',
    ];
@endphp

<span class="inline-flex items-center">
    <button
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white"
        title="Markdown help"
        aria-label="Open markdown help"
        @click="window.dispatchEvent(new CustomEvent('open-modal', { detail: '{{ $modalName }}' }))"
    >
        <x-heroicon-m-question-mark-circle class="h-4 w-4" aria-hidden="true" />
    </button>

    <x-modal :name="$modalName" maxWidth="2xl" focusable>
        <div class="p-6 text-slate-900">
            <h3 class="text-lg font-semibold">{{ $title }}</h3>
            <p class="mt-1 text-sm text-slate-600">Use these examples to format your content.</p>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">You type</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($examples as $example)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs text-slate-700">{!! nl2br(e($example)) !!}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    <div class="session-markdown text-sm leading-relaxed">
                                        {!! Illuminate\Support\Str::markdown($example) !!}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                Leave a blank line between paragraphs so markdown renders cleanly.
            </div>

            <div class="mt-5 flex justify-end">
                <x-modal-secondary-button
                    type="button"
                    @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: '{{ $modalName }}' }))"
                >
                    Close
                </x-modal-secondary-button>
            </div>
        </div>
    </x-modal>
</span>