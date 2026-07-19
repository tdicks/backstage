<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SlotType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function conflicts(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'slot_type_conflicts',
            'slot_type_id',
            'conflicting_slot_type_id'
        )->withTimestamps();
    }
}
