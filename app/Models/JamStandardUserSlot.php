<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /**
     * @param  Collection<int, int>|array<int, int>  $songIds
     * @return array<int, array<string, int>>
     */
    public static function recentCapabilityCountsForSongs(Collection|array $songIds): array
    {
        $counts = [];

        self::query()
            ->join('users', 'users.id', '=', 'jam_standard_user_slots.user_id')
            ->whereIn('jam_standard_user_slots.jam_standard_song_id', $songIds)
            ->where('users.is_deleted_account', false)
            ->where('users.last_seen_at', '>=', now()->subMonths(6))
            ->selectRaw('jam_standard_user_slots.jam_standard_song_id, jam_standard_user_slots.slot_name, count(*) as capability_count')
            ->groupBy('jam_standard_user_slots.jam_standard_song_id', 'jam_standard_user_slots.slot_name')
            ->get()
            ->each(function (self $userSlot) use (&$counts): void {
                $counts[$userSlot->jam_standard_song_id][$userSlot->slot_name] = max(0, (int) $userSlot->capability_count);
            });

        return $counts;
    }
}
