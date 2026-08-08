@props([
    'tiles' => [],
    'songCount' => 0,
    'seed' => 0,
])

@php
    $totalSongs = max(0, (int) $songCount);
    $tileCount = max(1, min(4, $totalSongs > 0 ? $totalSongs : count($tiles)));

    $tilesCollection = collect($tiles)->take($tileCount)->values();

    while ($tilesCollection->count() < $tileCount) {
        $tilesCollection->push([
            'url' => null,
            'label' => 'No artwork',
        ]);
    }

    $gridClass = match ($tileCount) {
        1 => 'grid-cols-1 grid-rows-1',
        2 => 'grid-cols-2 grid-rows-1',
        default => 'grid-cols-2 grid-rows-2',
    };

    $threeTileLayout = ((int) $seed % 2 === 0) ? 'left-heavy' : 'top-heavy';

    $placementClassFor = static function (int $count, int $index, string $layout): string {
        if ($count !== 3) {
            return '';
        }

        if ($layout === 'left-heavy' && $index === 0) {
            return 'row-span-2';
        }

        if ($layout === 'top-heavy' && $index === 0) {
            return 'col-span-2';
        }

        return '';
    };
@endphp

<div class="absolute inset-0 grid {{ $gridClass }} gap-0.5 overflow-hidden">
    @foreach ($tilesCollection as $index => $tile)
        @php
            $tileClass = $placementClassFor($tileCount, $index, $threeTileLayout);
            $label = (string) ($tile['label'] ?? 'No artwork');
            $url = $tile['url'] ?? null;
        @endphp
        <div class="{{ $tileClass }} relative overflow-hidden bg-slate-200">
            @if (is_string($url) && $url !== '')
                <img src="{{ $url }}" alt="{{ $label }}" loading="lazy" class="h-full w-full object-cover" />
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-300 to-slate-100 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                    {{ str($label)->limit(22) }}
                </div>
            @endif
        </div>
    @endforeach
</div>

@if ($totalSongs > 4)
    <div class="absolute bottom-2 right-2 rounded-full bg-black/75 px-2 py-0.5 text-[10px] font-semibold text-white">
        +{{ $totalSongs - 4 }}
    </div>
@endif
