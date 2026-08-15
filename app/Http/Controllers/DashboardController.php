<?php

namespace App\Http\Controllers;

use App\Models\BandTemplate;
use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\Set;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\User;
use App\Services\DashboardActionQueueService;
use App\Support\DashboardWidgetCatalog;
use App\Support\DashboardWidgets\DashboardWidgetContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function saveLayout(Request $request, DashboardWidgetCatalog $widgetCatalog): Response
    {
        /** @var User $user */
        $user = $request->user();
        $widgets = $widgetCatalog->forContext(new DashboardWidgetContext($user, [
            'live_session' => $this->liveSessionFor($user),
        ]));
        /** @var list<string> $enabledWidgetIds */
        $enabledWidgetIds = array_column($widgets, 'id');

        $validated = $request->validate([
            'layout' => ['required', 'array'],
            'layout.*.id' => ['required', 'string', 'max:100'],
            'layout.*.x' => ['required', 'integer', 'min:0'],
            'layout.*.y' => ['required', 'integer', 'min:0'],
            'layout.*.w' => ['required', 'integer', 'min:1'],
            'layout.*.h' => ['required', 'integer', 'min:1'],
        ]);

        $layout = $this->normalizeDashboardWidgetLayout($validated['layout'], $enabledWidgetIds);

        $user->forceFill([
            'dashboard_widget_layouts' => $layout,
        ])->save();

        return response()->noContent();
    }

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

    public function gridstack(Request $request, DashboardActionQueueService $queueService, DashboardWidgetCatalog $widgetCatalog): View
    {
        /** @var User $user */
        $user = $request->user();
        $liveSession = $this->liveSessionFor($user);
        $widgets = $widgetCatalog->forContext(new DashboardWidgetContext($user, [
            'live_session' => $liveSession,
        ]));
        /** @var list<string> $enabledWidgetIds */
        $enabledWidgetIds = array_column($widgets, 'id');
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
            'widgetCatalog' => $widgets,
            'enabledWidgetIds' => $enabledWidgetIds,
            'initialWidgetLayout' => $this->normalizeDashboardWidgetLayout($this->dashboardWidgetLayoutFor($user), $enabledWidgetIds),
            'liveSession' => $liveSession,
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

    /**
     * @param  list<string>  $enabledWidgetIds
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     */
    private function normalizeDashboardWidgetLayout(mixed $layout, array $enabledWidgetIds): array
    {
        if (! is_array($layout)) {
            return [];
        }

        $allowedIds = array_fill_keys($enabledWidgetIds, true);
        $normalized = [];

        foreach ($layout as $node) {
            $id = is_array($node) && is_string($node['id'] ?? null) ? $node['id'] : null;
            if (! $id || ! isset($allowedIds[$id]) || isset($normalized[$id])) {
                continue;
            }

            $x = max(0, (int) ($node['x'] ?? 0));
            $y = max(0, (int) ($node['y'] ?? 0));
            $w = max(1, (int) ($node['w'] ?? 1));
            $h = max(1, (int) ($node['h'] ?? 1));

            $normalized[$id] = [
                'id' => $id,
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     */
    private function dashboardWidgetLayoutFor(User $user): array
    {
        if (is_array($user->dashboard_widget_layouts) && $user->dashboard_widget_layouts !== []) {
            return $user->dashboard_widget_layouts;
        }

        $defaultLayout = Setting::query()
            ->where('key', Setting::DASHBOARD_DEFAULT_WIDGET_LAYOUT_KEY)
            ->value('value');

        if (! is_string($defaultLayout)) {
            return [];
        }

        $decodedLayout = json_decode($defaultLayout, true);

        return is_array($decodedLayout) ? $decodedLayout : [];
    }

    private function liveSessionFor(User $user): ?JamSession
    {
        return JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->where('is_live', true)
            ->orderByDesc('date')
            ->first();
    }
}
