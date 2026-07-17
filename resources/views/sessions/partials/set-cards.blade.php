<div class="space-y-6">
    @forelse ($session->sets as $set)
        <x-sessions.set-card
            :set="$set"
            :sessions="$sessions"
            :users="$users"
            :templates="$templates"
            :slot-options="$slotOptions"
        />
    @empty
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
            No sets for this jam session yet.
        </div>
    @endforelse
</div>