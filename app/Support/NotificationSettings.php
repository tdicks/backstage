<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;

final class NotificationSettings
{
    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return ['enabled', 'popup', 'email', 'text'];
    }

    public static function ensureAdminSettingsExist(): void
    {
        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            foreach (self::channels() as $channel) {
                Setting::query()->updateOrCreate(
                    ['key' => NotificationTypeCatalog::adminSettingKey($type, $channel)],
                    [
                        'name' => $definition['label'].' '.str($channel)->replace('_', ' ')->title()->toString(),
                        'input_type' => 'checkbox',
                        'value' => $definition['defaults'][$channel] ? '1' : '0',
                    ]
                );
            }
        }
    }

    public static function isNotificationKey(string $key): bool
    {
        return str_starts_with($key, 'notifications.');
    }

    /**
     * @return array<string, array{enabled: bool, popup: bool, email: bool, text: bool}>
     */
    public static function adminPreferences(): array
    {
        self::ensureAdminSettingsExist();

        $settings = Setting::query()
            ->where('key', 'like', 'notifications.%')
            ->get()
            ->keyBy('key');

        $preferences = [];

        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            $preferences[$type] = [];

            foreach (self::channels() as $channel) {
                $setting = $settings->get(NotificationTypeCatalog::adminSettingKey($type, $channel));
                $preferences[$type][$channel] = $setting
                    ? $setting->isEnabled()
                    : $definition['defaults'][$channel];
            }
        }

        return $preferences;
    }

    /**
     * @return array{enabled: bool, popup: bool, email: bool, text: bool}
     */
    public static function adminPreferencesForType(string $type): array
    {
        return self::adminPreferences()[$type];
    }

    /**
     * @return array<string, array{enabled: bool, popup: bool, email: bool, text: bool}>
     */
    public static function userPreferences(User $user): array
    {
        $storedPreferences = is_array($user->notification_preferences)
            ? $user->notification_preferences
            : [];

        $preferences = [];

        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            $defaults = $definition['defaults'];

            $preferences[$type] = [
                'enabled' => (bool) Arr::get($storedPreferences, $type.'.enabled', $defaults['enabled']),
                'popup' => (bool) Arr::get($storedPreferences, $type.'.popup', $defaults['popup']),
                'email' => (bool) Arr::get($storedPreferences, $type.'.email', $defaults['email']),
                'text' => (bool) Arr::get($storedPreferences, $type.'.text', $defaults['text']),
            ];
        }

        return $preferences;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{enabled: bool, popup: bool, email: bool, text: bool}>
     */
    public static function normalizeUserPreferences(User $user, array $input): array
    {
        $preferences = self::userPreferences($user);

        foreach (NotificationTypeCatalog::keys() as $type) {
            if (! is_array($input[$type] ?? null)) {
                continue;
            }

            foreach (['enabled', 'popup', 'email'] as $channel) {
                if (array_key_exists($channel, $input[$type])) {
                    $preferences[$type][$channel] = filter_var($input[$type][$channel], FILTER_VALIDATE_BOOL);
                }
            }
        }

        return $preferences;
    }

    /**
     * @return array{enabled: bool, popup: bool, email: bool, text: bool}
     */
    public static function effectiveDeliveryPreferences(User $user, string $type): array
    {
        $admin = self::adminPreferencesForType($type);
        $userPreferences = self::userPreferences($user)[$type];

        return [
            'enabled' => $admin['enabled'] && $userPreferences['enabled'],
            'popup' => $admin['enabled'] && $admin['popup'] && $userPreferences['enabled'] && $userPreferences['popup'],
            'email' => $admin['enabled'] && $admin['email'] && $userPreferences['enabled'] && $userPreferences['email'],
            'text' => $admin['enabled'] && $admin['text'] && $userPreferences['enabled'] && $userPreferences['text'],
        ];
    }

    /**
     * @return array<string, array{category: string, label: string, options: list<array{type: string, label: string, description: string, enabled: bool, popup: bool, email: bool, popup_available: bool, email_available: bool}>}>
     */
    public static function profileOptions(User $user): array
    {
        $adminPreferences = self::adminPreferences();
        $userPreferences = self::userPreferences($user);
        $groupedOptions = [];

        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            $admin = $adminPreferences[$type];

            if (! $admin['enabled']) {
                continue;
            }

            $category = $definition['category'];
            $preferences = $userPreferences[$type];

            if (! isset($groupedOptions[$category])) {
                $categories = NotificationTypeCatalog::categories();
                $groupedOptions[$category] = [
                    'category' => $category,
                    'label' => $categories[$category],
                    'options' => [],
                ];
            }

            $groupedOptions[$category]['options'][] = [
                'type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => $preferences['enabled'],
                'popup' => $preferences['popup'],
                'email' => $preferences['email'],
                'popup_available' => $admin['popup'],
                'email_available' => $admin['email'],
            ];
        }

        return $groupedOptions;
    }

    /**
     * @return array<string, array{category: string, label: string, options: list<array{type: string, label: string, description: string, settings: array{enabled: Setting, popup: Setting, email: Setting, text: Setting}}>}>
     */
    public static function adminOptions(): array
    {
        self::ensureAdminSettingsExist();

        $settings = Setting::query()
            ->where('key', 'like', 'notifications.%')
            ->get()
            ->keyBy('key');

        $groupedOptions = [];

        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            $category = $definition['category'];

            if (! isset($groupedOptions[$category])) {
                $categories = NotificationTypeCatalog::categories();
                $groupedOptions[$category] = [
                    'category' => $category,
                    'label' => $categories[$category],
                    'options' => [],
                ];
            }

            $groupedOptions[$category]['options'][] = [
                'type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'settings' => [
                    'enabled' => $settings->get(NotificationTypeCatalog::adminSettingKey($type, 'enabled')),
                    'popup' => $settings->get(NotificationTypeCatalog::adminSettingKey($type, 'popup')),
                    'email' => $settings->get(NotificationTypeCatalog::adminSettingKey($type, 'email')),
                    'text' => $settings->get(NotificationTypeCatalog::adminSettingKey($type, 'text')),
                ],
            ];
        }

        return $groupedOptions;
    }
}
