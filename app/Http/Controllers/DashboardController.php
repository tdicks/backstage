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
use App\Support\DashboardWidgetCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const DASHBOARD_LAYOUT_KEY = 'layout-preview';

    private const LEGACY_WIDGET_SIZE_MAP = [
        'third' => 1,
        'half' => 2,
        'full' => 3,
    ];

    private const LEGACY_WIDGET_HEIGHT_MAP = [
        'short' => 1,
        'medium' => 2,
        'tall' => 3,
    ];

    public function __invoke(Request $request, DashboardActionQueueService $queueService): View
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

        [$showGetStartedQuest, $getStartedItems, $allGetStartedItemsCompleted] = $this->getStartedQuestData($user);

        $queueData = $queueService->queuesForUser($user);
        $bandTemplates = BandTemplate::query()
            ->with('slots')
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'nextSession' => $nextSession,
            'nextSessionSets' => $nextSessionSets,
            'slotLabels' => Slot::options(),
            'approvalsTotal' => $queueData['approvals_total'],
            'pendingForUser' => $queueData['pending_for_user'],
            'approvalSessions' => $queueData['approval_sessions'],
            'bandTemplates' => $bandTemplates,
            'slotOptions' => Slot::options(),
            'slotConflicts' => $this->slotConflicts(),
            'getStartedItems' => $getStartedItems,
            'showGetStartedQuest' => $showGetStartedQuest,
            'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
        ]);
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

        $widgetHtml = view('dashboard.layout-preview.widgets.action-inbox', [
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

    public function freshGridstack(): View
    {
        return view('dashboard.fresh-gridstack');
    }

    public function layoutPreview(Request $request, DashboardActionQueueService $queueService): View
    {
        $user = $request->user();
        [$showGetStartedQuest, $getStartedItems, $allGetStartedItemsCompleted] = $this->getStartedQuestData($user);
        $queueData = $queueService->queuesForUser($user);
        $bandTemplates = BandTemplate::query()
            ->with('slots')
            ->orderBy('name')
            ->get();

        $liveSession = JamSession::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->where('is_closed', false)
            ->where('is_live', true)
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

        $widgetDefinitions = DashboardWidgetCatalog::previewDefinitions();
        $widgetDefinitionState = array_map(
            fn (array $widget): array => [
                'id' => $widget['id'],
                'defaults' => $widget['defaults'],
            ],
            $widgetDefinitions,
        );

        return view('dashboard.layout-preview', [
            'liveSession' => $liveSession,
            'nextNonLiveSession' => $nextNonLiveSession,
            'nextNonLiveSets' => $nextNonLiveSets,
            'slotLabels' => Slot::options(),
            'widgetDefinitions' => $widgetDefinitions,
            'widgetDefinitionState' => $widgetDefinitionState,
            'widgetOrderIds' => $this->resolveDashboardWidgetOrder($user),
            'widgetSizeMap' => $this->resolveDashboardWidgetSizes($user),
            'widgetHeightMap' => $this->resolveDashboardWidgetHeights($user),
            'widgetPositionMap' => $this->resolveDashboardWidgetPositions($user),
            'getStartedItems' => $getStartedItems,
            'showGetStartedQuest' => $showGetStartedQuest,
            'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
            'widgetContext' => [
                'liveSession' => $liveSession,
                'showGetStartedQuest' => $showGetStartedQuest,
                'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
                'getStartedItems' => $getStartedItems,
                'nextNonLiveSession' => $nextNonLiveSession,
                'nextNonLiveSets' => $nextNonLiveSets,
                'slotLabels' => Slot::options(),
                'approvalsTotal' => $queueData['approvals_total'],
                'pendingForUser' => $queueData['pending_for_user'],
                'approvalSessions' => $queueData['approval_sessions'],
                'bandTemplates' => $bandTemplates,
                'slotOptions' => Slot::options(),
                'slotConflicts' => $this->slotConflicts(),
            ],
        ]);
    }

    public function updateLayoutPreviewWidgetOrder(Request $request): JsonResponse
    {
        $widgetIds = DashboardWidgetCatalog::previewWidgetIds();
        $widgetSizeOptions = DashboardWidgetCatalog::previewWidgetSizeOptions();

        $validated = $request->validate([
            'widget_order' => ['required', 'array', 'size:'.count($widgetIds)],
            'widget_order.*' => ['string', 'distinct', Rule::in($widgetIds)],
            'widget_sizes' => ['required', 'array'],
            'widget_sizes.*' => ['integer', Rule::in($widgetSizeOptions)],
            'widget_heights' => ['required', 'array'],
            'widget_heights.*' => ['integer', 'min:1'],
            'widget_positions' => ['sometimes', 'array'],
            'widget_positions.*.column' => ['required_with:widget_positions', 'integer', 'min:1', 'max:3'],
            'widget_positions.*.row' => ['required_with:widget_positions', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $normalizedOrder = $this->normalizeDashboardWidgetOrder($validated['widget_order']);
        $normalizedSizes = $this->normalizeDashboardWidgetSizes($validated['widget_sizes']);
        $normalizedHeights = $this->normalizeDashboardWidgetHeights($validated['widget_heights']);
        $normalizedPositions = $this->normalizeDashboardWidgetPositions($validated['widget_positions'] ?? [], $normalizedOrder, $normalizedSizes, $normalizedHeights);
        $normalizedLayout = $this->buildDashboardWidgetLayout($normalizedOrder, $normalizedSizes, $normalizedHeights, $normalizedPositions);
        $layoutPreferences = is_array($user->dashboard_widget_layouts) ? $user->dashboard_widget_layouts : [];
        $layoutPreferences[self::DASHBOARD_LAYOUT_KEY] = $normalizedLayout;

        $user->forceFill([
            'dashboard_widget_order' => $normalizedOrder,
            'dashboard_widget_sizes' => $normalizedSizes,
            'dashboard_widget_layouts' => $layoutPreferences,
        ])->save();

        return response()->json([
            'widget_order' => $normalizedOrder,
            'widget_sizes' => $normalizedSizes,
            'widget_heights' => $normalizedHeights,
            'widget_positions' => $normalizedPositions,
            'widget_layout' => $normalizedLayout,
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
     * @return array<int, string>
     */
    private function resolveDashboardWidgetOrder(?User $user): array
    {
        $savedLayout = $this->resolveSavedDashboardWidgetLayout($user);

        if ($savedLayout !== null) {
            return array_keys($savedLayout);
        }

        $savedOrder = is_array($user?->dashboard_widget_order) ? $user->dashboard_widget_order : [];

        return $this->normalizeDashboardWidgetOrder($savedOrder);
    }

    /**
     * @return array<string, int>
     */
    private function resolveDashboardWidgetSizes(?User $user): array
    {
        $savedLayout = $this->resolveSavedDashboardWidgetLayout($user);

        if ($savedLayout !== null) {
            $sizes = [];

            foreach ($savedLayout as $widgetId => $layout) {
                $sizes[$widgetId] = $layout['size'];
            }

            return $sizes;
        }

        $savedSizes = is_array($user?->dashboard_widget_sizes) ? $user->dashboard_widget_sizes : [];

        return $this->normalizeDashboardWidgetSizes($savedSizes);
    }

    /**
     * @return array<string, int>
     */
    private function resolveDashboardWidgetHeights(?User $user): array
    {
        $savedLayout = $this->resolveSavedDashboardWidgetLayout($user);

        if ($savedLayout !== null) {
            $heights = [];

            foreach ($savedLayout as $widgetId => $layout) {
                $heights[$widgetId] = $layout['height'];
            }

            return $heights;
        }

        return $this->normalizeDashboardWidgetHeights([]);
    }

    /**
     * @return array<string, array{column: int, row: int}>
     */
    private function resolveDashboardWidgetPositions(?User $user): array
    {
        $savedLayout = $this->resolveSavedDashboardWidgetLayout($user);

        if ($savedLayout !== null) {
            $positions = [];

            foreach ($savedLayout as $widgetId => $layout) {
                $positions[$widgetId] = [
                    'column' => $layout['column'],
                    'row' => $layout['row'],
                ];
            }

            return $positions;
        }

        $normalizedOrder = $this->normalizeDashboardWidgetOrder(is_array($user?->dashboard_widget_order) ? $user->dashboard_widget_order : []);
        $normalizedSizes = $this->normalizeDashboardWidgetSizes(is_array($user?->dashboard_widget_sizes) ? $user->dashboard_widget_sizes : []);
        $normalizedHeights = $this->normalizeDashboardWidgetHeights([]);

        return $this->normalizeDashboardWidgetPositions([], $normalizedOrder, $normalizedSizes, $normalizedHeights);
    }

    /**
     * @return array<string, array{order: int, size: int, height: int, column: int, row: int}>|null
     */
    private function resolveSavedDashboardWidgetLayout(?User $user): ?array
    {
        $layoutPreferences = is_array($user?->dashboard_widget_layouts) ? $user->dashboard_widget_layouts : [];
        $savedLayout = $layoutPreferences[self::DASHBOARD_LAYOUT_KEY] ?? null;

        if (! is_array($savedLayout)) {
            return null;
        }

        $normalizedOrder = [];
        $normalizedSizes = [];
        $normalizedHeights = [];
        $normalizedPositions = [];

        foreach (DashboardWidgetCatalog::previewWidgetIds() as $index => $widgetId) {
            $widgetLayout = $savedLayout[$widgetId] ?? null;

            if (! is_array($widgetLayout)) {
                continue;
            }

            $requestedOrder = $widgetLayout['order'] ?? null;
            $requestedSize = $widgetLayout['size'] ?? null;
            $requestedHeight = $widgetLayout['height'] ?? null;
            $requestedColumn = $widgetLayout['column'] ?? null;
            $requestedRow = $widgetLayout['row'] ?? null;

            $normalizedOrder[$widgetId] = is_int($requestedOrder) && $requestedOrder >= 0 ? $requestedOrder : $index;
            $normalizedSizes[$widgetId] = $this->normalizeDashboardWidgetSpanValue($requestedSize, self::LEGACY_WIDGET_SIZE_MAP);
            $normalizedHeights[$widgetId] = $this->normalizeDashboardWidgetHeightValue($requestedHeight);
            $normalizedPositions[$widgetId] = [
                'column' => is_int($requestedColumn) ? $requestedColumn : (is_numeric($requestedColumn) ? (int) $requestedColumn : null),
                'row' => is_int($requestedRow) ? $requestedRow : (is_numeric($requestedRow) ? (int) $requestedRow : null),
            ];
        }

        if ($normalizedOrder === []) {
            return null;
        }

        asort($normalizedOrder, SORT_NUMERIC);

        $orderedWidgetIds = array_keys($normalizedOrder);
        $resolvedSizes = $this->normalizeDashboardWidgetSizes($normalizedSizes);
        $resolvedHeights = $this->normalizeDashboardWidgetHeights($normalizedHeights);
        $resolvedPositions = $this->normalizeDashboardWidgetPositions($normalizedPositions, $orderedWidgetIds, $resolvedSizes, $resolvedHeights);

        return $this->buildDashboardWidgetLayout($orderedWidgetIds, $resolvedSizes, $resolvedHeights, $resolvedPositions);
    }

    /**
     * @param  array<int, string>  $widgetOrder
     * @return array<int, string>
     */
    private function normalizeDashboardWidgetOrder(array $widgetOrder): array
    {
        $allowedWidgetIds = DashboardWidgetCatalog::previewWidgetIds();
        $normalizedOrder = [];

        foreach ($widgetOrder as $widgetId) {
            if (in_array($widgetId, $allowedWidgetIds, true) && ! in_array($widgetId, $normalizedOrder, true)) {
                $normalizedOrder[] = $widgetId;
            }
        }

        foreach ($allowedWidgetIds as $widgetId) {
            if (! in_array($widgetId, $normalizedOrder, true)) {
                $normalizedOrder[] = $widgetId;
            }
        }

        return $normalizedOrder;
    }

    /**
     * @param  array<string, mixed>  $widgetSizes
     * @return array<string, int>
     */
    private function normalizeDashboardWidgetSizes(array $widgetSizes): array
    {
        $allowedSizes = DashboardWidgetCatalog::previewWidgetSizeOptions();
        $defaultSizes = DashboardWidgetCatalog::previewDefaultSizes();
        $normalizedSizes = [];

        foreach (DashboardWidgetCatalog::previewWidgetIds() as $widgetId) {
            $requestedSize = $this->normalizeDashboardWidgetSpanValue($widgetSizes[$widgetId] ?? null, self::LEGACY_WIDGET_SIZE_MAP);

            if ($requestedSize !== null && in_array($requestedSize, $allowedSizes, true)) {
                $normalizedSizes[$widgetId] = $requestedSize;

                continue;
            }

            $normalizedSizes[$widgetId] = $defaultSizes[$widgetId] ?? 3;
        }

        return $normalizedSizes;
    }

    /**
     * @param  array<string, mixed>  $widgetHeights
     * @return array<string, int>
     */
    private function normalizeDashboardWidgetHeights(array $widgetHeights): array
    {
        $defaultHeights = DashboardWidgetCatalog::previewDefaultHeights();
        $normalizedHeights = [];

        foreach (DashboardWidgetCatalog::previewWidgetIds() as $widgetId) {
            $requestedHeight = $this->normalizeDashboardWidgetHeightValue($widgetHeights[$widgetId] ?? null);

            if ($requestedHeight !== null) {
                $normalizedHeights[$widgetId] = $requestedHeight;

                continue;
            }

            $normalizedHeights[$widgetId] = $defaultHeights[$widgetId] ?? 2;
        }

        return $normalizedHeights;
    }

    /**
     * @param  array<string, mixed>  $widgetPositions
     * @param  array<int, string>  $widgetOrder
     * @param  array<string, int>  $widgetSizes
     * @param  array<string, int>  $widgetHeights
     * @return array<string, array{column: int, row: int}>
     */
    private function normalizeDashboardWidgetPositions(array $widgetPositions, array $widgetOrder, array $widgetSizes, array $widgetHeights): array
    {
        $defaultSizes = DashboardWidgetCatalog::previewDefaultSizes();
        $defaultHeights = DashboardWidgetCatalog::previewDefaultHeights();
        $occupiedCells = [];
        $normalizedPositions = [];

        foreach ($widgetOrder as $widgetId) {
            $requestedPosition = $widgetPositions[$widgetId] ?? null;
            $requestedColumn = is_array($requestedPosition) ? ($requestedPosition['column'] ?? null) : null;
            $requestedRow = is_array($requestedPosition) ? ($requestedPosition['row'] ?? null) : null;
            $columnSpan = $widgetSizes[$widgetId] ?? $defaultSizes[$widgetId] ?? 1;
            $rowSpan = $widgetHeights[$widgetId] ?? $defaultHeights[$widgetId] ?? 1;

            $normalizedPositions[$widgetId] = $this->findDashboardWidgetPlacement(
                $occupiedCells,
                $columnSpan,
                $rowSpan,
                is_int($requestedColumn) ? $requestedColumn : (is_numeric($requestedColumn) ? (int) $requestedColumn : null),
                is_int($requestedRow) ? $requestedRow : (is_numeric($requestedRow) ? (int) $requestedRow : null),
            );

            $this->fillDashboardWidgetPlacement(
                $occupiedCells,
                $normalizedPositions[$widgetId]['column'],
                $normalizedPositions[$widgetId]['row'],
                $columnSpan,
                $rowSpan,
            );
        }

        return $normalizedPositions;
    }

    /**
     * @param  array<int, string>  $widgetOrder
     * @param  array<string, int>  $widgetSizes
     * @param  array<string, int>  $widgetHeights
     * @param  array<string, array{column: int, row: int}>  $widgetPositions
     * @return array<string, array{order: int, size: int, height: int, column: int, row: int}>
     */
    private function buildDashboardWidgetLayout(array $widgetOrder, array $widgetSizes, array $widgetHeights, array $widgetPositions): array
    {
        $defaultSizes = DashboardWidgetCatalog::previewDefaultSizes();
        $defaultHeights = DashboardWidgetCatalog::previewDefaultHeights();
        $defaultColumns = DashboardWidgetCatalog::previewDefaultColumns();
        $defaultRows = DashboardWidgetCatalog::previewDefaultRows();
        $layout = [];

        foreach ($widgetOrder as $index => $widgetId) {
            $layout[$widgetId] = [
                'order' => $index,
                'size' => $widgetSizes[$widgetId] ?? $defaultSizes[$widgetId] ?? 3,
                'height' => $widgetHeights[$widgetId] ?? $defaultHeights[$widgetId] ?? 2,
                'column' => $widgetPositions[$widgetId]['column'] ?? $defaultColumns[$widgetId] ?? 1,
                'row' => $widgetPositions[$widgetId]['row'] ?? $defaultRows[$widgetId] ?? 1,
            ];
        }

        return $layout;
    }

    /**
     * @param  array<string, true>  $occupiedCells
     * @return array{column: int, row: int}
     */
    private function findDashboardWidgetPlacement(array $occupiedCells, int $columnSpan, int $rowSpan, ?int $preferredColumn = null, ?int $preferredRow = null): array
    {
        if ($preferredColumn !== null && $preferredRow !== null && $this->canPlaceDashboardWidgetAt($occupiedCells, $preferredColumn, $preferredRow, $columnSpan, $rowSpan)) {
            return [
                'column' => $preferredColumn,
                'row' => $preferredRow,
            ];
        }

        $startRow = max(1, $preferredRow ?? 1);

        for ($row = $startRow; $row < $startRow + 500; $row++) {
            for ($column = 1; $column <= 4 - $columnSpan; $column++) {
                if ($this->canPlaceDashboardWidgetAt($occupiedCells, $column, $row, $columnSpan, $rowSpan)) {
                    return [
                        'column' => $column,
                        'row' => $row,
                    ];
                }
            }
        }

        return [
            'column' => 1,
            'row' => $startRow,
        ];
    }

    /**
     * @param  array<string, true>  $occupiedCells
     */
    private function canPlaceDashboardWidgetAt(array $occupiedCells, int $column, int $row, int $columnSpan, int $rowSpan): bool
    {
        if ($column < 1 || $column > 3 || $column + $columnSpan - 1 > 3 || $row < 1 || $rowSpan < 1) {
            return false;
        }

        for ($rowOffset = 0; $rowOffset < $rowSpan; $rowOffset++) {
            for ($columnOffset = 0; $columnOffset < $columnSpan; $columnOffset++) {
                if (isset($occupiedCells[($column + $columnOffset).':'.($row + $rowOffset)])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, true>  $occupiedCells
     */
    private function fillDashboardWidgetPlacement(array &$occupiedCells, int $column, int $row, int $columnSpan, int $rowSpan): void
    {
        for ($rowOffset = 0; $rowOffset < $rowSpan; $rowOffset++) {
            for ($columnOffset = 0; $columnOffset < $columnSpan; $columnOffset++) {
                $occupiedCells[($column + $columnOffset).':'.($row + $rowOffset)] = true;
            }
        }
    }

    /**
     * @param  array<string, int>  $legacyMap
     */
    private function normalizeDashboardWidgetSpanValue(mixed $requestedValue, array $legacyMap): ?int
    {
        if (is_int($requestedValue) && in_array($requestedValue, [1, 2, 3], true)) {
            return $requestedValue;
        }

        if (is_numeric($requestedValue)) {
            $normalizedValue = (int) $requestedValue;

            if (in_array($normalizedValue, [1, 2, 3], true)) {
                return $normalizedValue;
            }
        }

        if (is_string($requestedValue)) {
            return $legacyMap[$requestedValue] ?? null;
        }

        return null;
    }

    private function normalizeDashboardWidgetHeightValue(mixed $requestedValue): ?int
    {
        if (is_int($requestedValue) && $requestedValue >= 1) {
            return $requestedValue;
        }

        if (is_numeric($requestedValue)) {
            $normalizedValue = (int) $requestedValue;

            if ($normalizedValue >= 1) {
                return $normalizedValue;
            }
        }

        if (is_string($requestedValue)) {
            return self::LEGACY_WIDGET_HEIGHT_MAP[$requestedValue] ?? null;
        }

        return null;
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
