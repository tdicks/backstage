export function lazySessionSets(url, activityUrl = null) {
	return {
		loaded: false,
		refreshing: false,
		activityRefreshing: false,
		error: '',
		activityRefreshProvider: null,
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
		refreshOpenSongCards() {
			if (this.hasOpenSongCard()) {
				this.refreshActivity();
			}
		},
		async refresh() {
			if (this.refreshing) {
				return;
			}

			this.refreshing = true;
			this.error = '';

			try {
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
				const container = this.$refs.setsContainer;
				container.innerHTML = html;
				this.loaded = true;

				this.$nextTick(() => {
					if (window.Alpine) {
						window.Alpine.initTree(container);
					}

					window.focusSessionFragmentTarget();
				});
			} catch (e) {
				this.error = 'Could not load sets right now. Refresh the page to try again.';
			} finally {
				this.refreshing = false;
			}
		},
	};
}
