<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamSessionSignIn extends Model
{
    protected $fillable = [
        'jam_session_id',
        'user_id',
        'signed_in_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
        ];
    }

    public function jamSession(): BelongsTo
    {
        return $this->belongsTo(JamSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
