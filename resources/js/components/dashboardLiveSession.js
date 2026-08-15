export function dashboardLiveSession(config = {}) {
	return {
		dataUrl: config.dataUrl || null,
		sets: [],
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
				this.sets = (Array.isArray(payload?.sets) ? payload.sets : [])
					.filter((set) => set && typeof set === 'object')
					.sort((firstSet, secondSet) => Number(firstSet.order ?? 0) - Number(secondSet.order ?? 0));
			} catch (error) {
				this.errorMessage = error.message || 'Could not refresh the live session.';
			} finally {
				this.loading = false;
			}
		},
			setsForStatus(status) {
				return this.sets.filter((set) => set.status === status);
			},
			statusLabel(status) {
				return {
					playing_now: 'Playing now',
					coming_up: 'Coming up',
					pending: 'Up later',
					finished: 'Finished',
					postponed: 'Postponed',
				}[status] || 'Up later';
			},
			setCardClasses(status) {
				return {
					playing_now: 'border-emerald-700 bg-emerald-950 text-slate-50',
					coming_up: 'border-amber-700 bg-amber-950 text-slate-50',
					pending: 'border-slate-800 bg-slate-900 text-slate-100',
					finished: 'border-slate-700 bg-slate-950 text-slate-300',
					postponed: 'border-rose-900 bg-rose-950/70 text-slate-200',
				}[status] || 'border-slate-800 bg-slate-900 text-slate-100';
			},
			statusTextClasses(status) {
				return {
					playing_now: 'text-emerald-300',
					coming_up: 'text-amber-300',
					pending: 'text-slate-400',
					finished: 'text-slate-400',
					postponed: 'text-rose-300',
				}[status] || 'text-slate-400';
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