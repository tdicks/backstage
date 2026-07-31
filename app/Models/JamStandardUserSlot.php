<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamStandardUserSlot extends Model
{
    protected $fillable = ['jam_standard_song_id', 'user_id', 'slot_name'];

    public function jamStandardSong(): BelongsTo
    {
        return $this->belongsTo(JamStandardSong::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
