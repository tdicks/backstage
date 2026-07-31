<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamStandardSlot extends Model
{
    protected $fillable = ['name', 'position'];

    public function jamStandardSong(): BelongsTo
    {
        return $this->belongsTo(JamStandardSong::class);
    }
}
