<?php

namespace App\Services;

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\AppActivityNotification;
use App\Support\NotificationSettings;
use Carbon\CarbonInterface;
use Closure;
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
     * @param  iterable<User>  $users
     * @param  Closure(User): array{title: string, body: string, action_url: string|null, action_label?: string|null}  $contentResolver
     * @param  list<int>  $excludedUserIds
     */
    public function notifyUsersWithContentResolver(string $type, iterable $users, ?User $actor, Closure $contentResolver, bool $excludeActor = true, array $excludedUserIds = []): void
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
            $content = $contentResolver($recipient);

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
     * @return array{title: string, body: string, action_url: string|null, action_label: string|null}
     */
    public function setDeletedContent(Set $set, User $recipient, ?User $actor = null): array
    {
        $set->loadMissing('session', 'songs.slots.user');

        $impactParts = [];

        if ((int) $recipient->id === (int) $set->owner_id) {
            $impactParts[] = 'You owned this set.';
        }

        if ($set->isCollaborator($recipient)) {
            $impactParts[] = 'You were a collaborator on this set.';
        }

        $slotSummaries = $this->recipientSlotSummariesForSet($set, $recipient);
        if ($slotSummaries !== []) {
            $impactParts[] = 'Your slots: '.implode('; ', $slotSummaries).'.';
        }

        $body = ($actor?->name ?? 'Someone').' deleted '.$set->name.' from '.($set->session?->name ?? 'its session').'.';

        if ($impactParts !== []) {
            $body .= ' '.implode(' ', $impactParts);
        }

        return [
            'title' => 'Set deleted',
            'body' => $body,
            'action_url' => $recipient->is_admin ? route('recycle-bin.index') : null,
            'action_label' => $recipient->is_admin ? 'Open Recycle Bin' : null,
        ];
    }

    /**
     * @return array{title: string, body: string, action_url: string|null, action_label: string|null}
     */
    public function jamSessionDeletedContent(JamSession $session, User $recipient, ?User $actor = null): array
    {
        $session->loadMissing('sets.songs.slots.user', 'sets.owner');

        $impactedSets = $session->sets
            ->filter(fn (Set $set) => $this->recipientImpactedBySetDelete($set, $recipient))
            ->map(function (Set $set) use ($recipient): string {
                $summaries = $this->recipientSlotSummariesForSet($set, $recipient);

                if ($summaries === []) {
                    if ((int) $recipient->id === (int) $set->owner_id) {
                        return $set->name.' (owner)';
                    }

                    if ($set->isCollaborator($recipient)) {
                        return $set->name.' (collaborator)';
                    }

                    return $set->name;
                }

                return $set->name.' ('.implode('; ', $summaries).')';
            })
            ->values();

        $body = ($actor?->name ?? 'Someone').' deleted jam session '.$session->name.'.';

        if ($impactedSets->isNotEmpty()) {
            $body .= ' Impacted sets: '.$this->summarizeList($impactedSets->all()).'.';
        }

        return [
            'title' => 'Jam session deleted',
            'body' => $body,
            'action_url' => $recipient->is_admin ? route('recycle-bin.index') : null,
            'action_label' => $recipient->is_admin ? 'Open Recycle Bin' : null,
        ];
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

    /**
     * @return list<string>
     */
    private function recipientSlotSummariesForSet(Set $set, User $recipient): array
    {
        return $set->songs
            ->flatMap(function ($song) use ($recipient): array {
                $slotLabels = $song->slots
                    ->filter(fn ($slot) => (int) ($slot->user_id ?? 0) === (int) $recipient->id)
                    ->map(fn ($slot) => Slot::options()[$slot->name] ?? str($slot->name)->replace('_', ' ')->title()->toString())
                    ->values()
                    ->all();

                if ($slotLabels === []) {
                    return [];
                }

                return [$song->artist.' - '.$song->title.' ('.implode(', ', $slotLabels).')'];
            })
            ->values()
            ->all();
    }

    private function recipientImpactedBySetDelete(Set $set, User $recipient): bool
    {
        return (int) $recipient->id === (int) $set->owner_id
            || $set->isCollaborator($recipient)
            || $set->songs->contains(fn ($song) => $song->slots->contains(fn ($slot) => (int) ($slot->user_id ?? 0) === (int) $recipient->id));
    }

    /**
     * @param  list<string>  $items
     */
    private function summarizeList(array $items, int $limit = 3): string
    {
        $visibleItems = array_slice($items, 0, $limit);

        if (count($items) > $limit) {
            $visibleItems[] = '+'.(count($items) - $limit).' more';
        }

        return implode(' | ', $visibleItems);
    }
}
