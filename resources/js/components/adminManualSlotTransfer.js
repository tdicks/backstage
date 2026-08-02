function normalizeSlot(row) {
	return {
		slotId: Number(row.slot_id),
		slotLabel: row.slot_label || row.slot_key || 'Slot',
		manualPerformerName: row.manual_performer_name || '',
		songTitle: row.song_title || '',
		songArtist: row.song_artist || '',
		setName: row.set_name || '',
		sessionName: row.session_name || '',
		sessionDateLabel: row.session_date_label || '',
		sessionUrl: row.session_url || '#',
		userOptions: Array.isArray(row.user_options) ? row.user_options : [],
		selectedUserId: '',
		status: 'idle',
		message: '',
	};
}

export function adminManualSlotTransfer(config = {}) {
	return {
		open: false,
		loading: false,
		saving: false,
		error: '',
		feedback: '',
		slots: [],
		completed: [],
		dataUrl: config.dataUrl || '',
		applyUrl: config.applyUrl || '',
		csrfToken: config.csrfToken || '',

		async openModal() {
			this.open = true;
			this.error = '';
			this.feedback = '';
			this.completed = [];
			await this.loadSlots();
		},

		closeModal() {
			this.open = false;
			this.error = '';
			this.feedback = '';
		},

		async loadSlots() {
			this.loading = true;
			this.error = '';

			try {
				const response = await fetch(this.dataUrl, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(payload.message || 'Could not load manual slot assignments.');
				}

				this.slots = (payload.slots || []).map((row) => normalizeSlot(row));
			} catch (error) {
				this.error = error?.message || 'Could not load manual slot assignments.';
			} finally {
				this.loading = false;
			}
		},

		hasChanges() {
			return this.slots.some((slot) => slot.selectedUserId !== '');
		},

		pendingChanges() {
			return this.slots
				.filter((slot) => slot.selectedUserId !== '')
				.map((slot) => ({
					slot_id: slot.slotId,
					user_id: Number(slot.selectedUserId),
				}));
		},

		async submit() {
			this.error = '';
			this.feedback = '';

			const changes = this.pendingChanges();

			if (changes.length === 0) {
				this.feedback = 'No changes staged yet.';
				return;
			}

			this.saving = true;

			try {
				const response = await fetch(this.applyUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body: JSON.stringify({ changes }),
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(payload.message || 'Could not save transfer changes.');
				}

				const resultsBySlot = new Map((payload.results || []).map((result) => [Number(result.slot_id), result]));
				const completedEntries = [];
				const errorEntries = new Map();

				(resultsBySlot.values() || []).forEach((result) => {
					if (result.status === 'updated') {
						const row = this.slots.find((slot) => slot.slotId === Number(result.slot_id));

						if (row) {
							completedEntries.push({
								...row,
								assignedUserName: result.assigned_user_name || 'User',
								message: result.message || 'Transferred.',
							});
						}
					}

					if (result.status === 'error' || result.status === 'stale') {
						errorEntries.set(Number(result.slot_id), result.message || 'Could not transfer this slot.');
					}
				});

				this.completed = [...completedEntries, ...this.completed].filter((value, index, array) => {
					return array.findIndex((entry) => entry.slotId === value.slotId) === index;
				});

				this.slots = (payload.slots || []).map((row) => {
					const normalized = normalizeSlot(row);
					const errorMessage = errorEntries.get(normalized.slotId);

					if (errorMessage) {
						normalized.status = 'error';
						normalized.message = errorMessage;
					}

					return normalized;
				});

				const completedCount = completedEntries.length;
				this.feedback = completedCount > 0
					? `${completedCount} slot${completedCount === 1 ? '' : 's'} transferred.`
					: 'No slot transfers were applied.';
			} catch (error) {
				this.error = error?.message || 'Could not save transfer changes.';
			} finally {
				this.saving = false;
			}
		},
	};
}
