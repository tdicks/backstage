@props(['session'])

<div
	x-data="{
		open: false,
		attendees: [],
		loading: false,
		clearBusy: false,
		manualQuery: '',
		manualSuggestions: [],
		manualLookupBusy: false,
		manualCheckInBusy: false,
		manualLookupError: '',
		manualLookupTimer: null,
		feedback: '',
		feedbackTimer: null,
		error: '',
		pollId: null,
		async fetchAttendees() {
			this.loading = true;
			this.error = '';

			try {
				const response = await fetch('{{ route('sessions.check-ins', $session) }}', {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Failed to fetch attendees');
				}

				const payload = await response.json();
				this.attendees = payload.attendees || [];
			} catch (e) {
				this.error = 'Could not load the sign-in list right now.';
			} finally {
				this.loading = false;
			}
		},
		startPolling() {
			this.stopPolling();
			this.pollId = setInterval(() => {
				if (this.open) {
					this.fetchAttendees();
				}
			}, 15000);
		},
		stopPolling() {
			if (this.pollId) {
				clearInterval(this.pollId);
				this.pollId = null;
			}
		},
		openModal() {
			this.open = true;
			this.fetchAttendees();
			this.startPolling();
		},
		closeModal() {
			this.open = false;
			this.feedback = '';
			this.stopPolling();
		},
		showFeedback(message) {
			clearTimeout(this.feedbackTimer);
			this.feedback = message;
			this.feedbackTimer = setTimeout(() => {
				this.feedback = '';
			}, 3500);
		},
		queueManualLookup() {
			clearTimeout(this.manualLookupTimer);

			if (this.manualQuery.trim() === '') {
				this.manualSuggestions = [];
				this.manualLookupError = '';

				return;
			}

			this.manualLookupTimer = setTimeout(() => this.fetchManualSuggestions(), 250);
		},
		async fetchManualSuggestions() {
			this.manualLookupBusy = true;
			this.manualLookupError = '';

			try {
				const url = new URL(@js(route('sessions.check-ins.users', $session)), window.location.origin);
				url.searchParams.set('q', this.manualQuery.trim());

				const response = await fetch(url, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Failed to fetch available users');
				}

				const payload = await response.json();
				this.manualSuggestions = payload.users || [];
			} catch (e) {
				this.manualLookupError = 'Could not load matching users right now.';
				this.manualSuggestions = [];
			} finally {
				this.manualLookupBusy = false;
			}
		},
		async manualSignIn(user) {
			if (this.manualCheckInBusy) {
				return;
			}

			this.feedback = '';
			this.error = '';
			this.manualLookupError = '';
			this.manualCheckInBusy = true;

			try {
				const response = await fetch('{{ route('sessions.check-ins.sign-in', $session) }}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
					},
					body: JSON.stringify({ user_id: user.id }),
				});

				const payload = await response.json();

				if (!response.ok) {
					this.error = payload.message || 'Could not check this user in right now.';

					return;
				}

				this.showFeedback(payload.message || `${user.name} has been checked in.`);
				this.manualQuery = '';
				this.manualSuggestions = [];
				await this.fetchAttendees();
			} catch (e) {
				this.error = 'Could not check this user in right now.';
			} finally {
				this.manualCheckInBusy = false;
			}
		},
		async signOutEveryone() {
			const confirmed = window.confirm('Sign everyone out for this jam session?');
			if (!confirmed) {
				return;
			}

			this.feedback = '';
			this.error = '';
			this.clearBusy = true;

			try {
				const response = await fetch('{{ route('sessions.check-ins.sign-out-all', $session) }}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
					},
					body: JSON.stringify({}),
				});

				const payload = await response.json();

				if (!response.ok) {
					this.error = payload.message || 'Could not sign everyone out right now.';
					return;
				}

				this.showFeedback(payload.message || 'Everyone has been signed out.');
				await this.fetchAttendees();
			} catch (e) {
				this.error = 'Could not sign everyone out right now.';
			} finally {
				this.clearBusy = false;
			}
		},
	}"
	x-init="$watch('open', value => { if (!value) stopPolling(); })"
	@open-who-is-here.window="openModal()"
>
	<div x-show="open" x-cloak data-drag-blocking-modal class="fixed inset-0 z-[90] bg-black/40" @click="closeModal"></div>
	<div x-show="open" x-cloak class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto p-4 pt-20 sm:items-center sm:pt-4" @keydown.escape.window="closeModal">
		<div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-visible rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl">
			<div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-6 py-4">
				<div>
					<h3 class="text-lg font-semibold text-slate-900">Who's Here</h3>
					<p class="mt-1 text-sm text-slate-600">{{ $session->name }} ({{ $session->date->format('D, M j, Y') }})</p>
				</div>
				<x-modal-secondary-button type="button" @click="closeModal">Close</x-modal-secondary-button>
			</div>

			<div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
				<p x-show="feedback" x-transition.opacity.duration.300ms x-text="feedback" class="text-sm text-emerald-700"></p>
				<p x-show="error" x-text="error" class="text-sm text-rose-700"></p>

				<template x-if="!loading && attendees.length === 0">
					<p class="text-sm text-slate-600">No one has signed in yet.</p>
				</template>

				<ul x-show="attendees.length > 0" class="space-y-2">
					<template x-for="attendee in attendees" :key="`${attendee.id}-${attendee.signed_in_at}`">
						<li class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2">
							<span class="font-medium text-slate-900" x-text="attendee.name"></span>
							<span class="text-xs text-slate-600" x-text="attendee.signed_in_at_label || ''"></span>
						</li>
					</template>
				</ul>
			</div>

			<div class="flex shrink-0 flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-end sm:justify-between">
				<div class="relative w-full sm:max-w-sm">
					<x-input-label for="manual_check_in_user" value="Check in a user" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
					<x-text-input
						id="manual_check_in_user"
						type="search"
						x-model="manualQuery"
						@input="queueManualLookup()"
						@focus="queueManualLookup()"
						class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
						placeholder="Start typing a name"
						autocomplete="off"
					/>
					<p x-show="manualLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Searching...</p>
					<p x-show="manualLookupError" x-text="manualLookupError" class="mt-1 text-xs text-rose-700" x-cloak></p>

					<div
						x-show="manualSuggestions.length > 0"
						x-cloak
						class="absolute bottom-full left-0 z-[120] mb-2 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
					>
						<template x-for="user in manualSuggestions" :key="user.id">
							<button
								type="button"
								@click="manualSignIn(user)"
								:disabled="manualCheckInBusy"
								class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-slate-800 transition hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-60"
							>
								<span x-text="user.name"></span>
								<span class="text-xs font-medium text-emerald-700" x-show="manualCheckInBusy" x-cloak>Checking in...</span>
							</button>
						</template>
					</div>
				</div>
				<button
					type="button"
					@click="signOutEveryone()"
					:disabled="clearBusy"
					class="inline-flex cursor-pointer items-center justify-center rounded-md border border-rose-600 bg-rose-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-rose-400 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-auto"
				>
					Sign Everyone Out
				</button>
			</div>
		</div>
	</div>
</div>
