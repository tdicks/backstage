export function jamStandardsCatalog(config) {
    return {
        openAddSong: false,
        openRequestSong: false,
        openQuickSet: false,
        openEditSong: false,
        editingSong: null,
        catalogActionMenuOpen: false,
        catalogActionSong: null,
        catalogActionMenuAnchor: null,
        catalogActionMenuStyle: '',
        coverageSong: null,
        coverageUsers: [],
        coverageLoading: false,
        coverageError: '',
        catalogAutocompleteTarget: 'form',
        catalogArtistQuery: '',
        catalogTitleQuery: '',
        catalogSelectedArtist: '',
        catalogSelectedDeezerDuration: null,
        catalogDeezerTitleSelected: false,
        catalogArtistSuggestions: [],
        catalogTitleSuggestions: [],
        showCatalogArtistSuggestions: false,
        showCatalogTitleSuggestions: false,
        catalogArtistLookupBusy: false,
        catalogTitleLookupBusy: false,
        catalogArtistLookupTimer: null,
        catalogTitleLookupTimer: null,
        catalogArtistLookupToken: 0,
        catalogTitleLookupToken: 0,
        entryMode: 'template',
        entrySlotNames: [],
        requestTemplateId: '',
        statusMessage: config.initialStatusMessage || '',
        statusTimeout: null,
        searchLoading: false,
        performerFilterOpen: false,
        catalogPage: config.catalogPage,
        catalogPagination: config.initialCatalogPagination || {
            current_page: config.catalogPage || 1,
            last_page: 1,
            total: (config.initialCatalogSongs || []).length,
        },
        slotLabels: config.slotLabels,
        canEditCatalog: config.canEditCatalog,
        templateSlots: config.templateSlots,
        quickSetSongs: config.quickSetSongs,
        selectedSongIds: [],
        quickSetAssignments: {},
        quickSetJamSessionId: '',
        quickSetName: '',
        selectedPerformerIds: config.selectedPerformerIds,
        performerNames: config.performerNames,
        slotConflicts: config.slotConflicts,
        currentCatalogSongs: config.initialCatalogSongs || [],
        currentCatalogPerformers: config.initialPerformers || [],
        mobileSelectionMode: false,
        init() {
            if (this.statusMessage) {
                this.setStatusMessage(this.statusMessage);
            }
        },
        setStatusMessage(message) {
            this.statusMessage = message;
            if (this.statusTimeout) {
                clearTimeout(this.statusTimeout);
            }
            this.statusTimeout = setTimeout(() => {
                this.statusMessage = '';
                this.statusTimeout = null;
            }, 5000);
        },
        selectedPerformerFilterLabel() {
            if (this.selectedPerformerIds.length === 0) {
                return 'All performers';
            }

            if (this.selectedPerformerIds.length > 3) {
                return `${this.selectedPerformerIds.length} performers`;
            }

            return this.selectedPerformerIds
                .map((performerId) => this.performerNames[performerId])
                .filter(Boolean)
                .join(', ');
        },
        toggleSong(songId, selected) {
            this.selectedSongIds = selected
                ? [...new Set([...this.selectedSongIds, songId])]
                : this.selectedSongIds.filter((id) => id !== songId);
        },
        openMobileSelectionMode() {
            this.mobileSelectionMode = true;
        },
        clearSelectedSongs() {
            this.selectedSongIds = [];
        },
        cancelMobileSelectionMode() {
            this.mobileSelectionMode = false;
            this.selectedSongIds = [];
        },
        selectAllVisibleSongs() {
            this.selectedSongIds = [...new Set([
                ...this.selectedSongIds,
                ...this.currentCatalogSongs.map((song) => song.id),
            ])];
        },
        selectedQuickSetSongs() {
            return this.selectedSongIds
                .map((songId) => this.quickSetSongs[songId])
                .filter(Boolean);
        },
        catalogRowClass(selected) {
            return selected
                ? 'border-t border-amber-200 bg-amber-50/60 align-top'
                : 'border-t border-slate-200 bg-white align-top';
        },
        catalogCardClass(selected) {
            return selected
                ? 'rounded-xl border border-emerald-400 bg-emerald-50/70 p-4 shadow-sm ring-1 ring-emerald-200'
                : 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm';
        },
        editSong(song) {
            this.catalogActionMenuOpen = false;
            const slotNames = song.slots.map((slot) => typeof slot === 'string' ? slot : slot.name);
            this.editingSong = { ...song, slots: slotNames };
            this.catalogAutocompleteTarget = 'edit';
            this.catalogArtistQuery = song.artist;
            this.catalogTitleQuery = song.title;
            this.catalogSelectedArtist = song.artist;
            this.catalogArtistSuggestions = [];
            this.catalogTitleSuggestions = [];
            this.entryMode = song.band_template_id ? 'template' : 'manual';
            this.entrySlotNames = song.band_template_id ? [] : slotNames;
            this.requestTemplateId = song.band_template_id ? String(song.band_template_id) : '';
            this.openEditSong = true;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'catalog-song-edit' }));
        },
        catalogActionMenuPosition(anchor, menu) {
            const buttonRect = anchor.getBoundingClientRect();
            const viewportPadding = 8;
            const menuWidth = Math.min(288, window.innerWidth - (viewportPadding * 2));
            const menuHeight = menu ? menu.offsetHeight : 0;
            const spaceRight = window.innerWidth - buttonRect.left;
            const spaceLeft = buttonRect.right;
            const openToLeft = spaceRight < menuWidth && spaceLeft > spaceRight;
            const left = openToLeft
                ? Math.max(viewportPadding, buttonRect.right - menuWidth)
                : Math.min(buttonRect.left, window.innerWidth - menuWidth - viewportPadding);
            const topBelow = buttonRect.bottom + viewportPadding;
            const topAbove = buttonRect.top - viewportPadding - menuHeight;
            const canOpenBelow = menuHeight === 0 || (topBelow + menuHeight <= window.innerHeight - viewportPadding);
            const canOpenAbove = menuHeight > 0 && topAbove >= viewportPadding;
            const top = canOpenBelow
                ? Math.max(viewportPadding, topBelow)
                : canOpenAbove
                    ? topAbove
                    : Math.max(viewportPadding, window.innerHeight - viewportPadding - menuHeight);

            return `position: fixed; left: ${left}px; top: ${top}px; width: ${menuWidth}px; max-height: calc(100vh - ${viewportPadding * 2}px); overflow-y: auto;`;
        },
        positionCatalogActionMenu() {
            if (!this.catalogActionMenuOpen || !this.catalogActionMenuAnchor) {
                return;
            }

            this.$nextTick(() => {
                this.catalogActionMenuStyle = this.catalogActionMenuPosition(this.catalogActionMenuAnchor, this.$refs.catalogActionMenu);
            });
        },
        toggleCatalogActionMenu(song, anchor) {
            const shouldOpen = !this.catalogActionMenuOpen || this.catalogActionSong?.id !== song.id;
            this.catalogActionMenuOpen = shouldOpen;
            this.catalogActionSong = shouldOpen ? song : null;
            this.catalogActionMenuAnchor = shouldOpen ? anchor : null;
            if (shouldOpen) {
                this.positionCatalogActionMenu();
            }
        },
        async showCoverage(song) {
            this.catalogActionMenuOpen = false;
            this.coverageSong = song;
            this.coverageUsers = [];
            this.coverageError = '';
            this.coverageLoading = true;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'catalog-song-coverage' }));

            try {
                const response = await fetch(`${config.catalogUrl}/${song.id}/coverage`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error('Coverage lookup failed');
                }

                const payload = await response.json();
                this.coverageSong = payload.song;
                this.coverageUsers = payload.coverage || [];
            } catch (error) {
                this.coverageError = 'Could not load song coverage.';
            } finally {
                this.coverageLoading = false;
            }
        },
        closeCatalogSongForm() {
            this.openAddSong = false;
            this.openRequestSong = false;
            this.resetCatalogSongForm();
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-song-form' }));
        },
        resetCatalogSongForm() {
            this.entryMode = 'template';
            this.entrySlotNames = [];
            this.requestTemplateId = '';
            this.resetCatalogAutocomplete();
        },
        resetCatalogAutocomplete() {
            this.catalogAutocompleteTarget = 'form';
            this.catalogArtistQuery = '';
            this.catalogTitleQuery = '';
            this.catalogSelectedArtist = '';
            this.catalogSelectedDeezerDuration = null;
            this.catalogDeezerTitleSelected = false;
            this.catalogArtistSuggestions = [];
            this.catalogTitleSuggestions = [];
            this.showCatalogArtistSuggestions = false;
            this.showCatalogTitleSuggestions = false;
            this.catalogArtistLookupBusy = false;
            this.catalogTitleLookupBusy = false;
            this.catalogArtistLookupToken++;
            this.catalogTitleLookupToken++;
            if (this.catalogArtistLookupTimer) {
                clearTimeout(this.catalogArtistLookupTimer);
            }
            if (this.catalogTitleLookupTimer) {
                clearTimeout(this.catalogTitleLookupTimer);
            }
        },
        setCatalogAutocompleteValue(field, value) {
            this[field === 'artist' ? 'catalogArtistQuery' : 'catalogTitleQuery'] = value;
            if (this.catalogAutocompleteTarget === 'edit') {
                this.editingSong[field] = value;
            }
        },
        queueCatalogArtistLookup() {
            this.catalogSelectedArtist = '';
            this.catalogTitleSuggestions = [];
            this.showCatalogTitleSuggestions = false;
            if (this.catalogArtistLookupTimer) {
                clearTimeout(this.catalogArtistLookupTimer);
            }
            const query = this.catalogArtistQuery.trim();
            if (query.length < 2) {
                this.catalogArtistSuggestions = [];
                this.showCatalogArtistSuggestions = false;
                return;
            }
            this.catalogArtistLookupTimer = setTimeout(() => this.fetchCatalogArtistSuggestions(query), 250);
        },
        async fetchCatalogArtistSuggestions(query) {
            const token = ++this.catalogArtistLookupToken;
            this.catalogArtistLookupBusy = true;
            try {
                const response = await fetch(`${config.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error('Artist lookup failed');
                }

                const payload = await response.json();
                if (token !== this.catalogArtistLookupToken) {
                    return;
                }
                this.catalogArtistSuggestions = payload.artists || [];
                this.showCatalogArtistSuggestions = this.catalogArtistSuggestions.length > 0;
            } catch (error) {
                if (token === this.catalogArtistLookupToken) {
                    this.catalogArtistSuggestions = [];
                    this.showCatalogArtistSuggestions = false;
                }
            } finally {
                if (token === this.catalogArtistLookupToken) {
                    this.catalogArtistLookupBusy = false;
                }
            }
        },
        selectCatalogArtistSuggestion(artist) {
            this.setCatalogAutocompleteValue('artist', artist);
            this.catalogSelectedArtist = artist;
            this.setCatalogAutocompleteValue('title', '');
            this.catalogSelectedDeezerDuration = null;
            this.catalogDeezerTitleSelected = false;
            this.catalogArtistSuggestions = [];
            this.catalogTitleSuggestions = [];
            this.showCatalogArtistSuggestions = false;
            this.showCatalogTitleSuggestions = false;
        },
        queueCatalogTitleLookup() {
            this.catalogSelectedDeezerDuration = null;
            this.catalogDeezerTitleSelected = false;
            if (this.catalogTitleLookupTimer) {
                clearTimeout(this.catalogTitleLookupTimer);
            }
            const query = this.catalogTitleQuery.trim();
            const artist = (this.catalogSelectedArtist || this.catalogArtistQuery).trim();
            if (query.length < 2 || artist.length < 2) {
                this.catalogTitleSuggestions = [];
                this.showCatalogTitleSuggestions = false;
                return;
            }
            this.catalogTitleLookupTimer = setTimeout(() => this.fetchCatalogTitleSuggestions(artist, query), 250);
        },
        async fetchCatalogTitleSuggestions(artist, query) {
            const token = ++this.catalogTitleLookupToken;
            this.catalogTitleLookupBusy = true;
            try {
                const response = await fetch(`${config.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error('Title lookup failed');
                }

                const payload = await response.json();
                if (token !== this.catalogTitleLookupToken) {
                    return;
                }
                this.catalogTitleSuggestions = payload.titles || [];
                this.showCatalogTitleSuggestions = this.catalogTitleSuggestions.length > 0;
            } catch (error) {
                if (token === this.catalogTitleLookupToken) {
                    this.catalogTitleSuggestions = [];
                    this.showCatalogTitleSuggestions = false;
                }
            } finally {
                if (token === this.catalogTitleLookupToken) {
                    this.catalogTitleLookupBusy = false;
                }
            }
        },
        selectCatalogTitleSuggestion(track) {
            this.setCatalogAutocompleteValue('title', track.title);
            this.catalogSelectedDeezerDuration = track.duration ?? null;
            this.catalogDeezerTitleSelected = true;
            this.catalogTitleSuggestions = [];
            this.showCatalogTitleSuggestions = false;
        },
        openQuickSetModal() {
            this.quickSetAssignments = {};
            this.quickSetJamSessionId = '';
            this.quickSetName = '';
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'catalog-quick-set' }));
        },
        isQuickSetSlotDisabled(songId, slotName) {
            const selectedSlots = this.quickSetAssignments[songId] || [];
            return selectedSlots.some((selectedSlot) => (this.slotConflicts[slotName] || []).includes(selectedSlot));
        },
        toggleQuickSetAssignment(songId, slotName, selected) {
            const selectedSlots = this.quickSetAssignments[songId] || [];
            this.quickSetAssignments = {
                ...this.quickSetAssignments,
                [songId]: selected
                    ? [...new Set([...selectedSlots, slotName])]
                    : selectedSlots.filter((selectedSlot) => selectedSlot !== slotName),
            };
        },
        confirmQuickSetSubmission() {
            const uncoveredSongTitles = this.selectedSongIds
                .filter((songId) => (this.quickSetAssignments[songId] || []).length === 0)
                .map((songId) => {
                    const song = this.quickSetSongs[songId];

                    return song ? `${song.artist} - ${song.title}` : 'Unknown song';
                });

            if (uncoveredSongTitles.length === 0) {
                return true;
            }

            return window.confirm(`You have not picked a slot on:\n\n${uncoveredSongTitles.join('\n')}\n\nAre you sure you want to continue?`);
        },
        availableRequesterSlots() {
            return this.entryMode === 'template'
                ? (this.templateSlots[this.requestTemplateId] || [])
                : this.entrySlotNames;
        },
        async updateCapabilities(songId, form) {
            const formData = new FormData(form);
            formData.set('_method', 'PUT');
            const response = await fetch(`${config.catalogUrl}/${songId}/capabilities`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: formData,
            });
            if (!response.ok) {
                window.alert('Could not save your song capability choices.');

                return null;
            }

            return response.json();
        },
        async jsonSubmit(form, method = 'POST') {
            const response = await fetch(form.action, {
                method,
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = Object.values(payload.errors || {}).flat().join('\n');
                window.alert(errors || payload.message || 'Could not save this catalog change.');
                return null;
            }
            return payload;
        },
        async fetchCatalog(page = 1, searchParameters = null) {
            const form = this.$refs.catalogSearch;
            const parameters = searchParameters || new URLSearchParams(new FormData(form));
            parameters.set('page', page);
            this.searchLoading = true;
            try {
                const response = await fetch(`${form.action}?${parameters.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    throw new Error('Search failed');
                }
                const payload = await response.json();
                this.catalogPage = payload.pagination.current_page;
                this.renderCatalogSongs(payload.songs || [], payload.performers || [], payload.pagination);
                window.history.replaceState({}, '', `${form.action}?${parameters.toString()}`);
            } catch (error) {
                window.alert('Could not search the catalog.');
            } finally {
                this.searchLoading = false;
            }
        },
        searchCatalog() {
            this.fetchCatalog();
        },
        resetCatalogSearch() {
            this.$refs.catalogSearch.querySelector('[name=q]').value = '';
            this.selectedPerformerIds = [];
            this.performerFilterOpen = false;
            this.fetchCatalog(1, new URLSearchParams());
        },
        goToCatalogPage(page) {
            if (!this.searchLoading && page !== this.catalogPage) {
                this.fetchCatalog(page);
            }
        },
        slotLabel(slotName) {
            return this.slotLabels[slotName] || slotName.replaceAll('_', ' ');
        },
        catalogSlotChipClass(selected) {
            return selected
                ? 'inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700 transition'
                : 'inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-300';
        },
        catalogCapabilityCountClass(selected) {
            return selected
                ? 'inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-sky-300 bg-white px-1 text-[11px] font-semibold text-sky-700'
                : 'inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-1 text-[11px] font-semibold text-slate-600';
        },
        selectedPerformerLegend() {
            if (!this.currentCatalogPerformers.length) {
                return '';
            }

            return `${this.currentCatalogPerformers.map((performer) => performer.name).join(', ')} know these parts`;
        },
        songSlotSelected(song, slotName) {
            return (song.user_slot_names || []).includes(slotName);
        },
        setSongSlotSelected(song, slotName, selected) {
            const currentSlots = new Set(song.user_slot_names || []);
            if (selected) {
                currentSlots.add(slotName);
            } else {
                currentSlots.delete(slotName);
            }

            song.user_slot_names = Array.from(currentSlots);
        },
        hasPerformerMatches(song) {
            return Object.keys(song.performer_slots || {}).length > 0;
        },
        performersForSlot(song, slotName) {
            const matchingIds = song.performer_slots?.[slotName] || [];
            return (this.currentCatalogPerformers || []).filter((performer) => matchingIds.includes(performer.id));
        },
        performerSlotBadgeTitle(song, slotName) {
            const names = this.performersForSlot(song, slotName).map((performer) => performer.name).join(', ');
            return `${names} can play ${this.slotLabel(slotName)}`;
        },
        async updateSongSlotSelection(song, slotName, selected, form) {
            this.setSongSlotSelected(song, slotName, selected);
            const payload = await this.updateCapabilities(song.id, form);
            if (!payload) {
                this.setSongSlotSelected(song, slotName, !selected);
                return;
            }

            const nextCount = payload.slot_capability_counts?.[slotName];
            const targetSlot = (song.slots || []).find((slot) => slot.name === slotName);
            if (targetSlot && Number.isFinite(Number(nextCount))) {
                targetSlot.recent_capability_count = Math.max(0, Number(nextCount));
            }
        },
        catalogSummaryText() {
            const pagination = this.catalogPagination || { current_page: 1, last_page: 1, total: 0 };
            const total = Number(pagination.total || 0);
            return `Page ${pagination.current_page} of ${pagination.last_page} · ${total} song${total === 1 ? '' : 's'}`;
        },
        renderCatalogSongs(songs, performers, pagination) {
            this.quickSetSongs = {
                ...this.quickSetSongs,
                ...Object.fromEntries(songs.map((song) => [song.id, song])),
            };
            this.currentCatalogSongs = songs;
            this.currentCatalogPerformers = performers || [];
            this.catalogPagination = pagination || {
                current_page: 1,
                last_page: 1,
                total: songs.length,
            };
        },
        async submitCatalogSong(event) {
            const payload = await this.jsonSubmit(event.target);
            if (!payload) {
                return;
            }
            const { near_matches: nearMatches } = payload;
            await this.fetchCatalog(this.catalogPage);
            event.target.reset();
            this.resetCatalogSongForm();
            this.closeCatalogSongForm();
            if (nearMatches.length) {
                window.alert(`A similar song already exists: ${nearMatches[0].artist} - ${nearMatches[0].title}`);
            }
        },
        async submitCatalogRequest(event) {
            const payload = await this.jsonSubmit(event.target);
            if (!payload) {
                return;
            }
            event.target.reset();
            this.resetCatalogSongForm();
            this.closeCatalogSongForm();
            await this.fetchCatalog(this.catalogPage);
            this.setStatusMessage('Catalog song request sent for review.');
            if (payload.near_matches?.length) {
                window.alert(`A similar song already exists: ${payload.near_matches[0].artist} - ${payload.near_matches[0].title}`);
            }
        },
        async submitCatalogEdit(event) {
            const payload = await this.jsonSubmit(event.target, 'PUT');
            if (!payload) {
                return;
            }
            this.openEditSong = false;
            this.resetCatalogSongForm();
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-song-edit' }));
            await this.fetchCatalog(this.catalogPage);
            this.setStatusMessage('Catalog song updated.');
        },
        async deleteCatalogSong() {
            if (!this.editingSong || !window.confirm('Delete this catalog song? Existing set songs will remain, but they will no longer be linked to this catalog song.')) {
                return;
            }
            const response = await fetch(`${config.catalogUrl}/${this.editingSong.id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
            });
            if (!response.ok) {
                window.alert('Could not delete this catalog song.');
                return;
            }
            const payload = await response.json();
            this.currentCatalogSongs = this.currentCatalogSongs.filter((song) => song.id !== payload.deleted_id);
            this.selectedSongIds = this.selectedSongIds.filter((songId) => songId !== payload.deleted_id);
            this.openEditSong = false;
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-song-edit' }));
            this.setStatusMessage('Catalog song deleted.');
        },
        async respondToCatalogRequest(event) {
            const payload = await this.jsonSubmit(event.target, 'POST');
            if (!payload) {
                return;
            }
            this.removeCatalogRequest(payload.request_id, payload.remaining_request_count);
            if (payload.song) {
                this.quickSetSongs = {
                    ...this.quickSetSongs,
                    [payload.song.id]: payload.song,
                };
                this.currentCatalogSongs = [payload.song, ...this.currentCatalogSongs.filter((song) => song.id !== payload.song.id)];
            }
            this.setStatusMessage(`Catalog request ${payload.status}.`);
        },
        removeCatalogRequest(requestId, remainingRequestCount) {
            const catalogRequests = document.querySelector('[data-catalog-requests]');
            catalogRequests?.querySelector(`[data-catalog-request-id='${requestId}']`)?.remove();
            if (remainingRequestCount === 0 || !catalogRequests?.querySelector('[data-catalog-request-id]')) {
                document.querySelector('[data-catalog-requests-panel]')?.remove();
            }
        },
        async cancelCatalogRequest(event) {
            if (!window.confirm('Cancel this catalog request?')) {
                return;
            }
            const payload = await this.jsonSubmit(event.target, 'DELETE');
            if (!payload) {
                return;
            }
            this.removeCatalogRequest(payload.request_id, payload.remaining_request_count);
            this.setStatusMessage('Catalog request cancelled.');
        },
    };
}