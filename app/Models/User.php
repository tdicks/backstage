<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

#[Fillable(['name', 'email', 'mobile_number', 'password', 'is_admin', 'bio', 'hide_from_directory', 'hide_from_slot_proposals', 'slot_coverage', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRelationships;
    use Notifiable;

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
            'hide_from_directory' => 'boolean',
            'hide_from_slot_proposals' => 'boolean',
            'slot_coverage' => 'array',
            'notification_preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
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
}
