<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
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
                ->with([
                    'songs' => fn ($query) => $query
                        ->whereHas('slots', fn ($slotQuery) => $slotQuery->where('user_id', $user->id))
                        ->with([
                            'slots' => fn ($slotQuery) => $slotQuery
                                ->where('user_id', $user->id)
                                ->orderBy('position'),
                        ])
                        ->orderBy('position'),
                ])
                ->orderBy('position')
                ->get()
            : collect();

        $showGetStartedQuest = ! $user->onboarding_dismissed_at;
        $getStartedItems = [];
        $allGetStartedItemsCompleted = false;

        if ($showGetStartedQuest) {
            $getStartedItems = [
                [
                    'label' => 'Add something to your bio',
                    'href' => route('profile.edit'),
                    'completed' => filled($user->bio),
                    'description' => 'Tell other people a bit about yourself and what you play.',
                ],
                [
                    'label' => 'Sign up for a song',
                    'href' => route('slot-finder.index'),
                    'completed' => $user->slots()->exists(),
                    'description' => 'Find a free slot to play on.',
                ],
                [
                    'label' => 'Create your own set',
                    'href' => route('sessions.index'),
                    'completed' => Set::query()->where('owner_id', $user->id)->exists(),
                    'description' => 'Start a new set ready for the next jam.',
                ],
            ];

            $allGetStartedItemsCompleted = collect($getStartedItems)->every(fn (array $item): bool => $item['completed']);
        }

        return view('dashboard', [
            'nextSession' => $nextSession,
            'nextSessionSets' => $nextSessionSets,
            'slotLabels' => Slot::options(),
            'getStartedItems' => $getStartedItems,
            'showGetStartedQuest' => $showGetStartedQuest,
            'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
        ]);
    }

    public function dismissGetStartedQuest(Request $request): RedirectResponse|Response
    {
        $request->user()->forceFill([
            'onboarding_dismissed_at' => now(),
        ])->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->noContent();
        }

        return Redirect::route('dashboard');
    }
}
