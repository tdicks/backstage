<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\User;
use App\Services\JamSessionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JamSessionAttendanceController extends Controller
{
    public function __construct(private readonly JamSessionAttendanceService $attendanceService) {}

    public function index(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('view', $jamSession);

        return response()->json([
            'users' => $this->attendanceService->usersForAttendanceModal($jamSession),
            'session_closed' => (bool) $jamSession->is_closed,
            'can_admin_override' => (bool) $request->user()?->is_admin,
        ]);
    }

    public function update(Request $request, JamSession $jamSession): JsonResponse
    {
        $this->authorize('view', $jamSession);

        if ($jamSession->is_closed) {
            return response()->json([
                'message' => 'Attendance cannot be changed on a closed session.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', JamSessionAttendance::statuses())],
            'dropout_action' => ['nullable', 'string', 'in:'.implode(',', JamSessionAttendanceService::dropoutActions())],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $actor = $request->user();
        $targetUser = $actor;

        if (! empty($validated['user_id']) && (int) $validated['user_id'] !== (int) $actor->id) {
            abort_unless($actor->is_admin, 403);

            $targetUser = User::query()
                ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE)
                ->findOrFail((int) $validated['user_id']);
        }

        $status = (string) $validated['status'];
        $dropoutAction = $validated['dropout_action'] ?? null;
        $isAdminOverride = (int) $targetUser->id !== (int) $actor->id;
        $currentStatus = $this->attendanceService->statusForUser($jamSession, $targetUser);

        $requiresDropoutAction = $status === JamSessionAttendance::STATUS_NOT_GOING
            && $currentStatus !== JamSessionAttendance::STATUS_NOT_GOING
            && $this->attendanceService->userRequiresDropoutActionPrompt($jamSession, $targetUser)
            && ! $isAdminOverride;

        if ($isAdminOverride && $status === JamSessionAttendance::STATUS_NOT_GOING && $dropoutAction === null) {
            $dropoutAction = JamSessionAttendanceService::DROPOUT_KEEP_CLAIMABLE;
        }

        if ($requiresDropoutAction && $dropoutAction === null) {
            return response()->json([
                'message' => 'Choose how to handle your current slots before marking not going.',
                'errors' => [
                    'dropout_action' => ['Choose how to handle your current slots before marking not going.'],
                ],
            ], 422);
        }

        $this->attendanceService->setStatus(
            $jamSession,
            $targetUser,
            $status,
            $isAdminOverride ? JamSessionAttendance::SOURCE_ADMIN_OVERRIDE : JamSessionAttendance::SOURCE_SELF,
            $dropoutAction
        );

        return response()->json([
            'message' => match ($status) {
                JamSessionAttendance::STATUS_GOING => 'Attendance set to going.',
                JamSessionAttendance::STATUS_NOT_GOING => 'Attendance set to not going.',
                default => 'Attendance set to maybe.',
            },
            'status' => $status,
            'requires_dropout_action' => $requiresDropoutAction,
            'target_user_id' => (string) $targetUser->id,
            'users' => $this->attendanceService->usersForAttendanceModal($jamSession),
        ]);
    }
}
