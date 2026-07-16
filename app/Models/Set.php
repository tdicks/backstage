<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'jam_session_id',
        'performed',
    ];

    protected function casts(): array
    {
        return [
            'performed' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(JamSession::class, 'jam_session_id');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function songRequests(): HasMany
    {
        return $this->hasMany(SongRequest::class);
    }
}
