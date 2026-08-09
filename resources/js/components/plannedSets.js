export function plannedSetsPage(config) {
	return {
		sets: config.initialSets || [],
		currentUserId: Number(config.currentUserId || 0),
		currentUserIsAdmin: Boolean(config.currentUserIsAdmin || false),
		attendanceSessionOptions: config.attendanceSessionOptions || [],
		scheduleSessionOptions: config.scheduleSessionOptions || [],
		collaboratorOptions: config.collaboratorOptions || [],
		templateOptions: config.templateOptions || [],
		jamStandardSongs: config.jamStandardSongs || [],
		slotOptions: config.slotOptions || {},
		slotConflicts: config.slotConflicts || {},
		destroySlotUrlTemplate: config.destroySlotUrlTemplate || '',
		filterQuery: '',
		filterMenuOpen: false,
		selectedAttributeFilters: [],
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
		statusMessage: '',
		errorMessage: '',
		editorBusy: false,
		scheduleBusy: false,
		songBusy: false,
		slotBusy: false,
		slotActionBusy: false,
		attendanceSaving: {},
		activeAvailabilitySetId: null,
		activeSlotAction: {
			set_id: null,
			song_id: null,
			slot_id: null,
			mode: null,
			message: '',
			target_user_id: '',
		},
		collaboratorQuery: '',
		collaboratorSuggestions: [],
		showCollaboratorSuggestions: false,
		dropoutPrompt: {
			jam_session_id: null,
			jam_session_name: '',
			action: 'keep_claimable',
		},
		editor: {
			id: null,
			name: '',
			description: '',
			is_hidden: false,
			free_for_all: false,
			song_requests: true,
			signups_open: false,
			collaborator_ids: [],
			candidate_session_ids: [],
		},
		scheduleForm: {
			set_id: null,
			jam_session_id: '',
			not_going_slot_action: '',
		},
		songEditor: {
			set_id: null,
			artist: '',
			title: '',
			notes: '',
			duration: null,
			source: null,
			song_slot_addition_mode: 'template',
			band_template_id: '',
			slot_names: [],
		},
		requestSongSetId: null,
		requestSongBusy: false,
		requestSongError: '',
		requestSongMode: 'manual',
		requestCatalogSongId: '',
		requestSongNotes: '',
		requestSongSlotNames: [],
		requestArtistQuery: '',
		requestTitleQuery: '',
		requestSelectedArtistName: '',
		requestArtistSuggestions: [],
		requestTitleSuggestions: [],
		showRequestArtistSuggestions: false,
		showRequestTitleSuggestions: false,
		requestArtistLookupBusy: false,
		requestTitleLookupBusy: false,
		requestArtistLookupError: '',
		requestTitleLookupError: '',
		requestArtistLookupTimer: null,
		requestTitleLookupTimer: null,
		requestArtistLookupToken: 0,
		requestTitleLookupToken: 0,
		songArtistQuery: '',
		songTitleQuery: '',
		songSelectedArtistName: '',
		selectedDeezerDuration: null,
		deezerTitleSelected: false,
		songArtistSuggestions: [],
		songTitleSuggestions: [],
		showSongArtistSuggestions: false,
		showSongTitleSuggestions: false,
		songArtistLookupBusy: false,
		songTitleLookupBusy: false,
		songArtistLookupError: '',
		songTitleLookupError: '',
		songArtistLookupTimer: null,
		songTitleLookupTimer: null,
		songArtistLookupToken: 0,
		songTitleLookupToken: 0,
		songEditEditor: {
			set_id: null,
			song_id: null,
			artist: '',
			title: '',
			notes: '',
			duration: null,
			source: null,
		},
		songEditBusy: false,
		songEditArtistQuery: '',
		songEditTitleQuery: '',
		songEditSelectedArtistName: '',
		songEditSelectedDeezerDuration: null,
		songEditDeezerTitleSelected: false,
		songEditArtistSuggestions: [],
		songEditTitleSuggestions: [],
		showSongEditArtistSuggestions: false,
		showSongEditTitleSuggestions: false,
		songEditArtistLookupBusy: false,
		songEditTitleLookupBusy: false,
		songEditArtistLookupError: '',
		songEditTitleLookupError: '',
		songEditArtistLookupTimer: null,
		songEditTitleLookupTimer: null,
		songEditArtistLookupToken: 0,
		songEditTitleLookupToken: 0,
		slotEditor: {
			set_id: null,
			song_id: null,
			addition_mode: 'individual',
			name: '',
			notes: '',
			band_template_id: '',
		},
		slotEditEditor: {
			set_id: null,
			song_id: null,
			slot_id: null,
			name: '',
			notes: '',
		},
		slotEditBusy: false,
		initialEditAssignedUserId: '',
		initialEditAssignedUserName: '',
		initialEditManualPerformerName: '',
		editAssignedUserId: '',
		editAssignedUserName: '',
		editAssignedUserQuery: '',
		showEditUserSuggestions: false,
		assignmentConflictMessage: '',
		assignmentConflictPending: false,
		assignmentConflictCooldown: false,
		assignmentConflictTimer: null,

		init() {
			this.sets = (this.sets || []).map((set) => this.decorateSetForUi(set));
		},

		totalSetCount() {
			return (this.sets || []).length;
		},

		visibleSetCount() {
			return this.filteredSets().length;
		},

		hasActiveFilters() {
			return this.filterQuery.trim().length > 0 || this.selectedAttributeFilters.length > 0;
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

		clearFilters() {
			this.filterQuery = '';
			this.selectedAttributeFilters = [];
			this.filterMenuOpen = false;
		},

		isPerformingOnSet(set) {
			if (!set) {
				return false;
			}

			return (set.songs || []).some((song) =>
				(song.slots || []).some((slot) => Number(slot.user_id || 0) === Number(this.currentUserId))
			);
		},

		matchesNonTextFilters(set) {
			if (this.selectedAttributeFilters.length === 0) {
				return true;
			}

			const isPerformed = Boolean(set?.performed);
			const isSignupsOpen = Boolean(set?.signups_open);
			const isHidden = Boolean(set?.is_hidden);
			const isFreeForAll = Boolean(set?.free_for_all);
			const hasAttachments = Boolean(set?.has_attachments);
			const isOwnedByCurrentUser = Number(set?.owner?.id || 0) === Number(this.currentUserId);
			const isCollaborating = (set?.collaborator_ids || []).map((id) => Number(id)).includes(Number(this.currentUserId));
			const isPerformingOnSet = this.isPerformingOnSet(set);

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

		setTextHaystack(set) {
			const songText = (set?.songs || [])
				.map((song) => `${song.artist || ''} ${song.title || ''}`.trim())
				.join(' ');

			const participantText = [
				set?.owner?.name || '',
				...((set?.collaborators || []).map((collaborator) => collaborator.name || '')),
			].join(' ');

			return `${set?.name || ''} ${participantText} ${songText}`.toLowerCase();
		},

		matchesFilters(set) {
			if (!this.matchesNonTextFilters(set)) {
				return false;
			}

			const query = this.filterQuery.trim().toLowerCase();
			if (!query) {
				return true;
			}

			return this.setTextHaystack(set).includes(query);
		},

		filteredSets() {
			return (this.sets || []).filter((set) => this.matchesFilters(set));
		},

		decorateSetForUi(set) {
			if (!set) {
				return set;
			}

			return {
				...set,
				pending_song_requests: (set.pending_song_requests || []).map((songRequest) => ({
					...songRequest,
					selected_band_template_id: songRequest?.band_template_id ? String(songRequest.band_template_id) : '',
					approved_slot_names: [],
					busy: false,
					error: '',
				})),
				pending_slot_requests: (set.pending_slot_requests || []).map((slotRequest) => ({
					...slotRequest,
					busy: false,
					error: '',
				})),
			};
		},

		slotRequestsSummary(set) {
			const requestCount = (set?.pending_slot_requests || []).filter((request) => request.type === 'request').length;
			const recommendationCount = (set?.pending_slot_requests || []).filter((request) => request.type === 'proposal').length;

			if (requestCount > 0 && recommendationCount > 0) {
				return `${requestCount} request${requestCount === 1 ? '' : 's'} and ${recommendationCount} recommendation${recommendationCount === 1 ? '' : 's'}`;
			}

			if (requestCount > 0) {
				return `${requestCount} slot request${requestCount === 1 ? '' : 's'}`;
			}

			if (recommendationCount > 0) {
				return `${recommendationCount} slot recommendation${recommendationCount === 1 ? '' : 's'}`;
			}

			return 'No slot requests pending.';
		},

		songRequestSlotSelected(songRequest, slotName) {
			return (songRequest?.approved_slot_names || []).includes(slotName);
		},

		slotsConflict(firstSlotName, secondSlotName) {
			if (firstSlotName === secondSlotName) {
				return false;
			}

			const firstConflicts = this.slotConflicts[firstSlotName] || [];
			const secondConflicts = this.slotConflicts[secondSlotName] || [];

			return firstConflicts.includes(secondSlotName) || secondConflicts.includes(firstSlotName);
		},

		songRequestSlotSelectionDisabled(songRequest, slotName) {
			if (songRequest?.busy) {
				return true;
			}

			if (this.songRequestSlotSelected(songRequest, slotName)) {
				return false;
			}

			return (songRequest?.approved_slot_names || []).some((selectedSlotName) => this.slotsConflict(selectedSlotName, slotName));
		},

		toggleApprovedSongRequestSlot(songRequest, slotName) {
			if (!songRequest || songRequest.busy) {
				return;
			}

			if (this.songRequestSlotSelected(songRequest, slotName)) {
				songRequest.approved_slot_names = (songRequest.approved_slot_names || []).filter((name) => name !== slotName);
				return;
			}

			if (this.songRequestSlotSelectionDisabled(songRequest, slotName)) {
				return;
			}

			songRequest.approved_slot_names = [...(songRequest.approved_slot_names || []), slotName];
		},

		openCreateModal() {
			this.editor = {
				id: null,
				name: '',
				description: '',
				is_hidden: false,
				free_for_all: false,
				song_requests: true,
				signups_open: false,
				collaborator_ids: [],
				candidate_session_ids: [],
			};
			this.resetCollaboratorPicker();
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-editor' }));
		},

		openEditModal(set) {
			if (!set?.can_edit) {
				return;
			}

			this.editor = {
				id: Number(set.id),
				name: set.name || '',
				description: set.description || '',
				is_hidden: Boolean(set.is_hidden),
				free_for_all: Boolean(set.free_for_all),
				song_requests: Boolean(set.song_requests),
				signups_open: Boolean(set.signups_open),
				collaborator_ids: (set.collaborator_ids || []).map((id) => Number(id)),
				candidate_session_ids: (set.candidate_session_ids || []).map((id) => Number(id)),
			};
			this.resetCollaboratorPicker();
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-editor' }));
		},

		openAddSongModal(set) {
			if (!set?.can_manage) {
				return;
			}

			this.songEditor = {
				set_id: Number(set.id),
				artist: '',
				title: '',
				notes: '',
				duration: null,
				source: null,
				song_slot_addition_mode: 'template',
				band_template_id: '',
				slot_names: [],
			};
			this.resetSongLookupState();
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-add-song' }));
		},

		canRequestSongForSet(set) {
			if (!set) {
				return false;
			}

			return Boolean(set.song_requests) && !this.isSetManager(set);
		},

		currentSongRequestSet() {
			return this.sets.find((set) => Number(set.id) === Number(this.requestSongSetId)) || null;
		},

		openSongRequestModal(set) {
			if (!this.canRequestSongForSet(set)) {
				return;
			}

			this.requestSongSetId = Number(set.id);
			this.requestSongMode = 'manual';
			this.resetSongRequestAutocomplete();
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-request-song' }));
		},

		closeSongRequestModal() {
			window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-request-song' }));
			this.resetSongRequestAutocomplete();
		},

		openEditSongModal(set, song) {
			if (!set?.can_manage || !song?.id) {
				return;
			}

			this.songEditEditor = {
				set_id: Number(set.id),
				song_id: Number(song.id),
				artist: song.artist || '',
				title: song.title || '',
				notes: song.notes || '',
				duration: song.duration ?? null,
				source: song.source ?? null,
			};
			this.resetSongEditLookupState();
			this.songEditArtistQuery = song.artist || '';
			this.songEditTitleQuery = song.title || '';
			this.songEditSelectedArtistName = song.artist || '';
			this.songEditSelectedDeezerDuration = song.duration ?? null;
			this.songEditDeezerTitleSelected = false;
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-edit-song' }));
		},

		openAddSlotModal(set, song) {
			if (!set?.can_manage || !song?.id) {
				return;
			}

			const slotKeys = Object.keys(this.slotOptions || {});
			this.slotEditor = {
				set_id: Number(set.id),
				song_id: Number(song.id),
				addition_mode: 'individual',
				name: slotKeys[0] || '',
				notes: '',
				band_template_id: '',
			};
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-add-slot' }));
		},

		openSlotActions(set, song, slot) {
			if (!set?.id || !slot?.id) {
				return;
			}

			if (Number(this.activeSlotAction.slot_id) === Number(slot.id)) {
				this.resetSlotActionState();
				return;
			}

			this.activeSlotAction = {
				set_id: Number(set.id),
				song_id: Number(song.id),
				slot_id: Number(slot.id),
				mode: null,
				message: '',
				target_user_id: '',
			};
			this.errorMessage = '';
		},

		closeSlotActions() {
			this.resetSlotActionState();
		},

		isSlotActionPopoverOpen(slotId) {
			return Number(this.activeSlotAction.slot_id) === Number(slotId);
		},

		activeSlotContext() {
			const set = this.sets.find((candidate) => Number(candidate.id) === Number(this.activeSlotAction.set_id));
			if (!set) {
				return null;
			}

			const song = (set.songs || []).find((candidate) => Number(candidate.id) === Number(this.activeSlotAction.song_id));
			if (!song) {
				return null;
			}

			const slot = (song.slots || []).find((candidate) => Number(candidate.id) === Number(this.activeSlotAction.slot_id));
			if (!slot) {
				return null;
			}

			return {
				set,
				song,
				slot,
			};
		},

		recommendedUsersForActiveSlot() {
			const slotContext = this.activeSlotContext();
			if (!slotContext) {
				return [];
			}

			const excludeIds = new Set([
				Number(this.currentUserId),
				Number(slotContext.slot.user_id || 0),
			]);

			return (this.collaboratorOptions || []).filter((candidate) => !excludeIds.has(Number(candidate.id)));
		},

		isSetManager(set) {
			if (!set) {
				return false;
			}

			if (set.can_manage) {
				return true;
			}

			if (this.currentUserIsAdmin) {
				return true;
			}

			return Number(set.owner?.id) === Number(this.currentUserId)
				|| (set.collaborator_ids || []).map((id) => Number(id)).includes(Number(this.currentUserId));
		},

		isAdminManagingOtherSet(set) {
			if (!set || !this.currentUserIsAdmin) {
				return false;
			}

			const isOwner = Number(set.owner?.id) === Number(this.currentUserId);
			const isCollaborator = (set.collaborator_ids || []).map((id) => Number(id)).includes(Number(this.currentUserId));

			return !isOwner && !isCollaborator;
		},

		canTakeActiveSlot() {
			const context = this.activeSlotContext();
			const slot = context?.slot;
			if (!slot || !context?.set) {
				return false;
			}

			const isManager = this.isSetManager(context.set);
			if (!context.set.signups_open && !isManager) {
				return false;
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return false;
			}

			if (!slot.is_open && !slot.is_claimable_manual) {
				return false;
			}

			if (isManager) {
				return true;
			}

			return Boolean(context.set.free_for_all);
		},

		canRequestActiveSlot() {
			const context = this.activeSlotContext();
			const slot = context?.slot;
			if (!slot || !context?.set) {
				return false;
			}

			if (!context.set.signups_open) {
				return false;
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return false;
			}

			if (this.isSetManager(context.set)) {
				return false;
			}

			if (context.set.free_for_all) {
				return false;
			}

			if (!slot.is_open && !slot.is_claimable_manual) {
				return false;
			}

			return !slot.has_pending_own_request;
		},

		canRecommendActiveSlot() {
			const context = this.activeSlotContext();
			const slot = context?.slot;
			if (!slot) {
				return false;
			}

			if (!context?.set?.signups_open) {
				return false;
			}

			if (!slot.is_open && !slot.is_claimable_manual) {
				return false;
			}

			return this.recommendedUsersForActiveSlot().length > 0;
		},

		canReleaseActiveSlot() {
			const context = this.activeSlotContext();
			const slot = context?.slot;
			if (!slot || slot.is_open) {
				return false;
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return true;
			}

			return this.isSetManager(context.set);
		},

		canToggleClaimableActiveSlot() {
			const context = this.activeSlotContext();
			const slot = context?.slot;
			if (!slot || slot.is_open || !slot.user_id) {
				return false;
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return true;
			}

			return this.isSetManager(context.set);
		},

		canEditActiveSlot() {
			const context = this.activeSlotContext();
			if (!context?.slot || !context?.set) {
				return false;
			}

			return Boolean(context.set.can_manage);
		},

		canDeleteActiveSlot() {
			const context = this.slotEditContext();
			return Boolean(context?.set?.can_manage && context?.slot?.id);
		},

		destroySlotUrl() {
			const context = this.slotEditContext();
			if (!context?.slot?.id) {
				return '';
			}

			return (this.destroySlotUrlTemplate || '').replace('__SLOT_ID__', String(context.slot.id));
		},

		async deleteActiveSlot() {
			const context = this.slotEditContext();
			if (this.slotEditBusy || !context) {
				return;
			}

			const confirmed = window.confirm('Delete this slot?');
			if (!confirmed) {
				return;
			}

			this.slotEditBusy = true;
			this.errorMessage = '';

			try {
				const body = new FormData();
				body.set('_method', 'DELETE');

				const response = await fetch(this.destroySlotUrl(), {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': config.csrfToken,
					},
					body,
				});

				if (!response.ok) {
					throw new Error('Request failed');
				}

				this.closeEditSlotModal();
				this.resetSlotActionState();
				this.statusMessage = 'Slot deleted.';
				this.replaceSetSong(context.set.id, {
					...context.song,
					slots: (context.song.slots || []).filter((slot) => Number(slot.id) !== Number(context.slot.id)),
				});
			} catch (e) {
				this.errorMessage = e?.message || 'Could not delete slot.';
			} finally {
				this.slotEditBusy = false;
			}
		},

		openEditSlotModal() {
			const context = this.activeSlotContext();
			if (!context || !this.canEditActiveSlot()) {
				return;
			}

			this.slotEditEditor = {
				set_id: Number(context.set.id),
				song_id: Number(context.song.id),
				slot_id: Number(context.slot.id),
				name: context.slot.name || '',
				notes: context.slot.notes || '',
			};

			this.initialEditAssignedUserId = context.slot.user_id ? String(context.slot.user_id) : '';
			this.initialEditAssignedUserName = context.slot.user_id ? (context.slot.user_name || '') : '';
			this.initialEditManualPerformerName = context.slot.manual_performer_name || '';
			this.editAssignedUserId = this.initialEditAssignedUserId;
			this.editAssignedUserName = this.initialEditAssignedUserName;
			this.editAssignedUserQuery = this.initialEditAssignedUserName || this.initialEditManualPerformerName || '';
			this.showEditUserSuggestions = false;
			this.resetAssignmentConflict();
			this.closeSlotActions();

			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-edit-slot' }));
		},

		closeEditSlotModal() {
			window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-edit-slot' }));
			this.showEditUserSuggestions = false;
			this.resetAssignmentConflict();
		},

		canSelectUser(user) {
			return user?.selectable !== false;
		},

		splitUserGroups(users) {
			return users.reduce((groups, user) => {
				if (user.attendance_group === 'not_attending') {
					groups.notAttending.push(user);
					return groups;
				}

				groups.available.push(user);
				return groups;
			}, { available: [], notAttending: [] });
		},

		slotEditContext() {
			const set = this.sets.find((candidate) => Number(candidate.id) === Number(this.slotEditEditor.set_id));
			if (!set) {
				return null;
			}

			const song = (set.songs || []).find((candidate) => Number(candidate.id) === Number(this.slotEditEditor.song_id));
			if (!song) {
				return null;
			}

			const slot = (song.slots || []).find((candidate) => Number(candidate.id) === Number(this.slotEditEditor.slot_id));
			if (!slot) {
				return null;
			}

			return {
				set,
				song,
				slot,
			};
		},

		slotEditUserOptions() {
			const context = this.slotEditContext();
			if (!context?.set) {
				return [];
			}

			const participantIds = new Set((context.set.participant_ids || []).map((id) => Number(id)));
			return (this.collaboratorOptions || [])
				.filter((candidate) => participantIds.has(Number(candidate.id)))
				.map((candidate) => ({
					id: Number(candidate.id),
					name: candidate.name,
					attendance_group: 'available',
					selectable: true,
				}));
		},

		filteredEditUsers() {
			const query = this.editAssignedUserQuery.trim().toLowerCase();
			const users = this.slotEditUserOptions();
			const filtered = query === ''
				? users
				: users.filter((user) => user.name.toLowerCase().includes(query));

			return filtered.slice(0, 16);
		},

		groupedEditUsers() {
			return this.splitUserGroups(this.filteredEditUsers());
		},

		updateEditUserQuery() {
			this.editAssignedUserId = '';
			this.showEditUserSuggestions = true;
			this.resetAssignmentConflict();
		},

		selectEditUser(user) {
			if (!this.canSelectUser(user)) {
				return;
			}

			this.editAssignedUserId = String(user.id);
			this.editAssignedUserQuery = user.name;
			this.editAssignedUserName = user.name;
			this.showEditUserSuggestions = false;
			this.resetAssignmentConflict();
		},

		resetAssignmentConflict() {
			this.assignmentConflictMessage = '';
			this.assignmentConflictPending = false;
			this.assignmentConflictCooldown = false;
			clearTimeout(this.assignmentConflictTimer);
			this.assignmentConflictTimer = null;
		},

		showAssignmentConflict(message) {
			this.assignmentConflictMessage = `${message} Click Save to move the assignment.`;
			this.assignmentConflictPending = true;
			this.assignmentConflictCooldown = true;
			clearTimeout(this.assignmentConflictTimer);
			this.assignmentConflictTimer = setTimeout(() => {
				this.assignmentConflictCooldown = false;
				this.assignmentConflictTimer = null;
			}, 2500);
		},

		shouldShowAssigneeWarning() {
			const query = this.editAssignedUserQuery.trim();
			return query !== '' && query !== this.initialEditAssignedUserName && query !== this.initialEditManualPerformerName;
		},

		resolveEditedSlotAssignment() {
			const query = this.editAssignedUserQuery.trim();
			const selectedUser = this.slotEditUserOptions().find((user) => String(user.id) === String(this.editAssignedUserId));

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

		slotManageMenuItemClass(set) {
			return this.isAdminManagingOtherSet(set)
				? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
				: 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
		},

		slotAdminIconClass(set) {
			return this.isAdminManagingOtherSet(set) ? 'text-sky-700' : 'text-slate-500';
		},

		slotAssigneeName(slot) {
			if (!slot) {
				return 'Open';
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return 'You';
			}

			return slot.user_name || 'Open';
		},

		slotAssigneeBadgeClass(slot) {
			if (!slot || slot.is_open) {
				return 'border-amber-200 bg-amber-50/80 text-amber-800';
			}

			if (Number(slot.user_id) === Number(this.currentUserId)) {
				return 'border-sky-200 bg-sky-50/90 text-sky-800';
			}

			return 'border-emerald-200 bg-emerald-50/80 text-emerald-800';
		},

		openRequestSlotForm() {
			if (!this.canRequestActiveSlot()) {
				return;
			}

			this.activeSlotAction.mode = 'request';
			this.activeSlotAction.message = '';
		},

		openRecommendSlotForm() {
			if (!this.activeSlotContext()) {
				return;
			}

			this.activeSlotAction.mode = 'recommend';
			this.activeSlotAction.message = '';
			this.activeSlotAction.target_user_id = '';
		},

		resetSlotActionState() {
			this.activeSlotAction = {
				set_id: null,
				song_id: null,
				slot_id: null,
				mode: null,
				message: '',
				target_user_id: '',
			};
			this.slotActionBusy = false;
		},

		openAvailabilityModal(set) {
			if (!set?.id) {
				return;
			}

			this.activeAvailabilitySetId = Number(set.id);
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-availability' }));
		},

		openScheduleModal(set) {
			if (!set?.can_manage) {
				return;
			}

			if (!this.hasSongsOnSet(set)) {
				this.errorMessage = 'Add at least one song before scheduling this set.';
				return;
			}

			if (!this.hasGoingParticipantsForSet(set)) {
				this.errorMessage = 'At least one participant needs to be marked Going before scheduling this set.';
				return;
			}

			const candidateSessionIds = (set.candidate_session_ids || []).map((id) => Number(id));
			if (candidateSessionIds.length === 0) {
				this.errorMessage = 'Pick at least one candidate jam session in the set editor before scheduling.';
				return;
			}

			const candidateOptions = (this.scheduleSessionOptions || []).filter((option) => candidateSessionIds.includes(Number(option.id)));
			if (candidateOptions.length === 0) {
				this.errorMessage = 'No candidate jam sessions are available for scheduling right now.';
				return;
			}

			const firstSessionId = candidateOptions[0]?.id ? String(candidateOptions[0].id) : '';
			this.scheduleForm = {
				set_id: Number(set.id),
				jam_session_id: firstSessionId,
				not_going_slot_action: '',
			};
			this.errorMessage = '';
			window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-schedule' }));
		},

		hasSongsOnSet(set) {
			return Array.isArray(set?.songs) && set.songs.length > 0;
		},

		hasGoingParticipantsForSet(set) {
			if (!set) {
				return false;
			}

			const candidateAttendance = this.attendanceSessionsForSet(set);
			const attendance = candidateAttendance.length > 0
				? candidateAttendance
				: (set.attendance_sessions || []);

			return attendance.some((session) => Number(session?.counts?.going || 0) > 0);
		},

		canScheduleSet(set) {
			return Boolean(set?.can_manage) && this.hasSongsOnSet(set) && this.hasGoingParticipantsForSet(set);
		},

		resetCollaboratorPicker() {
			this.collaboratorQuery = '';
			this.collaboratorSuggestions = [];
			this.showCollaboratorSuggestions = false;
		},

		queueCollaboratorLookup() {
			const query = this.collaboratorQuery.trim().toLowerCase();
			if (query.length < 1) {
				this.collaboratorSuggestions = [];
				this.showCollaboratorSuggestions = false;
				return;
			}

			const selectedIds = new Set((this.editor.collaborator_ids || []).map((id) => Number(id)));
			const candidates = (this.collaboratorOptions || [])
				.filter((candidate) => !selectedIds.has(Number(candidate.id)))
				.filter((candidate) => String(candidate.name || '').toLowerCase().includes(query))
				.slice(0, 20);

			this.collaboratorSuggestions = candidates;
			this.showCollaboratorSuggestions = candidates.length > 0;
		},

		filteredCollaboratorSuggestions() {
			const selectedIds = new Set((this.editor.collaborator_ids || []).map((id) => Number(id)));
			return (this.collaboratorSuggestions || []).filter((candidate) => !selectedIds.has(Number(candidate.id)));
		},

		addCollaborator(user) {
			const normalizedId = Number(user?.id);
			if (!normalizedId || this.editor.collaborator_ids.includes(normalizedId)) {
				return;
			}

			this.editor.collaborator_ids = [...this.editor.collaborator_ids, normalizedId];
			this.collaboratorQuery = '';
			this.collaboratorSuggestions = [];
			this.showCollaboratorSuggestions = false;
		},

		removeCollaborator(userId) {
			const normalizedId = Number(userId);
			this.editor.collaborator_ids = (this.editor.collaborator_ids || []).filter((id) => Number(id) !== normalizedId);
		},

		selectedCollaborators() {
			const selectedIds = new Set((this.editor.collaborator_ids || []).map((id) => Number(id)));
			return (this.collaboratorOptions || []).filter((candidate) => selectedIds.has(Number(candidate.id)));
		},

		isCandidateSessionSelected(sessionId) {
			return (this.editor.candidate_session_ids || []).map((id) => Number(id)).includes(Number(sessionId));
		},

		toggleCandidateSession(sessionId) {
			const normalizedId = Number(sessionId);
			const selectedIds = (this.editor.candidate_session_ids || []).map((id) => Number(id));

			if (selectedIds.includes(normalizedId)) {
				this.editor.candidate_session_ids = selectedIds.filter((id) => id !== normalizedId);
				return;
			}

			this.editor.candidate_session_ids = [...selectedIds, normalizedId];
		},

		toggleSongSlotName(slotName) {
			if (this.songEditor.slot_names.includes(slotName)) {
				this.songEditor.slot_names = this.songEditor.slot_names.filter((name) => name !== slotName);
				return;
			}

			this.songEditor.slot_names = [...this.songEditor.slot_names, slotName];
		},

		toggleRequestSongSlotName(slotName) {
			if (this.requestSongSlotNames.includes(slotName)) {
				this.requestSongSlotNames = this.requestSongSlotNames.filter((name) => name !== slotName);
				return;
			}

			this.requestSongSlotNames = [...this.requestSongSlotNames, slotName];
		},

		resetSongRequestAutocomplete() {
			this.requestSongBusy = false;
			this.requestSongError = '';
			this.requestCatalogSongId = '';
			this.requestSongNotes = '';
			this.requestSongSlotNames = [];
			this.requestArtistQuery = '';
			this.requestTitleQuery = '';
			this.requestSelectedArtistName = '';
			this.requestArtistSuggestions = [];
			this.requestTitleSuggestions = [];
			this.showRequestArtistSuggestions = false;
			this.showRequestTitleSuggestions = false;
			this.requestArtistLookupBusy = false;
			this.requestTitleLookupBusy = false;
			this.requestArtistLookupError = '';
			this.requestTitleLookupError = '';

			if (this.requestArtistLookupTimer) {
				clearTimeout(this.requestArtistLookupTimer);
				this.requestArtistLookupTimer = null;
			}

			if (this.requestTitleLookupTimer) {
				clearTimeout(this.requestTitleLookupTimer);
				this.requestTitleLookupTimer = null;
			}
		},

		applyRequestCatalogSong() {
			const selectedSongId = Number(this.requestCatalogSongId);
			const selectedSong = this.jamStandardSongs.find((song) => Number(song.id) === selectedSongId);

			if (!selectedSong) {
				this.requestArtistQuery = '';
				this.requestTitleQuery = '';
				this.requestSelectedArtistName = '';
				return;
			}

			this.requestArtistQuery = selectedSong.artist;
			this.requestSelectedArtistName = selectedSong.artist;
			this.requestTitleQuery = selectedSong.title;
			this.requestArtistSuggestions = [];
			this.requestTitleSuggestions = [];
			this.showRequestArtistSuggestions = false;
			this.showRequestTitleSuggestions = false;
		},

		queueRequestArtistLookup() {
			if (this.requestSongMode === 'catalog') {
				return;
			}

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
				const response = await fetch(`${config.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
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
			if (this.requestSongMode === 'catalog') {
				return;
			}

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
				const response = await fetch(`${config.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
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

		resetSongLookupState() {
			this.songArtistQuery = '';
			this.songTitleQuery = '';
			this.songSelectedArtistName = '';
			this.selectedDeezerDuration = null;
			this.deezerTitleSelected = false;
			this.songArtistSuggestions = [];
			this.songTitleSuggestions = [];
			this.showSongArtistSuggestions = false;
			this.showSongTitleSuggestions = false;
			this.songArtistLookupBusy = false;
			this.songTitleLookupBusy = false;
			this.songArtistLookupError = '';
			this.songTitleLookupError = '';

			if (this.songArtistLookupTimer) {
				clearTimeout(this.songArtistLookupTimer);
				this.songArtistLookupTimer = null;
			}

			if (this.songTitleLookupTimer) {
				clearTimeout(this.songTitleLookupTimer);
				this.songTitleLookupTimer = null;
			}
		},

		queueSongArtistLookup() {
			this.songArtistLookupError = '';
			this.showSongTitleSuggestions = false;
			this.songTitleSuggestions = [];
			this.selectedDeezerDuration = null;
			this.deezerTitleSelected = false;

			if (this.songArtistLookupTimer) {
				clearTimeout(this.songArtistLookupTimer);
			}

			const query = this.songArtistQuery.trim();
			if (query.length < 2) {
				this.songArtistSuggestions = [];
				this.showSongArtistSuggestions = false;
				this.songSelectedArtistName = '';
				this.songEditor.artist = this.songArtistQuery;
				return;
			}

			this.songEditor.artist = this.songArtistQuery;
			this.songArtistLookupTimer = setTimeout(() => this.fetchSongArtistSuggestions(query), 250);
		},

		async fetchSongArtistSuggestions(query) {
			const token = ++this.songArtistLookupToken;
			this.songArtistLookupBusy = true;

			try {
				const response = await fetch(`${config.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Artist lookup failed');
				}

				const payload = await response.json();
				if (token !== this.songArtistLookupToken) {
					return;
				}

				this.songArtistSuggestions = payload.artists || [];
				this.showSongArtistSuggestions = this.songArtistSuggestions.length > 0;
			} catch (e) {
				if (token !== this.songArtistLookupToken) {
					return;
				}

				this.songArtistLookupError = 'Could not fetch artist suggestions right now.';
				this.songArtistSuggestions = [];
				this.showSongArtistSuggestions = false;
			} finally {
				if (token === this.songArtistLookupToken) {
					this.songArtistLookupBusy = false;
				}
			}
		},

		selectSongArtistSuggestion(artistName) {
			this.songArtistQuery = artistName;
			this.songSelectedArtistName = artistName;
			this.songEditor.artist = artistName;
			this.songArtistSuggestions = [];
			this.showSongArtistSuggestions = false;
			this.songTitleQuery = '';
			this.songEditor.title = '';
			this.songTitleSuggestions = [];
			this.showSongTitleSuggestions = false;
			this.songTitleLookupError = '';
		},

		queueSongTitleLookup() {
			this.songTitleLookupError = '';
			this.selectedDeezerDuration = null;
			this.deezerTitleSelected = false;

			if (this.songTitleLookupTimer) {
				clearTimeout(this.songTitleLookupTimer);
			}

			const query = this.songTitleQuery.trim();
			const artist = (this.songSelectedArtistName || this.songArtistQuery).trim();
			this.songEditor.title = this.songTitleQuery;

			if (query.length < 2 || artist.length < 2) {
				this.songTitleSuggestions = [];
				this.showSongTitleSuggestions = false;
				return;
			}

			this.songTitleLookupTimer = setTimeout(() => this.fetchSongTitleSuggestions(artist, query), 250);
		},

		async fetchSongTitleSuggestions(artist, query) {
			const token = ++this.songTitleLookupToken;
			this.songTitleLookupBusy = true;

			try {
				const response = await fetch(`${config.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Title lookup failed');
				}

				const payload = await response.json();
				if (token !== this.songTitleLookupToken) {
					return;
				}

				this.songTitleSuggestions = payload.titles || [];
				this.showSongTitleSuggestions = this.songTitleSuggestions.length > 0;
			} catch (e) {
				if (token !== this.songTitleLookupToken) {
					return;
				}

				this.songTitleLookupError = 'Could not fetch song suggestions right now.';
				this.songTitleSuggestions = [];
				this.showSongTitleSuggestions = false;
			} finally {
				if (token === this.songTitleLookupToken) {
					this.songTitleLookupBusy = false;
				}
			}
		},

		selectSongTitleSuggestion(title, duration) {
			this.songTitleQuery = title;
			this.songEditor.title = title;
			this.selectedDeezerDuration = duration ?? null;
			this.deezerTitleSelected = true;
			this.songTitleSuggestions = [];
			this.showSongTitleSuggestions = false;
		},

		resetSongEditLookupState() {
			this.songEditArtistSuggestions = [];
			this.songEditTitleSuggestions = [];
			this.showSongEditArtistSuggestions = false;
			this.showSongEditTitleSuggestions = false;
			this.songEditArtistLookupBusy = false;
			this.songEditTitleLookupBusy = false;
			this.songEditArtistLookupError = '';
			this.songEditTitleLookupError = '';

			if (this.songEditArtistLookupTimer) {
				clearTimeout(this.songEditArtistLookupTimer);
				this.songEditArtistLookupTimer = null;
			}

			if (this.songEditTitleLookupTimer) {
				clearTimeout(this.songEditTitleLookupTimer);
				this.songEditTitleLookupTimer = null;
			}
		},

		queueSongEditArtistLookup() {
			this.songEditArtistLookupError = '';
			this.showSongEditTitleSuggestions = false;
			this.songEditTitleSuggestions = [];
			this.songEditSelectedDeezerDuration = null;
			this.songEditDeezerTitleSelected = false;

			if (this.songEditArtistLookupTimer) {
				clearTimeout(this.songEditArtistLookupTimer);
			}

			const query = this.songEditArtistQuery.trim();
			this.songEditEditor.artist = this.songEditArtistQuery;

			if (query.length < 2) {
				this.songEditArtistSuggestions = [];
				this.showSongEditArtistSuggestions = false;
				this.songEditSelectedArtistName = '';
				return;
			}

			this.songEditArtistLookupTimer = setTimeout(() => this.fetchSongEditArtistSuggestions(query), 250);
		},

		async fetchSongEditArtistSuggestions(query) {
			const token = ++this.songEditArtistLookupToken;
			this.songEditArtistLookupBusy = true;

			try {
				const response = await fetch(`${config.artistLookupUrl}?q=${encodeURIComponent(query)}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Artist lookup failed');
				}

				const payload = await response.json();
				if (token !== this.songEditArtistLookupToken) {
					return;
				}

				this.songEditArtistSuggestions = payload.artists || [];
				this.showSongEditArtistSuggestions = this.songEditArtistSuggestions.length > 0;
			} catch (e) {
				if (token !== this.songEditArtistLookupToken) {
					return;
				}

				this.songEditArtistLookupError = 'Could not fetch artist suggestions right now.';
				this.songEditArtistSuggestions = [];
				this.showSongEditArtistSuggestions = false;
			} finally {
				if (token === this.songEditArtistLookupToken) {
					this.songEditArtistLookupBusy = false;
				}
			}
		},

		selectSongEditArtistSuggestion(artistName) {
			this.songEditArtistQuery = artistName;
			this.songEditSelectedArtistName = artistName;
			this.songEditEditor.artist = artistName;
			this.songEditArtistSuggestions = [];
			this.showSongEditArtistSuggestions = false;
			this.songEditTitleQuery = '';
			this.songEditEditor.title = '';
			this.songEditTitleSuggestions = [];
			this.showSongEditTitleSuggestions = false;
			this.songEditTitleLookupError = '';
		},

		queueSongEditTitleLookup() {
			this.songEditTitleLookupError = '';
			this.songEditSelectedDeezerDuration = null;
			this.songEditDeezerTitleSelected = false;

			if (this.songEditTitleLookupTimer) {
				clearTimeout(this.songEditTitleLookupTimer);
			}

			const query = this.songEditTitleQuery.trim();
			const artist = (this.songEditSelectedArtistName || this.songEditArtistQuery).trim();
			this.songEditEditor.title = this.songEditTitleQuery;

			if (query.length < 2 || artist.length < 2) {
				this.songEditTitleSuggestions = [];
				this.showSongEditTitleSuggestions = false;
				return;
			}

			this.songEditTitleLookupTimer = setTimeout(() => this.fetchSongEditTitleSuggestions(artist, query), 250);
		},

		async fetchSongEditTitleSuggestions(artist, query) {
			const token = ++this.songEditTitleLookupToken;
			this.songEditTitleLookupBusy = true;

			try {
				const response = await fetch(`${config.titleLookupUrl}?artist=${encodeURIComponent(artist)}&q=${encodeURIComponent(query)}`, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Title lookup failed');
				}

				const payload = await response.json();
				if (token !== this.songEditTitleLookupToken) {
					return;
				}

				this.songEditTitleSuggestions = payload.titles || [];
				this.showSongEditTitleSuggestions = this.songEditTitleSuggestions.length > 0;
			} catch (e) {
				if (token !== this.songEditTitleLookupToken) {
					return;
				}

				this.songEditTitleLookupError = 'Could not fetch song suggestions right now.';
				this.songEditTitleSuggestions = [];
				this.showSongEditTitleSuggestions = false;
			} finally {
				if (token === this.songEditTitleLookupToken) {
					this.songEditTitleLookupBusy = false;
				}
			}
		},

		selectSongEditTitleSuggestion(title, duration) {
			this.songEditTitleQuery = title;
			this.songEditEditor.title = title;
			this.songEditSelectedDeezerDuration = duration ?? null;
			this.songEditDeezerTitleSelected = true;
			this.songEditTitleSuggestions = [];
			this.showSongEditTitleSuggestions = false;
		},

		async saveEditor() {
			if (this.editorBusy) {
				return;
			}

			this.editorBusy = true;
			this.errorMessage = '';

			try {
				const isEditing = Number.isInteger(this.editor.id) && this.editor.id > 0;
				const url = isEditing
					? config.updateUrlTemplate.replace('__SET_ID__', String(this.editor.id))
					: config.storeUrl;
				const method = isEditing ? 'PATCH' : 'POST';

				const payload = await this.requestJson(url, {
					method,
					body: {
						name: this.editor.name,
						description: this.editor.description,
						is_hidden: this.editor.is_hidden,
						free_for_all: this.editor.free_for_all,
						song_requests: this.editor.song_requests,
						signups_open: this.editor.signups_open,
						collaborator_ids: this.editor.collaborator_ids,
						candidate_session_ids: this.editor.candidate_session_ids,
					},
				});

				this.upsertSet(payload.set);
				this.statusMessage = payload.message || (isEditing ? 'Planned set updated.' : 'Planned set created.');
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-editor' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not save planned set.';
			} finally {
				this.editorBusy = false;
			}
		},

		async submitSongRequest() {
			if (this.requestSongBusy || !this.requestSongSetId) {
				return;
			}

			if (!this.requestArtistQuery.trim() || !this.requestTitleQuery.trim()) {
				this.requestSongError = 'Artist and title are required.';
				return;
			}

			this.requestSongBusy = true;
			this.requestSongError = '';

			try {
				const payload = await this.requestJson(
					config.songRequestStoreUrlTemplate.replace('__SET_ID__', String(this.requestSongSetId)),
					{
						method: 'POST',
						body: {
							artist: this.requestArtistQuery.trim(),
							title: this.requestTitleQuery.trim(),
							notes: this.requestSongNotes.trim() || null,
							jam_standard_song_id: this.requestSongMode === 'catalog' && this.requestCatalogSongId
								? Number(this.requestCatalogSongId)
								: null,
							slot_names: [...this.requestSongSlotNames],
						},
					}
				);

				this.statusMessage = payload.message || 'Song request submitted to the set owner.';
				this.closeSongRequestModal();
			} catch (e) {
				this.requestSongError = e?.message || 'Could not submit song request. Try again.';
			} finally {
				this.requestSongBusy = false;
			}
		},

		async saveSong() {
			if (this.songBusy || !this.songEditor.set_id) {
				return;
			}

			if (!this.songEditor.artist.trim() || !this.songEditor.title.trim()) {
				this.errorMessage = 'Artist and title are required.';
				return;
			}

			const addingByTemplate = this.songEditor.song_slot_addition_mode === 'template';
			if (!addingByTemplate && this.songEditor.slot_names.length === 0 && !window.confirm('No slots will be added to this song now. You can add slots later. Continue?')) {
				return;
			}

			this.songBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.songStoreUrlTemplate.replace('__SET_ID__', String(this.songEditor.set_id)),
					{
						method: 'POST',
						body: {
							artist: this.songEditor.artist,
							title: this.songEditor.title,
							notes: this.songEditor.notes,
							duration: this.deezerTitleSelected && this.selectedDeezerDuration ? Number(this.selectedDeezerDuration) : null,
							source: this.deezerTitleSelected ? 'deezer' : null,
							band_template_id: addingByTemplate && this.songEditor.band_template_id ? Number(this.songEditor.band_template_id) : null,
							slot_names: addingByTemplate ? [] : this.songEditor.slot_names,
						},
					}
				);

				if (payload?.song) {
					this.appendSetSong(this.songEditor.set_id, payload.song);
				}

				this.statusMessage = payload.message || 'Song added to set.';
				this.resetSongLookupState();
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-song' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not add song.';
			} finally {
				this.songBusy = false;
			}
		},

		async saveSongEdit() {
			if (this.songEditBusy || !this.songEditEditor.set_id || !this.songEditEditor.song_id) {
				return;
			}

			if (!this.songEditEditor.artist.trim() || !this.songEditEditor.title.trim()) {
				this.errorMessage = 'Artist and title are required.';
				return;
			}

			this.songEditBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.songUpdateUrlTemplate
						.replace('__SET_ID__', String(this.songEditEditor.set_id))
						.replace('__SONG_ID__', String(this.songEditEditor.song_id)),
					{
						method: 'PATCH',
						body: {
							artist: this.songEditEditor.artist,
							title: this.songEditEditor.title,
							notes: this.songEditEditor.notes,
							duration: this.songEditDeezerTitleSelected && this.songEditSelectedDeezerDuration
								? Number(this.songEditSelectedDeezerDuration)
								: this.songEditEditor.duration,
							source: this.songEditDeezerTitleSelected
								? 'deezer'
								: (this.songEditEditor.source || null),
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(this.songEditEditor.set_id, payload.song);
				}

				this.statusMessage = payload.message || 'Song updated.';
				this.resetSongEditLookupState();
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-edit-song' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not update song.';
			} finally {
				this.songEditBusy = false;
			}
		},

		songEditContext() {
			const set = this.sets.find((candidate) => Number(candidate.id) === Number(this.songEditEditor.set_id));
			if (!set) {
				return null;
			}

			const song = (set.songs || []).find((candidate) => Number(candidate.id) === Number(this.songEditEditor.song_id));
			if (!song) {
				return null;
			}

			return {
				set,
				song,
			};
		},

		canDeleteActiveSong() {
			const context = this.songEditContext();
			return Boolean(context?.set?.can_manage && context?.song?.id);
		},

		songDestroyUrl() {
			const context = this.songEditContext();
			if (!context?.song?.id) {
				return '';
			}

			return (config.songDestroyUrlTemplate || '').replace('__SONG_ID__', String(context.song.id));
		},

		async deleteActiveSong() {
			const context = this.songEditContext();
			if (this.songEditBusy || !context) {
				return;
			}

			if (!window.confirm('Delete this song from the set? This cannot be undone.')) {
				return;
			}

			this.songEditBusy = true;
			this.errorMessage = '';

			try {
				const body = new FormData();
				body.set('_method', 'DELETE');

				const response = await fetch(this.songDestroyUrl(), {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': config.csrfToken,
					},
					body,
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(payload?.message || 'Request failed');
				}

				this.sets = this.sets.map((set) => {
					if (Number(set.id) !== Number(context.set.id)) {
						return set;
					}

					return {
						...set,
						songs: (set.songs || []).filter((song) => Number(song.id) !== Number(context.song.id)),
					};
				});

				this.statusMessage = payload.message || 'Song deleted.';
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-edit-song' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not delete song.';
			} finally {
				this.songEditBusy = false;
			}
		},

		async saveSlot() {
			if (this.slotBusy || !this.slotEditor.set_id || !this.slotEditor.song_id) {
				return;
			}

			if (this.slotEditor.addition_mode === 'individual' && !this.slotEditor.name) {
				this.errorMessage = 'Choose a slot name.';
				return;
			}

			if (this.slotEditor.addition_mode === 'template' && !this.slotEditor.band_template_id) {
				this.errorMessage = 'Choose a band template.';
				return;
			}

			this.slotBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotStoreUrlTemplate
						.replace('__SET_ID__', String(this.slotEditor.set_id))
						.replace('__SONG_ID__', String(this.slotEditor.song_id)),
					{
						method: 'POST',
						body: {
							addition_mode: this.slotEditor.addition_mode,
							name: this.slotEditor.addition_mode === 'individual' ? this.slotEditor.name : null,
							notes: this.slotEditor.addition_mode === 'individual' ? this.slotEditor.notes : null,
							band_template_id: this.slotEditor.addition_mode === 'template' ? Number(this.slotEditor.band_template_id) : null,
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(this.slotEditor.set_id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot added.';
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-slot' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not add slot.';
			} finally {
				this.slotBusy = false;
			}
		},

		async takeActiveSlot() {
			const context = this.activeSlotContext();
			if (this.slotActionBusy || !context || !this.canTakeActiveSlot()) {
				return;
			}

			this.slotActionBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotTakeUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'POST',
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot assigned to you.';
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not take slot.';
			} finally {
				this.slotActionBusy = false;
			}
		},

		async requestActiveSlot() {
			const context = this.activeSlotContext();
			if (this.slotActionBusy || !context || !this.canRequestActiveSlot()) {
				return;
			}

			this.slotActionBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotRequestUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'POST',
						body: {
							message: this.activeSlotAction.message || null,
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Request submitted.';
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not request slot.';
			} finally {
				this.slotActionBusy = false;
			}
		},

		async recommendActiveSlot() {
			const context = this.activeSlotContext();
			if (this.slotActionBusy || !context) {
				return;
			}

			if (!this.activeSlotAction.target_user_id) {
				this.errorMessage = 'Choose someone to recommend.';
				return;
			}

			this.slotActionBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotProposeUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'POST',
						body: {
							target_user_id: Number(this.activeSlotAction.target_user_id),
							message: this.activeSlotAction.message || null,
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Recommendation sent.';
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not send recommendation.';
			} finally {
				this.slotActionBusy = false;
			}
		},

		async releaseActiveSlot() {
			const context = this.activeSlotContext();
			if (this.slotActionBusy || !context || !this.canReleaseActiveSlot()) {
				return;
			}

			this.slotActionBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotReleaseUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'POST',
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot released.';
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not release slot.';
			} finally {
				this.slotActionBusy = false;
			}
		},

		async toggleClaimableActiveSlot() {
			const context = this.activeSlotContext();
			if (this.slotActionBusy || !context || !this.canToggleClaimableActiveSlot()) {
				return;
			}

			this.slotActionBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotClaimableUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'PATCH',
						body: {
							is_claimable_manual: !Boolean(context.slot.is_claimable_manual),
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot updated.';
			} catch (e) {
				this.errorMessage = e?.message || 'Could not update slot claimable status.';
			} finally {
				this.slotActionBusy = false;
			}
		},

		async clearEditedSlot() {
			const context = this.slotEditContext();
			if (this.slotEditBusy || !context) {
				return;
			}

			if (!window.confirm('Clear this slot assignment?')) {
				return;
			}

			this.slotEditBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotUpdateUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'PATCH',
						body: {
							name: this.slotEditEditor.name,
							notes: this.slotEditEditor.notes || null,
							user_id: null,
							manual_performer_name: '',
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot cleared.';
				this.closeEditSlotModal();
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not clear slot.';
			} finally {
				this.slotEditBusy = false;
			}
		},

		async submitEditSlot() {
			const context = this.slotEditContext();
			if (this.slotEditBusy || !context) {
				return;
			}

			if (!this.slotEditEditor.name) {
				this.errorMessage = 'Choose a slot name.';
				return;
			}

			this.slotEditBusy = true;
			this.errorMessage = '';

			try {
				const assignment = this.resolveEditedSlotAssignment();
				const body = {
					name: this.slotEditEditor.name,
					notes: this.slotEditEditor.notes || null,
					user_id: assignment.user_id ? Number(assignment.user_id) : null,
					manual_performer_name: assignment.manual_performer_name,
				};

				if (this.assignmentConflictPending) {
					body.replace_conflicting_assignment = true;
				}

				const response = await fetch(
					config.slotUpdateUrlTemplate
						.replace('__SET_ID__', String(context.set.id))
						.replace('__SLOT_ID__', String(context.slot.id)),
					{
						method: 'PATCH',
						headers: {
							'Accept': 'application/json',
							'Content-Type': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
							'X-CSRF-TOKEN': config.csrfToken,
						},
						body: JSON.stringify(body),
					}
				);

				const payload = await response.json().catch(() => ({}));

				if (response.status === 409) {
					this.showAssignmentConflict(payload?.message || 'This assignment conflicts with another slot on the song.');
					return;
				}

				if (!response.ok) {
					const firstError = Object.values(payload?.errors || {})[0];
					const firstValidationMessage = Array.isArray(firstError) ? firstError[0] : null;
					throw new Error(firstValidationMessage || payload?.message || 'Could not save slot.');
				}

				if (payload?.song) {
					this.replaceSetSong(context.set.id, payload.song);
				}

				this.statusMessage = payload.message || 'Slot updated.';
				this.closeEditSlotModal();
				this.resetSlotActionState();
			} catch (e) {
				this.errorMessage = e?.message || 'Could not save slot.';
			} finally {
				this.slotEditBusy = false;
			}
		},

		async respondSongRequest(set, songRequest, status) {
			if (!set?.id || !songRequest?.id || songRequest.busy) {
				return;
			}

			songRequest.busy = true;
			songRequest.error = '';
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.songRequestRespondUrlTemplate
						.replace('__SET_ID__', String(set.id))
						.replace('__SONG_REQUEST_ID__', String(songRequest.id)),
					{
						method: 'PATCH',
						body: {
							status,
							band_template_id: status === 'accepted' && songRequest.selected_band_template_id
								? Number(songRequest.selected_band_template_id)
								: null,
							approved_slot_names: status === 'accepted'
								? [...(songRequest.approved_slot_names || [])]
								: [],
						},
					}
				);

				if (payload?.song) {
					this.appendSetSong(set.id, payload.song);
				}

				this.removePendingSongRequest(set.id, payload?.song_request_id || songRequest.id);
				this.statusMessage = payload.message || (status === 'accepted' ? 'Song request approved.' : 'Song request rejected.');
			} catch (e) {
				songRequest.error = e?.message || 'Could not update song request.';
			} finally {
				songRequest.busy = false;
			}
		},

		async respondSlotRequest(set, slotRequest, status) {
			if (!set?.id || !slotRequest?.id || slotRequest.busy) {
				return;
			}

			slotRequest.busy = true;
			slotRequest.error = '';
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.slotAssignmentRespondUrlTemplate
						.replace('__SET_ID__', String(set.id))
						.replace('__SLOT_ASSIGNMENT_ID__', String(slotRequest.id)),
					{
						method: 'PATCH',
						body: {
							status,
						},
					}
				);

				if (payload?.song) {
					this.replaceSetSong(set.id, payload.song);
				}

				this.removePendingSlotRequests(set.id, payload?.processed_slot_assignment_ids || [slotRequest.id]);
				this.statusMessage = payload.message || (status === 'accepted' ? 'Slot request approved.' : 'Slot request rejected.');
			} catch (e) {
				slotRequest.error = e?.message || 'Could not update slot request.';
			} finally {
				slotRequest.busy = false;
			}
		},

		currentAvailabilitySet() {
			return this.sets.find((set) => Number(set.id) === Number(this.activeAvailabilitySetId)) || null;
		},

		candidateSessionIdsForSet(set) {
			return (set?.candidate_session_ids || []).map((id) => Number(id));
		},

		attendanceSessionsForSet(set) {
			const candidateSessionIds = this.candidateSessionIdsForSet(set);

			if (candidateSessionIds.length === 0) {
				return [];
			}

			return (set?.attendance_sessions || []).filter((session) => candidateSessionIds.includes(Number(session.jam_session_id)));
		},

		currentAvailabilitySessions() {
			return this.attendanceSessionsForSet(this.currentAvailabilitySet());
		},

		currentAvailabilityHasCandidateSessions() {
			return this.candidateSessionIdsForSet(this.currentAvailabilitySet()).length > 0;
		},

		currentScheduleSet() {
			return this.sets.find((set) => Number(set.id) === Number(this.scheduleForm.set_id)) || null;
		},

		scheduleCandidateSessionOptions() {
			const currentSet = this.currentScheduleSet();
			if (!currentSet) {
				return [];
			}

			const candidateIds = new Set((currentSet.candidate_session_ids || []).map((id) => Number(id)));
			return (this.scheduleSessionOptions || []).filter((option) => candidateIds.has(Number(option.id)));
		},

		scheduleAvailabilityForSession(sessionId) {
			const currentSet = this.currentScheduleSet();
			if (!currentSet) {
				return null;
			}

			return (currentSet.attendance_sessions || []).find((session) => Number(session.jam_session_id) === Number(sessionId)) || null;
		},

		scheduleAvailabilityWarningMessage() {
			const availability = this.scheduleAvailabilityForSession(this.scheduleForm.jam_session_id);
			const currentSet = this.currentScheduleSet();
			if (!availability || !currentSet) {
				return '';
			}

			const warningParts = [];
			if (availability.owner_unavailable) {
				warningParts.push(`${currentSet.owner?.name || 'The set owner'} is marked unavailable for this jam.`);
			}

			if (availability.all_collaborators_unavailable) {
				warningParts.push('All collaborators are marked unavailable for this jam.');
			}

			if (Number(availability?.counts?.not_going || 0) > 0) {
				const names = this.availabilityNamesList(availability.not_going_names || []);
				warningParts.push(`${availability.counts.not_going} participant${availability.counts.not_going === 1 ? '' : 's'} marked Not Going: ${names}.`);
			}

			return warningParts.join(' ');
		},

		scheduleHasNotGoingParticipants() {
			const availability = this.scheduleAvailabilityForSession(this.scheduleForm.jam_session_id);
			if (!availability) {
				return false;
			}

			return Number(availability?.counts?.not_going || 0) > 0;
		},

		isAttendanceSaving(sessionId) {
			return this.attendanceSaving[String(sessionId)] === true;
		},

		attendanceStatusLabel(status) {
			if (status === 'going') {
				return 'Going';
			}

			if (status === 'not_going') {
				return 'Not Going';
			}

			return 'Not Specified';
		},

		attendanceStatusBadge(status) {
			if (status === 'going') {
				return 'border-emerald-200 bg-emerald-50 text-emerald-700';
			}

			if (status === 'not_going') {
				return 'border-slate-300 bg-slate-100 text-slate-700';
			}

			return 'border-slate-200 bg-slate-50 text-slate-600';
		},

		availabilityHeadline(attendanceSessions) {
			if (!Array.isArray(attendanceSessions) || attendanceSessions.length === 0) {
				return 'No upcoming jam sessions yet.';
			}

			const answeredCount = attendanceSessions.filter((session) => session.my_status !== 'maybe').length;

			if (answeredCount === 0) {
				return 'No dates confirmed yet.';
			}

			return `${answeredCount} upcoming date${answeredCount === 1 ? '' : 's'} answered`;
		},

		plannedForLabel(set) {
			const count = this.attendanceSessionsForSet(set).length;
			if (count <= 1) {
				return 'Planned for:';
			}
            if (count == 2) {
                return 'Planned for either of:';
            }
            return 'Planned for one of:';
		},

		availabilityNamesList(names) {
			if (!Array.isArray(names) || names.length === 0) {
				return 'none';
			}

			return names.join(', ');
		},

		async setAttendanceStatus(session, status, dropoutAction = null) {
			if (!session?.jam_session_id || session.is_closed || this.isAttendanceSaving(session.jam_session_id)) {
				return;
			}

			this.attendanceSaving[String(session.jam_session_id)] = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.attendanceUpdateUrlTemplate.replace('__SESSION_ID__', String(session.jam_session_id)),
					{
						method: 'POST',
						body: {
							status,
							dropout_action: dropoutAction,
						},
					}
				);

				this.applyAttendanceUpdate(session.jam_session_id, status);
				this.statusMessage = payload.message || 'Attendance updated.';
			} catch (e) {
				if (e?.requiresDropoutAction) {
					this.dropoutPrompt = {
						jam_session_id: Number(session.jam_session_id),
						jam_session_name: session.jam_session_name || '',
						action: 'keep_claimable',
					};
					window.dispatchEvent(new CustomEvent('open-modal', { detail: 'planned-set-dropout-choice' }));
					return;
				}

				this.errorMessage = e?.message || 'Could not update attendance.';
			} finally {
				delete this.attendanceSaving[String(session.jam_session_id)];
			}
		},

		async confirmDropoutChoice() {
			const session = this.currentAvailabilitySessions().find((item) => Number(item.jam_session_id) === Number(this.dropoutPrompt.jam_session_id));

			if (!session) {
				return;
			}

			window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-dropout-choice' }));
			await this.setAttendanceStatus(session, 'not_going', this.dropoutPrompt.action);
		},

		async saveSchedule() {
			if (this.scheduleBusy || !this.scheduleForm.set_id) {
				return;
			}

			const candidateOptions = this.scheduleCandidateSessionOptions();
			if (candidateOptions.length === 0) {
				this.errorMessage = 'Pick at least one candidate jam session in the set editor before scheduling.';
				return;
			}

			if (!this.scheduleForm.jam_session_id) {
				this.errorMessage = 'Choose a candidate jam session to schedule this set.';
				return;
			}

			if (this.scheduleHasNotGoingParticipants() && !this.scheduleForm.not_going_slot_action) {
				this.errorMessage = 'Choose how to handle slots for participants marked Not Going.';
				return;
			}

			this.scheduleBusy = true;
			this.errorMessage = '';

			try {
				const payload = await this.requestJson(
					config.scheduleUrlTemplate.replace('__SET_ID__', String(this.scheduleForm.set_id)),
					{
						method: 'POST',
						body: {
							jam_session_id: Number(this.scheduleForm.jam_session_id),
							not_going_slot_action: this.scheduleHasNotGoingParticipants() ? this.scheduleForm.not_going_slot_action : null,
						},
					}
				);

				this.sets = this.sets.filter((set) => Number(set.id) !== Number(payload.set_id));
				this.statusMessage = payload.message || 'Set scheduled.';
				window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-schedule' }));
			} catch (e) {
				this.errorMessage = e?.message || 'Could not schedule set.';
			} finally {
				this.scheduleBusy = false;
			}
		},

		appendSetSong(setId, song) {
			this.sets = this.sets.map((set) => {
				if (Number(set.id) !== Number(setId)) {
					return set;
				}

				return {
					...set,
					songs: [...(set.songs || []), song],
				};
			});
		},

		replaceSetSong(setId, updatedSong) {
			this.sets = this.sets.map((set) => {
				if (Number(set.id) !== Number(setId)) {
					return set;
				}

				return {
					...set,
					songs: (set.songs || []).map((song) => (Number(song.id) === Number(updatedSong.id) ? updatedSong : song)),
				};
			});
		},

		removePendingSongRequest(setId, songRequestId) {
			this.sets = this.sets.map((set) => {
				if (Number(set.id) !== Number(setId)) {
					return set;
				}

				return {
					...set,
					pending_song_requests: (set.pending_song_requests || []).filter((songRequest) => Number(songRequest.id) !== Number(songRequestId)),
				};
			});
		},

		removePendingSlotRequests(setId, assignmentIds) {
			const processedIds = new Set((assignmentIds || []).map((id) => Number(id)));

			this.sets = this.sets.map((set) => {
				if (Number(set.id) !== Number(setId)) {
					return set;
				}

				return {
					...set,
					pending_slot_requests: (set.pending_slot_requests || []).filter((slotRequest) => !processedIds.has(Number(slotRequest.id))),
				};
			});
		},

		applyAttendanceUpdate(sessionId, nextStatus) {
			this.sets = this.sets.map((set) => {
				if (!Array.isArray(set.participant_ids) || !set.participant_ids.includes(this.currentUserId)) {
					return set;
				}

				return {
					...set,
					attendance_sessions: (set.attendance_sessions || []).map((session) => {
						if (Number(session.jam_session_id) !== Number(sessionId)) {
							return session;
						}

						const previousStatus = session.my_status || 'maybe';
						const nextCounts = {
							...session.counts,
						};

						if (typeof nextCounts[previousStatus] === 'number') {
							nextCounts[previousStatus] = Math.max(0, nextCounts[previousStatus] - 1);
						}

						if (typeof nextCounts[nextStatus] === 'number') {
							nextCounts[nextStatus] += 1;
						}

						return {
							...session,
							my_status: nextStatus,
							counts: nextCounts,
						};
					}),
				};
			});
		},

		upsertSet(nextSet) {
			if (!nextSet?.id) {
				return;
			}

			const hydratedSet = this.decorateSetForUi(nextSet);

			const index = this.sets.findIndex((set) => Number(set.id) === Number(nextSet.id));
			if (index === -1) {
				this.sets = [hydratedSet, ...this.sets];
				return;
			}

			this.sets.splice(index, 1, hydratedSet);
		},

		async requestJson(url, { method = 'GET', body = null } = {}) {
			const response = await fetch(url, {
				method,
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': config.csrfToken,
				},
				body: body ? JSON.stringify(body) : null,
			});

			const payload = await response.json().catch(() => ({}));

			if (!response.ok) {
				const error = new Error('Request failed.');
				const firstError = Object.values(payload?.errors || {})[0];
				const firstValidationMessage = Array.isArray(firstError) ? firstError[0] : null;
				error.message = firstValidationMessage || payload?.message || 'Request failed.';
				error.requiresDropoutAction = Boolean(payload?.errors?.dropout_action);
				throw error;
			}

			return payload;
		},
	};
}
