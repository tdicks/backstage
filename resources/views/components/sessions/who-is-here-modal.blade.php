@props(['session'])

<div
	x-data="{
		open: false,
		attendees: [],
		loading: false,
		clearBusy: false,
		feedback: '',
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
			this.stopPolling();
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

				this.feedback = payload.message || 'Everyone has been signed out.';
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
		<div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl">
			<div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-6 py-4">
				<div>
					<h3 class="text-lg font-semibold text-slate-900">Who's Here</h3>
					<p class="mt-1 text-sm text-slate-600">{{ $session->name }} ({{ $session->date->format('D, M j, Y') }})</p>
				</div>
				<x-modal-secondary-button type="button" @click="closeModal">Close</x-modal-secondary-button>
			</div>

			<div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
				<p x-show="loading" class="text-sm text-slate-600">Loading sign-ins...</p>
				<p x-show="feedback" x-text="feedback" class="text-sm text-emerald-700"></p>
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

			<div class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
				<button
					type="button"
					@click="signOutEveryone()"
					:disabled="clearBusy"
					class="inline-flex cursor-pointer items-center rounded-md border border-rose-600 bg-rose-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-rose-400 disabled:cursor-not-allowed disabled:opacity-50"
				>
					Sign Everyone Out
				</button>
			</div>
		</div>
	</div>
</div>
