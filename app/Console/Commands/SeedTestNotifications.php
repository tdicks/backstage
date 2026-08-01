<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AppActivityNotification;
use App\Support\NotificationTypeCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:seed-test
    {user : User id or email address}
    {--count=30 : Number of notifications to create}
    {--seen=0 : Number to mark as seen from newest to oldest}')]
#[Description('Seed test notifications for a user so tray paging and clearing can be validated')]
class SeedTestNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userLookup = trim((string) $this->argument('user'));
        $count = max(1, (int) $this->option('count'));
        $seenCount = max(0, (int) $this->option('seen'));

        $user = User::query()
            ->where('id', $userLookup)
            ->orWhere('email', $userLookup)
            ->first();

        if (! $user instanceof User) {
            $this->error('User not found. Provide a valid user id or email address.');

            return self::FAILURE;
        }

        $createdIds = [];

        for ($index = 0; $index < $count; $index++) {
            $sequence = $count - $index;

            $user->notify(new AppActivityNotification(
                NotificationTypeCatalog::SET_UPDATED,
                [
                    'title' => "Test notification {$sequence}",
                    'body' => "Seeded notification {$sequence} for tray pagination testing.",
                    'action_url' => route('dashboard'),
                    'action_label' => 'Open dashboard',
                    'popup' => false,
                ]
            ));

            $notification = $user->notifications()
                ->whereNotIn('id', $createdIds)
                ->latest()
                ->first();

            if ($notification === null) {
                continue;
            }

            // Spread timestamps to make cursor pagination deterministic.
            $notification->forceFill([
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ])->save();

            $createdIds[] = $notification->id;
        }

        if ($seenCount > 0 && $createdIds !== []) {
            $markSeenIds = array_slice($createdIds, 0, min($seenCount, count($createdIds)));

            $user->notifications()
                ->whereIn('id', $markSeenIds)
                ->update(['read_at' => now()]);
        }

        $this->info("Created {$count} test notifications for {$user->name} ({$user->email}).");
        $this->line('Tip: open the notifications tray and use Dismiss All repeatedly to validate top-up behavior without refreshing.');

        return self::SUCCESS;
    }
}
