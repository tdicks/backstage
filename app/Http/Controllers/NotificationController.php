<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'after' => ['nullable', 'date'],
            'before' => ['nullable', 'date'],
            'known_ids' => ['nullable', 'array', 'max:50'],
            'known_ids.*' => ['string', 'max:255'],
        ]);

        return response()->json(
            $this->notificationService->feedForUser(
                $request->user(),
                limit: (int) ($validated['limit'] ?? 25),
                after: $request->date('after'),
                before: $request->date('before'),
                knownIds: $validated['known_ids'] ?? [],
            )
        );
    }

    public function markSeen(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->markAsSeen($request->user(), $notification);

        return response()->json(['status' => 'ok']);
    }

    public function dismiss(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->dismiss($request->user(), $notification);

        return response()->json(['status' => 'ok']);
    }
}
