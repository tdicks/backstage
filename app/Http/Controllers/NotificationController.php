<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->notificationService->feedForUser($request->user())
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
