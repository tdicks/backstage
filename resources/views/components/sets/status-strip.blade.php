@props([
    'status' => null,
    'showSignups' => false,
    'signupsOpen' => null,
    'signupsOpenExpr' => null,
    'showAttachments' => false,
    'hasAttachments' => null,
    'hasAttachmentsExpr' => null,
    'attachmentButton' => false,
    'attachmentClick' => null,
    'attachmentTitle' => 'Open set attachments',
    'attachmentIconClass' => 'text-slate-500',
    'attachmentButtonClass' => 'inline-flex items-center rounded-sm text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400',
    'attachmentButtonClassExpr' => null,
    'attachmentContext' => null,
    'attachmentKey' => null,
    'attachmentCount' => null,
    'attachmentListUrl' => null,
    'attachmentStoreUrl' => null,
    'showHealth' => false,
    'healthDotClass' => 'bg-slate-400',
    'healthTitle' => null,
    'healthSrLabel' => null,
    'showFreeForAll' => false,
    'freeForAll' => null,
    'freeForAllExpr' => null,
    'showHidden' => false,
    'hidden' => null,
    'hiddenExpr' => null,
    'hiddenIconClass' => 'text-sky-500',
    'showSongRequests' => false,
    'songRequestsOpen' => null,
    'songRequestsOpenExpr' => null,
])

@php
    $signupsReactive = filled($signupsOpenExpr);
    $freeForAllReactive = filled($freeForAllExpr);
    $hiddenReactive = filled($hiddenExpr);
    $songRequestsReactive = filled($songRequestsOpenExpr);
    $attachmentsReactive = filled($hasAttachmentsExpr);
@endphp

@if ($status === 'performed')
    <span class="inline-flex items-center" title="Performed">
        <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
        <span class="sr-only">Performed</span>
    </span>
@elseif ($status === 'scheduled')
    <span class="inline-flex items-center" title="Scheduled">
        <x-heroicon-m-clock class="h-4 w-4 text-sky-600" aria-hidden="true" />
        <span class="sr-only">Scheduled</span>
    </span>
@elseif ($status === 'planned')
    <span class="inline-flex items-center" title="Planned">
        <x-heroicon-m-clock class="h-4 w-4 text-slate-400" aria-hidden="true" />
        <span class="sr-only">Planned</span>
    </span>
@endif

@if ($showSignups)
    @if ($signupsReactive)
        <span class="inline-flex items-center" x-bind:title="'Sign ups ' + (({{ $signupsOpenExpr }}) ? 'open' : 'closed')">
            <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" x-show="{{ $signupsOpenExpr }}" x-cloak aria-hidden="true" />
            <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" x-show="!({{ $signupsOpenExpr }})" x-cloak aria-hidden="true" />
            <span class="sr-only" x-text="({{ $signupsOpenExpr }}) ? 'Sign ups open' : 'Sign ups closed'"></span>
        </span>
    @else
        <span class="inline-flex items-center" title="Sign ups {{ $signupsOpen ? 'open' : 'closed' }}">
            @if ($signupsOpen)
                <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                <span class="sr-only">Sign ups open</span>
            @else
                <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                <span class="sr-only">Sign ups closed</span>
            @endif
        </span>
    @endif
@endif

@if ($showSongRequests)
    @if ($songRequestsReactive)
        <span class="inline-flex items-center" x-bind:title="({{ $songRequestsOpenExpr }}) ? 'Song requests open' : 'Song requests closed'">
            <x-heroicon-m-musical-note class="h-4 w-4 text-emerald-700" x-show="{{ $songRequestsOpenExpr }}" x-cloak aria-hidden="true" />
            <x-heroicon-m-musical-note class="h-4 w-4 text-slate-400" x-show="!({{ $songRequestsOpenExpr }})" x-cloak aria-hidden="true" />
            <span class="sr-only" x-text="({{ $songRequestsOpenExpr }}) ? 'Song requests open' : 'Song requests closed'"></span>
        </span>
    @else
        <span class="inline-flex items-center" title="{{ $songRequestsOpen ? 'Song requests open' : 'Song requests closed' }}">
            @if ($songRequestsOpen)
                <x-heroicon-m-musical-note class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                <span class="sr-only">Song requests open</span>
            @else
                <x-heroicon-m-musical-note class="h-4 w-4 text-slate-400" aria-hidden="true" />
                <span class="sr-only">Song requests closed</span>
            @endif
        </span>
    @endif
