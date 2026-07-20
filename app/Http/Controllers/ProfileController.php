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

        $request->user()->fill([
            'name' => $validated['name'] ?? $request->user()->name,
            'email' => $validated['email'] ?? $request->user()->email,
            'mobile_number' => array_key_exists('mobile_number', $validated) ? $validated['mobile_number'] : $request->user()->mobile_number,
            'bio' => array_key_exists('bio', $validated) ? ($validated['bio'] ?? null) : $request->user()->bio,
            'hide_from_directory' => $request->has('hide_from_directory')
                ? $request->boolean('hide_from_directory')
                : $request->user()->hide_from_directory,
            'hide_from_slot_proposals' => $request->has('hide_from_slot_proposals')
                ? $request->boolean('hide_from_slot_proposals')
                : $request->user()->hide_from_slot_proposals,
            'slot_coverage' => array_key_exists('slot_coverage', $validated)
                ? $request->input('slot_coverage', [])
                : $request->user()->slot_coverage,
            'notification_preferences' => array_key_exists('notification_preferences', $validated)
                ? NotificationSettings::normalizeUserPreferences(
                    $request->user(),
                    $request->input('notification_preferences', [])
                )
                : NotificationSettings::userPreferences($request->user()),
        ]);

        if (array_key_exists('email', $validated) && $request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
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
