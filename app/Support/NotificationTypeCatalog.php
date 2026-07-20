<?php

namespace App\Support;

final class NotificationTypeCatalog
{
    public const SLOT_REQUEST_ACCEPTED = 'slot_request_accepted';

    public const SLOT_REQUEST_RECEIVED = 'slot_request_received';

    public const SONG_REQUEST_RECEIVED = 'song_request_received';

    public const SLOT_RECOMMENDATION_RECEIVED = 'slot_recommendation_received';

    public const SLOT_DROPPED_FROM_SET = 'slot_dropped_from_set';

    public const SLOT_MANUALLY_ASSIGNED = 'slot_manually_assigned';

    public const SET_COLLABORATOR_ADDED = 'set_collaborator_added';

    public const SET_COLLABORATOR_REMOVED = 'set_collaborator_removed';

    public const JAM_SESSION_PUBLISHED = 'jam_session_published';

    public const JAM_SESSION_LOCK_CHANGED = 'jam_session_lock_changed';

    public const JAM_SESSION_DATE_CHANGED = 'jam_session_date_changed';

    public const SET_UPDATED = 'set_updated';

    /**
     * @return array<string, array{label: string, description: string, category: string, defaults: array{enabled: bool, popup: bool, email: bool, text: bool}}>
     */
    public static function definitions(): array
    {
        return [
            self::SLOT_REQUEST_ACCEPTED => [
                'label' => 'Slot request accepted',
                'description' => 'When one of your requested slots is accepted.',
                'category' => 'slots',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SLOT_REQUEST_RECEIVED => [
                'label' => 'Slot request received',
                'description' => 'When someone requests a slot on a set you own or collaborate on.',
                'category' => 'slots',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SONG_REQUEST_RECEIVED => [
                'label' => 'Song request received',
                'description' => 'When someone requests a song on a set you own or collaborate on.',
                'category' => 'sets',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SLOT_RECOMMENDATION_RECEIVED => [
                'label' => 'Slot recommendation received',
                'description' => 'When someone recommends you for a slot.',
                'category' => 'slots',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SLOT_DROPPED_FROM_SET => [
                'label' => 'Slot dropped from set',
                'description' => 'When someone drops a slot from a set you own or collaborate on.',
                'category' => 'slots',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SLOT_MANUALLY_ASSIGNED => [
                'label' => 'Slot manually assigned',
                'description' => 'When someone manually assigns you to a slot on a set.',
                'category' => 'slots',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SET_COLLABORATOR_ADDED => [
                'label' => 'Added as collaborator',
                'description' => 'When someone adds you as a collaborator on a set.',
                'category' => 'sets',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SET_COLLABORATOR_REMOVED => [
                'label' => 'Removed as collaborator',
                'description' => 'When someone removes you as a collaborator on a set.',
                'category' => 'sets',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::JAM_SESSION_PUBLISHED => [
                'label' => 'Jam session published',
                'description' => 'When a new jam session becomes visible to you.',
                'category' => 'jam_sessions',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::JAM_SESSION_LOCK_CHANGED => [
                'label' => 'Jam session locked or unlocked',
                'description' => 'When a jam session you are involved in is locked or unlocked.',
                'category' => 'jam_sessions',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::JAM_SESSION_DATE_CHANGED => [
                'label' => 'Jam session date changed',
                'description' => 'When the date changes on a jam session you are involved in.',
                'category' => 'jam_sessions',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
            self::SET_UPDATED => [
                'label' => 'Set updated',
                'description' => 'When a set you are involved in moves session or has songs added or removed.',
                'category' => 'sets',
                'defaults' => ['enabled' => true, 'popup' => true, 'email' => true, 'text' => false],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'slots' => 'Slots',
            'sets' => 'Sets',
            'jam_sessions' => 'Jam Sessions',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array{label: string, description: string, defaults: array{enabled: bool, popup: bool, email: bool, text: bool}}
     */
    public static function definition(string $type): array
    {
        return self::definitions()[$type];
    }

    public static function adminSettingKey(string $type, string $channel): string
    {
        return 'notifications.'.$type.'.'.$channel;
    }
}
