function statusRank(status) {
	if (status === 'playing_now') {
		return 0;
	}

	if (status === 'coming_up') {
		return 1;
	}

	if (status === 'pending') {
		return 2;
	}

	if (status === 'finished') {
		return 3;
	}

	return 4;
}

export function createDashboardLiveState(config = {}) {
	return {
		liveDataUrl: config.liveDataUrl || null,
		liveSessionName: config.liveSessionName || '',
		liveSessionDashboardUrl: config.liveSessionDashboardUrl || null,
		liveSetPanels: [],
		liveSetPanelsLoading: false,
		liveSetPanelsLoaded: false,
		liveSetPanelsError: '',
		liveSessionIsLive: false,
		liveLastUpdated: '',
		pollTimer: null,
		initLiveDashboardState() {
			if (!this.liveDataUrl) {
				return;
			}

			this.refreshLiveParts();
			this.pollTimer = window.setInterval(() => {
				this.refreshLiveParts();
			}, 5000);
		},
		disposeLiveDashboardState() {
			if (this.pollTimer) {
				window.clearInterval(this.pollTimer);
				this.pollTimer = null;
			}
		},
		statusLabel(status) {
			if (status === 'playing_now') {
				return 'Playing now';
			}

			if (status === 'coming_up') {
				return 'Coming up';
			}

			if (status === 'pending') {
				return 'Up later';
			}

			if (status === 'finished') {
				return 'Finished';
			}

			if (status === 'postponed') {
				return 'Postponed';
			}

			return 'Up later';
		},
		statusClasses(status) {
			if (status === 'playing_now') {
				return 'border-emerald-500/40 bg-emerald-900/40 text-emerald-100';
			}

			if (status === 'coming_up') {
				return 'border-amber-500/40 bg-amber-950/50 text-amber-100';
			}

			if (status === 'pending') {
				return 'border-sky-700/50 bg-slate-800/80 text-sky-100';
			}

			return 'border-sky-700/50 bg-slate-800/80 text-sky-100';
		},
		setCardClasses(status, isFeatureSet = false) {
			if (status === 'playing_now') {
				return isFeatureSet
					? 'border border-amber-400/80 bg-emerald-950/45'
					: 'border border-emerald-700/70 bg-emerald-950/45';
			}

			if (status === 'coming_up') {
				return isFeatureSet
					? 'border border-amber-400/80 bg-amber-950/45'
					: 'border border-amber-700/70 bg-amber-950/45';
			}

			if (status === 'pending') {
				return isFeatureSet
					? 'border border-amber-400/80 bg-slate-800/80'
					: 'border border-sky-700/70 bg-slate-800/80';
			}

			return isFeatureSet
				? 'border border-amber-400/80 bg-slate-800/80'
				: 'border border-sky-700/70 bg-slate-800/80';
		},
		songCardClasses(status) {
			if (status === 'playing_now') {
				return 'bg-emerald-950/55 ring-1 ring-emerald-700/60';
			}

			if (status === 'coming_up') {
				return 'bg-amber-950/45 ring-1 ring-amber-700/50';
			}

			if (status === 'pending') {
				return 'bg-slate-800/80 ring-1 ring-sky-700/40';
			}

			return 'bg-slate-800/80 ring-1 ring-sky-700/40';
		},
		slotChipClasses(status) {
			if (status === 'playing_now') {
				return 'bg-emerald-900/70 text-emerald-100 ring-1 ring-emerald-700/60';
			}

			if (status === 'coming_up') {
				return 'bg-amber-900/70 text-amber-100 ring-1 ring-amber-700/55';
			}

			if (status === 'pending') {
				return 'bg-sky-900/50 text-sky-100 ring-1 ring-sky-700/50';
			}

			return 'bg-sky-900/50 text-sky-100 ring-1 ring-sky-700/50';
		},
		textClasses(status) {
			if (status === 'playing_now') {
				return 'text-emerald-50';
			}

			if (status === 'coming_up') {
				return 'text-amber-50';
			}

			return 'text-slate-100';
		},
		secondaryTextClasses(status) {
			if (status === 'playing_now') {
				return 'text-emerald-100';
			}

			if (status === 'coming_up') {
				return 'text-amber-100';
			}

			return 'text-slate-300';
		},
		attachmentButtonClasses(status) {
			if (status === 'playing_now') {
				return 'text-emerald-100 hover:text-emerald-50 focus:ring-emerald-300/60';
			}

			if (status === 'coming_up') {
				return 'text-amber-100 hover:text-amber-50 focus:ring-amber-300/60';
			}

			return 'text-slate-200 hover:text-slate-100 focus:ring-sky-300/60';
		},
		async refreshLiveParts() {
			if (!this.liveDataUrl) {
				this.liveSetPanels = [];
				this.liveSessionIsLive = false;
				return;
			}

			if (!this.liveSetPanelsLoaded) {
				this.liveSetPanelsLoading = true;
			}

			this.liveSetPanelsError = '';

			try {
				const response = await fetch(this.liveDataUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Could not refresh live jam right now.');
				}

				const payload = await response.json();
				const { setPanels, activeKeys } = this.extractLiveParts(payload);
				this.liveSetPanels = setPanels;
				this.liveSessionIsLive = Boolean(payload.is_live);
				this.liveSetPanelsLoaded = true;

				if (payload.updated_at) {
					this.liveLastUpdated = new Date(payload.updated_at).toLocaleTimeString();
				}

				if (this.openAttachments && this.activeAttachmentKey && !activeKeys.has(this.activeAttachmentKey)) {
					this.closeAttachmentsModal(true);
				}
			} catch (error) {
				this.liveSetPanelsError = error.message || 'Could not refresh live jam right now.';
			} finally {
				this.liveSetPanelsLoading = false;
			}
		},
		extractLiveParts(payload) {
			const sets = Array.isArray(payload?.sets) ? payload.sets : [];
			const setPanels = [];
			const activeKeys = new Set();

			for (const set of sets) {
				if (!set || !Number.isInteger(set.id)) {
					continue;
				}

				const setKey = `set:${set.id}`;
				if (Number.isFinite(set.attachments_count)) {
					this.attachmentCounts[setKey] = Math.max(0, Number(set.attachments_count));
				}

				const setPanel = {
					setId: set.id,
					setName: set.name,
					setAttachmentKey: setKey,
					setAttachmentCount: set.attachments_count,
					status: set.status,
					isFeatureSet: Boolean(set.feature_set),
					statusLabel: this.statusLabel(set.status),
					statusRank: statusRank(set.status),
					setOrder: Number.isFinite(set.order) ? Number(set.order) : 0,
					songs: [],
				};

				const songs = Array.isArray(set.songs) ? set.songs : [];
				for (const song of songs) {
					if (!song || song.completed || !Number.isInteger(song.id)) {
						continue;
					}

					const songKey = `song:${song.id}`;
					if (Number.isFinite(song.attachments_count)) {
						this.attachmentCounts[songKey] = Math.max(0, Number(song.attachments_count));
					}

					const songPanel = {
						songId: song.id,
						songName: `${song.artist} - ${song.title}`,
						songAttachmentKey: songKey,
						songAttachmentCount: song.attachments_count,
						slots: [],
					};

					const slots = Array.isArray(song.slots) ? song.slots : [];
					for (const slot of slots) {
						if (!slot || Number(slot.user_id) !== this.currentUserId || !Number.isInteger(slot.id)) {
							continue;
						}

						const slotKey = `slot:${slot.id}`;
						activeKeys.add(slotKey);
						if (Number.isFinite(slot.attachments_count)) {
							this.attachmentCounts[slotKey] = Math.max(0, Number(slot.attachments_count));
						}

						songPanel.slots.push({
							slotId: slot.id,
							slotName: slot.name,
							slotAttachmentKey: slotKey,
							slotAttachmentCount: slot.attachments_count,
						});
					}

					if (songPanel.slots.length > 0) {
						activeKeys.add(songKey);
						setPanel.songs.push(songPanel);
					}
				}

				if (setPanel.songs.length > 0) {
					activeKeys.add(setKey);
					setPanels.push(setPanel);
				}
			}

			setPanels.sort((firstSetPanel, secondSetPanel) => {
				if (firstSetPanel.statusRank !== secondSetPanel.statusRank) {
					return firstSetPanel.statusRank - secondSetPanel.statusRank;
				}

				if (firstSetPanel.setOrder !== secondSetPanel.setOrder) {
					return firstSetPanel.setOrder - secondSetPanel.setOrder;
				}

				return firstSetPanel.setName.localeCompare(secondSetPanel.setName);
			});

			for (const setPanel of setPanels) {
				setPanel.songs.sort((firstSong, secondSong) => firstSong.songName.localeCompare(secondSong.songName));

				for (const songPanel of setPanel.songs) {
					songPanel.slots.sort((firstSlot, secondSlot) => firstSlot.slotName.localeCompare(secondSlot.slotName));
				}
			}

			return { setPanels, activeKeys };
		},
	};
}
