<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

#[Fillable(['name', 'email', 'mobile_number', 'password', 'is_admin', 'is_deleted_account', 'deleted_account_at', 'bio', 'onboarding_dismissed_at', 'hide_from_directory', 'hide_from_slot_proposals', 'slot_coverage', 'notification_preferences', 'notifications_snoozed_until', 'notifications_snoozed_forever'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ACTIVE_ACCOUNTS_SCOPE = 'activeAccounts';

    public const SLOT_COVERAGE_CAN = 'can';

    public const SLOT_COVERAGE_UNSPECIFIED = 'unspecified';

    public const SLOT_COVERAGE_WONT = 'wont_cover';

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRelationships;
    use Notifiable;

    protected static function booted(): void
    {
        static::addGlobalScope(self::ACTIVE_ACCOUNTS_SCOPE, function (Builder $builder): void {
            $builder->where('is_deleted_account', false);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_deleted_account' => 'boolean',
            'deleted_account_at' => 'datetime',
            'onboarding_dismissed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
            'hide_from_directory' => 'boolean',
            'hide_from_slot_proposals' => 'boolean',
            'slot_coverage' => 'array',
            'notification_preferences' => 'array',
            'notifications_snoozed_forever' => 'boolean',
            'notifications_snoozed_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeWithDeletedAccounts(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::ACTIVE_ACCOUNTS_SCOPE);
    }

    public static function slotCoverageStates(): array
    {
        return [
            self::SLOT_COVERAGE_UNSPECIFIED => 'Unspecified',
            self::SLOT_COVERAGE_CAN => 'Can cover',
            self::SLOT_COVERAGE_WONT => "Won't cover",
        ];
    }

    public function slotCoverageMap(): array
    {
        $storedCoverage = is_array($this->slot_coverage) ? $this->slot_coverage : [];
        $normalized = [];

        foreach ($storedCoverage as $slotName => $state) {
            if (is_int($slotName)) {
                $normalized[(string) $state] = self::SLOT_COVERAGE_CAN;

                continue;
            }

            $normalizedState = self::normalizeSlotCoverageState((string) $state);

            if ($normalizedState === self::SLOT_COVERAGE_UNSPECIFIED) {
                continue;
            }

            $normalized[(string) $slotName] = $normalizedState;
        }

        return $normalized;
    }

    public function slotCoverageState(string $slotName): string
    {
        return $this->slotCoverageMap()[$slotName] ?? self::SLOT_COVERAGE_UNSPECIFIED;
    }

    public function coversSlot(string $slotName): bool
    {
        return $this->slotCoverageState($slotName) === self::SLOT_COVERAGE_CAN;
    }

    public function willNotCoverSlot(string $slotName): bool
    {
        return $this->slotCoverageState($slotName) === self::SLOT_COVERAGE_WONT;
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @return array<string, string>
     */
    public static function normalizeSlotCoverage(array $coverage): array
    {
        $normalized = [];

        foreach ($coverage as $slotName => $state) {
            if (is_int($slotName)) {
                $normalized[(string) $state] = self::SLOT_COVERAGE_CAN;

                continue;
            }

            $normalizedState = self::normalizeSlotCoverageState((string) $state);

            if ($normalizedState === self::SLOT_COVERAGE_UNSPECIFIED) {
                continue;
            }

            $normalized[(string) $slotName] = $normalizedState;
        }

        return $normalized;
    }

    private static function normalizeSlotCoverageState(string $state): string
    {
        return in_array($state, [self::SLOT_COVERAGE_CAN, self::SLOT_COVERAGE_WONT], true)
            ? $state
            : self::SLOT_COVERAGE_UNSPECIFIED;
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploader_user_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(NotificationPushSubscription::class);
    }

    public function jamSessionAttendances(): HasMany
    {
        return $this->hasMany(JamSessionAttendance::class);
    }

    public function activeNotifications(): MorphMany
    {
        return $this->notifications()->whereNull('dismissed_at');
    }

    public function songs()
    {
        return $this->hasManyDeep(Song::class, [Slot::class]);
    }

    public function sets()
    {
        return $this->hasManyDeep(Set::class, [Slot::class, Song::class]);
    }

    public function notificationsAreSnoozed(): bool
    {
        if ($this->notifications_snoozed_forever) {
            return true;
        }

        if ($this->notifications_snoozed_until === null) {
            return false;
        }

        return $this->notifications_snoozed_until->isFuture();
    }

    public function snoozeNotificationsUntil(?\DateTimeInterface $until = null): void
    {
        $this->forceFill([
            'notifications_snoozed_until' => $until,
            'notifications_snoozed_forever' => false,
        ])->save();
    }

    public function resumeNotifications(): void
    {
        $this->forceFill([
            'notifications_snoozed_until' => null,
            'notifications_snoozed_forever' => false,
        ])->save();
    }
}
