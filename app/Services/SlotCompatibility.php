<?php

namespace App\Services;

use App\Models\Slot;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SlotCompatibility
{
    public static function ensureUserCanPerformSlot(int $userId, Slot $slot, ?string $slotName = null, string $field = 'user_id'): void
    {
        $slot->loadMissing('song');

        self::ensureUserCanPerformSlotInSong(
            $userId,
            $slot->song,
            $slotName ?? $slot->name,
            $slot->id,
            $field
        );
    }

    public static function ensureUserCanPerformSlotInSong(int $userId, Song $song, string $slotName, ?int $exceptSlotId = null, string $field = 'user_id'): void
    {
        $conflictingSlot = self::conflictingSlotInSong($userId, $song, $slotName, $exceptSlotId);

        if (! $conflictingSlot) {
            return;
        }

        $slotOptions = Slot::options();
        $targetLabel = $slotOptions[$slotName] ?? str($slotName)->replace('_', ' ')->title()->toString();
        $conflictingLabel = $slotOptions[$conflictingSlot->name] ?? str($conflictingSlot->name)->replace('_', ' ')->title()->toString();
        $playerName = User::query()->find($userId)?->name ?? 'This player';

        throw ValidationException::withMessages([
            $field => "$playerName is already assigned to $conflictingLabel on this song, so they cannot also take $targetLabel. They don't have enough limbs for that.",
        ]);
    }

    public static function conflictingSlotForSlot(int $userId, Slot $slot, ?string $slotName = null): ?Slot
    {
        $slot->loadMissing('song');

        return self::conflictingSlotInSong(
            $userId,
            $slot->song,
            $slotName ?? $slot->name,
            $slot->id
        );
    }

    public static function conflictingSlotInSong(int $userId, Song $song, string $slotName, ?int $exceptSlotId = null): ?Slot
    {
        $conflictingSlotNames = self::conflictingSlotNames($slotName);

        if ($conflictingSlotNames === []) {
            return null;
        }

        return Slot::query()
            ->where('user_id', $userId)
            ->whereIn('name', $conflictingSlotNames)
            ->when($exceptSlotId, fn ($query) => $query->whereKeyNot($exceptSlotId))
            ->where('song_id', $song->id)
            ->first();
    }

    private static function conflictingSlotNames(string $slotName): array
    {
        if (! Schema::hasTable('slot_type_conflicts') || ! Schema::hasTable('slot_types')) {
            return [];
        }

        $forward = DB::table('slot_type_conflicts')
            ->join('slot_types as source_slot_types', 'source_slot_types.id', '=', 'slot_type_conflicts.slot_type_id')
            ->join('slot_types as conflicting_slot_types', 'conflicting_slot_types.id', '=', 'slot_type_conflicts.conflicting_slot_type_id')
            ->where('source_slot_types.key', $slotName)
            ->where('conflicting_slot_types.active', true)
            ->pluck('conflicting_slot_types.key');

        $reverse = DB::table('slot_type_conflicts')
            ->join('slot_types as source_slot_types', 'source_slot_types.id', '=', 'slot_type_conflicts.slot_type_id')
            ->join('slot_types as conflicting_slot_types', 'conflicting_slot_types.id', '=', 'slot_type_conflicts.conflicting_slot_type_id')
            ->where('conflicting_slot_types.key', $slotName)
            ->where('source_slot_types.active', true)
            ->pluck('source_slot_types.key');

        return $forward
            ->merge($reverse)
            ->unique()
            ->values()
            ->all();
    }
}
