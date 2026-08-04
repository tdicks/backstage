<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reset-feature-tour-state
    {user : User ID or email}
    {once_key? : Tour once_key to clear}
    {--all : Clear all feature tour state for the user}')]
#[Description('Reset persisted feature tour state for a user')]
class ResetFeatureTourState extends Command
{
    public function handle(): int
    {
        $userInput = trim((string) $this->argument('user'));
        $clearAll = (bool) $this->option('all');
        $onceKey = trim((string) ($this->argument('once_key') ?? ''));

        if (! $clearAll && $onceKey === '') {
            $this->error('Provide once_key or use --all.');

            return self::INVALID;
        }

        $user = User::query()
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE)
            ->where('id', is_numeric($userInput) ? (int) $userInput : -1)
            ->orWhere('email', $userInput)
            ->first();

        if (! $user) {
            $this->error("User [{$userInput}] not found.");

            return self::FAILURE;
        }

        if ($clearAll) {
            $user->forceFill(['feature_tour_state' => null])->save();
            $this->info("Cleared all feature tour state for user {$user->id} ({$user->email}).");

            return self::SUCCESS;
        }

        $state = is_array($user->feature_tour_state) ? $user->feature_tour_state : [];
        $completed = is_array($state['completed'] ?? null) ? $state['completed'] : [];
        $promptDismissed = is_array($state['prompt_dismissed'] ?? null) ? $state['prompt_dismissed'] : [];
        $optedOut = is_array($state['opted_out'] ?? null) ? $state['opted_out'] : [];

        unset($completed[$onceKey], $promptDismissed[$onceKey], $optedOut[$onceKey]);

        $normalizedState = [
            'completed' => $completed,
            'prompt_dismissed' => $promptDismissed,
            'opted_out' => $optedOut,
        ];

        $hasState = $completed !== [] || $promptDismissed !== [] || $optedOut !== [];

        $user->forceFill([
            'feature_tour_state' => $hasState ? $normalizedState : null,
        ])->save();

        $this->info("Cleared feature tour key [{$onceKey}] for user {$user->id} ({$user->email}).");

        return self::SUCCESS;
    }
}
