<?php

use App\Models\JamSession;
use App\Models\Set;
use App\Models\Slot;
use App\Models\SocialAccount;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('deleting account anonymizes user and preserves related set data', function () {
    $user = User::factory()->create([
        'name' => 'Real Name',
        'email' => 'real@example.com',
        'mobile_number' => '+447700900001',
        'bio' => 'My bio',
        'is_admin' => true,
        'hide_from_directory' => false,
        'hide_from_slot_proposals' => false,
    ]);

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'provider_email' => 'real@example.com',
        'provider_name' => 'Real Name',
    ]);

    $session = JamSession::query()->create([
        'name' => 'Deletion Session',
        'date' => now()->addWeek()->toDateString(),
        'description' => null,
        'is_closed' => false,
    ]);

    $set = Set::query()->create([
        'name' => 'Owned Set',
        'description' => null,
        'owner_id' => $user->id,
        'jam_session_id' => $session->id,
        'position' => 1,
        'performed' => false,
        'signups_open' => true,
        'song_requests' => true,
    ]);

    $song = Song::query()->create([
        'set_id' => $set->id,
        'artist' => 'Artist',
        'title' => 'Title',
        'position' => 1,
    ]);

    $slot = Slot::query()->create([
        'song_id' => $song->id,
        'name' => 'vocals',
        'position' => 1,
        'user_id' => $user->id,
    ]);

    $set->attachments()->create([
        'uploader_user_id' => $user->id,
        'type' => 'link',
        'label' => 'Chart',
        'url' => 'https://example.com/chart',
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect('/');

    $this->assertGuest();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();

    $deletedUser = User::withDeletedAccounts()->findOrFail($user->id);

    expect($deletedUser->name)->toBe('[deleted user]');
    expect($deletedUser->email)->toBe('deleted-user-'.$user->id.'@backstage.invalid');
    expect($deletedUser->is_deleted_account)->toBeTrue();
    expect($deletedUser->deleted_account_at)->not->toBeNull();
    expect($deletedUser->is_admin)->toBeFalse();
    expect($deletedUser->mobile_number)->toBeNull();
    expect($deletedUser->bio)->toBeNull();
    expect($deletedUser->hide_from_directory)->toBeTrue();
    expect($deletedUser->hide_from_slot_proposals)->toBeTrue();
    expect($deletedUser->notification_preferences)->toBeNull();
    expect(Hash::check('password', $deletedUser->password))->toBeFalse();

    expect(SocialAccount::query()->where('user_id', $user->id)->count())->toBe(0);

    expect(Set::query()->whereKey($set->id)->exists())->toBeTrue();
    expect(Song::query()->whereKey($song->id)->exists())->toBeTrue();
    expect(Slot::query()->whereKey($slot->id)->exists())->toBeTrue();
    expect(Slot::query()->findOrFail($slot->id)->user_id)->toBe($user->id);
    expect($set->attachments()->count())->toBe(1);
});
