export function mySetsLibrary() {
	return {
		query: '',
		selectedSet: null,
		popoverVisible: false,
		popoverStyle: '',
		popoverPlacement: 'bottom',
		popoverCardId: null,
		resizeHandler: null,
		init() {
			this.resizeHandler = () => this.positionPopover();

			window.addEventListener('resize', this.resizeHandler);
		},
		destroy() {
			window.removeEventListener('resize', this.resizeHandler);
		},
		matchesSetCard(card) {
			if (!card) {
				return false;
			}

			const query = this.query.trim().toLowerCase();
			const haystack = String(card.dataset.search || '').toLowerCase();

			if (query === '') {
				return true;
			}

			return haystack.includes(query);
		},
		openSetPopover(el) {
			if (!el) {
				return;
			}

			const cardId = String(el.dataset.cardId || '');
			if (this.popoverVisible && this.popoverCardId === cardId) {
				this.closeSetPopover();
				return;
			}

			const collaborators = String(el.dataset.collaborators || '')
				.split('|')
				.map((name) => name.trim())
				.filter((name) => name.length > 0);

			this.selectedSet = {
				name: el.dataset.name || '',
				owner: el.dataset.owner || 'Unknown',
				session: el.dataset.session || '',
				date: el.dataset.date || '',
				songs: Number(el.dataset.songs || 0),
				lifecycle: el.dataset.lifecycleLabel || '',
				isOwned: el.dataset.owned === '1',
				isCollaborator: el.dataset.collaborator === '1',
				hasMySlots: el.dataset.hasMySlots === '1',
				collaborators,
				openUrl: el.dataset.openUrl || '',
				manageUrl: el.dataset.manageUrl || '',
			};

			this.popoverCardId = cardId;
			this.popoverVisible = true;

			this.$nextTick(() => {
				this.positionPopover(el);
				this.$nextTick(() => this.positionPopover(el));
			});
		},
		closeSetPopover() {
			this.popoverVisible = false;
			this.popoverCardId = null;
			this.popoverPlacement = 'bottom';
		},
		positionPopover(sourceEl = null) {
			if (!this.popoverVisible) {
				return;
			}

			const anchor = sourceEl || this.currentPopoverAnchor();
			if (!anchor) {
				return;
			}

			const viewportPadding = 16;
			const popoverWidth = Math.min(352, window.innerWidth - (viewportPadding * 2));
			const anchorRect = anchor.getBoundingClientRect();
			const scrollX = window.scrollX;
			const scrollY = window.scrollY;
			const viewportHeight = window.innerHeight;
			const popoverHeight = this.$refs?.setPopoverPanel?.offsetHeight || 360;

			let left = scrollX + anchorRect.left + (anchorRect.width / 2) - (popoverWidth / 2);
			const minLeft = scrollX + viewportPadding;
			const maxLeft = scrollX + window.innerWidth - popoverWidth - viewportPadding;
			left = Math.max(minLeft, Math.min(left, maxLeft));

			const preferredBelowTop = scrollY + anchorRect.bottom + 12;
			const preferredAboveTop = scrollY + anchorRect.top - popoverHeight - 12;
			const belowBottom = anchorRect.bottom + 12 + popoverHeight;
			const canFitBelow = belowBottom <= (viewportHeight - viewportPadding);
			const canFitAbove = preferredAboveTop >= (scrollY + viewportPadding);

			let top = preferredBelowTop;
			this.popoverPlacement = 'bottom';

			if (!canFitBelow && canFitAbove) {
				top = preferredAboveTop;
				this.popoverPlacement = 'top';
			} else if (!canFitBelow && !canFitAbove) {
				top = Math.max(scrollY + viewportPadding, scrollY + anchorRect.top - (popoverHeight / 2));
			}

			this.popoverStyle = `left:${Math.round(left)}px;top:${Math.round(top)}px;width:${Math.round(popoverWidth)}px;`;
		},
		currentPopoverAnchor() {
			if (!this.popoverCardId) {
				return null;
			}

			return this.$root.querySelector(`[data-card-id="${this.popoverCardId}"]`);
		},
	};
}
