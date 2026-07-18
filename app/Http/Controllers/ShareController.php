<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Set;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function session(JamSession $jamSession): View
    {
        $jamSession->load(['sets' => function ($query): void {
            $query->where('feature_set', true)
                ->with('owner');
        }]);

        $featureSets = $jamSession->sets;
        $featureSetNames = $featureSets
            ->map(fn (Set $set): string => $set->owner->name.' - '.$set->name)
            ->join('; ');

        $description = $jamSession->date->format('l, F j, Y');

        if ($featureSetNames !== '') {
            $description .= ' featuring '.$featureSetNames;
        }

        return view('share.show', [
            'title' => $jamSession->name,
            'description' => $description,
            'url' => route('share.session', $jamSession),
            'heading' => $jamSession->name,
            'summary' => $description,
            'items' => $featureSets->map(fn (Set $set): string => $set->owner->name.'\'s set - '.$set->name),
        ]);
    }

    public function set(Set $set): View
    {
        $set->load(['owner', 'session', 'songs']);

        $songs = $set->songs
            ->map(fn ($song): string => $song->artist.' - '.$song->title)
            ->values();

        $description = $songs->isNotEmpty()
            ? $songs->join('; ')
            : 'A set at '.$set->session->name;

        return view('share.show', [
            'title' => $set->owner->name.'\'s set - '.$set->name,
            'description' => $description,
            'url' => route('share.set', $set),
            'heading' => $set->owner->name.'\'s set - '.$set->name,
            'summary' => $set->session->name.' - '.$set->session->date->format('l, F j, Y'),
            'items' => $songs,
        ]);
    }
}
