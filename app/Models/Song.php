<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Song extends Model
{
    protected $fillable = [
        'artist',
        'title',
        'notes',
        'duration',
        'source',
        'set_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }
}
