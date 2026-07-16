<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class BandTemplate extends Model
{
    protected $fillable = [
        'name',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(BandTemplateSlot::class);
    }
}
