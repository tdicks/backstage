<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'bio', 'hide_from_directory', 'hide_from_slot_proposals'])]
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
            'password' => 'hashed',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
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
