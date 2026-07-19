<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialLoginController extends Controller
{
    /** @var array<int, string> */
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->abortIfSocialLoginsDisabled();
        $this->abortIfUnsupportedProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->abortIfSocialLoginsDisabled();
        $this->abortIfUnsupportedProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors([
                'email' => 'The social login session expired. Please try again.',
            ]);
        }

        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => 'The selected provider did not return an email address.',
            ]);
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;
        } else {
            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->forceFill([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: $email,
                    'password' => null,
                    'email_verified_at' => now(),
                ])->save();
            }

            if ($user->wasRecentlyCreated) {
                event(new Registered($user));
            }

            $socialAccount = $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_email' => $email,
                'provider_name' => $socialUser->getName() ?: $socialUser->getNickname(),
                'avatar_url' => $socialUser->getAvatar(),
            ]);
        }

        $socialAccount->update([
            'provider_email' => $email,
            'provider_name' => $socialUser->getName() ?: $socialUser->getNickname(),
            'avatar_url' => $socialUser->getAvatar(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended(route('my-sets.index', absolute: false));
    }

    private function abortIfUnsupportedProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    private function abortIfSocialLoginsDisabled(): void
    {
        abort_unless(Setting::enabled('enable_social_logins', true), 404);
    }
}
