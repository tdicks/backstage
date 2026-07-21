export function registerSessionCards(Alpine) {
    Alpine.data('sessionSetCard', sessionSetCard);
    Alpine.data('sessionSongCard', sessionSongCard);
    Alpine.data('sessionSlotRow', sessionSlotRow);
    Alpine.data('sessionSongRequestRow', sessionSongRequestRow);
}

function baseDragState() {
    return {
        isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,
    };
}

export function sessionSetCard(config) {
    return {
        openSong: false,
        openSongRequest: false,
        songArtistQuery: '',
        songTitleQuery: '',
        selectedArtistName: '',
        selectedDeezerDuration: null,
        deezerTitleSelected: false,
        artistSuggestions: [],
        titleSuggestions: [],
        artistLookupBusy: false,
        titleLookupBusy: false,
        artistLookupError: '',
        titleLookupError: '',
        showArtistSuggestions: false,
        showTitleSuggestions: false,
        artistLookupTimer: null,
        titleLookupTimer: null,
        artistLookupToken: 0,
        titleLookupToken: 0,
        requestArtistQuery: '',
        requestTitleQuery: '',
        requestSelectedArtistName: '',
        requestArtistSuggestions: [],
        requestTitleSuggestions: [],
        requestArtistLookupBusy: false,
        requestTitleLookupBusy: false,
        requestArtistLookupError: '',
        requestTitleLookupError: '',
        showRequestArtistSuggestions: false,
        showRequestTitleSuggestions: false,
        requestArtistLookupTimer: null,
        requestTitleLookupTimer: null,
        requestArtistLookupToken: 0,
        requestTitleLookupToken: 0,
        requestSongBusy: false,
        requestSongError: '',
        artistLookupUrl: config.artistLookupUrl,
        titleLookupUrl: config.titleLookupUrl,
        songRequestStoreUrl: config.songRequestStoreUrl,
        openSetEdit: false,
        openCollaborators: false,
        collaboratorsList: config.initialCollaborators ?? [],
        collaboratorNames: (config.initialCollaborators ?? []).map((c) => c.name),
        collaboratorQuery: '',
        collaboratorSuggestions: [],
        collaboratorLookupBusy: false,
        collaboratorLookupTimer: null,
        showCollaboratorSuggestions: false,
        collaboratorSaveError: '',
        collaboratorSaveBusy: false,
        openSummary: false,
        summaryData: null,
        summaryLoading: false,
        summaryLoaded: false,
        summaryError: '',
        summaryLastUpdated: '',
        summaryPollId: null,
        setCollapsed: false,
        songRequestsCollapsed: false,
        setId: config.setId,
        songRequestsPendingCount: config.initialSongRequestsPendingCount,
        setKey: config.setKey,
        songRequestsKey: config.songRequestsKey,
        canReorderSongs: config.canReorderSongs,
        setLocked: config.setLocked,
        initialSetPerformed: config.initialSetPerformed,
        performedDraft: config.performedDraft,
        initialSongRequestsEnabled: config.initialSongRequestsEnabled,
        songRequestsDraft: config.songRequestsDraft,
        initialFreeForAll: config.initialFreeForAll,
        freeForAllDraft: config.freeForAllDraft,
        reorderBusy: false,
        reorderError: '',
        reorderFeedback: '',
        addSongBusy: false,
        addSongError: '',
        shareCopied: false,
        directLinkCopied: false,
        openActionMenu: false,
        dragSongId: null,
        draggingSongId: null,
        dropTargetSongId: null,
        dropTargetPosition: 'before',
        dropPlaceholderEl: null,
        ...baseDragState(),
        hasOpenDragBlockingModal() {
            return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((el) => window.getComputedStyle(el).display !== 'none');
        },
        canDragSongs() {
            return this.canReorderSongs && !this.hasOpenDragBlockingModal();
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        onSongRequestProcessed(payload = {}) {
            if (Number(payload.setId) !== Number(this.setId)) {
                return;
            }

            this.songRequestsPendingCount = Math.max(0, this.songRequestsPendingCount - 1);

            if (payload.refreshSet) {
                this.refreshSessionSets();
            }
        },
        async moveSong(songId, direction) {
            if (!this.canDragSongs() || this.reorderBusy) {
                return;
            }

            this.clearDropPlaceholder();

            const songsContainer = this.$refs.songsContainer;
            const songElements = Array.from(songsContainer.querySelectorAll('[data-song-id]'));
            const currentIndex = songElements.findIndex((el) => Number(el.dataset.songId) === Number(songId));
            const targetIndex = currentIndex + direction;

            if (currentIndex < 0 || targetIndex < 0 || targetIndex >= songElements.length) {
                return;
            }

            const draggedEl = songElements[currentIndex];
            const targetEl = songElements[targetIndex];

            if (direction < 0) {
                songsContainer.insertBefore(draggedEl, targetEl);
            } else {
                songsContainer.insertBefore(draggedEl, targetEl.nextElementSibling);
            }

            await this.persistSongOrder();
        },
        closeSessionModals() {
            this.closeSummaryModal();
            this.openSetEdit = false;
            this.openSong = false;
            this.openSongRequest = false;
            this.openCollaborators = false;
            this.resetCollaboratorModal();
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            this.openActionMenu = shouldOpen;
        },
        async copySetShareLink() {
            await window.copyShareLink(config.shareSetUrl);
            this.shareCopied = true;
            setTimeout(() => this.shareCopied = false, 1800);
        },
        async copySetDirectLink() {
            await window.copyShareLink(config.setDirectUrl);
            this.directLinkCopied = true;
            setTimeout(() => this.directLinkCopied = false, 1800);
        },
        ensureDropPlaceholder(container, draggedEl) {
            if (!this.dropPlaceholderEl) {
                const placeholder = document.createElement('div');
                placeholder.className = 'rounded-xl border-2 border-dashed border-sky-400 bg-sky-50/70 p-4 text-sm font-medium text-sky-700 shadow-sm';
                placeholder.textContent = 'Drop song here';
                this.dropPlaceholderEl = placeholder;
            }

            this.dropPlaceholderEl.style.minHeight = `${draggedEl.offsetHeight}px`;
            return this.dropPlaceholderEl;
        },
        clearDropPlaceholder() {
            if (this.dropPlaceholderEl?.parentNode) {
                this.dropPlaceholderEl.parentNode.removeChild(this.dropPlaceholderEl);
            }
        },
        onSongDragStart(event, songId) {
            if (!this.canDragSongs()) {
                event.preventDefault();
                return;
            }

            this.dragSongId = songId;
            this.draggingSongId = songId;
            this.dropTargetSongId = null;
            this.dropTargetPosition = 'before';
            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer ? songsContainer.querySelector(`[data-song-id='${songId}']`) : null;

            if (draggedEl && event.dataTransfer) {
                event.dataTransfer.setDragImage(draggedEl, 24, 16);
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(songId));
        },
        onSongDragEnd() {
            this.dragSongId = null;
            this.draggingSongId = null;
            this.dropTargetSongId = null;
            this.dropTargetPosition = 'before';
            this.clearDropPlaceholder();
        },
        onSongDragOver(event, targetSongId = null) {
            if (!this.canDragSongs() || this.reorderBusy) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (this.dragSongId === null || targetSongId === null || this.dragSongId === targetSongId) {
                return;
            }

            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer.querySelector(`[data-song-id='${this.dragSongId}']`);
            const targetEl = songsContainer.querySelector(`[data-song-id='${targetSongId}']`);

            if (!draggedEl || !targetEl) {
                return;
            }

            const targetRect = targetEl.getBoundingClientRect();
            const placeAfter = event.clientY > (targetRect.top + targetRect.height / 2);
            const insertionReference = placeAfter ? targetEl.nextElementSibling : targetEl;

            const songElements = Array.from(songsContainer.querySelectorAll('[data-song-id]'));
            const currentIndex = songElements.indexOf(draggedEl);
            const referenceIndex = insertionReference ? songElements.indexOf(insertionReference) : songElements.length;
            const prospectiveIndex = insertionReference
                ? (referenceIndex > currentIndex ? referenceIndex - 1 : referenceIndex)
                : songElements.length - 1;

            if (prospectiveIndex === currentIndex) {
                this.clearDropPlaceholder();
                this.dropTargetSongId = null;
                this.dropTargetPosition = 'before';
                return;
            }

            const placeholderEl = this.ensureDropPlaceholder(songsContainer, draggedEl);

            if (insertionReference !== placeholderEl) {
                songsContainer.insertBefore(placeholderEl, insertionReference);
            }

            this.dropTargetSongId = targetSongId;
            this.dropTargetPosition = placeAfter ? 'after' : 'before';
        },
        async onSongDrop(event) {
            event.preventDefault();

            if (!this.canDragSongs() || this.reorderBusy) {
                this.clearDropPlaceholder();
                return;
            }

            if (this.dragSongId === null) {
                this.clearDropPlaceholder();
                return;
            }

            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer.querySelector(`[data-song-id='${this.dragSongId}']`);

            if (draggedEl && this.dropPlaceholderEl?.parentNode === songsContainer) {
                songsContainer.insertBefore(draggedEl, this.dropPlaceholderEl);
            }

            this.clearDropPlaceholder();

            this.dragSongId = null;
            this.draggingSongId = null;
            this.dropTargetSongId = null;
            this.dropTargetPosition = 'before';
            await this.persistSongOrder();
        },
        async persistSongOrder() {
            this.reorderBusy = true;
            this.reorderError = '';
            this.reorderFeedback = '';

            const songIds = Array.from(this.$refs.songsContainer.querySelectorAll('[data-song-id]'))
                .map((el) => Number(el.dataset.songId));

            try {
                const response = await fetch(config.songsReorderUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ song_ids: songIds }),
                });

                if (!response.ok) {
                    throw new Error('Reorder failed');
                }

                this.reorderFeedback = 'Song order saved.';
            } catch (e) {
                this.reorderError = 'Could not save song order. Refresh and try again.';
            } finally {
                this.reorderBusy = false;
            }
        },
        async loadSummary(initial = false) {
            if (initial && !this.summaryLoaded) {
                this.summaryLoading = true;
            }

            this.summaryError = '';

            try {
                const response = await fetch(config.setSummaryUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load summary');
                }

                const payload = await response.json();
                this.summaryData = payload;
                this.summaryLoaded = true;
                this.summaryLastUpdated = new Date().toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                });
            } catch (e) {
                this.summaryError = 'Could not load the live summary right now.';
            } finally {
                this.summaryLoading = false;
            }
        },
        openSummaryModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openSummary = true;
            this.loadSummary(true);
            this.startSummaryPolling();
        },
        closeSummaryModal() {
            this.openSummary = false;
            this.stopSummaryPolling();
        },
        startSummaryPolling() {
            this.stopSummaryPolling();
            this.summaryPollId = setInterval(() => {
                if (this.openSummary) {
                    this.loadSummary(false);
                }
            }, 15000);
        },
        stopSummaryPolling() {
            if (this.summaryPollId) {
                clearInterval(this.summaryPollId);
                this.summaryPollId = null;
            }
        },
        openSetEditModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.performedDraft = this.initialSetPerformed;
            this.songRequestsDraft = this.initialSongRequestsEnabled;
            this.freeForAllDraft = this.initialFreeForAll;
            this.openSetEdit = true;
        },
        openCollaboratorsModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.collaboratorsList = [...(config.initialCollaborators ?? [])];
            this.collaboratorSaveError = '';
            this.resetCollaboratorModal();
            this.openCollaborators = true;
        },
        resetCollaboratorModal() {
            this.collaboratorQuery = '';
            this.collaboratorSuggestions = [];
            this.showCollaboratorSuggestions = false;
            this.collaboratorLookupBusy = false;
            this.collaboratorSaveError = '';
            if (this.collaboratorLookupTimer) {
                clearTimeout(this.collaboratorLookupTimer);
                this.collaboratorLookupTimer = null;
            }
        },
        filteredCollaboratorSuggestions() {
            const existingIds = new Set(this.collaboratorsList.map((c) => c.id));
            return this.collaboratorSuggestions.filter((u) => !existingIds.has(u.id));
        },
        addCollaborator(user) {
            if (!this.collaboratorsList.find((c) => c.id === user.id)) {
                this.collaboratorsList.push({ id: user.id, name: user.name });
            }
            this.collaboratorQuery = '';
            this.collaboratorSuggestions = [];
            this.showCollaboratorSuggestions = false;
        },
        removeCollaborator(id) {
            this.collaboratorsList = this.collaboratorsList.filter((c) => c.id !== id);
        },
        queueCollaboratorLookup() {
            if (this.collaboratorLookupTimer) {
                clearTimeout(this.collaboratorLookupTimer);
            }

            const query = this.collaboratorQuery.trim();
            if (query.length < 1) {
                this.collaboratorSuggestions = [];
                this.showCollaboratorSuggestions = false;
                return;
            }

            this.collaboratorLookupTimer = setTimeout(() => this.fetchCollaboratorSuggestions(query), 250);
        },
        async fetchCollaboratorSuggestions(query) {
            if (!config.collaboratorsUsersUrl) {
                return;
            }

            this.collaboratorLookupBusy = true;

            try {
                const url = new URL(config.collaboratorsUsersUrl, window.location.origin);
                url.searchParams.set('q', query);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Search failed');
                }

                const payload = await response.json();
                this.collaboratorSuggestions = payload.users ?? [];
                this.showCollaboratorSuggestions = this.collaboratorSuggestions.length > 0;
            } catch (e) {
                this.collaboratorSuggestions = [];
            } finally {
                this.collaboratorLookupBusy = false;
            }
        },
        async saveCollaborators() {
            if (!config.collaboratorsUrl || this.collaboratorSaveBusy) {
                return;
            }

            this.collaboratorSaveBusy = true;
            this.collaboratorSaveError = '';

            try {
                const response = await fetch(config.collaboratorsUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ collaborator_ids: this.collaboratorsList.map((c) => c.id) }),
                });

                if (!response.ok) {
                    throw new Error('Could not save collaborators. Try again.');
                }

                const payload = await response.json();
                const updated = payload.collaborators ?? this.collaboratorsList;
                config.initialCollaborators = updated;
                this.collaboratorsList = [...updated];
                this.collaboratorNames = updated.map((c) => c.name);
                this.openCollaborators = false;
                this.resetCollaboratorModal();
            } catch (e) {
                this.collaboratorSaveError = e.message || 'Could not save collaborators. Try again.';
            } finally {
                this.collaboratorSaveBusy = false;
            }
        },
        openAddSongModal() {
            if (this.setLocked) {
                return;
            }

            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openSong = true;
            this.addSongError = '';
            this.resetSongAutocomplete();
        },
        openSongRequestModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openSongRequest = true;
            this.requestSongError = '';
            this.resetSongRequestAutocomplete();
        },
        async submitSongRequest(event) {
            this.requestSongBusy = true;
            this.requestSongError = '';

            try {
                const response = await fetch(this.songRequestStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    let message = 'Could not submit song request. Try again.';

                    try {
                        const payload = await response.json();
                        const validationErrors = Object.values(payload.errors || {}).flat();
                        message = validationErrors[0] || payload.message || message;
                    } catch (e) {
                        message = 'Could not submit song request. Try again.';
                    }

                    throw new Error(message);
                }

                this.openSongRequest = false;
                this.resetSongRequestAutocomplete();
                event.target.reset();
                this.refreshSessionSets();
            } catch (e) {
                this.requestSongError = e.message || 'Could not submit song request. Try again.';
            } finally {
                this.requestSongBusy = false;
            }
        },
        async submitAddSong(event) {
            this.addSongBusy = true;
            this.addSongError = '';

            try {
                const response = await fetch(config.songStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.openSong = false;
                this.resetSongAutocomplete();
                this.refreshSessionSets();
            } catch (e) {
                this.addSongError = 'Could not add song. Try again.';
            } finally {
                this.addSongBusy = false;
            }
        },
        resetSongAutocomplete() {
            this.songArtistQuery = '';
            this.songTitleQuery = '';
            this.selectedArtistName = '';
            this.selectedDeezerDuration = null;
            this.deezerTitleSelected = false;
            this.artistSuggestions = [];
            this.titleSuggestions = [];
            this.artistLookupBusy = false;
            this.titleLookupBusy = false;
            this.artistLookupError = '';
            this.titleLookupError = '';
            this.showArtistSuggestions = false;
            this.showTitleSuggestions = false;
            if (this.artistLookupTimer) {
                clearTimeout(this.artistLookupTimer);
                this.artistLookupTimer = null;
            }
            if (this.titleLookupTimer) {
                clearTimeout(this.titleLookupTimer);
                this.titleLookupTimer = null;
            }
        },
        queueArtistLookup() {
            this.artistLookupError = '';
            this.showTitleSuggestions = false;
            this.titleSuggestions = [];
            this.selectedDeezerDuration = null;
            this.deezerTitleSelected = false;

            if (this.artistLookupTimer) {
                clearTimeout(this.artistLookupTimer);
            }

            const query = this.songArtistQuery.trim();
            if (query.length < 2) {
                this.artistSuggestions = [];
                this.showArtistSuggestions = false;
                this.selectedArtistName = '';
                return;
            }

            this.artistLookupTimer = setTimeout(() => this.fetchArtistSuggestions(query), 250);
        },
        async fetchArtistSuggestions(query) {
            const token = ++this.artistLookupToken;
            this.artistLookupBusy = true;

            try {
                const response = await fetch(`${this.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error('Artist lookup failed');
                }

                const payload = await response.json();
                if (token !== this.artistLookupToken) {
                    return;
                }

                this.artistSuggestions = payload.artists || [];

                this.showArtistSuggestions = this.artistSuggestions.length > 0;
            } catch (e) {
                if (token !== this.artistLookupToken) {
                    return;
                }

                this.artistLookupError = 'Could not fetch artist suggestions right now.';
                this.artistSuggestions = [];
                this.showArtistSuggestions = false;
            } finally {
                if (token === this.artistLookupToken) {
                    this.artistLookupBusy = false;
                }
            }
        },
        selectArtistSuggestion(artistName) {
            this.songArtistQuery = artistName;
            this.selectedArtistName = artistName;
            this.artistSuggestions = [];
            this.showArtistSuggestions = false;
            this.songTitleQuery = '';
            this.titleSuggestions = [];
            this.showTitleSuggestions = false;
            this.titleLookupError = '';
        },
        queueTitleLookup() {
            this.titleLookupError = '';
            this.selectedDeezerDuration = null;
            this.deezerTitleSelected = false;

            if (this.titleLookupTimer) {
                clearTimeout(this.titleLookupTimer);
            }

            const query = this.songTitleQuery.trim();
            const artist = (this.selectedArtistName || this.songArtistQuery).trim();
            if (query.length < 2 || artist.length < 2) {
                this.titleSuggestions = [];
                this.showTitleSuggestions = false;
                return;
            }

            this.titleLookupTimer = setTimeout(() => this.fetchTitleSuggestions(artist, query), 250);
        },
        async fetchTitleSuggestions(artist, query) {
            const token = ++this.titleLookupToken;
            this.titleLookupBusy = true;

            try {
                const response = await fetch(`${this.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error('Title lookup failed');
                }

                const payload = await response.json();
                if (token !== this.titleLookupToken) {
                    return;
                }

                this.titleSuggestions = payload.titles || [];

                this.showTitleSuggestions = this.titleSuggestions.length > 0;
            } catch (e) {
                if (token !== this.titleLookupToken) {
                    return;
                }

                this.titleLookupError = 'Could not fetch song suggestions right now.';
                this.titleSuggestions = [];
                this.showTitleSuggestions = false;
            } finally {
                if (token === this.titleLookupToken) {
                    this.titleLookupBusy = false;
                }
            }
        },
        selectTitleSuggestion(title, duration) {
            this.songTitleQuery = title;
            this.selectedDeezerDuration = duration ?? null;
            this.deezerTitleSelected = true;
            this.titleSuggestions = [];
            this.showTitleSuggestions = false;
        },
        resetSongRequestAutocomplete() {
            this.requestArtistQuery = '';
            this.requestTitleQuery = '';
            this.requestSelectedArtistName = '';
            this.requestArtistSuggestions = [];
            this.requestTitleSuggestions = [];
            this.requestArtistLookupBusy = false;
            this.requestTitleLookupBusy = false;
            this.requestArtistLookupError = '';
            this.requestTitleLookupError = '';
            this.showRequestArtistSuggestions = false;
            this.showRequestTitleSuggestions = false;
            if (this.requestArtistLookupTimer) {
                clearTimeout(this.requestArtistLookupTimer);
                this.requestArtistLookupTimer = null;
            }
            if (this.requestTitleLookupTimer) {
                clearTimeout(this.requestTitleLookupTimer);
                this.requestTitleLookupTimer = null;
            }
        },
        queueRequestArtistLookup() {
            this.requestArtistLookupError = '';
            this.showRequestTitleSuggestions = false;
            this.requestTitleSuggestions = [];

            if (this.requestArtistLookupTimer) {
                clearTimeout(this.requestArtistLookupTimer);
            }

            const query = this.requestArtistQuery.trim();
            if (query.length < 2) {
                this.requestArtistSuggestions = [];
                this.showRequestArtistSuggestions = false;
                this.requestSelectedArtistName = '';
                return;
            }

            this.requestArtistLookupTimer = setTimeout(() => this.fetchRequestArtistSuggestions(query), 250);
        },
        async fetchRequestArtistSuggestions(query) {
            const token = ++this.requestArtistLookupToken;
            this.requestArtistLookupBusy = true;

            try {
                const response = await fetch(`${this.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error('Artist lookup failed');
                }

                const payload = await response.json();
                if (token !== this.requestArtistLookupToken) {
                    return;
                }

                this.requestArtistSuggestions = payload.artists || [];
                this.showRequestArtistSuggestions = this.requestArtistSuggestions.length > 0;
            } catch (e) {
                if (token !== this.requestArtistLookupToken) {
                    return;
                }

                this.requestArtistLookupError = 'Could not fetch artist suggestions right now.';
                this.requestArtistSuggestions = [];
                this.showRequestArtistSuggestions = false;
            } finally {
                if (token === this.requestArtistLookupToken) {
                    this.requestArtistLookupBusy = false;
                }
            }
        },
        selectRequestArtistSuggestion(artistName) {
            this.requestArtistQuery = artistName;
            this.requestSelectedArtistName = artistName;
            this.requestArtistSuggestions = [];
            this.showRequestArtistSuggestions = false;
            this.requestTitleQuery = '';
            this.requestTitleSuggestions = [];
            this.showRequestTitleSuggestions = false;
            this.requestTitleLookupError = '';
        },
        queueRequestTitleLookup() {
            this.requestTitleLookupError = '';

            if (this.requestTitleLookupTimer) {
                clearTimeout(this.requestTitleLookupTimer);
            }

            const query = this.requestTitleQuery.trim();
            const artist = (this.requestSelectedArtistName || this.requestArtistQuery).trim();
            if (query.length < 2 || artist.length < 2) {
                this.requestTitleSuggestions = [];
                this.showRequestTitleSuggestions = false;
                return;
            }

            this.requestTitleLookupTimer = setTimeout(() => this.fetchRequestTitleSuggestions(artist, query), 250);
        },
        async fetchRequestTitleSuggestions(artist, query) {
            const token = ++this.requestTitleLookupToken;
            this.requestTitleLookupBusy = true;

            try {
                const response = await fetch(`${this.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error('Title lookup failed');
                }

                const payload = await response.json();
                if (token !== this.requestTitleLookupToken) {
                    return;
                }

                this.requestTitleSuggestions = payload.titles || [];
                this.showRequestTitleSuggestions = this.requestTitleSuggestions.length > 0;
            } catch (e) {
                if (token !== this.requestTitleLookupToken) {
                    return;
                }

                this.requestTitleLookupError = 'Could not fetch song suggestions right now.';
                this.requestTitleSuggestions = [];
                this.showRequestTitleSuggestions = false;
            } finally {
                if (token === this.requestTitleLookupToken) {
                    this.requestTitleLookupBusy = false;
                }
            }
        },
        selectRequestTitleSuggestion(title) {
            this.requestTitleQuery = title;
            this.requestTitleSuggestions = [];
            this.showRequestTitleSuggestions = false;
        },
    };
}

export function sessionSongCard(config) {
    return {
        openEditSong: false,
        openAddSlot: false,
        openActionMenu: false,
        directLinkCopied: false,
        songCollapsed: false,
        songKey: config.songKey,
        busyAction: false,
        actionError: '',
        reorderBusy: false,
        reorderError: '',
        reorderFeedback: '',
        toast: { visible: false, type: 'error', message: '' },
        toastTimer: null,
        canReorderSlots: config.canReorderSlots,
        ...baseDragState(),
        dragSlotId: null,
        draggingSlotId: null,
        dropTargetSlotId: null,
        hasOpenDragBlockingModal() {
            return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((el) => window.getComputedStyle(el).display !== 'none');
        },
        canDragSlots() {
            return this.canReorderSlots && !this.hasOpenDragBlockingModal();
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        async moveSlot(slotId, direction) {
            if (!this.canDragSlots() || this.busyAction) {
                return;
            }

            this.clearSlotDropPlaceholder();

            const slotsContainer = this.$refs.slotsContainer;
            const slotElements = Array.from(slotsContainer.querySelectorAll('[data-slot-id]'));
            const currentIndex = slotElements.findIndex((el) => Number(el.dataset.slotId) === Number(slotId));
            const targetIndex = currentIndex + direction;

            if (currentIndex < 0 || targetIndex < 0 || targetIndex >= slotElements.length) {
                return;
            }

            const draggedEl = slotElements[currentIndex];
            const targetEl = slotElements[targetIndex];

            if (direction < 0) {
                slotsContainer.insertBefore(draggedEl, targetEl);
            } else {
                slotsContainer.insertBefore(draggedEl, targetEl.nextElementSibling);
            }

            await this.persistSlotOrder();
        },
        showToast(type, message) {
            this.toast = { visible: true, type, message };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast.visible = false, 4500);
        },
        async failedResponseMessage(response, fallback) {
            let message = fallback;

            try {
                const payload = await response.json();
                const validationErrors = Object.values(payload.errors || {}).flat();
                message = validationErrors[0] || payload.message || fallback;
            } catch (e) {
                message = fallback;
            }

            if (response.status === 422) {
                this.showToast('error', message);
                return null;
            }

            return message;
        },
        closeSessionModals() {
            this.openEditSong = false;
            this.openAddSlot = false;
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        setSongCollapsed(collapsed) {
            const wasCollapsed = this.songCollapsed;
            this.songCollapsed = collapsed;

            if (wasCollapsed && !collapsed) {
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('session-song-opened')));
            }
        },
        toggleSongCollapsed() {
            this.setSongCollapsed(!this.songCollapsed);
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            this.openActionMenu = shouldOpen;
        },
        openEditSongModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openEditSong = true;
        },
        openAddSlotModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openAddSlot = true;
        },
        async copySongDirectLink() {
            await window.copyShareLink(config.songDirectUrl);
            this.directLinkCopied = true;
            setTimeout(() => this.directLinkCopied = false, 1800);
        },
        clearSlotDropPlaceholder() {
            this.$refs.slotDropPlaceholder?.classList.add('hidden');
        },
        onSlotDragStart(event, slotId) {
            if (!this.canDragSlots()) {
                event.preventDefault();
                return;
            }

            this.dragSlotId = slotId;
            this.draggingSlotId = slotId;
            this.dropTargetSlotId = null;

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer ? slotsContainer.querySelector(`[data-slot-id='${slotId}']`) : null;

            if (draggedEl && event.dataTransfer) {
                event.dataTransfer.setDragImage(draggedEl, 24, 16);
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(slotId));
        },
        onSlotDragEnd() {
            this.dragSlotId = null;
            this.draggingSlotId = null;
            this.dropTargetSlotId = null;
            this.clearSlotDropPlaceholder();
        },
        onSlotDragOver(event, targetSlotId = null) {
            if (!this.canDragSlots() || this.busyAction) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (this.dragSlotId === null || targetSlotId === null || this.dragSlotId === targetSlotId) {
                return;
            }

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer.querySelector(`[data-slot-id='${this.dragSlotId}']`);
            const targetEl = slotsContainer.querySelector(`[data-slot-id='${targetSlotId}']`);

            if (!draggedEl || !targetEl) {
                return;
            }

            const targetRect = targetEl.getBoundingClientRect();
            const placeAfter = event.clientY > (targetRect.top + targetRect.height / 2);
            const insertionReference = placeAfter ? targetEl.nextElementSibling : targetEl;

            const slotElements = Array.from(slotsContainer.querySelectorAll('[data-slot-id]'));
            const currentIndex = slotElements.indexOf(draggedEl);
            const referenceIndex = insertionReference ? slotElements.indexOf(insertionReference) : slotElements.length;
            const prospectiveIndex = insertionReference
                ? (referenceIndex > currentIndex ? referenceIndex - 1 : referenceIndex)
                : slotElements.length - 1;

            if (prospectiveIndex === currentIndex) {
                this.clearSlotDropPlaceholder();
                this.dropTargetSlotId = null;
                return;
            }

            const placeholderEl = this.$refs.slotDropPlaceholder;
            placeholderEl.classList.remove('hidden');
            placeholderEl.querySelector('[data-slot-drop-label]').style.minHeight = `${draggedEl.offsetHeight}px`;

            if (insertionReference !== placeholderEl) {
                slotsContainer.insertBefore(placeholderEl, insertionReference);
            }

            this.dropTargetSlotId = targetSlotId;
        },
        async onSlotDrop(event) {
            event.preventDefault();

            if (!this.canDragSlots() || this.busyAction) {
                this.clearSlotDropPlaceholder();
                return;
            }

            if (this.dragSlotId === null) {
                this.clearSlotDropPlaceholder();
                return;
            }

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer.querySelector(`[data-slot-id='${this.dragSlotId}']`);

            if (draggedEl && this.$refs.slotDropPlaceholder?.parentNode === slotsContainer) {
                slotsContainer.insertBefore(draggedEl, this.$refs.slotDropPlaceholder);
            }

            this.clearSlotDropPlaceholder();

            this.dragSlotId = null;
            this.draggingSlotId = null;
            this.dropTargetSlotId = null;
            await this.persistSlotOrder();
        },
        async persistSlotOrder() {
            this.busyAction = true;
            this.actionError = '';

            const slotIds = Array.from(this.$refs.slotsContainer.querySelectorAll('[data-slot-id]'))
                .map((el) => Number(el.dataset.slotId));

            try {
                const response = await fetch(config.slotsReorderUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ slot_ids: slotIds }),
                });

                if (!response.ok) {
                    throw new Error('Reorder failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not save slot order. Refresh and try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitAddSlot(event) {
            this.busyAction = true;
            this.actionError = '';

            try {
                const response = await fetch(config.slotsStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not add slot. Try again.');
                    throw new Error(message);
                }

                this.openAddSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = e.message || 'Could not add slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
    };
}

export function sessionSongRequestRow(config) {
    return {
        hidden: false,
        busy: false,
        error: '',
        bandTemplateId: config.initialBandTemplateId ? String(config.initialBandTemplateId) : '',
        async respond(status) {
            this.busy = true;
            this.error = '';

            const payload = {
                _method: 'PATCH',
                status,
            };

            if (status === 'accepted' && this.bandTemplateId !== '') {
                payload.band_template_id = Number(this.bandTemplateId);
            }

            try {
                const response = await fetch(config.respondUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Could not update this song request. Try again.');
                }

                this.hidden = true;
                window.dispatchEvent(new CustomEvent('session-song-request-processed', {
                    detail: {
                        setId: config.setId,
                        refreshSet: status === 'accepted',
                    },
                }));

                if (config.decrementApprovalsCounter) {
                    window.dispatchEvent(new CustomEvent('pending-approval-processed'));
                }
            } catch (e) {
                this.error = e.message || 'Could not update this song request. Try again.';
            } finally {
                this.busy = false;
            }
        },
        async removeOwnSongRequest() {
            await this.respond('rejected');
        },
    };
}

export function sessionSlotRow(config) {
    return {
        openPropose: false,
        openEditSlot: false,
        openActionMenu: false,
        actionMenuStyle: '',
        assignedUserName: config.assignedUserName,
        slotLabel: config.slotLabel,
        slotIsOpen: config.slotIsOpen,
        assignmentIsManual: config.assignmentIsManual,
        initialEditAssignedUserId: config.initialEditAssignedUserId,
        initialEditAssignedUserName: config.initialEditAssignedUserName,
        initialEditManualPerformerName: config.initialEditManualPerformerName,
        editAssignedUserId: config.editAssignedUserId,
        editAssignedUserName: config.initialEditAssignedUserName || config.initialEditManualPerformerName || '',
        currentUserId: config.currentUserId,
        assignedToCurrentUser: config.assignedToCurrentUser,
        hasPendingOwnRequest: config.hasPendingOwnRequest,
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        toast: { visible: false, type: 'error', message: '' },
        toastStyle: '',
        toastTimer: null,
        proposalUserOptions: config.proposalUserOptions,
        users: config.users,
        ...baseDragState(),
        proposeTargetUserId: '',
        proposeTargetUserQuery: '',
        editAssignedUserQuery: config.initialEditAssignedUserName || config.initialEditManualPerformerName || '',
        showEditUserSuggestions: false,
        showProposalUserSuggestions: false,
        proposeMessage: '',
        filteredEditUsers() {
            const query = this.editAssignedUserQuery.trim().toLowerCase();
            if (query === '') {
                return this.users.slice(0, 8);
            }

            return this.users
                .filter((user) => user.name.toLowerCase().includes(query))
                .slice(0, 8);
        },
        updateEditUserQuery() {
            this.editAssignedUserId = '';
            this.showEditUserSuggestions = true;
        },
        selectEditUser(user) {
            this.editAssignedUserId = String(user.id);
            this.editAssignedUserQuery = user.name;
            this.editAssignedUserName = user.name;
            this.showEditUserSuggestions = false;
        },
        filteredProposalUsers() {
            const query = this.proposeTargetUserQuery.trim().toLowerCase();
            if (query === '') {
                return [];
            }

            const users = query === ''
                ? this.proposalUserOptions
                : this.proposalUserOptions.filter((user) => user.name.toLowerCase().includes(query));

            return users.slice(0, 8);
        },
        updateProposalUserQuery() {
            const selectedUser = this.proposalUserOptions.find((user) => String(user.id) === String(this.proposeTargetUserId));
            if (!selectedUser || selectedUser.name !== this.proposeTargetUserQuery) {
                this.proposeTargetUserId = '';
            }

            this.showProposalUserSuggestions = true;
        },
        selectProposalUser(user) {
            this.proposeTargetUserId = String(user.id);
            this.proposeTargetUserQuery = user.name;
            this.showProposalUserSuggestions = false;
        },
        shouldShowAssigneeWarning() {
            const query = this.editAssignedUserQuery.trim();
            return query !== '' && query !== this.initialEditAssignedUserName && query !== this.initialEditManualPerformerName;
        },
        resolveEditedSlotAssignment() {
            const query = this.editAssignedUserQuery.trim();
            const selectedUser = this.users.find((user) => String(user.id) === String(this.editAssignedUserId));

            if (selectedUser) {
                return {
                    user_id: String(selectedUser.id),
                    manual_performer_name: '',
                };
            }

            return {
                user_id: '',
                manual_performer_name: query,
            };
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        showToast(type, message) {
            const anchorRect = (this.$refs.toastAnchor || this.$refs.actionMenuButton || this.$el).getBoundingClientRect();
            const viewportPadding = 12;
            const toastWidth = Math.min(384, window.innerWidth - (viewportPadding * 2));
            const left = Math.max(
                viewportPadding,
                Math.min(window.innerWidth - toastWidth - viewportPadding, anchorRect.right - toastWidth)
            );
            const top = Math.max(viewportPadding, anchorRect.top - 4);

            this.toastStyle = `left: ${left}px; top: ${top}px; width: ${toastWidth}px;`;
            this.toast = { visible: true, type, message };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast.visible = false, 4500);
        },
        async failedResponseMessage(response, fallback) {
            let message = fallback;

            try {
                const payload = await response.json();
                const validationErrors = Object.values(payload.errors || {}).flat();
                message = validationErrors[0] || payload.message || fallback;
            } catch (e) {
                message = fallback;
            }

            if (response.status === 422) {
                this.showToast('error', message);
                return null;
            }

            return message;
        },
        closeSessionModals() {
            this.openPropose = false;
            this.openEditSlot = false;
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        positionActionMenu() {
            const buttonRect = this.$refs.actionMenuButton.getBoundingClientRect();
            const viewportPadding = 8;
            const menuWidth = Math.min(288, window.innerWidth - (viewportPadding * 2));
            const left = window.scrollX + Math.max(
                viewportPadding,
                Math.min(window.innerWidth - menuWidth - viewportPadding, buttonRect.right - menuWidth)
            );
            const top = window.scrollY + buttonRect.bottom + viewportPadding;

            this.actionMenuStyle = `left: ${left}px; top: ${top}px; width: ${menuWidth}px;`;
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                this.positionActionMenu();
            }

            this.openActionMenu = shouldOpen;
        },
        openProposeModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.proposeTargetUserId = '';
            this.proposeTargetUserQuery = '';
            this.showProposalUserSuggestions = false;
            this.openPropose = true;
        },
        openEditSlotModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.editAssignedUserId = this.initialEditAssignedUserId;
            this.editAssignedUserQuery = this.initialEditAssignedUserName || this.initialEditManualPerformerName || '';
            this.editAssignedUserName = this.editAssignedUserQuery;
            this.showEditUserSuggestions = false;
            this.openEditSlot = true;
        },
        async requestSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.requestSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.actionFeedback = 'Request sent.';
                this.hasPendingOwnRequest = true;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not send request. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async takeSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.takeSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not take slot. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = e.message || 'Could not take slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitProposal() {
            if (this.setLocked) {
                return;
            }

            if (!this.proposeTargetUserId) {
                this.actionError = config.noProposableUsersMessage;
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.proposeSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({
                        target_user_id: this.proposeTargetUserId,
                        message: this.proposeMessage,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.actionFeedback = 'Recommendation sent.';
                this.openPropose = false;
                this.proposeMessage = '';
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not send recommendation. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async releaseSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.releaseSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not release slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async clearSlot() {
            if (this.setLocked) {
                return;
            }

            if (!window.confirm('Clear this slot assignment?')) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.updateSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({
                        _method: 'PATCH',
                        name: config.slotName,
                        user_id: null,
                        manual_performer_name: '',
                        position: config.slotPosition,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.openEditSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not clear slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async copySlotDirectLink() {
            await window.copyShareLink(config.slotDirectUrl);
            this.actionFeedback = 'Direct link copied.';
            setTimeout(() => {
                if (this.actionFeedback === 'Direct link copied.') {
                    this.actionFeedback = '';
                }
            }, 1800);
        },
        async submitEditSlot(event) {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const assignment = this.resolveEditedSlotAssignment();
                const formData = new FormData(event.target);
                formData.set('user_id', assignment.user_id);
                formData.set('manual_performer_name', assignment.manual_performer_name);
                const response = await fetch(config.updateSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: formData,
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not save slot. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                this.openEditSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = e.message || 'Could not save slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async deleteSlot(event) {
            if (this.setLocked) {
                return;
            }

            const confirmed = window.confirm('Delete this slot?');
            if (!confirmed) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch(config.destroySlotUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.openEditSlot = false;
                this.$el.remove();
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not delete slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        setLocked: config.setLocked,
        canReorderSlots: config.canReorderSlots,
        isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,
        canDragSlots() {
            return this.canReorderSlots && !this.hasOpenDragBlockingModal();
        },
        hasOpenDragBlockingModal() {
            return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((el) => window.getComputedStyle(el).display !== 'none');
        },
    };
}
