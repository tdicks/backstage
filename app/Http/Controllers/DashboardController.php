<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\User;
use App\Services\DashboardActionQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function actionQueues(Request $request, DashboardActionQueueService $queueService): JsonResponse
    {
        $queueData = $queueService->queuesForUser($request->user());
        $bandTemplates = BandTemplate::query()
            ->with('slots')
            ->orderBy('name')
            ->get();

        $html = view('dashboard.partials.action-queues', [
            'approvalsTotal' => $queueData['approvals_total'],
            'pendingForUser' => $queueData['pending_for_user'],
            'approvalSessions' => $queueData['approval_sessions'],
            'bandTemplates' => $bandTemplates,
            'slotOptions' => Slot::options(),
            'slotConflicts' => $this->slotConflicts(),
        ])->render();

        $widgetHtml = view('dashboard.widgets.action-inbox', [
            'approvalsTotal' => $queueData['approvals_total'],
            'pendingForUser' => $queueData['pending_for_user'],
            'approvalSessions' => $queueData['approval_sessions'],
            'bandTemplates' => $bandTemplates,
            'slotOptions' => Slot::options(),
            'slotConflicts' => $this->slotConflicts(),
        ])->render();

        return response()->json([
            'count' => $queueData['approvals_total'],
            'html' => $html,
            'widget_html' => $widgetHtml,
        ]);
    }

    public function gridstack(Request $request, DashboardActionQueueService $queueService): View
    {
        /** @var User $user */
        $user = $request->user();
        [$showGetStartedQuest, $getStartedItems, $allGetStartedItemsCompleted] = $this->getStartedQuestData($user);
        $queueData = $queueService->queuesForUser($user);
        $bandTemplates = BandTemplate::query()
            ->with('slots')
            ->orderBy('name')
            ->get();

        $nextNonLiveSession = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->where('is_closed', false)
            ->where('is_live', false)
            ->whereDate('date', '>=', today())
            ->whereDoesntHave('attendances', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->where('status', JamSessionAttendance::STATUS_NOT_GOING);
            })
            ->whereHas('sets', function ($query) use ($user): void {
                $query->where('performed', false)
                    ->whereHas('songs.slots', fn ($slotQuery) => $slotQuery->where('user_id', $user->id));
            })
            ->orderBy('date')
            ->first();

        $nextNonLiveSets = $nextNonLiveSession
            ? Set::query()
                ->where('jam_session_id', $nextNonLiveSession->id)
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

        return view('dashboard.gridstack', [
            'showGetStartedQuest' => $showGetStartedQuest,
            'getStartedItems' => $getStartedItems,
            'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
            'approvalsTotal' => $queueData['approvals_total'],
            'pendingForUser' => $queueData['pending_for_user'],
            'approvalSessions' => $queueData['approval_sessions'],
            'bandTemplates' => $bandTemplates,
            'slotOptions' => Slot::options(),
            'slotConflicts' => $this->slotConflicts(),
            'nextNonLiveSession' => $nextNonLiveSession,
            'nextNonLiveSets' => $nextNonLiveSets,
            'slotLabels' => Slot::options(),
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

    /**
     * @return array<string, list<string>>
     */
    private function slotConflicts(): array
    {
        return collect(SlotType::query()
            ->with('conflicts:key')
            ->where('active', true)
            ->get(['id', 'key'])
            ->reduce(function (array $conflicts, SlotType $slotType): array {
                $conflicts[$slotType->key] ??= [];

                foreach ($slotType->conflicts->pluck('key') as $conflictingKey) {
                    $conflicts[$slotType->key][] = $conflictingKey;
                    $conflicts[$conflictingKey][] = $slotType->key;
                }

                return $conflicts;
            }, []))
            ->map(fn (array $conflictingKeys) => collect($conflictingKeys)->unique()->values()->all())
            ->all();
    }

    /**
     * @return array{bool, list<array{label: string, href: string, completed: bool, description: string}>, bool}
     */
    private function getStartedQuestData(User $user): array
    {
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

        return [$showGetStartedQuest, $getStartedItems, $allGetStartedItemsCompleted];
    }
}
