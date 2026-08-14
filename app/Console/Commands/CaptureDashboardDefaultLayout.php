<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:capture-dashboard-default-layout
    {user : User ID or email}')]
#[Description('Capture a user dashboard layout as the default for users without one')]
class CaptureDashboardDefaultLayout extends Command
{
    public function handle(): int
    {
        $userInput = trim((string) $this->argument('user'));

        $user = User::query()
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE)
            ->where('id', is_numeric($userInput) ? (int) $userInput : -1)
            ->orWhere('email', $userInput)
            ->first();

        if (! $user) {
            $this->error("User [{$userInput}] not found.");

            return self::FAILURE;
        }

        $layout = $user->dashboard_widget_layouts;

        if (! is_array($layout) || $layout === []) {
            $this->error("User {$user->id} ({$user->email}) has no saved dashboard layout.");

            return self::INVALID;
        }

        Setting::query()->updateOrCreate(
            ['key' => Setting::DASHBOARD_DEFAULT_WIDGET_LAYOUT_KEY],
            [
                'name' => 'Default dashboard layout',
                'input_type' => 'textarea',
                'value' => json_encode($layout, JSON_THROW_ON_ERROR),
            ],
        );

        $this->info("Captured dashboard layout from user {$user->id} ({$user->email}).");

        return self::SUCCESS;
    }
}
