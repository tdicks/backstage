<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\JamSessionAttendance;
use App\Models\JamSessionSignIn;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SongRequest;
use App\Services\JamSessionAttendanceService;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SetController extends Controller
{
    public function summary(Set $set): JsonResponse
    {
        $this->authorize('view', $set);
        $this->authorize('view', $set->session);

        $viewerId = request()->user()?->id;

        $set->load([
            'songs.slots.user:id,name',
        ]);

        $checkedInUserIds = JamSessionSignIn::query()
            ->where('jam_session_id', $set->jam_session_id)
            ->pluck('user_id')
            ->all();

        $slotOptions = Slot::options();
        $slotNames = collect(array_keys($slotOptions))
            ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
            ->values();

        $songs = $set->songs->map(function ($song) use ($slotNames, $checkedInUserIds, $viewerId) {
            $slotMap = [];

            foreach ($slotNames as $slotName) {
                $slot = $song->slots->firstWhere('name', $slotName);

                if (! $slot) {
                    $slotMap[$slotName] = [
                        'state' => 'empty',
                        'display' => '-',
                        'checked_in' => false,
                        'is_manual' => false,
                        'is_current_user' => false,
                    ];

                    continue;
                }

                if ($slot->user) {
                    $isCurrentUser = $slot->user->id === $viewerId;

                    $slotMap[$slotName] = [
                        'state' => 'user',
                        'display' => $slot->user->name,
                        'checked_in' => in_array($slot->user->id, $checkedInUserIds, true),
                        'is_manual' => false,
                        'is_current_user' => $isCurrentUser,
                    ];

                    continue;
                }

                if (! blank($slot->manual_performer_name)) {
                    $slotMap[$slotName] = [
                        'state' => 'user',
                        'display' => $slot->manual_performer_name,
                        'checked_in' => false,
                        'is_manual' => true,
                        'is_current_user' => false,
                    ];

                    continue;
                }

                $slotMap[$slotName] = [
                    'state' => 'open',
                    'display' => 'Open',
                    'checked_in' => false,
                    'is_manual' => false,
                    'is_current_user' => false,
                ];
            }

            return [
                'id' => $song->id,
                'artist' => $song->artist,
                'title' => $song->title,
                'slot_map' => $slotMap,
            ];
        })->values();

        return response()->json([
            'slot_names' => $slotNames->map(fn (string $name) => [
                'name' => $name,
                'label' => $slotOptions[$name] ?? ucfirst(str_replace('_', ' ', $name)),
            ])->values(),
            'songs' => $songs,
        ]);
    }

    public function store(Request $request, JamSession $jamSession): RedirectResponse
    {
        $this->authorize('create', Set::class);

        $attendanceService = app(JamSessionAttendanceService::class);

        if (! $request->user()->is_admin && $attendanceService->isNotGoing($jamSession, $request->user())) {
            return back()->with('status', 'You marked yourself as not attending this session. Set your attendance to Maybe or Going to create a set.');
        }

        if ($jamSession->is_closed && ! $request->user()->is_admin) {
            return back()->with('status', 'This jam session is closed. No new sets can be created.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_hidden' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
        ]);

        $nextPosition = ((int) $jamSession->sets()->max('position')) + 1;

        $set = $jamSession->sets()->create([
            ...$validated,
            'owner_id' => $request->user()->id,
            'position' => $nextPosition,
            'performed' => false,
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            'song_requests' => true,
        ]);

        $attendanceService->markGoingIfAllowed($jamSession, $request->user(), JamSessionAttendance::SOURCE_AUTO_SET);

        if ($set->is_hidden) {
            Cache::forever(self::newSetDeferredNotificationCacheKey($set), true);
        } else {
            $this->notifySetCreated($set, $request);
        }

        return back()->with('status', 'Set created.');
    }

    public function update(Request $request, Set $set): RedirectResponse
    {
        $this->authorize('update', $set);

        $isAdmin = $request->user()->is_admin;
        $previousSessionId = $set->jam_session_id;
        $previousSession = $set->session()->first();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'performed' => ['nullable', 'boolean'],
            'signups_open' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'song_requests' => ['nullable', 'boolean'],
            'free_for_all' => ['nullable', 'boolean'],
            'jam_session_id' => ['nullable', 'integer', 'exists:jam_sessions,id'],
        ];

        if ($isAdmin) {
            $rules['owner_id'] = ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_deleted_account', false))];
            $rules['feature_set'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        $targetSessionId = (int) ($validated['jam_session_id'] ?? $set->jam_session_id);

        if ($targetSessionId !== (int) $set->jam_session_id) {
            $targetSession = JamSession::query()
                ->visibleTo($request->user())
                ->where('is_archived', false)
                ->select(['id', 'date', 'is_closed', 'is_archived'])
                ->find($targetSessionId);

            if (! $targetSession) {
                return back()
                    ->withErrors(['jam_session_id' => 'Choose a valid jam session to move this set.'])
                    ->withInput();
            }

            if (! $isAdmin && $targetSession->is_closed) {
                return back()
                    ->withErrors(['jam_session_id' => 'Sets can only be moved to open jam sessions.'])
                    ->withInput();
            }

            if (! $isAdmin && $targetSession->date->isBefore(today())) {
                return back()
                    ->withErrors(['jam_session_id' => 'Sets can only be moved to today or future jam sessions.'])
                    ->withInput();
            }
        }

        $wasAcceptingSongRequests = (bool) $set->song_requests;
        $wasHidden = (bool) $set->is_hidden;

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? $set->position,
            'performed' => (bool) ($validated['performed'] ?? false),
            'signups_open' => (bool) ($validated['signups_open'] ?? false),
            'is_hidden' => (bool) ($validated['is_hidden'] ?? false),
            'song_requests' => (bool) ($validated['song_requests'] ?? false),
            'free_for_all' => (bool) ($validated['free_for_all'] ?? false),
            'jam_session_id' => $validated['jam_session_id'] ?? $set->jam_session_id,
        ];

        if ($isAdmin) {
            $updateData['owner_id'] = $validated['owner_id'];
            $updateData['feature_set'] = (bool) ($validated['feature_set'] ?? false);
        }

        $set->update($updateData);

        if ($wasAcceptingSongRequests && ! $updateData['song_requests']) {
            $set->songRequests()
                ->where('status', SongRequest::STATUS_PENDING)
                ->update([
                    'status' => SongRequest::STATUS_REJECTED,
                    'responded_by_user_id' => $request->user()->id,
                    'responded_at' => now(),
                ]);
        }

        if ($previousSessionId !== $set->jam_session_id) {
            $set->loadMissing('session', 'songs.slots');

            app(NotificationService::class)->notifyUsers(
                NotificationTypeCatalog::SET_UPDATED,
                app(NotificationService::class)->involvedUsersForSet($set),
                $request->user(),
                [
                    'title' => 'Set moved to a different session',
                    'body' => $request->user()->name.' moved '.$set->name.' from '.($previousSession?->name ?? 'another session').' to '.$set->session->name.'.',
                    'action_url' => route('sessions.show', $set->session).'#set-'.$set->id,
                    'action_label' => 'Open set',
                ]
            );
        }

        $isNowVisible = ! $set->is_hidden;
        $canFireDeferredCreatedNotification = $wasHidden
            && $isNowVisible
            && Cache::pull(self::newSetDeferredNotificationCacheKey($set), false);

        if ($canFireDeferredCreatedNotification) {
            $this->notifySetCreated($set, $request);
        }

        return back()->with('status', 'Set updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Set $set): RedirectResponse
    {
        $this->authorize('delete', $set);

        $set->delete();

        return back()->with('status', 'Set removed.');
    }

    private function notifySetCreated(Set $set, Request $request): void
    {
        $set->loadMissing('session');

        app(NotificationService::class)->notifyUsers(
            NotificationTypeCatalog::SET_CREATED,
            app(NotificationService::class)->visibleUsersForSession($set->session),
            $request->user(),
            [
                'title' => 'New set created',
                'body' => $request->user()->name.' created '.$set->name.' in '.$set->session->name.'.',
                'action_url' => route('sessions.show', $set->session).'#set-'.$set->id,
                'action_label' => 'Open set',
            ]
        );
    }

    private static function newSetDeferredNotificationCacheKey(Set $set): string
    {
        return 'notifications:set_created:deferred:'.$set->id;
    }
}
