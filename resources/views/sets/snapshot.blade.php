@php
    $slotNames = collect($summary['slot_names'] ?? []);
    $songs = collect($summary['songs'] ?? []);
    $rows = $songs->isNotEmpty()
        ? $songs
        : collect([['artist' => 'No songs in this set yet.', 'title' => '', 'slot_map' => []]]);
    $songColumnWidth = 280;
    $slotColumnWidth = 140;
    $outerPaddingX = 24;
    $innerCellPaddingX = 10;
    $innerCellPaddingY = 10;
    $width = ($outerPaddingX * 2) + $songColumnWidth + ($slotNames->count() * $slotColumnWidth);
    $height = 108 + 42 + ($rows->count() * 56) + 40;
@endphp

<div xmlns="http://www.w3.org/1999/xhtml" data-snapshot-width="{{ $width }}" data-snapshot-height="{{ $height }}" style="width: {{ $width }}px; min-height: {{ $height }}px; background: #e2e8f0; color: #0f172a; font-family: 'Instrument Sans', Arial, sans-serif;">
    <div style="height: 8px; background: #0ea5e9;"></div>

    <div style="padding: 16px {{ $outerPaddingX }}px 0; box-sizing: border-box;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border: 1px solid #cbd5e1; border-radius: 10px; background: #ffffff; padding: 16px 18px 14px; box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);">
            <div style="min-width: 0; flex: 1;">
                <div style="font-size: 10px; font-weight: 700; letter-spacing: 0.18em; color: #0369a1;">SETLIST</div>
                <div style="margin-top: 4px; font-size: 24px; font-weight: 700; line-height: 1.1; color: #0f172a; word-break: break-word;">{{ $set->name }}</div>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 18px; font-size: 14px; color: #475569;">
                    <span>{{ $ownerName }}</span>
                    <span>{{ $sessionDateLabel }}</span>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: center; min-width: 64px; text-align: center;">
                <x-application-logo width="64" height="64" style="display: block; flex: 0 0 auto; width: 64px; height: 64px;" />
            </div>
        </div>
    </div>

    <div style="padding: 24px {{ $outerPaddingX }}px 0; box-sizing: border-box;">
        <div style="border-radius: 8px; overflow: hidden;">
            <div style="display: grid; grid-template-columns: {{ $songColumnWidth }}px repeat({{ $slotNames->count() }}, {{ $slotColumnWidth }}px); background: #0f172a; color: #f8fafc; font-size: 11px; font-weight: 700; letter-spacing: 0.06em;">
                <div style="padding: 10px {{ $innerCellPaddingX }}px; box-sizing: border-box;">ARTIST / TITLE</div>
                @foreach ($slotNames as $slot)
                    <div style="padding: 10px {{ $innerCellPaddingX }}px; box-sizing: border-box; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ strtoupper($slot['label']) }}</div>
                @endforeach
            </div>

            <div style="display: grid; grid-auto-rows: 56px; background: #ffffff;">
                @foreach ($rows as $index => $song)
                    <div style="display: grid; grid-template-columns: {{ $songColumnWidth }}px repeat({{ $slotNames->count() }}, {{ $slotColumnWidth }}px); background: {{ $index % 2 === 0 ? '#ffffff' : '#f8fafc' }}; border-left: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                        <div style="min-width: 0; padding: {{ $innerCellPaddingY }}px {{ $innerCellPaddingX }}px; box-sizing: border-box; font-size: 13px; font-weight: 600; line-height: 1.35; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $song['artist'] }}{{ filled($song['title'] ?? '') ? ' - '.$song['title'] : '' }}
                        </div>

                        @foreach ($slotNames as $slot)
                            @php
                                $assignment = $song['slot_map'][$slot['name']] ?? ['state' => 'empty', 'display' => '-'];
                                $pillStyle = match ($assignment['state']) {
                                    'open' => 'background:#fffbeb;border:1px solid #fcd34d;color:#92400e;',
                                    'user' => ($assignment['is_manual'] ?? false)
                                        ? 'background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;'
                                        : 'background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;',
                                    default => 'color:#94a3b8;',
                                };
                                $pillLabel = $assignment['display'] ?? '-';
                            @endphp
                            <div style="min-width: 0; padding: 12px {{ $innerCellPaddingX }}px; box-sizing: border-box;">
                                <span style="display: inline-flex; align-items: center; max-width: 100%; min-height: 26px; padding: 0 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; line-height: 26px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; {{ $pillStyle }}">
                                    <span>{{ $pillLabel }}</span>
                                    {{-- 
                                    @if ($assignment['state'] === 'user' && ($assignment['is_manual'] ?? false))
                                        <span style="display: inline-flex; margin-left: 4px; align-items: center; flex: 0 0 auto; width: 14px; height: 14px;" aria-hidden="true">
                                            <x-heroicon-m-pencil-square width="14" height="14" style="display: block; flex: 0 0 auto; width: 14px; height: 14px;" />
                                        </span>
                                    @endif
                                    --}}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div style="padding: 0 32px 0; box-sizing: border-box;">
        <div style="padding: 14px 0 18px; font-size: 11px; color: #64748b; text-align: center;">
            {{ request()->getHost() }}
        </div>
    </div>
</div>