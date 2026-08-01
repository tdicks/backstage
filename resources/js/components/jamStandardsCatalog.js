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
                this.selectedSongIds = [];
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
        createPerformerSlotBadge(performers, slotName) {
            const badge = document.createElement('span');
            badge.className = 'inline-flex h-4 w-4 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-[11px] font-bold text-emerald-700';
            badge.textContent = '\u2713';
            badge.title = `${performers.map((performer) => performer.name).join(', ')} can play ${this.slotLabel(slotName)}`;
            badge.setAttribute('aria-label', badge.title);
            return badge;
        },
        renderPerformerCapabilityLegend(performers) {
            this.$refs.performerCapabilityLegend.replaceChildren();
            if (!performers.length) {
                return;
            }
            const badge = document.createElement('span');
            badge.className = 'inline-flex h-5 w-5 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-xs font-bold text-emerald-700';
            badge.textContent = '\u2713';
            const label = document.createElement('p');
            label.className = 'text-xs font-medium text-slate-600';
            label.textContent = `${performers.map((performer) => performer.name).join(', ')} know these parts`;
            this.$refs.performerCapabilityLegend.append(badge, label);
        },
        appendCatalogSong(song, performers = []) {
            const row = document.createElement('tr');
            row.dataset.catalogSongId = song.id;
            row.className = this.catalogRowClass(this.selectedSongIds.includes(song.id));
            const selectionCell = document.createElement('td');
            selectionCell.className = 'cursor-pointer px-4 py-3';
            const selection = document.createElement('input');
            selection.type = 'checkbox';
            selection.className = 'cursor-pointer rounded border-slate-300 text-amber-600 focus:ring-amber-500';
            selection.setAttribute('aria-label', `Select ${song.artist} - ${song.title}`);
            selection.addEventListener('change', () => {
                this.toggleSong(song.id, selection.checked);
                row.className = this.catalogRowClass(selection.checked);
            });
            selectionCell.addEventListener('click', (event) => {
                if (event.target !== selection) {
                    selection.click();
                }
            });
            selectionCell.append(selection);
            row.append(selectionCell);
            [song.artist, song.title].forEach((value, index) => {
                const cell = document.createElement('td');
                cell.className = 'px-4 py-3 text-sm text-slate-700';
                cell.textContent = value;
                if (index === 0) {
                    cell.classList.add('font-medium', 'text-slate-900');
                    cell.dataset.catalogArtist = '';
                }
                if (index === 1) {
                    cell.dataset.catalogTitle = '';
                    if (song.notes) {
                        const notes = document.createElement('p');
                        notes.className = 'mt-1 text-xs text-slate-500';
                        notes.textContent = song.notes;
                        cell.append(notes);
                    }
                }
                row.append(cell);
            });
            const slotsCell = document.createElement('td');
            slotsCell.dataset.catalogSlots = '';
            slotsCell.className = 'px-4 py-3 text-sm text-slate-700';
            const capabilityForm = document.createElement('form');
            capabilityForm.className = 'flex flex-wrap gap-2';
            song.slots.forEach((slot) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'slot_names[]';
                input.value = slot.name;
                input.checked = (song.user_slot_names || []).includes(slot.name);
                input.className = 'sr-only';
                label.className = this.catalogSlotChipClass(input.checked);
                const capabilityCount = document.createElement('span');
                capabilityCount.className = this.catalogCapabilityCountClass(input.checked);
                capabilityCount.textContent = String(Math.max(0, Number(slot.recent_capability_count || 0)));
                label.append(input, document.createTextNode(this.slotLabel(slot.name)), capabilityCount);
                const matchingPerformers = performers.filter((performer) => (song.performer_slots?.[slot.name] || []).includes(performer.id));
                if (matchingPerformers.length) {
                    label.append(this.createPerformerSlotBadge(matchingPerformers, slot.name));
                }
                input.addEventListener('change', async () => {
                    label.className = this.catalogSlotChipClass(input.checked);
                    capabilityCount.className = this.catalogCapabilityCountClass(input.checked);
                    const payload = await this.updateCapabilities(song.id, capabilityForm);
                    if (payload) {
                        capabilityCount.textContent = String(Math.max(0, Number(payload.slot_capability_counts?.[slot.name] || 0)));
                    }
                });
                capabilityForm.append(label);
            });
            slotsCell.append(capabilityForm);
            row.append(slotsCell);
            if (this.canEditCatalog) {
                const actionCell = document.createElement('td');
                actionCell.className = 'px-4 py-3 text-right';
                const actionButton = document.createElement('button');
                actionButton.type = 'button';
                actionButton.className = 'inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400';
                actionButton.setAttribute('aria-label', 'Song actions');
                actionButton.title = 'Song actions';
                const actionIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                actionIcon.setAttribute('viewBox', '0 0 24 24');
                actionIcon.setAttribute('fill', 'currentColor');
                actionIcon.setAttribute('aria-hidden', 'true');
                actionIcon.classList.add('h-4', 'w-4');
                const actionIconPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                actionIconPath.setAttribute('fill-rule', 'evenodd');
                actionIconPath.setAttribute('d', 'M3.75 5.25a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15a.75.75 0 0 1-.75-.75Zm0 6a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15a.75.75 0 0 1-.75-.75Zm0 6a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15a.75.75 0 0 1-.75-.75Z');
                actionIconPath.setAttribute('clip-rule', 'evenodd');
                actionIcon.append(actionIconPath);
                actionButton.append(actionIcon);
                actionButton.addEventListener('click', () => this.toggleCatalogActionMenu(song, actionButton));
                actionCell.append(actionButton);
                row.append(actionCell);
            }
            this.$refs.catalogRows.prepend(row);
        },
        renderCatalogSongs(songs, performers, pagination) {
            this.quickSetSongs = {
                ...this.quickSetSongs,
                ...Object.fromEntries(songs.map((song) => [song.id, song])),
            };
            this.$refs.catalogRows.replaceChildren();
            this.renderPerformerCapabilityLegend(performers || []);
            if (!songs.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = this.canEditCatalog ? 5 : 4;
                cell.className = 'px-4 py-10 text-center text-sm text-slate-500';
                cell.textContent = 'No catalog songs found.';
                row.append(cell);
                this.$refs.catalogRows.append(row);
                this.renderCatalogPagination(pagination);
                return;
            }
            [...songs].reverse().forEach((song) => this.appendCatalogSong(song, performers || []));
            this.renderCatalogPagination(pagination);
        },
        renderCatalogPagination(pagination) {
            this.$refs.catalogPagination.replaceChildren();
            if (pagination.total === 0) {
                return;
            }
            const controls = document.createElement('div');
            controls.className = 'flex flex-wrap items-center justify-between gap-3';
            const summary = document.createElement('p');
            summary.className = 'text-sm text-slate-600';
            summary.textContent = `Page ${pagination.current_page} of ${pagination.last_page} · ${pagination.total} song${pagination.total === 1 ? '' : 's'}`;
            if (pagination.last_page <= 1) {
                this.$refs.catalogPagination.append(controls);
                return;
            }
            const buttons = document.createElement('div');
            buttons.className = 'flex gap-2';
            [['Previous', pagination.current_page - 1, pagination.current_page === 1], ['Next', pagination.current_page + 1, pagination.current_page === pagination.last_page]].forEach(([label, page, disabled]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'inline-flex items-center border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50';
                button.textContent = label;
                button.disabled = disabled;
                button.addEventListener('click', () => this.goToCatalogPage(page));
                buttons.append(button);
            });
            controls.append(summary, buttons);
            this.$refs.catalogPagination.append(controls);
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
            this.appendOwnCatalogRequest(payload.request);
            event.target.reset();
            this.resetCatalogSongForm();
            this.closeCatalogSongForm();
            this.setStatusMessage('Catalog song request sent for review.');
            if (payload.near_matches?.length) {
                window.alert(`A similar song already exists: ${payload.near_matches[0].artist} - ${payload.near_matches[0].title}`);
            }
        },
        appendOwnCatalogRequest(request) {
            if (!request) {
                return;
            }

            let panel = document.querySelector('[data-catalog-requests-panel]');
            if (!panel) {
                panel = document.createElement('section');
                panel.dataset.catalogRequestsPanel = '';
                panel.className = 'mb-6 rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6';
                const heading = document.createElement('h3');
                heading.className = 'text-lg font-semibold text-slate-900';
                heading.textContent = 'Catalog Requests';
                const requests = document.createElement('div');
                requests.dataset.catalogRequests = '';
                requests.className = 'mt-3 space-y-3';
                panel.append(heading, requests);
                document.querySelector('[data-catalog-section]')?.before(panel);
            }

            const requests = panel.querySelector('[data-catalog-requests]');
            if (!requests || requests.querySelector(`[data-catalog-request-id='${request.id}']`)) {
                return;
            }

            const row = document.createElement('div');
            row.dataset.catalogRequestId = request.id;
            row.className = 'flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white/90 p-4 shadow-sm';
            const song = document.createElement('p');
            song.className = 'text-sm text-slate-700';
            const songName = document.createElement('span');
            songName.className = 'font-semibold';
            songName.textContent = `${request.artist} - ${request.title}`;
            song.append(songName, document.createTextNode(' requested by you'));
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${config.catalogRequestsUrl}/${request.id}`;
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = config.csrfToken;
            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'inline-flex items-center rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400';
            button.textContent = 'Cancel';
            form.append(csrfToken, method, button);
            form.addEventListener('submit', (submitEvent) => {
                submitEvent.preventDefault();
                this.cancelCatalogRequest({ target: form });
            });
            row.append(song, form);
            requests.prepend(row);
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
            this.$refs.catalogRows.querySelector(`[data-catalog-song-id='${payload.deleted_id}']`)?.remove();
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
                this.appendCatalogSong(payload.song);
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