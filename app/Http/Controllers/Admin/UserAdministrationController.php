<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAdministrationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString() ?: 'name';
        $direction = strtolower($request->string('direction')->toString()) === 'desc' ? 'desc' : 'asc';

        $sortableColumns = ['name', 'email', 'is_admin', 'created_at'];

        if (! in_array($sort, $sortableColumns, true)) {
            $sort = 'name';
        }

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $updates = [
            'email' => $validated['email'],
        ];

        if (array_key_exists('is_admin', $validated)) {
            if ($request->user()->is($user)) {
                return back()->withErrors([
                    'role' => 'You cannot change your own role.',
                ]);
            }

            $updates['is_admin'] = (bool) $validated['is_admin'];
        }

        if ($validated['email'] !== $user->email) {
            $updates['email_verified_at'] = null;
        }

        $user->forceFill($updates)->save();

        if (array_key_exists('is_admin', $validated) && $validated['email'] === $user->email) {
            return back()->with('status', 'User role updated.');
        }

        return back()->with('status', 'User updated.');
    }

    public function sendPasswordResetLink(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors([
            'email' => __($status),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}