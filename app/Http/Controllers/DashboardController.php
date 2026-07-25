<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Set;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $nextSession = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->where('is_closed', false)
            ->whereDate('date', '>=', today())
            ->whereHas('sets', function ($query) use ($user): void {
                $query->where('performed', false)
                    ->whereHas('songs.slots', fn ($slotQuery) => $slotQuery->where('user_id', $user->id));
            })
            ->orderBy('date')
            ->first();

        $nextSessionSets = $nextSession
            ? Set::query()
                ->where('jam_session_id', $nextSession->id)
                ->where('performed', false)
                ->whereHas('songs.slots', fn ($slotQuery) => $slotQuery->where('user_id', $user->id))
                ->with(['songs' => fn ($query) => $query->whereHas('slots', fn ($slotQuery) => $slotQuery->where('user_id', $user->id))])
                ->orderBy('position')
                ->get()
            : collect();

        return view('dashboard', [
            'nextSession' => $nextSession,
            'nextSessionSets' => $nextSessionSets,
        ]);
    }
}
