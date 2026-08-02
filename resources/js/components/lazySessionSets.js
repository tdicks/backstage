export function lazySessionSets(url, activityUrl = null, options = {}) {
	return {
		loaded: false,
		refreshing: false,
		backgroundRefreshing: false,
		activityRefreshing: false,
		error: '',
		filterQuery: '',
		filterMenuOpen: false,
		selectedAttributeFilters: [],
		visibleSetCount: 0,
		totalSetCount: 0,
		currentUserId: options.currentUserId ? String(options.currentUserId) : '',
		filterOptions: [
			{ key: 'my_sets', label: 'My sets' },
			{ key: 'collaborating', label: "Sets I'm collaborating on" },
			{ key: 'performing_on', label: "Set's I'm performing on" },
			{ key: 'planned', label: 'Planned' },
			{ key: 'performed', label: 'Performed' },
			{ key: 'signups_open', label: 'Sign ups open' },
			{ key: 'signups_closed', label: 'Sign ups closed' },
			{ key: 'hidden', label: 'Hidden' },
			{ key: 'free_for_all', label: 'Free for all mode' },
			{ key: 'has_attachments', label: 'Has attachments' },
		],
		summarySearchCache: {},
		summarySearchPendingByQuery: {},
		summarySearchLoading: false,
		summarySearchQuery: '',
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
		setUiState(root = null, setIds = null) {
			const source = root || this.$refs.setsContainer;

			if (!source) {
				return {};
			}

			const state = {};
			const setCards = source.querySelectorAll('[data-session-set-card][data-set-id]');
			const setIdFilter = Array.isArray(setIds) && setIds.length > 0
				? new Set(setIds.map((setId) => String(setId)))
				: null;

			setCards.forEach((setCard) => {
				const setId = setCard.dataset.setId;

				if (!setId) {
					return;
				}

				if (setIdFilter && !setIdFilter.has(String(setId))) {
					return;
				}

				state[setId] = {
					isSetOpen: setCard.dataset.setOpen === 'true',
					isSongRequestsOpen: setCard.dataset.songRequestsOpen === 'true',
				};
			});

			return state;
		},
		restoreSetUiState(state = {}) {
			Object.entries(state).forEach(([setId, cardState]) => {
				window.dispatchEvent(new CustomEvent('session-set-restore-state', {
					detail: {
						setId,
						setCollapsed: cardState.isSetOpen !== true,
						songRequestsCollapsed: cardState.isSongRequestsOpen !== true,
					},
				}));
			});
		},
		forceOpenSetCards(setIds = []) {
			setIds.forEach((setId) => {
				const setCard = this.$refs.setsContainer?.querySelector(`[data-session-set-card][data-set-id="${setId}"]`);
				if (!setCard || setCard.dataset.setOpen === 'true') {
					return;
				}

				const toggleButton = setCard.querySelector('[aria-label="Toggle set details"]');
				if (toggleButton) {
					toggleButton.click();
				}
			});
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
			if (document.hidden || this.hasOpenSessionModal() || this.hasFocusedSetFormControl() || this.hasOpenSessionActionMenu() || this.hasActiveFilters()) {
				return false;
			}

			return true;
		},
		hasActiveFilters() {
			return this.filterQuery.trim().length > 0
				|| this.selectedAttributeFilters.length > 0;
		},
		selectedFilterLabel() {
			if (!this.selectedAttributeFilters.length) {
				return 'Filter';
			}

			if (this.selectedAttributeFilters.length === 1) {
				const selectedKey = this.selectedAttributeFilters[0];
				return this.filterOptions.find((option) => option.key === selectedKey)?.label || 'Filter';
			}

			return `Filter (${this.selectedAttributeFilters.length})`;
		},
		matchesNonTextFilters(setCard) {
			if (this.selectedAttributeFilters.length === 0) {
				return true;
			}

			const isPerformed = setCard.dataset.setPerformed === '1';
			const isSignupsOpen = setCard.dataset.setSignupsOpen === '1';
			const isHidden = setCard.dataset.setHidden === '1';
			const isFreeForAll = setCard.dataset.setFreeForAll === '1';
			const hasAttachments = setCard.dataset.setHasAttachments === '1';
			const isOwnedByCurrentUser = this.currentUserId !== '' && String(setCard.dataset.setOwnerId || '') === this.currentUserId;
			const isCollaborating = setCard.dataset.setCollaborating === '1';
			const isPerformingOnSet = setCard.dataset.setPerforming === '1';

			return this.selectedAttributeFilters.some((filterKey) => {
				switch (filterKey) {
					case 'my_sets':
						return isOwnedByCurrentUser;
					case 'collaborating':
						return isCollaborating;
					case 'performing_on':
						return isPerformingOnSet;
					case 'planned':
						return !isPerformed;
					case 'performed':
						return isPerformed;
					case 'signups_open':
						return isSignupsOpen;
					case 'signups_closed':
						return !isSignupsOpen;
					case 'hidden':
						return isHidden;
					case 'free_for_all':
						return isFreeForAll;
					case 'has_attachments':
						return hasAttachments;
					default:
						return false;
				}
			});
		},
		textHaystack(setCard) {
			return `${setCard.dataset.setName || ''} ${setCard.dataset.setOwnerName || ''} ${setCard.dataset.setParticipants || ''}`;
		},
		setSummaryText(setId) {
			return this.summarySearchCache[String(setId)] || '';
		},
		matchesSongSummary(setCard, query) {
			if (!query) {
				return false;
			}

			const setId = String(setCard.dataset.setId || '');
			if (!setId) {
				return false;
			}

			return this.setSummaryText(setId).includes(query);
		},
		collectPendingSummarySearchCards(query, setCards) {
			if (!query || query.length < 2) {
				this.summarySearchPendingByQuery = {};
				return;
			}

			const pendingBySetId = {};
			setCards.forEach((setCard) => {
				if (!this.matchesNonTextFilters(setCard)) {
					return;
				}

				if (this.textHaystack(setCard).includes(query) || this.matchesSongSummary(setCard, query)) {
					return;
				}

				const setId = String(setCard.dataset.setId || '');
				const summaryUrl = setCard.dataset.setSummaryUrl;
				if (!setId || !summaryUrl) {
					return;
				}

				if (this.setSummaryText(setId) !== '') {
					return;
				}

				pendingBySetId[setId] = summaryUrl;
			});

			this.summarySearchPendingByQuery = pendingBySetId;
		},
		summaryTextFromPayload(payload) {
			const songs = payload?.songs || [];
			return songs
				.map((song) => `${song.artist || ''} ${song.title || ''}`.trim())
				.filter(Boolean)
				.join(' ')
				.toLowerCase();
		},
		async fetchSummaryForSearch(setId, summaryUrl) {
			try {
				const response = await fetch(summaryUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					return false;
				}

				const payload = await response.json();
				this.summarySearchCache = {
					...this.summarySearchCache,
					[String(setId)]: this.summaryTextFromPayload(payload),
				};

				return true;
			} catch (e) {
				return false;
			}
		},
		async runSummarySearch(query) {
			if (this.summarySearchLoading || !query || query.length < 2) {
				return;
			}

			this.summarySearchLoading = true;
			this.summarySearchQuery = query;

			try {
				const entries = Object.entries(this.summarySearchPendingByQuery || {});
				for (const [setId, summaryUrl] of entries) {
					if (this.summarySearchQuery !== query || this.filterQuery.trim().toLowerCase() !== query) {
						break;
					}

					await this.fetchSummaryForSearch(setId, summaryUrl);
					this.applyFilters();
				}
			} finally {
				this.summarySearchLoading = false;
			}
		},
		matchesFilters(setCard) {
			const query = this.filterQuery.trim().toLowerCase();

			if (!this.matchesNonTextFilters(setCard)) {
				return false;
			}

			if (!query) {
				return true;
			}

			if (this.textHaystack(setCard).includes(query)) {
				return true;
			}

			return this.matchesSongSummary(setCard, query);

		},
		applyFilters() {
			const query = this.filterQuery.trim().toLowerCase();
			const setCards = Array.from(this.$refs.setsContainer?.querySelectorAll('[data-session-set-card][data-set-id]') || []);
			this.totalSetCount = setCards.length;
			let visibleCount = 0;

			setCards.forEach((setCard) => {
				const visible = this.matchesFilters(setCard);
				setCard.classList.toggle('hidden', !visible);
				if (visible) {
					visibleCount++;
				}
			});

			this.visibleSetCount = visibleCount;
			this.collectPendingSummarySearchCards(query, setCards);
			this.runSummarySearch(query);
		},
		clearFilters() {
			this.filterQuery = '';
			this.selectedAttributeFilters = [];
			this.filterMenuOpen = false;
			this.applyFilters();
		},
		openSongIds() {
			return Array.from(this.$refs.setsContainer?.querySelectorAll('[data-session-set-card][data-set-open="true"]:not(.hidden) [data-session-song-card][data-song-open="true"]') || [])
				.map((card) => card.dataset.songId)
				.filter(Boolean);
		},
		activitySongIds() {
			const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

			return Array.from(this.$refs.setsContainer?.querySelectorAll('[data-session-set-card][data-set-open="true"]:not(.hidden) [data-session-song-card][data-song-open="true"]') || [])
				.filter((card) => {
					const { top, bottom } = card.getBoundingClientRect();

					return bottom >= -viewportHeight && top <= viewportHeight * 2;
				})
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
				this.activitySongIds().forEach((songId) => params.append('song_ids[]', songId));

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
			if (this.hasOpenSessionModal() || this.hasOpenSessionActionMenu()) {
				return;
			}

			Object.entries(songs).forEach(([songId, song]) => {
				const container = this.$refs.setsContainer?.querySelector(`[data-song-slots-body][data-song-slots-id="${songId}"]`);

				if (!container || typeof song.slots_html !== 'string') {
					return;
				}

				this.patchSlotRows(container, song.slots_html);
			});
		},
		patchSlotRows(container, nextSlotsHtml) {
			const templateBody = document.createElement('tbody');
			templateBody.innerHTML = nextSlotsHtml;

			const nextRows = Array.from(templateBody.querySelectorAll('[data-slot-id]'));
			if (nextRows.length === 0) {
				if (container.innerHTML !== nextSlotsHtml) {
					container.innerHTML = nextSlotsHtml;

					this.$nextTick(() => {
						if (window.Alpine) {
							window.Alpine.initTree(container);
						}
					});
				}

				return;
			}

			const nextSlotIds = new Set(nextRows.map((row) => String(row.dataset.slotId || '')).filter(Boolean));

			Array.from(container.querySelectorAll(':scope > [data-slot-id]')).forEach((existingRow) => {
				const existingSlotId = String(existingRow.dataset.slotId || '');
				if (existingSlotId !== '' && !nextSlotIds.has(existingSlotId)) {
					existingRow.remove();
				}
			});

			let pointer = container.firstElementChild;
			const insertedOrReplacedRows = [];

			nextRows.forEach((nextRow) => {
				const slotId = String(nextRow.dataset.slotId || '');
				if (slotId === '') {
					return;
				}

				const nextRowHtml = nextRow.outerHTML;
				const matchingRowAtPointer = pointer && pointer.matches('[data-slot-id]') && String(pointer.dataset.slotId || '') === slotId
					? pointer
					: null;

				if (matchingRowAtPointer) {
					if (matchingRowAtPointer.outerHTML !== nextRowHtml) {
						const replacementRow = nextRow.cloneNode(true);
						matchingRowAtPointer.replaceWith(replacementRow);
						insertedOrReplacedRows.push(replacementRow);
						pointer = replacementRow.nextElementSibling;
					} else {
						pointer = matchingRowAtPointer.nextElementSibling;
					}

					return;
				}

				const existingRow = container.querySelector(`:scope > [data-slot-id="${slotId}"]`);
				if (!existingRow) {
					const insertedRow = nextRow.cloneNode(true);
					container.insertBefore(insertedRow, pointer);
					insertedOrReplacedRows.push(insertedRow);
					return;
				}

				if (existingRow.outerHTML !== nextRowHtml) {
					const replacementRow = nextRow.cloneNode(true);
					existingRow.replaceWith(replacementRow);
					container.insertBefore(replacementRow, pointer);
					insertedOrReplacedRows.push(replacementRow);
					return;
				}

				container.insertBefore(existingRow, pointer);
			});

			if (insertedOrReplacedRows.length > 0) {
				this.$nextTick(() => {
					if (!window.Alpine) {
						return;
					}

					insertedOrReplacedRows.forEach((row) => window.Alpine.initTree(row));
				});
			}
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
			const preserveSetIds = Array.isArray(options.preserveSetIds)
				? options.preserveSetIds.map((setId) => String(setId)).filter(Boolean)
				: [];
			const forceOpenSetIds = Array.isArray(options.forceOpenSetIds)
				? options.forceOpenSetIds.map((setId) => String(setId)).filter(Boolean)
				: [];

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
				const previousUiState = this.setUiState(null, preserveSetIds);

				forceOpenSetIds.forEach((setId) => {
					const setStorageKey = this.currentUserId !== ''
						? `backstage:u${this.currentUserId}:set:${setId}`
						: '';

					if (setStorageKey !== '') {
						window.localStorage.setItem(setStorageKey, '0');
					}

					const existingState = previousUiState[setId];
					if (existingState) {
						previousUiState[setId] = {
							...existingState,
							isSetOpen: true,
						};
						return;
					}

					const setCard = this.$refs.setsContainer?.querySelector(`[data-session-set-card][data-set-id="${setId}"]`);
					if (!setCard) {
						return;
					}

					previousUiState[setId] = {
						isSetOpen: true,
						isSongRequestsOpen: setCard.dataset.songRequestsOpen === 'true',
					};
				});
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
				const targetedSetIds = new Set(preserveSetIds);
				const replacedSetCards = [];

				if (targetedSetIds.size > 0) {
					targetedSetIds.forEach((setId) => {
						const currentCard = container.querySelector(`[data-session-set-card][data-set-id="${setId}"]`);
						if (!currentCard) {
							return;
						}

						const nextCard = nextRoot.querySelector(`[data-session-set-card][data-set-id="${setId}"]`);
						if (!nextCard) {
							currentCard.remove();
							return;
						}

						if (currentCard.outerHTML === nextCard.outerHTML) {
							return;
						}

						const replacementCard = nextCard.cloneNode(true);
						currentCard.replaceWith(replacementCard);
						replacedSetCards.push(replacementCard);
					});

					if (replacedSetCards.length === 0) {
						container.innerHTML = html;
					}
				} else {
					container.innerHTML = html;
				}
				this.loaded = true;

				this.$nextTick(() => {
					if (window.Alpine) {
						if (replacedSetCards.length > 0) {
							replacedSetCards.forEach((card) => window.Alpine.initTree(card));
						} else {
							window.Alpine.initTree(container);
						}
					}

					window.requestAnimationFrame(() => {
						this.restoreSetUiState(previousUiState);

						if (forceOpenSetIds.length > 0) {
							window.requestAnimationFrame(() => this.forceOpenSetCards(forceOpenSetIds));
						}

						this.applyFilters();

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
