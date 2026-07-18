<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:2000'],
            'hide_from_directory' => ['nullable', 'boolean'],
            'hide_from_slot_proposals' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->id === $user->id && array_key_exists('is_admin', $validated) && ! (bool) $validated['is_admin']) {
            return back()->withErrors([
                'role' => 'You cannot remove your own admin role.',
            ]);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'bio' => $validated['bio'] ?? null,
            'hide_from_directory' => (bool) ($validated['hide_from_directory'] ?? false),
            'hide_from_slot_proposals' => (bool) ($validated['hide_from_slot_proposals'] ?? false),
        ];

        if ($request->user()->id !== $user->id) {
            $payload['is_admin'] = (bool) ($validated['is_admin'] ?? false);
        }

        if ($user->email !== $validated['email']) {
            $payload['email_verified_at'] = null;
        }

        $user->forceFill($payload)->save();

        return back()->with('status', 'User details updated.');
    }

    public function sendPasswordResetLink(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __($status),
                ]);
            }

            return back()->with('status', __($status));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return back()->withErrors([
            'email' => __($status),
        ]);
    }

    public function toggleRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($request->user()->id === $user->id) {
            return back()->withErrors([
                'role' => 'You cannot change your own role.',
            ]);
        }

        $user->forceFill([
            'is_admin' => ! $user->is_admin,
        ])->save();

        return back()->with('status', 'User role updated.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}