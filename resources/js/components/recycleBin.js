export function recycleBinPage(config) {
	return {
		loading: true,
		error: '',
		sets: [],
		sessions: [],
		restoreSessionOptions: [],
		showOverviewPrompt: false,
		overviewItemType: null,
		overviewItem: null,
		showRestorePrompt: false,
		restorePromptType: null,
		restorePromptItemId: null,
		restorePromptItemName: '',
		restorePromptParentSessionName: '',
		restoreTargetSessionId: '',
		restoreSelectedSetIds: [],
		restoreAsHidden: false,
		clearSlotAssignments: false,
		restoring: {
			sets: {},
			sessions: {},
		},

		async init() {
			await this.refresh();
		},

		async refresh() {
			this.loading = true;
			this.error = '';

			try {
				const response = await fetch(config.listUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Could not load recycle bin items.');
				}

				const payload = await response.json();
				this.sets = payload.sets || [];
				this.sessions = payload.sessions || [];
				this.restoreSessionOptions = payload.restore_session_options || [];
				this.$store.recycleBin.setCount(Number(payload.count || 0));
			} catch (e) {
				this.error = e?.message || 'Could not load recycle bin items.';
			} finally {
				this.loading = false;
			}
		},

		openOverview(type, item) {
			if (!item?.id || (type !== 'set' && type !== 'session')) {
				return;
			}

			this.overviewItemType = type;
			this.overviewItem = item;
			this.showOverviewPrompt = true;
			window.dispatchEvent(new CustomEvent('open-modal', {
				detail: 'recycle-bin-item-overview',
			}));
		},

		closeOverview() {
			this.showOverviewPrompt = false;
			window.dispatchEvent(new CustomEvent('close-modal', {
				detail: 'recycle-bin-item-overview',
			}));
		},

		resetOverviewState() {
			this.showOverviewPrompt = false;
			this.overviewItemType = null;
			this.overviewItem = null;
		},

		openRestorePrompt(type, item) {
			if (!item?.id || (type !== 'set' && type !== 'session')) {
				return;
			}

			if (type === 'set' && this.isSetRestoring(item.id)) {
				return;
			}

			if (type === 'session' && this.isSessionRestoring(item.id)) {
				return;
			}

			this.restorePromptType = type;
			this.restorePromptItemId = Number(item.id);
			this.restorePromptItemName = item.name || '';
			this.restorePromptParentSessionName = type === 'set' ? (item.session_name || '') : '';
			this.restoreTargetSessionId = type === 'set' ? this.defaultRestoreSessionIdFor(item) : '';
			this.restoreSelectedSetIds = type === 'session'
				? (item.deleted_sets || []).map((set) => String(set.id))
				: [];

			this.restoreAsHidden = false;
			this.clearSlotAssignments = false;
			this.showRestorePrompt = true;
			window.dispatchEvent(new CustomEvent('open-modal', {
				detail: 'recycle-bin-restore-item',
			}));
		},

		closeRestorePrompt() {
			this.showRestorePrompt = false;
			window.dispatchEvent(new CustomEvent('close-modal', {
				detail: 'recycle-bin-restore-item',
			}));
		},

		resetRestorePromptState() {
			this.showRestorePrompt = false;
			this.restorePromptType = null;
			this.restorePromptItemId = null;
			this.restorePromptItemName = '';
			this.restorePromptParentSessionName = '';
			this.restoreTargetSessionId = '';
			this.restoreSelectedSetIds = [];
			this.restoreAsHidden = false;
			this.clearSlotAssignments = false;
		},

		handleModalClosed(name) {
			if (name === 'recycle-bin-item-overview') {
				this.resetOverviewState();
			}

			if (name === 'recycle-bin-restore-item') {
				this.resetRestorePromptState();
			}
		},

		defaultRestoreSessionIdFor(item) {
			const matchingOption = this.restoreSessionOptions.find((option) => Number(option.id) === Number(item.session_id));

			if (!matchingOption || matchingOption.disabled) {
				return '';
			}

			return String(matchingOption.id);
		},

		async confirmRestore() {
			if (!this.restorePromptType || !this.restorePromptItemId) {
				return;
			}

			if (this.restorePromptType === 'set') {
				await this.restoreSet(this.restorePromptItemId, {
					restoreAsHidden: this.restoreAsHidden,
					clearSlotAssignments: this.clearSlotAssignments,
				});
			}

			if (this.restorePromptType === 'session') {
				await this.restoreSession(this.restorePromptItemId, {
					restoreAsHidden: this.restoreAsHidden,
					selectedSetIds: this.restoreSelectedSetIds,
				});
			}
		},

		async restoreSet(setId, { restoreAsHidden = false, clearSlotAssignments = false } = {}) {
			if (this.isSetRestoring(setId)) {
				return;
			}

			const key = String(setId);
			this.restoring.sets[key] = true;
			this.error = '';

			try {
				const response = await fetch(config.restoreSetUrlTemplate.replace('__SET_ID__', String(setId)), {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': config.csrfToken,
					},
					body: JSON.stringify({
						restore_as_hidden: Boolean(restoreAsHidden),
						clear_slot_assignments: Boolean(clearSlotAssignments),
						jam_session_id: this.restoreTargetSessionId ? Number(this.restoreTargetSessionId) : null,
					}),
				});

				if (!response.ok) {
					const payload = await response.json().catch(() => ({}));
					throw new Error(payload?.message || 'Could not restore set.');
				}

				const payload = await response.json();
				this.$store.recycleBin.setCount(Number(payload.count || 0));
				this.closeRestorePrompt();
				await this.refresh();
			} catch (e) {
				this.error = e?.message || 'Could not restore set.';
			} finally {
				this.restoring.sets[key] = false;
			}
		},

		async restoreSession(sessionId, { restoreAsHidden = false, selectedSetIds = [] } = {}) {
			if (this.isSessionRestoring(sessionId)) {
				return;
			}

			const key = String(sessionId);
			this.restoring.sessions[key] = true;
			this.error = '';

			try {
				const response = await fetch(config.restoreSessionUrlTemplate.replace('__SESSION_ID__', String(sessionId)), {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': config.csrfToken,
					},
					body: JSON.stringify({
						restore_as_hidden: Boolean(restoreAsHidden),
						selected_set_ids: (selectedSetIds || []).map((id) => Number(id)),
					}),
				});

				if (!response.ok) {
					const payload = await response.json().catch(() => ({}));
					throw new Error(payload?.message || 'Could not restore jam session.');
				}

				const payload = await response.json();
				this.$store.recycleBin.setCount(Number(payload.count || 0));
				this.closeRestorePrompt();
				await this.refresh();
			} catch (e) {
				this.error = e?.message || 'Could not restore jam session.';
			} finally {
				this.restoring.sessions[key] = false;
			}
		},

		isSetRestoring(setId) {
			return this.restoring.sets[String(setId)] === true;
		},

		isSessionRestoring(sessionId) {
			return this.restoring.sessions[String(sessionId)] === true;
		},

		get confirmRestoreDisabled() {
			if (!this.restorePromptType || !this.restorePromptItemId) {
				return true;
			}

			if (this.restorePromptType === 'set') {
				return this.isSetRestoring(this.restorePromptItemId) || !this.restoreTargetSessionId;
			}

			if (this.restorePromptType === 'session') {
				return this.isSessionRestoring(this.restorePromptItemId);
			}

			return true;
		},

		get hasSessionDeletedSets() {
			return this.sessions.some((session) => (session.deleted_sets || []).length > 0);
		},

		get overviewTitle() {
			if (!this.overviewItem) {
				return 'Item Overview';
			}

			return this.overviewItemType === 'session'
				? `${this.overviewItem.name} Overview`
				: `${this.overviewItem.name} Overview`;
		},

		get hasRestoreSessionOptions() {
			return this.restoreSessionOptions.length > 0;
		},

		get hasSessionRestoreChoices() {
			return this.restorePromptType === 'session' && (this.currentRestoreSessionSets.length > 0);
		},

		get currentRestoreSessionSets() {
			if (this.restorePromptType !== 'session') {
				return [];
			}

			return this.sessions.find((session) => Number(session.id) === Number(this.restorePromptItemId))?.deleted_sets || [];
		},

		get hasItems() {
			return this.sets.length > 0 || this.sessions.length > 0;
		},
	};
}
