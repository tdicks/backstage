export function liveQuickSet(config) {
    return {
        liveDisplayModalOpen: false,
        liveQuickSetStep: 1,
        selectedLiveSongIds: [],
        liveQuickSetAssignments: {},
        liveQuickSetCollapsedSongs: {},
        liveManagerId: config.liveManagerId,
        liveQuickSetSongs: [],
        liveQuickSetAttendees: [],
        liveQuickSetLoading: false,
        liveQuickSetSubmitting: false,
        liveQuickSetError: '',
        liveQuickSetPollTimer: null,
        async refreshLiveQuickSetData() {
            this.liveQuickSetLoading = true;
            try {
                const response = await fetch(config.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) { return; }
                const payload = await response.json();
                this.liveQuickSetSongs = payload.songs || [];
                this.liveQuickSetAttendees = payload.attendees || [];
            } finally { this.liveQuickSetLoading = false; }
        },
        startLiveQuickSetPolling() {
            clearInterval(this.liveQuickSetPollTimer);
            this.liveQuickSetPollTimer = setInterval(() => this.refreshLiveQuickSetData(), 5000);
        },
        stopLiveQuickSetPolling() {
            clearInterval(this.liveQuickSetPollTimer);
            this.liveQuickSetPollTimer = null;
        },
        openLiveQuickSet() {
            this.selectedLiveSongIds = [];
            this.liveQuickSetAssignments = {};
            this.liveQuickSetCollapsedSongs = {};
            this.liveQuickSetStep = 1;
            this.liveQuickSetError = '';
            this.refreshLiveQuickSetData();
            this.startLiveQuickSetPolling();
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'live-quick-set' }));
        },
        closeLiveQuickSet() {
            this.stopLiveQuickSetPolling();
            this.selectedLiveSongIds = [];
            this.liveQuickSetAssignments = {};
            this.liveQuickSetCollapsedSongs = {};
            this.liveQuickSetStep = 1;
            this.liveQuickSetError = '';
        },
        async submitLiveQuickSet(form) {
            this.liveQuickSetSubmitting = true;
            this.liveQuickSetError = '';
            try {
                const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) { this.liveQuickSetError = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Could not create the live quick set.'; return; }
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'live-quick-set' }));
            } catch (error) { this.liveQuickSetError = 'Could not create the live quick set.'; } finally { this.liveQuickSetSubmitting = false; }
        },
        isExcludedFromSlot(user, slotName) { return user.slot_coverage?.[slotName] === 'wont_cover'; },
        suggestedUsers(song, slotName) { const capableIds = song.capable_user_ids?.[slotName] || []; const confirmedIds = song.confirmed_user_ids?.[slotName] || []; return this.liveQuickSetAttendees.filter(user => capableIds.includes(user.id) && !this.isExcludedFromSlot(user, slotName)).sort((left, right) => Number(confirmedIds.includes(right.id)) - Number(confirmedIds.includes(left.id))); },
        otherCheckedInUsers(song, slotName) { const capableIds = song.capable_user_ids?.[slotName] || []; return this.liveQuickSetAttendees.filter(user => !capableIds.includes(user.id) && !this.isExcludedFromSlot(user, slotName)); },
        toggleLiveSong(songId, selected) { this.selectedLiveSongIds = selected ? [...new Set([...this.selectedLiveSongIds, songId])] : this.selectedLiveSongIds.filter(id => id !== songId); },
        isLiveQuickSetAssignmentDisabled(songId, slotName, userId) { if (!userId) { return false; } return Object.entries(this.liveQuickSetAssignments[songId] || {}).some(([assignedSlotName, assignedUserId]) => assignedSlotName !== slotName && String(assignedUserId) === String(userId) && (config.slotConflicts[slotName] || []).includes(assignedSlotName)); },
        topSuggestedUsers(song, slotName) { return this.suggestedUsers(song, slotName).filter(user => !this.isLiveQuickSetAssignmentDisabled(song.id, slotName, user.id)).slice(0, 3); },
        setLiveQuickSetAssignment(songId, slotName, userId) { this.liveQuickSetAssignments = { ...this.liveQuickSetAssignments, [songId]: { ...(this.liveQuickSetAssignments[songId] || {}), [slotName]: userId } }; },
        isLiveQuickSetAssignmentSelected(songId, slotName, userId) { return String(this.liveQuickSetAssignments[songId]?.[slotName] || '') === String(userId); },
        isLiveQuickSetSongFullyAssigned(song) { return song.slots.every(slot => Boolean(this.liveQuickSetAssignments[song.id]?.[slot.name])); },
        toggleLiveQuickSetSongCollapsed(songId) { this.liveQuickSetCollapsedSongs = { ...this.liveQuickSetCollapsedSongs, [songId]: !this.liveQuickSetCollapsedSongs[songId] }; },
        completeLiveQuickSetSong(songId) {
            this.liveQuickSetCollapsedSongs = { ...this.liveQuickSetCollapsedSongs, [songId]: true };
            this.$nextTick(() => { const assignmentList = this.$refs.liveQuickSetAssignmentList; const completedSongCard = assignmentList?.querySelector(`[data-live-quick-set-song-id='${songId}']`); if (!assignmentList || !completedSongCard) { return; } assignmentList.scrollTo({ top: assignmentList.scrollTop + completedSongCard.getBoundingClientRect().top - assignmentList.getBoundingClientRect().top, behavior: 'smooth' }); });
        },
        isLiveQuickSetSongCollapsed(songId) { return Boolean(this.liveQuickSetCollapsedSongs[songId]); },
        selectedLiveQuickSetSongs() { return this.liveQuickSetSongs.filter(song => this.selectedLiveSongIds.includes(song.id)); },
        liveQuickSetHasDurationEstimate() { const songs = this.selectedLiveQuickSetSongs(); return songs.length > 0 && songs.every(song => Number(song.duration) > 0 && song.source); },
        liveQuickSetAttendeeName(userId) { return this.liveQuickSetAttendees.find(user => String(user.id) === String(userId))?.name || 'Unknown attendee'; },
        liveQuickSetCoverageDescription(song, type) { const assignments = song.suggested_assignments || {}; return song.slots.filter(slot => { const userId = assignments[slot.name]; if (type === 'uncovered') { return !userId; } const isConfirmed = (song.confirmed_user_ids?.[slot.name] || []).map(String).includes(String(userId)); return userId && (type === 'confirmed' ? isConfirmed : !isConfirmed); }).map(slot => type === 'uncovered' ? this.slotLabel(slot.name) : `${this.slotLabel(slot.name)}: ${this.liveQuickSetAttendeeName(assignments[slot.name])}`).join('; '); },
        liveQuickSetAssignedUserCount() { return new Set(this.selectedLiveQuickSetSongs().flatMap(song => Object.values(this.liveQuickSetAssignments[song.id] || {})).filter(Boolean).map(String)).size; },
        liveQuickSetTransitionDuration() { return Math.max(0, this.liveQuickSetAssignedUserCount() - 1) * config.transitionSeconds; },
        liveQuickSetTotalDuration() { return this.selectedLiveQuickSetSongs().reduce((total, song) => total + Number(song.duration || 0), 0) + this.liveQuickSetTransitionDuration(); },
        formatLiveQuickSetDuration(seconds) { return window.setDuration.format(seconds); },
        liveQuickSetDurationStatusClass() { const hasSelectedSongs = this.selectedLiveQuickSetSongs().length > 0; return window.setDuration.statusClass(this.liveQuickSetTotalDuration(), hasSelectedSongs ? this.liveQuickSetHasDurationEstimate() : null); },
        slotLabel(slotName) { return config.slotLabels[slotName] || slotName.replaceAll('_', ' '); },
        destroy() { this.stopLiveQuickSetPolling(); },
    };
}