<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>Jam Register</title>
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-slate-100 antialiased">
	<main class="py-10" x-data="{
		sessions: @js($sessions->map(fn ($session) => [
			'id' => $session->id,
			'name' => $session->name,
			'date_label' => $session->date->format('D, M j, Y'),
		])->values()),
		selectedSessionId: null,
		selectedSessionName: '',
		showSessionPicker: true,
		query: '',
		selectedUserId: null,
		selectedUserName: '',
		suggestions: [],
		busy: false,
		modalOpen: false,
		modalBusy: false,
		selectedUserSignedIn: false,
		error: '',
		toastVisible: false,
		toastMessage: '',
		toastTimer: null,
		searchTimer: null,
		showToast(message) {
			this.toastMessage = message;
			this.toastVisible = true;
			if (this.toastTimer) {
				clearTimeout(this.toastTimer);
			}
			this.toastTimer = setTimeout(() => {
				this.toastVisible = false;
			}, 2800);
		},
		selectSession(session) {
			this.selectedSessionId = session.id;
			this.selectedSessionName = session.name;
			this.showSessionPicker = false;
			this.error = '';
			this.query = '';
			this.selectedUserId = null;
			this.selectedUserName = '';
			this.suggestions = [];
			this.modalOpen = false;
		},
		onType() {
			if (!this.selectedSessionId) {
				return;
			}
			this.selectedUserId = null;
			this.error = '';
			if (this.searchTimer) {
				clearTimeout(this.searchTimer);
			}
			const trimmed = this.query.trim();
			if (trimmed.length < 1) {
				this.suggestions = [];
				return;
			}
			this.searchTimer = setTimeout(() => this.searchUsers(trimmed), 200);
		},
		async searchUsers(search) {
			try {
				const response = await fetch(`{{ route('jam-register.users') }}?q=${encodeURIComponent(search)}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				if (!response.ok) {
					throw new Error('Search failed');
				}
				const data = await response.json();
				this.suggestions = data.users || [];
			} catch (e) {
				this.suggestions = [];
			}
		},
		chooseUser(user) {
			this.query = user.name;
			this.selectedUserId = user.id;
			this.selectedUserName = user.name;
			this.suggestions = [];
			this.error = '';
			this.openUserActionModal();
		},
		async openUserActionModal() {
			if (!this.selectedSessionId || !this.selectedUserId) {
				return;
			}
			this.modalBusy = true;
			this.error = '';
			try {
				const response = await fetch(`{{ url('/jam-register/sessions') }}/${this.selectedSessionId}/users/${this.selectedUserId}/status`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				const payload = await response.json();
				if (!response.ok) {
					this.error = payload.message || 'Could not check sign-in status. Please try again.';
					return;
				}
				this.selectedUserSignedIn = !!payload.signed_in;
				this.modalBusy = false;
				this.modalOpen = true;
			} catch (e) {
				this.error = 'Could not check sign-in status. Please try again.';
			} finally {
				this.modalBusy = false;
			}
		},
		closeModal() {
			this.modalOpen = false;
			this.query = '';
			this.selectedUserId = null;
			this.selectedUserName = '';
			this.suggestions = [];
		},
		async submitUserAction() {
			this.error = '';
			if (!this.selectedSessionId) {
				this.error = 'No active jam session is configured. Please ask an organiser for help.';
				return;
			}
			if (!this.selectedUserId) {
				this.error = 'Select your name from the suggestions before signing in.';
				return;
			}
			this.busy = true;
			this.modalBusy = true;
			try {
				const isSigningOut = this.selectedUserSignedIn;
				const actionUrl = isSigningOut
					? `{{ url('/jam-register/sessions') }}/${this.selectedSessionId}/check-out/${this.selectedUserId}`
					: `{{ url('/jam-register/sessions') }}/${this.selectedSessionId}/check-in`;
				const response = await fetch(actionUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
					},
					body: JSON.stringify({ user_id: this.selectedUserId }),
				});
				const payload = await response.json();
				if (!response.ok) {
					this.error = payload.message || 'Could not update sign-in status. Please ask an organiser for help.';
					return;
				}
				this.showToast(payload.message || 'Status updated successfully.');
				this.query = '';
				this.selectedUserId = null;
				this.selectedUserName = '';
				this.suggestions = [];
				this.modalOpen = false;
			} catch (e) {
				this.error = 'Could not update sign-in status. Please ask an organiser for help.';
			} finally {
				this.busy = false;
				this.modalBusy = false;
			}
		}
	}">
		<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
			<div class="mb-8 flex items-center gap-4">
				<x-application-logo class="h-12 w-12 fill-current text-slate-100" />
				<div>
					<h1 class="text-3xl font-semibold">Jam Register</h1>
					<p class="text-sm text-slate-300">Backstage sign-in.</p>
				</div>
			</div>

			<div class="mx-auto max-w-3xl">
				<section class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm text-slate-900">
					<h3 class="text-2xl font-semibold text-slate-900">Welcome!</h3>
					<p class="mt-2 text-sm text-slate-700">
						Start typing your name below to sign in or out.
					</p>

					<div class="mt-4 space-y-2" x-show="showSessionPicker">
						<p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Select Jam Session</p>
						<template x-for="session in sessions" :key="session.id">
							<button
								type="button"
								@click="selectSession(session)"
								class="flex w-full cursor-pointer items-start justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-slate-700 shadow-sm transition hover:border-slate-300"
							>
								<span class="font-medium" x-text="session.name"></span>
								<span class="text-xs text-slate-500" x-text="session.date_label"></span>
							</button>
						</template>
						<p class="text-sm text-slate-500" x-show="sessions.length === 0">No upcoming jam sessions found.</p>
					</div>

					<h4 class="mt-4 text-lg font-semibold text-slate-900" x-show="selectedSessionName && !showSessionPicker" x-text="`Tonight's jam is ${selectedSessionName}`"></h4>

					<div class="mt-5">
						<x-input-label for="register_name" value="Your Name" />
						<input
							id="register_name"
							type="text"
							x-model="query"
							@input="onType()"
							:disabled="!selectedSessionId || showSessionPicker"
							autocomplete="off"
							class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200 disabled:cursor-not-allowed disabled:bg-slate-100"
							placeholder="Type your name"
						>

						<div x-show="suggestions.length > 0" class="mt-2 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
							<template x-for="user in suggestions" :key="user.id">
								<button
									type="button"
									@click="chooseUser(user)"
									class="block w-full cursor-pointer border-b border-slate-100 px-3 py-2 text-left text-sm text-slate-700 transition last:border-b-0 hover:bg-slate-50"
									x-text="user.name"
								></button>
							</template>
						</div>
					</div>

					<div class="mt-6 flex flex-wrap items-center gap-3">
						<p class="text-sm text-slate-600">Select your name to continue.</p>
					</div>
				</section>
			</div>

			<div
				x-show="toastVisible"
				x-cloak
				x-transition:enter="transition ease-out duration-250"
				x-transition:enter-start="opacity-0"
				x-transition:enter-end="opacity-100"
				x-transition:leave="transition ease-in duration-200"
				x-transition:leave-start="opacity-100"
				x-transition:leave-end="opacity-0"
				class="fixed inset-0 z-[130] bg-black/35"
				style="display: none;"
			></div>
			<div
				x-show="toastVisible"
				x-cloak
				x-transition:enter="transition ease-out duration-300"
				x-transition:enter-start="translate-y-2 scale-95 opacity-0"
				x-transition:enter-end="translate-y-0 scale-100 opacity-100"
				x-transition:leave="transition ease-in duration-200"
				x-transition:leave-start="translate-y-0 scale-100 opacity-100"
				x-transition:leave-end="translate-y-2 scale-95 opacity-0"
				class="fixed inset-0 z-[140] flex items-center justify-center p-4"
				style="display: none;"
			>
				<div class="w-full max-w-md rounded-xl border border-emerald-300/60 bg-emerald-500 px-5 py-4 text-center text-base font-semibold text-white shadow-2xl">
					<span x-text="toastMessage"></span>
				</div>
			</div>

			<div x-show="modalOpen" x-cloak class="fixed inset-0 z-[100] bg-black/45" @click="closeModal()"></div>
			<div x-show="modalOpen" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center p-4" @keydown.escape.window="closeModal()">
				<div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl">
					<h4 class="text-lg font-semibold">Confirm Action</h4>
					<p class="mt-2 text-sm text-slate-700">
						<span class="font-semibold" x-text="selectedUserName"></span>
						<span x-text="selectedUserSignedIn ? ' is currently signed in.' : ' is currently signed out.'"></span>
					</p>
					<p class="mt-1 text-sm text-slate-700" x-text="selectedUserSignedIn ? 'Do you want to sign out now?' : 'Do you want to sign in now?'"></p>

					<div class="mt-5 flex justify-end gap-2">
						<button
							type="button"
							@click="closeModal()"
							class="inline-flex cursor-pointer items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50"
						>
							Cancel
						</button>
						<button
							type="button"
							@click.stop.prevent="submitUserAction()"
							:disabled="busy"
							:class="selectedUserSignedIn ? 'border-rose-600 bg-rose-500 text-white hover:bg-rose-400' : 'border-amber-600 bg-amber-500 text-slate-900 hover:bg-amber-400'"
							class="inline-flex cursor-pointer items-center rounded-md border px-3 py-2 text-xs font-semibold uppercase tracking-widest shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
						>
							<span x-text="selectedUserSignedIn ? 'Sign Out' : 'Sign In'"></span>
						</button>
					</div>
				</div>
			</div>

			<p class="mx-auto mt-3 max-w-3xl text-sm text-rose-300" x-show="error" x-text="error"></p>
		</div>
	</main>
</body>
</html>
