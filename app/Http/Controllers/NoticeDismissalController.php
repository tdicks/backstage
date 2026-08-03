<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeDismissal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeDismissalController extends Controller
{
    public function store(Request $request, Notice $notice): JsonResponse
    {
        abort_unless($request->user(), 403);

        if (! $notice->dismissable) {
            return response()->json([
                'message' => 'This notice cannot be dismissed.',
            ], 422);
        }

        NoticeDismissal::query()->updateOrCreate(
            [
                'notice_id' => $notice->id,
                'user_id' => $request->user()->id,
            ],
            [
                'dismissed_at' => now(),
            ]
        );

        return response()->json(['status' => 'ok']);
    }
}
