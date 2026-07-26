<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $returnTo = $request->query('return_to');

        return view('auth.register', [
            'socialLoginsEnabled' => Setting::enabled('enable_social_logins'),
            'returnTo' => $this->isJamRegisterReturnUrl($returnTo, $request) ? $returnTo : null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'return_to' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $returnTo = $request->input('return_to');

        if ($this->isJamRegisterReturnUrl($returnTo, $request)) {
            return redirect($returnTo);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function isJamRegisterReturnUrl(mixed $returnTo, Request $request): bool
    {
        if (! is_string($returnTo)) {
            return false;
        }

        $url = parse_url($returnTo);

        return ($url['host'] ?? null) === $request->getHost()
            && preg_match('#^/jam-register/[A-Za-z0-9]{4}$#', $url['path'] ?? '') === 1;
    }
}
