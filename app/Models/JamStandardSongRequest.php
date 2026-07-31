<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamStandardSongRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'requester_user_id',
        'reviewed_by_user_id',
        'band_template_id',
        'artist',
        'title',
        'notes',
        'slot_names',
        'requester_slot_names',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['slot_names' => 'array', 'requester_slot_names' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id')
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE);
    }

    public function bandTemplate(): BelongsTo
    {
        return $this->belongsTo(BandTemplate::class);
    }
}
