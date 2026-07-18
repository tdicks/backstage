<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    public const NAMES = [
        'vocals',
        'lead_guitar',
        'rhythm_guitar',
        'bass',
        'drums',
        'keys',
        'other',
    ];

    protected $fillable = [
        'song_id',
        'name',
        'user_id',
        'manual_performer_name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public static function options(): array
    {
        return collect(self::NAMES)
            ->mapWithKeys(fn (string $value) => [$value => str($value)->replace('_', ' ')->title()->toString()])
            ->all();
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SlotAssignment::class);
    }

    public function isOpen(): bool
    {
        return $this->user_id === null && blank($this->manual_performer_name);
    }

    public function assignedPerformerName(): string
    {
        return $this->user?->name ?? $this->manual_performer_name ?? 'Open';
    }
}
