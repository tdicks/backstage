<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\FullPageRouteCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NoticeAdministrationController extends Controller
{
    public function __construct(private readonly FullPageRouteCatalog $routeCatalog) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.notices.index', [
            'listUrl' => route('admin.notices.items'),
            'storeUrl' => route('admin.notices.store'),
            'reorderUrl' => route('admin.notices.reorder'),
            'previewUrl' => route('admin.notices.preview'),
            'clearDismissalsUrlTemplate' => route('admin.notices.dismissals.clear', '__NOTICE_ID__'),
            'updateUrlTemplate' => route('admin.notices.update', '__NOTICE_ID__'),
            'deleteUrlTemplate' => route('admin.notices.destroy', '__NOTICE_ID__'),
            'csrfToken' => csrf_token(),
            'routeOptions' => $this->routeCatalog->options(),
            'locationOptions' => [
                ['value' => Notice::LOCATION_ABOVE_NAV, 'label' => 'Above nav'],
                ['value' => Notice::LOCATION_BELOW_NAV, 'label' => 'Below nav'],
                ['value' => Notice::LOCATION_BELOW_HEADER, 'label' => 'Below header'],
            ],
            'audienceScopeOptions' => [
                ['value' => Notice::AUDIENCE_ALL_USERS, 'label' => 'All users'],
                ['value' => Notice::AUDIENCE_ADMINS_ONLY, 'label' => 'Admins only'],
            ],
            'levelOptions' => [
                ['value' => Notice::LEVEL_INFO, 'label' => 'Info'],
                ['value' => Notice::LEVEL_WARNING, 'label' => 'Warning'],
                ['value' => Notice::LEVEL_CRITICAL, 'label' => 'Critical'],
            ],
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $locationOrder = Notice::locations();

        $notices = Notice::query()
            ->orderByRaw(
                'case location '.collect($locationOrder)
                    ->map(fn (string $location, int $index) => 'when ? then '.$index)
                    ->implode(' ').' else '.count($locationOrder).' end',
                $locationOrder,
            )
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Notice $notice) => $this->payload($notice))
            ->values();

        return response()->json([
            'notices' => $notices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatePayload($request);

        $notice = Notice::query()->create([
            ...$validated,
            'position' => $this->nextPositionForLocation((string) $validated['location']),
        ]);

        return response()->json([
            'message' => 'Notice created.',
            'notice' => $this->payload($notice),
        ], 201);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:10000'],
        ]);

        return response()->json([
            'content_html' => Str::markdown((string) ($validated['content'] ?? ''), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validatePayload($request);
        $originalLocation = (string) $notice->location;
        $newLocation = (string) $validated['location'];
        $locationChanged = $originalLocation !== $newLocation;

        $notice->fill($validated);

        if ($locationChanged) {
            $notice->position = $this->nextPositionForLocation($newLocation);
        }

        $notice->save();

        if ($locationChanged) {
            $this->normalizeLocationPositions($originalLocation);
        }

        return response()->json([
            'message' => 'Notice updated.',
            'notice' => $this->payload($notice->fresh()),
        ]);
    }

    public function destroy(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeAdmin($request);

        $location = (string) $notice->location;
        $notice->delete();
        $this->normalizeLocationPositions($location);

        return response()->json([
            'message' => 'Notice deleted.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'location' => ['required', 'string', Rule::in(Notice::locations())],
            'notice_ids' => ['required', 'array', 'min:1'],
            'notice_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $location = (string) $validated['location'];
        $orderedNoticeIds = array_values(array_map('intval', $validated['notice_ids']));
        $locationNoticeIds = Notice::query()
            ->where('location', $location)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sortedOrderedNoticeIds = $orderedNoticeIds;
        $sortedLocationNoticeIds = $locationNoticeIds;
        sort($sortedOrderedNoticeIds);
        sort($sortedLocationNoticeIds);

        if ($sortedOrderedNoticeIds !== $sortedLocationNoticeIds) {
            abort(422, 'Invalid notice list for this location.');
        }

        DB::transaction(function () use ($orderedNoticeIds, $location): void {
            foreach ($orderedNoticeIds as $index => $noticeId) {
                Notice::query()
                    ->where('location', $location)
                    ->where('id', $noticeId)
                    ->update(['position' => $index + 1]);
            }
        });

        return response()->json([
            'message' => 'Notice order updated.',
        ]);
    }

    public function clearDismissals(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeAdmin($request);

        $clearedCount = $notice->dismissals()->count();
        $notice->dismissals()->delete();

        return response()->json([
            'message' => $clearedCount > 0
                ? 'Dismissals cleared. This notice can show again.'
                : 'No dismissals to clear.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'content' => $notice->content,
            'content_html' => Str::markdown((string) ($notice->content ?? ''), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'level' => $notice->level,
            'location' => $notice->location,
            'position' => (int) $notice->position,
            'audience_scope' => $notice->audience_scope,
            'show_on_all_pages' => (bool) $notice->show_on_all_pages,
            'show_on_routes' => array_values($notice->show_on_routes ?? []),
            'dismissable' => (bool) $notice->dismissable,
            'enabled' => (bool) $notice->enabled,
            'updated_at' => $notice->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $routeNames = $this->routeCatalog->routeNames();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'level' => ['required', 'string', Rule::in(Notice::levels())],
            'location' => ['required', 'string', Rule::in(Notice::locations())],
            'audience_scope' => ['required', 'string', Rule::in(Notice::audienceScopes())],
            'show_on_all_pages' => ['required', 'boolean'],
            'show_on_routes' => [
                Rule::when(
                    fn () => ! $request->boolean('show_on_all_pages'),
                    ['required', 'array', 'min:1'],
                    ['nullable', 'array'],
                ),
            ],
            'show_on_routes.*' => ['string', Rule::in($routeNames)],
            'dismissable' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ]);

        $showOnAllPages = (bool) $validated['show_on_all_pages'];
        $showOnRoutes = collect($validated['show_on_routes'] ?? [])->map(fn ($name) => (string) $name)->unique()->values()->all();

        return [
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'level' => $validated['level'],
            'location' => $validated['location'],
            'audience_scope' => $validated['audience_scope'],
            'show_on_all_pages' => $showOnAllPages,
            'show_on_routes' => $showOnAllPages ? [] : $showOnRoutes,
            'dismissable' => (bool) $validated['dismissable'],
            'enabled' => (bool) $validated['enabled'],
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    private function nextPositionForLocation(string $location): int
    {
        return (int) Notice::query()
            ->where('location', $location)
            ->max('position') + 1;
    }

    private function normalizeLocationPositions(string $location): void
    {
        $noticeIds = Notice::query()
            ->where('location', $location)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($noticeIds as $index => $noticeId) {
            Notice::query()
                ->where('id', $noticeId)
                ->update(['position' => $index + 1]);
        }
    }
}