@endif

@if ($showFreeForAll)
    @if ($freeForAllReactive)
        <span class="inline-flex items-center" x-show="{{ $freeForAllExpr }}" x-cloak title="Free for all mode">
            <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
            <span class="sr-only">Free for all mode</span>
        </span>
    @elseif ($freeForAll)
        <span class="inline-flex items-center" title="Free for all mode">
            <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
            <span class="sr-only">Free for all mode</span>
        </span>
    @endif
@endif

@if ($showHidden)
    @if ($hiddenReactive)
        <span class="inline-flex items-center" x-show="{{ $hiddenExpr }}" x-cloak title="Hidden set">
            <x-heroicon-m-eye-slash class="h-4 w-4 {{ $hiddenIconClass }}" aria-hidden="true" />
            <span class="sr-only">Hidden set</span>
        </span>
    @elseif ($hidden)
        <span class="inline-flex items-center" title="Hidden set">
            <x-heroicon-m-eye-slash class="h-4 w-4 {{ $hiddenIconClass }}" aria-hidden="true" />
            <span class="sr-only">Hidden set</span>
        </span>
    @endif
@endif

@if ($showAttachments)
    @if ($attachmentButton)
        <button
            type="button"
            @if ($attachmentsReactive)
                x-show="{{ $hasAttachmentsExpr }}"
                x-cloak
            @elseif (! $hasAttachments)
                style="display:none"
            @endif
            @if (filled($attachmentClick))
                @click.stop="{{ $attachmentClick }}"
            @endif
            @if (filled($attachmentButtonClassExpr))
                x-bind:class="{{ $attachmentButtonClassExpr }}"
            @endif
            @if (filled($attachmentContext))
                data-attachment-context="{{ $attachmentContext }}"
            @endif
            @if (filled($attachmentKey))
                data-attachment-key="{{ $attachmentKey }}"
            @endif
            @if (!is_null($attachmentCount))
                data-attachment-count="{{ (int) $attachmentCount }}"
            @endif
            @if (filled($attachmentListUrl))
                data-attachment-list-url="{{ $attachmentListUrl }}"
            @endif
            @if (filled($attachmentStoreUrl))
                data-attachment-store-url="{{ $attachmentStoreUrl }}"
            @endif
            class="{{ $attachmentButtonClass }}"
            aria-label="{{ $attachmentTitle }}"
            title="{{ $attachmentTitle }}"
        >
            <x-heroicon-m-paper-clip class="h-4 w-4 {{ $attachmentIconClass }}" aria-hidden="true" />
            <span class="sr-only">Set has attachments</span>
        </button>
    @else
        @if ($attachmentsReactive)
            <span class="inline-flex items-center" x-show="{{ $hasAttachmentsExpr }}" x-cloak title="Set has attachments">
                <x-heroicon-m-paper-clip class="h-4 w-4 {{ $attachmentIconClass }}" aria-hidden="true" />
                <span class="sr-only">Set has attachments</span>
            </span>
        @elseif ($hasAttachments)
            <span class="inline-flex items-center" title="Set has attachments">
                <x-heroicon-m-paper-clip class="h-4 w-4 {{ $attachmentIconClass }}" aria-hidden="true" />
                <span class="sr-only">Set has attachments</span>
            </span>
        @endif
    @endif
@endif

@if ($showHealth)
    <span class="inline-flex items-center" title="{{ $healthTitle }}">
        <span class="h-2.5 w-2.5 rounded-full {{ $healthDotClass }}"></span>
        <span class="sr-only">{{ $healthSrLabel }}</span>
    </span>
@endif