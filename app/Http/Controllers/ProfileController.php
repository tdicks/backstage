<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Slot;
use App\Support\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        NotificationSettings::ensureAdminSettingsExist();

        return view('profile.edit', [
            'user' => $request->user(),
            'slotOptions' => Slot::options(),
            'notificationOptions' => NotificationSettings::profileOptions($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $attributes = $validated;

        foreach (['hide_from_directory', 'hide_from_slot_proposals'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $request->boolean($field);
            }
        }

        if ($request->boolean('slot_coverage_present')) {
            $attributes['slot_coverage'] = $request->input('slot_coverage', []);
        }

        if (array_key_exists('notification_preferences', $attributes)) {
            $attributes['notification_preferences'] = NotificationSettings::normalizeUserPreferences(
                $request->user(),
                $request->input('notification_preferences', [])
            );
        }

        $request->user()->fill($attributes);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'Profile updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
