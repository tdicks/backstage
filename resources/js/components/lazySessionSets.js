export function lazySessionSets(url, activityUrl = null) {
	return {
		loaded: false,
		refreshing: false,
		backgroundRefreshing: false,
		activityRefreshing: false,
		error: '',
		activityRefreshProvider: null,
		fragmentFocusApplied: false,
		async init() {
			this.activityRefreshProvider = () => this.refreshActivity();

			if (activityUrl && this.$store?.approvals) {
				this.$store.approvals.useRefreshProvider(this.activityRefreshProvider);
			}

			await this.refresh();
		},
		destroy() {
			this.$store?.approvals?.clearRefreshProvider(this.activityRefreshProvider);
		},
		setState(root = null) {
			const source = root || this.$refs.setsContainer;

			if (!source) {
				return {};
			}

			const state = {};
			const setCards = source.querySelectorAll('[data-session-set-card][data-set-id]');

			setCards.forEach((setCard) => {
				const setId = setCard.dataset.setId;

				if (!setId) {
					return;
				}

				state[setId] = {
					songIds: Array.from(setCard.querySelectorAll('[data-session-song-card][data-song-id]')).map((songCard) => songCard.dataset.songId).filter(Boolean),
					pendingRequestIds: Array.from(setCard.querySelectorAll('[data-song-request-id]')).map((requestRow) => requestRow.dataset.songRequestId).filter(Boolean),
				};
			});

			return state;
		},
		externalApprovalTransitions(previousState, nextState) {
			const transitions = [];

			Object.keys(previousState).forEach((setId) => {
				if (!nextState[setId]) {
					return;
				}

				const previousRequests = new Set(previousState[setId].pendingRequestIds || []);
				const nextRequests = new Set(nextState[setId].pendingRequestIds || []);
				const previousSongs = new Set(previousState[setId].songIds || []);
				const nextSongs = new Set(nextState[setId].songIds || []);

				const resolvedRequestIds = Array.from(previousRequests).filter((requestId) => !nextRequests.has(requestId));
				const newSongIds = Array.from(nextSongs).filter((songId) => !previousSongs.has(songId));

				if (resolvedRequestIds.length > 0 && newSongIds.length > 0) {
					transitions.push({ setId, resolvedRequestIds, newSongIds });
				}
			});

			return transitions;
		},
		async animateResolvedRequests(transitions) {
			const rows = [];

			transitions.forEach((transition) => {
				transition.resolvedRequestIds.forEach((requestId) => {
					const row = this.$refs.setsContainer?.querySelector(`[data-session-set-card][data-set-id="${transition.setId}"] [data-song-request-id="${requestId}"]`);

					if (!row) {
						return;
					}

					row.classList.add('transition-all', 'duration-300', 'ease-in', 'opacity-0', '-translate-y-2', 'scale-[0.98]');
					rows.push(row);
				});
			});

			if (rows.length > 0) {
				await new Promise((resolve) => window.setTimeout(resolve, 280));
			}
		},
		highlightNewSongs(transitions) {
			transitions.forEach((transition) => {
				transition.newSongIds.forEach((songId) => {
					const songCard = this.$refs.setsContainer?.querySelector(`[data-session-set-card][data-set-id="${transition.setId}"] [data-session-song-card][data-song-id="${songId}"]`);

					if (!songCard) {
						return;
					}

					songCard.classList.add('transition-all', 'duration-700', 'ease-out', 'ring-2', 'ring-amber-300', 'bg-amber-50/90', '-translate-y-1', 'shadow-md');

					window.setTimeout(() => {
						songCard.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/90', '-translate-y-1', 'shadow-md');
					}, 1400);
				});
			});
		},
		canBackgroundRefreshSets() {
			if (document.hidden || this.hasOpenSessionModal() || this.hasFocusedSetFormControl() || this.hasOpenSessionActionMenu()) {
				return false;
			}

			return true;
		},
		openSongIds() {
			return Array.from(this.$refs.setsContainer?.querySelectorAll('[data-session-set-card][data-set-open="true"] [data-session-song-card][data-song-open="true"]') || [])
				.map((card) => card.dataset.songId)
				.filter(Boolean);
		},
		hasOpenSongCard() {
			return this.openSongIds().length > 0;
		},
		async refreshActivity() {
			if (!activityUrl || this.activityRefreshing) {
				return {
					count: this.$store?.approvals?.count,
				};
			}

			this.activityRefreshing = true;

			try {
				const params = new URLSearchParams();
				this.openSongIds().forEach((songId) => params.append('song_ids[]', songId));

				const activityResponse = await fetch(`${activityUrl}?${params.toString()}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!activityResponse.ok) {
					return null;
				}

				const payload = await activityResponse.json();
				this.patchOpenSongSlots(payload.songs || {});

				if (this.canBackgroundRefreshSets()) {
					await this.refresh({ source: 'provider', background: true });
				}

				return {
					count: payload.approval_count,
				};
			} catch (e) {
				return null;
			} finally {
				this.activityRefreshing = false;
			}
		},
		patchOpenSongSlots(songs) {
			if (this.hasOpenSessionModal()) {
				return;
			}

			Object.entries(songs).forEach(([songId, song]) => {
				const container = this.$refs.setsContainer?.querySelector(`[data-song-slots-body][data-song-slots-id="${songId}"]`);

				if (!container || typeof song.slots_html !== 'string') {
					return;
				}

				container.innerHTML = song.slots_html;

				this.$nextTick(() => {
					if (window.Alpine) {
						window.Alpine.initTree(container);
					}
				});
			});
		},
		hasOpenSessionModal() {
			return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((modal) => window.getComputedStyle(modal).display !== 'none');
		},
		hasFocusedSetFormControl() {
			const activeElement = document.activeElement;

			if (!activeElement || !this.$refs.setsContainer?.contains(activeElement)) {
				return false;
			}

			return ['INPUT', 'SELECT', 'TEXTAREA'].includes(activeElement.tagName);
		},
		hasOpenSessionActionMenu() {
			return Array.from(document.querySelectorAll('[data-session-action-menu]')).some((menu) => window.getComputedStyle(menu).display !== 'none');
		},
		refreshOpenSongCards() {
			if (this.hasOpenSongCard()) {
				this.refreshActivity();
			}
		},
		async refresh(options = {}) {
			const isBackground = options.background === true;

			if (this.refreshing || this.backgroundRefreshing) {
				return;
			}

			if (isBackground) {
				this.backgroundRefreshing = true;
			} else {
				this.refreshing = true;
				this.error = '';
			}

			try {
				const previousState = this.setState();
				let transitions = [];

				const response = await fetch(url, {
					headers: {
						'Accept': 'text/html',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Failed to load session sets');
				}

				const html = await response.text();
				const nextRoot = document.createElement('div');
				nextRoot.innerHTML = html;
				const nextState = this.setState(nextRoot);
				transitions = this.externalApprovalTransitions(previousState, nextState);

				if (isBackground && transitions.length > 0) {
					await this.animateResolvedRequests(transitions);
				}

				const container = this.$refs.setsContainer;
				container.innerHTML = html;
				this.loaded = true;

				this.$nextTick(() => {
					if (window.Alpine) {
						window.Alpine.initTree(container);
					}

					if (isBackground && transitions.length > 0) {
						this.highlightNewSongs(transitions);
					}

					if (!this.fragmentFocusApplied && window.location.hash) {
						window.focusSessionFragmentTarget();
						this.fragmentFocusApplied = true;
					}

					if (!window.location.hash) {
						this.fragmentFocusApplied = true;
					}
				});
			} catch (e) {
				if (!isBackground) {
					this.error = 'Could not load sets right now. Refresh the page to try again.';
				}
			} finally {
				if (isBackground) {
					this.backgroundRefreshing = false;
				} else {
					this.refreshing = false;
				}
			}
		},
	};
}
