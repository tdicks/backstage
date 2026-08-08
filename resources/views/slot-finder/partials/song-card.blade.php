@php
    $song = $songGroup['song'];
    $songKey = 'backstage:u'.auth()->id().':slot-finder:song:'.$song->id;
    $slotCountLabel = $songGroup['slot_count'].' open '.str('slot')->plural($songGroup['slot_count']);
    $songCardConfig = [
        'songKey' => $songKey,
        'setKey' => $setKey,
    ];
    $songCardData = 'slotFinderSongCard('.json_encode($songCardConfig).')';
@endphp

<x-sets.presentational.song-card
    x-data='{{ $songCardData }}'
    x-show="!removed"
    x-bind:class="removing ? 'opacity-0 translate-y-2 scale-[0.98] pointer-events-none' : ''"
    x-transition.opacity.duration.200ms
    :title-text="$song->artist.' - '.$song->title"
    :notes-text="$song->notes"
    :meta-text="$slotCountLabel"
>

    <div class="mt-3 flex flex-wrap gap-2" data-tour="find-a-slot-slots">
        @foreach ($songGroup['slots'] as $slot)
            @include('slot-finder.partials.slot-pill', ['set' => $set, 'songKey' => $songKey, 'slot' => $slot, 'slotOptions' => $slotOptions])
        @endforeach
    </div>
</x-sets.presentational.song-card>