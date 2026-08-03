function createEmptyForm() {
	return {
		title: '',
		content: '',
		level: 'info',
		location: 'below_header',
		audience_scope: 'all_users',
		show_on_all_pages: true,
		show_on_routes: [],
		dismissable: true,
		enabled: false,
	};
}

function normalizeForm(form) {
	return {
		title: form.title,
		content: form.content,
		level: form.level,
		location: form.location,
		audience_scope: form.audience_scope,
		show_on_all_pages: Boolean(form.show_on_all_pages),
		show_on_routes: form.show_on_routes || [],
		dismissable: Boolean(form.dismissable),
		enabled: Boolean(form.enabled),
	};
}

function isInteractiveDragSource(event) {
	if (!event?.target || typeof event.target.closest !== 'function') {
		return false;
	}

	return Boolean(event.target.closest('button, a, input, select, textarea, label, [data-no-notice-drag]'));
}

export function adminNotices(config = {}) {
	return {
		loading: true,
		saving: false,
		error: '',
		message: '',
		notices: [],
		routeOptions: config.routeOptions || [],
		locationOptions: config.locationOptions || [],
		audienceScopeOptions: config.audienceScopeOptions || [],
		levelOptions: config.levelOptions || [],
		listUrl: config.listUrl || '',
		storeUrl: config.storeUrl || '',
		reorderUrl: config.reorderUrl || '',
		previewUrl: config.previewUrl || '',
		clearDismissalsUrlTemplate: config.clearDismissalsUrlTemplate || '',
		updateUrlTemplate: config.updateUrlTemplate || '',
		deleteUrlTemplate: config.deleteUrlTemplate || '',
		csrfToken: config.csrfToken || '',
		createModalName: 'admin-notices-create',
		editModalName: 'admin-notices-edit',
		resetDismissalsModalName: 'admin-notices-reset-dismissals',
		createForm: createEmptyForm(),
		editForm: createEmptyForm(),
		createPreviewHtml: '',
		editPreviewHtml: '',
		createPreviewRequestId: 0,
		editPreviewRequestId: 0,
		editInitiallyDismissable: false,
		dragNoticeId: null,
		draggingNoticeId: null,
		dragLocation: null,
		dropTargetNoticeId: null,
		dropTargetPosition: 'before',
		isDesktopReorderEnabled: false,
		reorderBusyByLocation: {},
		resettingDismissals: false,
		resettingDismissalsNoticeId: null,
		editingNoticeId: null,

		async init() {
			this.syncDesktopReorderEnabled();
			await this.refresh();
		},

		newForm() {
			return createEmptyForm();
		},

		normalizeForm(form) {
			return normalizeForm(form);
		},

		openCreateModal() {
			this.error = '';
			this.createForm = this.newForm();
			this.createPreviewHtml = '';
			void this.refreshCreatePreview();
			window.dispatchEvent(new CustomEvent('open-modal', { detail: this.createModalName }));
		},

		closeCreateModal() {
			window.dispatchEvent(new CustomEvent('close-modal', { detail: this.createModalName }));
		},

		openEditModal(notice) {
			this.error = '';
			this.editingNoticeId = notice.id;
			this.editInitiallyDismissable = Boolean(notice.dismissable);
			this.editForm = {
				title: notice.title || '',
				content: notice.content || '',
				level: notice.level || 'info',
				location: notice.location || 'below_header',
				audience_scope: notice.audience_scope || 'all_users',
				show_on_all_pages: Boolean(notice.show_on_all_pages),
				show_on_routes: [...(notice.show_on_routes || [])],
				dismissable: Boolean(notice.dismissable),
				enabled: Boolean(notice.enabled),
			};
			this.editPreviewHtml = notice.content_html || '';
			void this.refreshEditPreview();
			window.dispatchEvent(new CustomEvent('open-modal', { detail: this.editModalName }));
		},

		closeEditModal() {
			window.dispatchEvent(new CustomEvent('close-modal', { detail: this.editModalName }));
		},

		openResetDismissalsModal(notice) {
			if (notice?.busy) {
				return;
			}

			this.error = '';
			this.resettingDismissalsNoticeId = Number(notice.id);
			window.dispatchEvent(new CustomEvent('open-modal', { detail: this.resetDismissalsModalName }));
		},

		closeResetDismissalsModal() {
			window.dispatchEvent(new CustomEvent('close-modal', { detail: this.resetDismissalsModalName }));
		},

		extractError(payload, fallback) {
			if (payload?.message) {
				return payload.message;
			}

			const firstError = Object.values(payload?.errors || {})[0];

			if (Array.isArray(firstError) && firstError.length > 0) {
				return firstError[0];
			}

			return fallback;
		},

		clearMessage() {
			if (!this.message) {
				return;
			}

			window.setTimeout(() => {
				this.message = '';
			}, 2500);
		},

		escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},

		fallbackPreviewHtml(value) {
			if (!value) {
				return '';
			}

			return `<p>${this.escapeHtml(value).replace(/\n/g, '<br>')}</p>`;
		},

		async renderPreviewHtml(content) {
			if (!this.previewUrl) {
				return this.fallbackPreviewHtml(content);
			}

			try {
				const response = await fetch(this.previewUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body: JSON.stringify({ content: content || '' }),
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					return this.fallbackPreviewHtml(content);
				}

				return payload.content_html || '';
			} catch (error) {
				return this.fallbackPreviewHtml(content);
			}
		},

		async refreshCreatePreview() {
			const requestId = ++this.createPreviewRequestId;
			const html = await this.renderPreviewHtml(this.createForm.content);

			if (requestId !== this.createPreviewRequestId) {
				return;
			}

			this.createPreviewHtml = html;
		},

		async refreshEditPreview() {
			const requestId = ++this.editPreviewRequestId;
			const html = await this.renderPreviewHtml(this.editForm.content);

			if (requestId !== this.editPreviewRequestId) {
				return;
			}

			this.editPreviewHtml = html;
		},

		async refresh() {
			this.loading = true;
			this.error = '';

			try {
				const response = await fetch(this.listUrl, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(payload.message || 'Could not load notices.');
				}

				this.notices = (payload.notices || []).map((notice) => ({
					...notice,
					busy: false,
				}));
				this.sortNotices();
			} catch (error) {
				this.error = error?.message || 'Could not load notices.';
			} finally {
				this.loading = false;
			}
		},

		async createNotice() {
			if (this.$refs.createTitleInput && !this.$refs.createTitleInput.checkValidity()) {
				this.$refs.createTitleInput.reportValidity();
				return;
			}

			this.saving = true;
			this.error = '';

			try {
				const response = await fetch(this.storeUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body: JSON.stringify(this.normalizeForm(this.createForm)),
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(this.extractError(payload, 'Could not create notice.'));
				}

				this.notices.push({
					...payload.notice,
					busy: false,
				});
				this.sortNotices();

				this.createForm = this.newForm();
				this.closeCreateModal();

				this.message = payload.message || 'Notice created.';
				this.clearMessage();
			} catch (error) {
				this.error = error?.message || 'Could not create notice.';
			} finally {
				this.saving = false;
			}
		},

		async saveEditNotice() {
			if (this.$refs.editTitleInput && !this.$refs.editTitleInput.checkValidity()) {
				this.$refs.editTitleInput.reportValidity();
				return;
			}

			if (this.editingNoticeId === null) {
				return;
			}

			const notice = this.notices.find((item) => Number(item.id) === Number(this.editingNoticeId));

			if (!notice || notice.busy) {
				return;
			}

			notice.busy = true;
			this.saving = true;
			this.error = '';

			try {
				const response = await fetch(this.updateUrlTemplate.replace('__NOTICE_ID__', String(this.editingNoticeId)), {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body: JSON.stringify(this.normalizeForm(this.editForm)),
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(this.extractError(payload, 'Could not update notice.'));
				}

				Object.assign(notice, payload.notice, { busy: false });
				this.sortNotices();
				this.closeEditModal();
				this.editingNoticeId = null;
				this.message = payload.message || 'Notice updated.';
				this.clearMessage();
			} catch (error) {
				this.error = error?.message || 'Could not update notice.';
			} finally {
				this.saving = false;
				notice.busy = false;
			}
		},

		async deleteNotice(notice) {
			if (notice.busy) {
				return;
			}

			notice.busy = true;
			this.error = '';

			try {
				const response = await fetch(this.deleteUrlTemplate.replace('__NOTICE_ID__', String(notice.id)), {
					method: 'DELETE',
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(this.extractError(payload, 'Could not delete notice.'));
				}

				this.notices = this.notices.filter((item) => Number(item.id) !== Number(notice.id));
				this.message = payload.message || 'Notice deleted.';
				this.clearMessage();
			} catch (error) {
				this.error = error?.message || 'Could not delete notice.';
				notice.busy = false;
			}
		},

		async deleteEditingNotice() {
			if (this.editingNoticeId === null) {
				return;
			}

			if (!window.confirm('Delete this notice? This cannot be undone.')) {
				return;
			}

			const notice = this.notices.find((item) => Number(item.id) === Number(this.editingNoticeId));

			if (!notice) {
				return;
			}

			await this.deleteNotice(notice);

			if (!this.notices.some((item) => Number(item.id) === Number(this.editingNoticeId))) {
				this.closeEditModal();
				this.editingNoticeId = null;
			}
		},

		async resetDismissalsForNotice() {
			if (this.resettingDismissalsNoticeId === null || this.resettingDismissals || !this.clearDismissalsUrlTemplate) {
				return;
			}

			const notice = this.notices.find((item) => Number(item.id) === Number(this.resettingDismissalsNoticeId));

			if (!notice || notice.busy) {
				return;
			}

			notice.busy = true;
			this.resettingDismissals = true;
			this.error = '';

			try {
				const response = await fetch(
					this.clearDismissalsUrlTemplate.replace('__NOTICE_ID__', String(this.resettingDismissalsNoticeId)),
					{
						method: 'DELETE',
						headers: {
							Accept: 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
							'X-CSRF-TOKEN': this.csrfToken,
						},
					}
				);

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(this.extractError(payload, 'Could not clear dismissals.'));
				}

				this.closeResetDismissalsModal();
				this.message = payload.message || 'Dismissals cleared.';
				this.clearMessage();
			} catch (error) {
				this.error = error?.message || 'Could not clear dismissals.';
			} finally {
				this.resettingDismissals = false;
				notice.busy = false;
			}
		},

		syncDesktopReorderEnabled() {
			this.isDesktopReorderEnabled = window.matchMedia('(min-width: 768px)').matches;
		},

		locationIndex(location) {
			const index = this.locationOptions.findIndex((option) => option.value === location);
			return index === -1 ? Number.MAX_SAFE_INTEGER : index;
		},

		sortNotices() {
			this.notices = [...this.notices].sort((left, right) => {
				const locationOrder = this.locationIndex(left.location) - this.locationIndex(right.location);

				if (locationOrder !== 0) {
					return locationOrder;
				}

				const leftPosition = Number(left.position ?? Number.MAX_SAFE_INTEGER);
				const rightPosition = Number(right.position ?? Number.MAX_SAFE_INTEGER);

				if (leftPosition !== rightPosition) {
					return leftPosition - rightPosition;
				}

				return Number(left.id) - Number(right.id);
			});
		},

		noticesForLocation(location) {
			return this.notices.filter((notice) => notice.location === location);
		},

		captureLocationCardRects(location) {
			const noticesContainer = this.$root?.querySelector(`[data-notice-items-for='${location}']`);

			if (!noticesContainer) {
				return new Map();
			}

			return new Map(
				Array.from(noticesContainer.querySelectorAll('[data-notice-id]')).map((element) => [
					Number(element.dataset.noticeId),
					element.getBoundingClientRect(),
				])
			);
		},

		animateLocationReorder(location, previousRects) {
			if (!previousRects || previousRects.size === 0) {
				return;
			}

			this.$nextTick(() => {
				const noticesContainer = this.$root?.querySelector(`[data-notice-items-for='${location}']`);

				if (!noticesContainer) {
					return;
				}

				Array.from(noticesContainer.querySelectorAll('[data-notice-id]')).forEach((element) => {
					const noticeId = Number(element.dataset.noticeId);
					const previousRect = previousRects.get(noticeId);

					if (!previousRect) {
						return;
					}

					const currentRect = element.getBoundingClientRect();
					const deltaY = previousRect.top - currentRect.top;

					if (Math.abs(deltaY) < 1) {
						return;
					}

					element.style.transition = 'none';
					element.style.transform = `translateY(${deltaY}px)`;
					element.style.boxShadow = '0 0 0 0 rgba(245, 158, 11, 0)';

					// Force style flush so the browser applies the initial transform
					// before transitioning to the final position.
					void element.offsetHeight;

					requestAnimationFrame(() => {
						element.style.transition = 'transform 320ms cubic-bezier(0.2, 0, 0, 1), box-shadow 320ms ease-out';
						element.style.transform = 'translateY(0)';
						element.style.boxShadow = '0 0 0 2px rgba(245, 158, 11, 0.45)';

						const clearInlineStyles = () => {
							element.style.transition = '';
							element.style.transform = '';
							element.style.boxShadow = '';
							element.removeEventListener('transitionend', clearInlineStyles);
						};

						element.addEventListener('transitionend', clearInlineStyles);
					});
				});
			});
		},

		isReorderBusy(location) {
			return Boolean(this.reorderBusyByLocation[location]);
		},

		canMoveNoticeUp(noticeId, location) {
			const notices = this.noticesForLocation(location);
			return notices.findIndex((notice) => Number(notice.id) === Number(noticeId)) > 0;
		},

		canMoveNoticeDown(noticeId, location) {
			const notices = this.noticesForLocation(location);
			const currentIndex = notices.findIndex((notice) => Number(notice.id) === Number(noticeId));
			return currentIndex >= 0 && currentIndex < notices.length - 1;
		},

		applyLocationOrder(location, orderedNoticeIds) {
			const orderedById = new Map(orderedNoticeIds.map((noticeId, index) => [Number(noticeId), index]));
			const locationNotices = this.notices
				.filter((notice) => notice.location === location)
				.sort((left, right) => orderedById.get(Number(left.id)) - orderedById.get(Number(right.id)));

			locationNotices.forEach((notice, index) => {
				notice.position = index + 1;
			});

			this.sortNotices();
		},

		async persistLocationOrder(location, orderedNoticeIds) {
			if (!this.reorderUrl) {
				return;
			}

			this.reorderBusyByLocation = {
				...this.reorderBusyByLocation,
				[location]: true,
			};

			try {
				const response = await fetch(this.reorderUrl, {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body: JSON.stringify({
						location,
						notice_ids: orderedNoticeIds,
					}),
				});

				const payload = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(this.extractError(payload, 'Could not reorder notices.'));
				}

				this.message = payload.message || 'Notice order updated.';
				this.clearMessage();
			} catch (error) {
				this.error = error?.message || 'Could not reorder notices.';
				await this.refresh();
			} finally {
				this.reorderBusyByLocation = {
					...this.reorderBusyByLocation,
					[location]: false,
				};
			}
		},

		async moveNotice(noticeId, location, direction) {
			if (this.isReorderBusy(location)) {
				return;
			}

			const notices = this.noticesForLocation(location);
			const currentIndex = notices.findIndex((notice) => Number(notice.id) === Number(noticeId));
			const targetIndex = currentIndex + direction;

			if (currentIndex < 0 || targetIndex < 0 || targetIndex >= notices.length) {
				return;
			}

			const orderedNoticeIds = notices.map((notice) => Number(notice.id));
			const [movedNoticeId] = orderedNoticeIds.splice(currentIndex, 1);
			orderedNoticeIds.splice(targetIndex, 0, movedNoticeId);
			const previousRects = this.captureLocationCardRects(location);

			this.applyLocationOrder(location, orderedNoticeIds);
			this.animateLocationReorder(location, previousRects);
			await this.persistLocationOrder(location, orderedNoticeIds);
		},

		clearNoticeDropPlaceholder(location) {
			const placeholderEl = this.$root?.querySelector(`[data-notice-drop-placeholder='${location}']`);

			if (!placeholderEl) {
				return;
			}

			placeholderEl.classList.add('hidden');
		},

		clearNoticeDragState() {
			if (this.dragLocation) {
				this.clearNoticeDropPlaceholder(this.dragLocation);
			}

			this.dragNoticeId = null;
			this.draggingNoticeId = null;
			this.dragLocation = null;
			this.dropTargetNoticeId = null;
			this.dropTargetPosition = 'before';
		},

		onNoticeDragStart(event, noticeId, location) {
			if (!this.isDesktopReorderEnabled || this.isReorderBusy(location) || isInteractiveDragSource(event)) {
				event.preventDefault();
				return;
			}

			this.dragNoticeId = Number(noticeId);
			this.draggingNoticeId = Number(noticeId);
			this.dragLocation = location;

			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('application/x-backstage-notice-id', String(noticeId));
			event.dataTransfer.setData('application/x-backstage-notice-location', location);
			event.dataTransfer.setData('text/plain', String(noticeId));
		},

		onNoticeDragEnd() {
			this.clearNoticeDragState();
		},

		onNoticeDragOver(event, targetNoticeId, location) {
			if (!this.isDesktopReorderEnabled || this.dragNoticeId === null || this.dragLocation !== location || this.isReorderBusy(location)) {
				return;
			}

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';

			if (this.dragNoticeId === Number(targetNoticeId)) {
				return;
			}

			const noticesContainer = this.$root?.querySelector(`[data-notice-items-for='${location}']`);
			const draggedEl = noticesContainer?.querySelector(`[data-notice-id='${this.dragNoticeId}']`);
			const targetEl = noticesContainer?.querySelector(`[data-notice-id='${targetNoticeId}']`);
			const placeholderEl = this.$root?.querySelector(`[data-notice-drop-placeholder='${location}']`);

			if (!noticesContainer || !draggedEl || !targetEl || !placeholderEl) {
				return;
			}

			const targetRect = targetEl.getBoundingClientRect();
			const placeAfter = event.clientY > (targetRect.top + targetRect.height / 2);
			const insertionReference = placeAfter ? targetEl.nextElementSibling : targetEl;

			const noticeElements = Array.from(noticesContainer.querySelectorAll('[data-notice-id]'));
			const currentIndex = noticeElements.indexOf(draggedEl);
			const referenceIndex = insertionReference ? noticeElements.indexOf(insertionReference) : noticeElements.length;
			const prospectiveIndex = insertionReference
				? (referenceIndex > currentIndex ? referenceIndex - 1 : referenceIndex)
				: noticeElements.length - 1;

			if (prospectiveIndex === currentIndex) {
				this.clearNoticeDropPlaceholder(location);
				this.dropTargetNoticeId = null;
				return;
			}

			placeholderEl.classList.remove('hidden');
			if (insertionReference !== placeholderEl) {
				noticesContainer.insertBefore(placeholderEl, insertionReference);
			}

			this.dropTargetNoticeId = Number(targetNoticeId);
			this.dropTargetPosition = placeAfter ? 'after' : 'before';
		},

		onNoticeGroupDragOver(event, location) {
			if (!this.isDesktopReorderEnabled || this.dragNoticeId === null || this.dragLocation !== location || this.isReorderBusy(location)) {
				return;
			}

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';

			const noticesContainer = this.$root?.querySelector(`[data-notice-items-for='${location}']`);
			const placeholderEl = this.$root?.querySelector(`[data-notice-drop-placeholder='${location}']`);

			if (!noticesContainer || !placeholderEl) {
				return;
			}

			const noticeElements = Array.from(noticesContainer.querySelectorAll('[data-notice-id]'));
			const lastNoticeEl = noticeElements[noticeElements.length - 1];

			// Only treat the group surface as an "append to end" target when
			// the cursor is below the last card. This avoids placeholder jumping
			// when hovering in spacing/border areas between cards.
			if (lastNoticeEl) {
				const lastNoticeRect = lastNoticeEl.getBoundingClientRect();

				if (event.clientY <= lastNoticeRect.bottom) {
					return;
				}
			}

			placeholderEl.classList.remove('hidden');
			noticesContainer.appendChild(placeholderEl);
			this.dropTargetNoticeId = null;
			this.dropTargetPosition = 'after';
		},

		onNoticePlaceholderDragOver(event, location) {
			if (!this.isDesktopReorderEnabled || this.dragNoticeId === null || this.dragLocation !== location || this.isReorderBusy(location)) {
				return;
			}

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
		},

		async onNoticeDrop(event, location) {
			if (!this.isDesktopReorderEnabled || this.dragNoticeId === null || this.dragLocation !== location || this.isReorderBusy(location)) {
				this.clearNoticeDragState();
				return;
			}

			event.preventDefault();

			const noticesContainer = this.$root?.querySelector(`[data-notice-items-for='${location}']`);
			const draggedEl = noticesContainer?.querySelector(`[data-notice-id='${this.dragNoticeId}']`);
			const placeholderEl = this.$root?.querySelector(`[data-notice-drop-placeholder='${location}']`);

			if (!noticesContainer || !draggedEl || !placeholderEl) {
				this.clearNoticeDragState();
				return;
			}

			const previousRects = this.captureLocationCardRects(location);

			if (placeholderEl.parentNode === noticesContainer) {
				noticesContainer.insertBefore(draggedEl, placeholderEl);
			}

			const orderedNoticeIds = Array.from(noticesContainer.querySelectorAll('[data-notice-id]')).map((el) => Number(el.dataset.noticeId));
			this.clearNoticeDragState();

			this.applyLocationOrder(location, orderedNoticeIds);
			this.animateLocationReorder(location, previousRects);
			await this.persistLocationOrder(location, orderedNoticeIds);
		},

		levelLabel(level) {
			return this.levelOptions.find((option) => option.value === level)?.label || level;
		},

		locationLabel(location) {
			return this.locationOptions.find((option) => option.value === location)?.label || location;
		},

		audienceScopeLabel(scope) {
			return this.audienceScopeOptions.find((option) => option.value === scope)?.label || scope;
		},

		routeLabel(name) {
			return this.routeOptions.find((option) => option.name === name)?.label || name;
		},

		routeLabelsSummary(routeNames) {
			return (routeNames || []).map((name) => this.routeLabel(name)).join(', ');
		},

		noticeTextAlignmentClass(location) {
			return location === 'below_header' ? 'text-left' : 'text-center';
		},

		groupedNotices() {
			return this.locationOptions
				.map((option) => ({
					location: option.value,
					label: option.label,
					notices: this.notices.filter((notice) => notice.location === option.value),
				}))
				.filter((group) => group.notices.length > 0);
		},

		noticeLevelStyles(level) {
			const stylesByLevel = {
				info: {
					container: 'border-sky-200 bg-sky-50 text-sky-950',
					icon: 'text-sky-600',
					body: 'text-sky-900/90',
					button: 'text-sky-700 hover:bg-sky-100 focus:ring-sky-500',
				},
				warning: {
					container: 'border-amber-200 bg-amber-50 text-amber-950',
					icon: 'text-amber-600',
					body: 'text-amber-900/90',
					button: 'text-amber-700 hover:bg-amber-100 focus:ring-amber-500',
				},
				critical: {
					container: 'border-rose-200 bg-rose-50 text-rose-950',
					icon: 'text-rose-600',
					body: 'text-rose-900/90',
					button: 'text-rose-700 hover:bg-rose-100 focus:ring-rose-500',
				},
			};

			return stylesByLevel[level] || stylesByLevel.info;
		},

		handleModalClosed(name) {
			if (name === this.editModalName) {
				this.editingNoticeId = null;
				this.editInitiallyDismissable = false;
				this.saving = false;
			}

			if (name === this.resetDismissalsModalName) {
				this.resettingDismissals = false;
				this.resettingDismissalsNoticeId = null;
			}
		},

		handleCreateRequested() {
			this.error = '';
			this.createForm = this.newForm();
			this.createPreviewHtml = '';
			void this.refreshCreatePreview();
		},
	};
}