<?php

namespace App\Services;

use App\Models\JamSession;
use App\Models\Set;
use App\Models\User;
use App\Notifications\AppActivityNotification;
use App\Support\NotificationSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function __construct(private readonly WebPushService $webPushService) {}

    /**
     * @param  iterable<User>  $users
     * @param  array{title: string, body: string, action_url: string|null, action_label?: string|null}  $content
     * @param  list<int>  $excludedUserIds
     */
    public function notifyUsers(string $type, iterable $users, ?User $actor, array $content, bool $excludeActor = true, array $excludedUserIds = []): void
    {
        NotificationSettings::ensureAdminSettingsExist();

        $recipients = collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->reject(fn (User $user) => $excludeActor && $actor !== null && $user->is($actor))
            ->reject(fn (User $user) => in_array($user->id, $excludedUserIds, true))
            ->filter(fn (User $user) => NotificationSettings::effectiveDeliveryPreferences($user, $type)['enabled']);

        foreach ($recipients as $recipient) {
            $delivery = NotificationSettings::effectiveDeliveryPreferences($recipient, $type);

            $recipient->notify(new AppActivityNotification($type, $content, $actor?->id));

            if ($delivery['push']) {
                $this->webPushService->sendToUser($recipient, [
                    ...$content,
                    'type_key' => $type,
                ]);
            }
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function managersForSet(Set $set): EloquentCollection
    {
        $ids = array_values(array_unique([
            $set->owner_id,
            ...$set->collaboratorUserIds(),
        ]));

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function involvedUsersForSet(Set $set): EloquentCollection
    {
        $set->loadMissing('songs.slots');

        $slotUserIds = $set->songs
            ->flatMap(fn ($song) => $song->slots->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_unique([
            $set->owner_id,
            ...$set->collaboratorUserIds(),
            ...$slotUserIds,
        ]));

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function participantsForSession(JamSession $session): EloquentCollection
    {
        $session->loadMissing('sets.songs.slots');

        $ids = $session->sets
            ->flatMap(function (Set $set): array {
                $slotUserIds = $set->songs
                    ->flatMap(fn ($song) => $song->slots->pluck('user_id'))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return [
                    $set->owner_id,
                    ...$set->collaboratorUserIds(),
                    ...$slotUserIds,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function visibleUsersForPublishedSession(): EloquentCollection
    {
        return User::query()->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function visibleUsersForSession(JamSession $session): EloquentCollection
    {
        return User::query()
            ->when(! $session->is_hidden, fn ($query) => $query, fn ($query) => $query->where('is_admin', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{notifications: list<array{id: string, type_key: string, title: string, body: string, action_url: string|null, action_label: string, should_popup: bool, seen: bool, created_at: string|null, created_at_human: string|null}>, unread_count: int, total_count: int}
     */
    public function feedForUser(User $user, int $limit = 25, ?CarbonInterface $after = null, ?CarbonInterface $before = null, array $knownIds = []): array
    {
        $notifications = $user->notifications()->whereNull('dismissed_at');

        if ($after !== null) {
            $notifications
                ->where('created_at', '>=', $after)
                ->when($knownIds !== [], fn ($query) => $query->whereNotIn('id', $knownIds))
                ->oldest();
        } elseif ($before !== null) {
            $notifications
                ->where(function ($query) use ($before, $knownIds): void {
                    $query->where('created_at', '<', $before);

                    if ($knownIds !== []) {
                        $query->orWhere(function ($timestampQuery) use ($before, $knownIds): void {
                            $timestampQuery
                                ->where('created_at', '=', $before)
                                ->whereNotIn('id', $knownIds);
                        });
                    }
                })
                ->latest();
        } else {
            $notifications->latest();
        }

        $notifications = $notifications->limit($limit)->get();
        $activeNotifications = $user->notifications()->whereNull('dismissed_at');

        return [
            'notifications' => $notifications
                ->map(fn (DatabaseNotification $notification) => $this->mapNotification($notification))
                ->all(),
            'unread_count' => (clone $activeNotifications)
                ->whereNull('read_at')
                ->count(),
            'total_count' => (clone $activeNotifications)->count(),
        ];
    }

    public function markAsSeen(User $user, string $notificationId): void
    {
        $notification = $this->findUserNotification($user, $notificationId);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public function dismiss(User $user, string $notificationId): void
    {
        $notification = $this->findUserNotification($user, $notificationId);

        $notification->forceFill([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ])->save();
    }

    private function findUserNotification(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();

        return $notification;
    }

    /**
     * @return array{id: string, type_key: string, title: string, body: string, action_url: string|null, action_label: string, should_popup: bool, seen: bool, created_at: string|null, created_at_human: string|null}
     */
    private function mapNotification(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type_key' => (string) ($data['type_key'] ?? $notification->type),
            'title' => (string) ($data['title'] ?? 'Notification'),
            'body' => (string) ($data['body'] ?? ''),
            'action_url' => isset($data['action_url']) ? (string) $data['action_url'] : null,
            'action_label' => (string) ($data['action_label'] ?? 'Open'),
            'should_popup' => (bool) ($data['popup'] ?? false),
            'seen' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
        ];
    }
}
