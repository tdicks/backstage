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
		manualCheckOutUserId: null,
		checkedInUserIds: new Set(),
		checkedOutUserIds: new Set(),
		manualLookupError: '',
		manualLookupTimer: null,
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
				this.attendees = (payload.attendees || []).filter(attendee => !this.checkedOutUserIds.has(String(attendee.id)));
			} catch (e) {
				this.error = 'Could not load the sign-in list right now.';
			} finally {
				this.loading = false;
			}
		},
		startPolling() {
			this.stopPolling();
			this.pollId = setInterval(() => {
				if (this.open && !this.manualCheckInBusy && this.manualCheckOutUserId === null && !this.clearBusy) {
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
			this.stopPolling();
		},
		markUserCheckedIn(userId) {
			this.checkedInUserIds.add(String(userId));
			setTimeout(() => this.checkedInUserIds.delete(String(userId)), 1400);
		},
		markUserCheckedOut(userId) {
			this.checkedOutUserIds.add(String(userId));
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
					this.error = payload.message || 'Could not sign this user in right now.';

					return;
				}

				this.manualQuery = '';
				this.manualSuggestions = [];
				await this.fetchAttendees();
				this.markUserCheckedIn(user.id);
			} catch (e) {
				this.error = 'Could not sign this user in right now.';
			} finally {
				this.manualCheckInBusy = false;
			}
		},
		async manualSignOut(attendee) {
			if (this.manualCheckOutUserId !== null) {
				return;
			}

			this.error = '';
			this.manualCheckOutUserId = attendee.id;

			try {
				const response = await fetch(@js(route('sessions.check-ins.sign-out', ['jamSession' => $session, 'user' => '__user__'])).replace('__user__', attendee.id), {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
					},
				});

				const payload = await response.json();

				if (!response.ok) {
					this.error = payload.message || 'Could not sign this user out right now.';

					return;
				}

				this.markUserCheckedOut(attendee.id);
				await new Promise(resolve => setTimeout(resolve, 350));
				await this.fetchAttendees();
				this.checkedOutUserIds.delete(String(attendee.id));
			} catch (e) {
				this.error = 'Could not sign this user out right now.';
			} finally {
				this.manualCheckOutUserId = null;
			}
		},
		async signOutEveryone() {
			const confirmed = window.confirm('Sign everyone out for this jam session?');
			if (!confirmed) {
				return;
			}

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

				const checkedOutUserIds = this.attendees.map(attendee => String(attendee.id));
				this.attendees.forEach(attendee => this.markUserCheckedOut(attendee.id));
				await new Promise(resolve => setTimeout(resolve, 350));
				await this.fetchAttendees();
				checkedOutUserIds.forEach(userId => this.checkedOutUserIds.delete(userId));
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
	<div x-show="open" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-[90] bg-black/40" @click="closeModal"></div>
	<div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @keydown.escape.window="closeModal">
		<div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl" @click.stop>
			<div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-6 py-4">
				<div>
					<h3 class="text-lg font-semibold text-slate-900">Who's Here</h3>
					<p class="mt-1 text-sm text-slate-600">{{ $session->name }} ({{ $session->date->format('D, M j, Y') }})</p>
				</div>
				<x-modal-secondary-button type="button" @click="closeModal">Close</x-modal-secondary-button>
			</div>

			<div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
				<p x-show="error" x-text="error" class="text-sm text-rose-700"></p>

				<template x-if="!loading && attendees.length === 0">
					<p class="text-sm text-slate-600">No one has signed in yet.</p>
				</template>

				<ul x-show="attendees.length > 0" class="space-y-2">
					<template x-for="attendee in attendees" :key="`${attendee.id}-${attendee.signed_in_at}`">
						<li
							class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2"
							:class="{
								'who-is-here-check-in': checkedInUserIds.has(String(attendee.id)),
								'who-is-here-check-out': checkedOutUserIds.has(String(attendee.id)),
							}"
						>
							<span class="font-medium text-slate-900" x-text="attendee.name"></span>
							<div class="flex items-center gap-2">
								<span class="text-xs text-slate-600" x-text="attendee.signed_in_at_label || ''"></span>
								<button
									type="button"
									@click="manualSignOut(attendee)"
									:disabled="manualCheckOutUserId !== null"
									class="inline-flex h-7 w-7 items-center justify-center rounded-md text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
									title="Sign out"
									aria-label="Sign out"
								>
									<x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
								</button>
							</div>
						</li>
					</template>
				</ul>
			</div>

			<div class="flex shrink-0 flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-end sm:justify-between">
				<div class="relative w-full sm:max-w-sm">
					<x-input-label for="manual_check_in_user" value="Sign someone in" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
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
								<span class="text-xs font-medium text-emerald-700" x-show="manualCheckInBusy" x-cloak>Signing in...</span>
							</button>
						</template>
					</div>
				</div>
				<div class="h-px w-full shrink-0 bg-slate-200 sm:h-10 sm:w-px" aria-hidden="true"></div>
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
