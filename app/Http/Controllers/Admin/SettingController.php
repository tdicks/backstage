<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.settings.index', [
            'settings' => Setting::query()
                ->orderBy('name')
                ->orderBy('key')
                ->get(),
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
