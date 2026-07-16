<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BandTemplateSlot extends Model
{
    protected $fillable = [
        'band_template_id',
        'name',
    ];

    public function bandTemplate(): BelongsTo
    {
        return $this->belongsTo(BandTemplate::class);
    }
}
