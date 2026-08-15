export function dashboardLiveSession(config = {}) {
	return {
		dataUrl: config.dataUrl || null,
		playingNow: null,
		comingUp: null,
		loading: false,
		errorMessage: '',
		pollTimer: null,
		init() {
			if (!this.dataUrl) {
				return;
			}

			this.refresh();
			this.pollTimer = window.setInterval(() => this.refresh(), 5000);
		},
		destroy() {
			if (this.pollTimer) {
				window.clearInterval(this.pollTimer);
				this.pollTimer = null;
			}
		},
		async refresh() {
			this.loading = true;
			this.errorMessage = '';

			try {
				const response = await fetch(this.dataUrl, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Could not refresh the live session.');
				}

				const payload = await response.json();
				const sets = Array.isArray(payload?.sets) ? payload.sets : [];
				this.playingNow = sets.find((set) => set?.status === 'playing_now') || null;
				this.comingUp = sets.find((set) => set?.status === 'coming_up') || null;
			} catch (error) {
				this.errorMessage = error.message || 'Could not refresh the live session.';
			} finally {
				this.loading = false;
			}
		},
		setDetail(set) {
			if (!set) {
				return '';
			}

			const songs = Array.isArray(set.songs) ? set.songs : [];
			const nextSong = songs.find((song) => !song?.completed);

			return nextSong ? `${nextSong.artist} - ${nextSong.title}` : '';
		},
	};
}