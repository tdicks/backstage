<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureTourStateController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'once_key' => ['required', 'string', 'max:120'],
            'action' => ['required', 'string', 'in:complete,dismiss_prompt,clear_prompt_dismissal,opt_out,clear_opt_out'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $state = is_array($user->feature_tour_state) ? $user->feature_tour_state : [];
        $completed = is_array($state['completed'] ?? null) ? $state['completed'] : [];
        $promptDismissed = is_array($state['prompt_dismissed'] ?? null) ? $state['prompt_dismissed'] : [];
        $optedOut = is_array($state['opted_out'] ?? null) ? $state['opted_out'] : [];
        $onceKey = trim($validated['once_key']);

        if ($validated['action'] === 'complete') {
            $completed[$onceKey] = true;
            unset($promptDismissed[$onceKey]);
            unset($optedOut[$onceKey]);
        }

        if ($validated['action'] === 'dismiss_prompt') {
            $promptDismissed[$onceKey] = true;
        }

        if ($validated['action'] === 'clear_prompt_dismissal') {
            unset($promptDismissed[$onceKey]);
        }

        if ($validated['action'] === 'opt_out') {
            $optedOut[$onceKey] = true;
            unset($promptDismissed[$onceKey]);
        }

        if ($validated['action'] === 'clear_opt_out') {
            unset($optedOut[$onceKey]);
        }

        $user->forceFill([
            'feature_tour_state' => [
                'completed' => $completed,
                'prompt_dismissed' => $promptDismissed,
                'opted_out' => $optedOut,
            ],
        ])->save();

        return response()->json([
            'state' => [
                'completed' => $completed,
                'prompt_dismissed' => $promptDismissed,
                'opted_out' => $optedOut,
            ],
        ]);
    }
}
