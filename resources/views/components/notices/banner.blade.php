@props([
    'notice',
    'dismissUrlTemplate' => null,
    'fullBleed' => false,
    'textAlign' => 'center',
])

@php
    $level = $notice['level'] ?? 'info';

    $styleByLevel = [
        'info' => [
            'container' => 'border-sky-200 bg-sky-50 text-sky-950',
            'icon' => 'text-sky-600',
            'title' => 'text-sky-900',
            'body' => 'text-sky-900/90',
            'button' => 'text-sky-700 hover:bg-sky-100 focus:ring-sky-500',
        ],
        'warning' => [
            'container' => 'border-amber-200 bg-amber-50 text-amber-950',
            'icon' => 'text-amber-600',
            'title' => 'text-amber-900',
            'body' => 'text-amber-900/90',
            'button' => 'text-amber-700 hover:bg-amber-100 focus:ring-amber-500',
        ],
        'critical' => [
            'container' => 'border-rose-200 bg-rose-50 text-rose-950',
            'icon' => 'text-rose-600',
            'title' => 'text-rose-900',
            'body' => 'text-rose-900/90',
            'button' => 'text-rose-700 hover:bg-rose-100 focus:ring-rose-500',
        ],
    ];

    $style = $styleByLevel[$level] ?? $styleByLevel['info'];
    $noticeId = (int) ($notice['id'] ?? 0);
    $dismissable = (bool) ($notice['dismissable'] ?? false);
    $containerClasses = $fullBleed
        ? 'rounded-none border-x-0 shadow-none'
        : 'rounded-lg shadow-sm';
    $spacingClasses = $fullBleed ? 'py-3' : 'px-4 py-3';
    $innerClasses = $fullBleed
        ? 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8'
        : '';
    $textAlignmentClass = $textAlign === 'left' ? 'text-left' : 'text-center';
@endphp

<article
    x-data="{
        hidden: false,
        busy: false,
        async dismiss() {
            if (! @js($dismissable) || this.busy) {
                return;
            }

            this.busy = true;

            try {
                const url = (@js($dismissUrlTemplate) || '').replace('__NOTICE_ID__', String(@js($noticeId)));
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content || '',
                    },
                });

                if (response.ok) {
                    this.hidden = true;
                }
            } finally {
                this.busy = false;
            }
        },
    }"
    x-show="!hidden"
    x-transition.opacity.duration.150ms
    class="border {{ $spacingClasses }} {{ $containerClasses }} {{ $style['container'] }}"
>
    <div class="{{ $innerClasses }}">
        <div class="flex items-center gap-3">
            <div class="{{ $style['icon'] }}" aria-hidden="true">
                @if ($level === 'critical')
                    <x-heroicon-m-x-circle class="h-5 w-5" />
                @elseif ($level === 'warning')
                    <x-heroicon-m-exclamation-triangle class="h-5 w-5" />
                @else
                    <x-heroicon-m-information-circle class="h-5 w-5" />
                @endif
            </div>

            <div class="min-w-0 flex-1 {{ $textAlignmentClass }}">
                <div class="notice-markdown text-sm {{ $style['body'] }}">
                    {!! $notice['content_html'] !!}
                </div>
            </div>

            @if ($dismissable)
                <button
                    type="button"
                    @click="dismiss()"
                    :disabled="busy"
                    class="inline-flex items-center self-center rounded-md p-1 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $style['button'] }}"
                    title="Dismiss notice"
                    aria-label="Dismiss notice"
                >
                    <x-heroicon-m-x-mark class="h-4 w-4" />
                </button>
            @endif
        </div>
    </div>
</article>
