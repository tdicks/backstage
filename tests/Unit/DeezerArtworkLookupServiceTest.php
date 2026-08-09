<?php

use App\Services\DeezerArtworkLookupService;

it('deduplicates artwork tiles that point to the same cover url', function () {
    $service = new DeezerArtworkLookupService;

    $tiles = [
        ['url' => 'https://cdn.example.com/album-a.jpg', 'label' => 'Artist - Song A'],
        ['url' => 'https://cdn.example.com/album-a.jpg', 'label' => 'Artist - Song B'],
        ['url' => 'https://cdn.example.com/album-b.jpg', 'label' => 'Artist - Song C'],
    ];

    expect($service->uniqueArtworkTiles($tiles))->toEqual([
        ['url' => 'https://cdn.example.com/album-a.jpg', 'label' => 'Artist - Song A'],
        ['url' => 'https://cdn.example.com/album-b.jpg', 'label' => 'Artist - Song C'],
    ]);
});
