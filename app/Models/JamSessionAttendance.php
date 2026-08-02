<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamSessionAttendance extends Model
{
    public const STATUS_MAYBE = 'maybe';

    public const STATUS_GOING = 'going';

    public const STATUS_NOT_GOING = 'not_going';

    public const SOURCE_SELF = 'self';

    public const SOURCE_AUTO_SET = 'auto_set';

    public const SOURCE_AUTO_SLOT = 'auto_slot';

    public const SOURCE_ADMIN_ASSIGNMENT = 'admin_assignment';

    public const SOURCE_ADMIN_OVERRIDE = 'admin_override';

    protected $fillable = [
        'jam_session_id',
        'user_id',
        'status',
        'source',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_MAYBE,
            self::STATUS_GOING,
            self::STATUS_NOT_GOING,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(JamSession::class, 'jam_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE);
    }
}
