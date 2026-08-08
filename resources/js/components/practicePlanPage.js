function initialAttachmentFormState() {
	return {
		type: 'link',
		url: '',
		file: null,
		label: '',
	};
}

function humanAttachmentSize(sizeBytes) {
	if (!Number.isFinite(sizeBytes) || sizeBytes <= 0) {
		return '';
	}

	const units = ['B', 'KB', 'MB', 'GB'];
	let size = sizeBytes;
	let unitIndex = 0;

	while (size >= 1024 && unitIndex < units.length - 1) {
		size /= 1024;
		unitIndex += 1;
	}

	const rounded = size >= 10 || unitIndex === 0 ? Math.round(size) : Math.round(size * 10) / 10;
	return `${rounded}${units[unitIndex]}`;
}

export function practicePlanPage(config = {}) {
	return {
		csrfToken: config.csrfToken || null,
		openAttachments: false,
		attachments: [],
		attachmentsLoading: false,
		attachmentsLoaded: false,
		attachmentsError: '',
		attachmentsFeedback: '',
		canManageAttachments: false,
		attachmentsListUrl: null,
		attachmentsStoreUrl: null,
		attachmentContextLabel: '',
		activeAttachmentKey: null,
		attachmentCounts: {},
		attachmentCount: 0,
		attachmentForm: initialAttachmentFormState(),
		attachmentFormBusy: false,
		attachmentFormError: '',
		deletingAttachmentId: null,
		failedResponseMessage: async (response, fallback) => {
			let message = fallback;

			try {
				const payload = await response.json();
				const validationErrors = Object.values(payload.errors || {}).flat();
				message = validationErrors[0] || payload.message || fallback;
			} catch (e) {
				message = fallback;
			}

			return message;
		},
		openAttachmentsFor(el) {
			if (!el) {
				return;
			}

			this.attachmentsListUrl = el.dataset.attachmentListUrl || null;
			this.attachmentsStoreUrl = el.dataset.attachmentStoreUrl || null;
			this.attachmentContextLabel = el.dataset.attachmentContext || '';
			this.activeAttachmentKey = el.dataset.attachmentKey || null;
			const initialCount = Number(el.dataset.attachmentCount || 0);

			if (this.activeAttachmentKey) {
				this.attachmentCounts[this.activeAttachmentKey] = this.resolveAttachmentCount(this.activeAttachmentKey, initialCount);
			}

			this.openAttachmentsModal();
		},
		resolveAttachmentCount(key, fallbackCount = 0) {
			const known = this.attachmentCounts[key];
			if (Number.isFinite(known)) {
				return Math.max(0, Number(known));
			}

			const parsedFallback = Number(fallbackCount || 0);
			if (!Number.isFinite(parsedFallback)) {
				return 0;
			}

			return Math.max(0, parsedFallback);
		},
		hasAttachments(key, fallbackCount = 0) {
			return this.resolveAttachmentCount(key, fallbackCount) > 0;
		},
		syncActiveAttachmentCount() {
			if (!this.activeAttachmentKey) {
				return;
			}

			this.attachmentCounts[this.activeAttachmentKey] = Math.max(0, Number(this.attachmentCount || 0));
		},
		async openAttachmentsModal() {
			this.openAttachments = true;
			this.attachments = [];
			this.attachmentsLoaded = false;
			await this.loadAttachments();
		},
		hasUncommittedAttachmentDraft() {
			const label = (this.attachmentForm?.label || '').trim();
			const url = (this.attachmentForm?.url || '').trim();
			const hasFile = Boolean(this.attachmentForm?.file);

			return label !== '' || url !== '' || hasFile;
		},
		closeAttachmentsModal(force = false) {
			if (this.openAttachments && !force && this.hasUncommittedAttachmentDraft()) {
				const continueEditing = window.confirm('You may not have finished adding an attachment. Do you want to continue editing?');
				if (continueEditing) {
					return;
				}
			}

			this.openAttachments = false;
			this.attachmentsFeedback = '';
			this.attachmentFormError = '';
			this.attachmentFormBusy = false;
			this.deletingAttachmentId = null;
			this.attachmentForm = initialAttachmentFormState();
			this.activeAttachmentKey = null;

			if (this.$refs.attachmentFileInput) {
				this.$refs.attachmentFileInput.value = '';
			}
		},
		attachmentSizeLabel(sizeBytes) {
			return humanAttachmentSize(Number(sizeBytes));
		},
		async loadAttachments() {
			if (!this.attachmentsListUrl) {
				this.attachmentsError = 'Attachments are not available for this item.';
				return;
			}

			this.attachmentsLoading = true;
			this.attachmentsError = '';

			try {
				const response = await fetch(this.attachmentsListUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Could not load attachments right now.');
				}

				const payload = await response.json();
				this.attachments = payload.attachments ?? [];
				this.attachmentCount = this.attachments.length;
				this.canManageAttachments = Boolean(payload.can_manage);
				this.attachmentsLoaded = true;
				this.syncActiveAttachmentCount();
			} catch (error) {
				this.attachmentsError = error.message || 'Could not load attachments right now.';
			} finally {
				this.attachmentsLoading = false;
			}
		},
		onAttachmentFileSelected(event) {
			const [selectedFile] = event.target.files ?? [];
			this.attachmentForm.file = selectedFile ?? null;
		},
		async submitAttachmentForm() {
			if (!this.attachmentsStoreUrl || this.attachmentFormBusy || !this.canManageAttachments) {
				return;
			}

			this.attachmentFormBusy = true;
			this.attachmentFormError = '';
			this.attachmentsFeedback = '';

			try {
				const body = new FormData();
				body.append('type', this.attachmentForm.type);
				body.append('label', this.attachmentForm.label || '');

				if (this.attachmentForm.type === 'link') {
					body.append('url', this.attachmentForm.url || '');
				} else if (this.attachmentForm.file) {
					body.append('file', this.attachmentForm.file);
				}

				const response = await fetch(this.attachmentsStoreUrl, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
					body,
				});

				if (!response.ok) {
					const message = await this.failedResponseMessage(response, 'Could not add attachment. Try again.');
					throw new Error(message || 'Could not add attachment. Try again.');
				}

				const payload = await response.json();
				this.attachments = payload.attachments ?? [];
				this.attachmentCount = this.attachments.length;
				this.canManageAttachments = Boolean(payload.can_manage);
				this.attachmentForm = initialAttachmentFormState();
				this.attachmentsFeedback = payload.message || 'Attachment added.';
				this.syncActiveAttachmentCount();
			} catch (error) {
				this.attachmentFormError = error.message || 'Could not add attachment. Try again.';
			} finally {
				this.attachmentFormBusy = false;
			}
		},
		async removeAttachment(attachmentId) {
			if (this.deletingAttachmentId || !this.canManageAttachments) {
				return;
			}

			this.deletingAttachmentId = attachmentId;
			this.attachmentFormError = '';
			this.attachmentsFeedback = '';

			try {
				const response = await fetch(`/attachments/${attachmentId}`, {
					method: 'DELETE',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': this.csrfToken,
					},
				});

				if (!response.ok) {
					const message = await this.failedResponseMessage(response, 'Could not remove attachment. Try again.');
					throw new Error(message || 'Could not remove attachment. Try again.');
				}

				const payload = await response.json();
				this.attachments = payload.attachments ?? [];
				this.attachmentCount = this.attachments.length;
				this.canManageAttachments = Boolean(payload.can_manage);
				this.attachmentsFeedback = payload.message || 'Attachment removed.';
				this.syncActiveAttachmentCount();
			} catch (error) {
				this.attachmentFormError = error.message || 'Could not remove attachment. Try again.';
			} finally {
				this.deletingAttachmentId = null;
			}
		},
	};
}
