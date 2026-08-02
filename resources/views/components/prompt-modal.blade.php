@props([
    'open',
    'title',
    'description' => null,
    'maxWidth' => 'lg',
    'closeAction' => null,
])

@php
$maxWidthClass = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
][$maxWidth] ?? 'max-w-lg';

$resolvedCloseAction = $closeAction ?? $open.' = false';
@endphp

<template x-teleport="body">
    <div x-cloak>
        <div
            x-show="{{ $open }}"
            x-cloak
            x-transition.opacity.duration.150ms
            data-drag-blocking-modal
            data-modal-overlay
            class="fixed inset-0 z-[120] bg-black/40"
            @click="{{ $resolvedCloseAction }}"
        ></div>

        <div
            x-show="{{ $open }}"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
            class="fixed inset-0 z-[130] flex items-start justify-center overflow-y-auto p-4 pt-4 sm:items-center sm:pt-4"
            @keydown.escape.window="{{ $resolvedCloseAction }}"
        >
            <section
                class="flex max-h-[calc(100vh-2rem)] w-full {{ $maxWidthClass }} flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-900 text-left text-slate-100 shadow-2xl sm:max-h-[calc(100vh-4rem)]"
                role="dialog"
                aria-modal="true"
                aria-label="{{ $title }}"
                @click.stop
            >
                <header class="flex items-center justify-between gap-3 border-b border-slate-700 px-5 py-4">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-100">{{ $title }}</h4>
                        @if (filled($description))
                            <p class="mt-1 text-xs text-slate-400">{{ $description }}</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        @click="{{ $resolvedCloseAction }}"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-800 hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-400"
                        aria-label="Close prompt"
                        title="Close"
                    >
                        <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    {{ $slot }}
                </div>

                @isset($actions)
                    <footer class="flex items-center justify-end gap-2 border-t border-slate-700 px-5 py-4">
                        {{ $actions }}
                    </footer>
                @endisset
            </section>
        </div>
    </div>
</template>
