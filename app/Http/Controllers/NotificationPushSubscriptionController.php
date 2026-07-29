<?php

namespace App\Http\Controllers;

use App\Models\NotificationPushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPushSubscriptionController extends Controller
{
    public function __construct(private readonly WebPushService $webPushService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:4096'],
            'keys.auth' => ['required', 'string', 'max:4096'],
            'content_encoding' => ['nullable', 'string', 'max:32'],
        ]);

        if (! $this->webPushService->validateSubscriptionPayload([
            'endpoint' => $validated['endpoint'],
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['content_encoding'] ?? null,
        ])) {
            return response()->json([
                'message' => 'Invalid push subscription payload.',
            ], 422);
        }

        $endpointHash = hash('sha256', $validated['endpoint']);

        $subscription = NotificationPushSubscription::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? null,
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'ok',
            'subscription_id' => $subscription->id,
        ], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096'],
        ]);

        NotificationPushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
