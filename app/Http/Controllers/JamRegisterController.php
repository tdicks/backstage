<?php

namespace App\Http\Controllers;

use App\Models\JamSession;
use App\Models\JamSessionSignIn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JamRegisterController extends Controller
{
	public function index(): View
	{
		return view('jam-register.index', [
			'sessions' => JamSession::query()
				->where('is_hidden', false)
				->whereDate('date', '>=', today())
				->orderBy('date')
				->get(['id', 'name', 'date']),
		]);
	}

	public function users(Request $request): JsonResponse
	{
		$query = trim((string) $request->query('q', ''));

		$users = User::query()
			->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
			->orderBy('name')
			->limit(12)
			->get(['id', 'name']);

		return response()->json([
			'users' => $users,
		]);
	}

	public function signIn(Request $request, JamSession $jamSession): JsonResponse
	{
		$this->abortIfHidden($jamSession);
		$this->authorize('view', $jamSession);

		$validated = $request->validate([
			'user_id' => ['required', 'integer', 'exists:users,id'],
		]);

		$signIn = JamSessionSignIn::query()->updateOrCreate(
			[
				'jam_session_id' => $jamSession->id,
				'user_id' => $validated['user_id'],
			],
			[
				'signed_in_at' => now(),
			]
		);

		$signIn->load('user:id,name');

		return response()->json([
			'message' => $signIn->user->name.' is signed in for '.$jamSession->name.'.',
			'signed_in' => true,
			'sign_in' => [
				'user_id' => $signIn->user_id,
				'name' => $signIn->user->name,
				'signed_in_at' => $signIn->signed_in_at?->toIso8601String(),
			],
		]);
	}

	public function status(JamSession $jamSession, User $user): JsonResponse
	{
		$this->abortIfHidden($jamSession);
		$this->authorize('view', $jamSession);

		$signIn = JamSessionSignIn::query()
			->where('jam_session_id', $jamSession->id)
			->where('user_id', $user->id)
			->first();

		return response()->json([
			'signed_in' => (bool) $signIn,
			'user' => [
				'id' => $user->id,
				'name' => $user->name,
			],
			'signed_in_at' => $signIn?->signed_in_at?->toIso8601String(),
		]);
	}

	public function signOut(JamSession $jamSession, User $user): JsonResponse
	{
		$this->abortIfHidden($jamSession);
		$this->authorize('view', $jamSession);

		JamSessionSignIn::query()
			->where('jam_session_id', $jamSession->id)
			->where('user_id', $user->id)
			->delete();

		return response()->json([
			'message' => $user->name.' is signed out from '.$jamSession->name.'.',
			'signed_in' => false,
		]);
	}

	public function attendees(Request $request, JamSession $jamSession): JsonResponse
	{
		$this->authorize('update', $jamSession);

		$attendees = $jamSession->signIns()
			->with('user:id,name')
			->latest('signed_in_at')
			->get()
			->map(fn (JamSessionSignIn $signIn) => [
				'id' => $signIn->user_id,
				'name' => $signIn->user?->name,
				'signed_in_at' => $signIn->signed_in_at?->toIso8601String(),
				'signed_in_at_label' => $signIn->signed_in_at?->format('g:i A'),
			])
			->values();

		return response()->json([
			'count' => $attendees->count(),
			'attendees' => $attendees,
		]);
	}

	public function signOutAll(Request $request, JamSession $jamSession): JsonResponse
	{
		$this->authorize('update', $jamSession);

		$count = JamSessionSignIn::query()
			->where('jam_session_id', $jamSession->id)
			->delete();

		return response()->json([
			'message' => $count > 0
				? 'Everyone has been signed out for '.$jamSession->name.'.'
				: 'No attendees were signed in.',
			'count' => $count,
		]);
	}

	private function abortIfHidden(JamSession $jamSession): void
	{
		abort_if($jamSession->is_hidden, 404);
	}
}
