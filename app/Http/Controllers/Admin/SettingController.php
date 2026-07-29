<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SlotType;
use App\Services\WebPushService;
use App\Support\NotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        NotificationSettings::ensureAdminSettingsExist();

        $settings = Setting::query()
            ->orderBy('name')
            ->orderBy('key')
            ->get();

        return view('admin.settings.index', [
            'settings' => $settings->reject(fn (Setting $setting) => NotificationSettings::isNotificationKey($setting->key))->values(),
            'notificationSettings' => NotificationSettings::adminOptions(),
            'slotTypes' => SlotType::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Setting $setting): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'value' => $this->rulesFor($setting),
        ]);

        $setting->update([
            'value' => $setting->input_type === 'checkbox'
                ? ($request->boolean('value') ? '1' : '0')
                : ($validated['value'] ?? null),
        ]);

        return response()->json([
            'message' => $setting->name.' updated.',
            'setting' => [
                'id' => $setting->id,
                'key' => $setting->key,
                'value' => $setting->value,
            ],
        ]);
    }

    public function sendTestPush(Request $request, WebPushService $webPushService): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = $request->user();

        if (! $webPushService->isConfigured()) {
            return response()->json([
                'message' => 'Web push is not configured. Set WEBPUSH_VAPID_* values first.',
            ], 422);
        }

        if (! $user || ! $user->pushSubscriptions()->exists()) {
            return response()->json([
                'message' => 'No push subscription found for your account on this browser.',
            ], 422);
        }

        $webPushService->sendToUser($user, [
            'title' => 'Backstage push test',
            'body' => 'This is a test push notification from Admin Settings.',
            'action_url' => route('notifications.index'),
            'action_label' => 'Open notifications',
            'type_key' => 'admin_push_test',
        ]);

        return response()->json([
            'message' => 'Test push sent. If you do not receive it, check browser site permissions for notifications.',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesFor(Setting $setting): array
    {
        return match ($setting->input_type) {
            'checkbox' => ['nullable', 'boolean'],
            'number' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'datetime-local' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'password' => ['nullable', 'string', 'max:255'],
            'select' => ['nullable', 'string', 'max:255'],
            'textarea' => ['nullable', 'string', 'max:10000'],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
