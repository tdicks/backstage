<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = [
        'artist',
        'title',
        'notes',
        'set_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
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
}
