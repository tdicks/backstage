import { isInteractiveDragSource } from '../utils/drag';

export function registerSessionCards(Alpine) {
    Alpine.data('sessionAttendanceControl', sessionAttendanceControl);
    Alpine.data('sessionSetCard', sessionSetCard);
    Alpine.data('sessionSongCard', sessionSongCard);
    Alpine.data('sessionSlotRow', sessionSlotRow);
    Alpine.data('sessionSongRequestRow', sessionSongRequestRow);
}

function baseDragState() {
    return {
        isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,
        syncDesktopReorderEnabled() {
            this.isDesktopReorderEnabled = window.matchMedia('(min-width: 768px)').matches;
        },
    };
}

function viewportActionMenuStyle(button, menu = null) {
    const buttonRect = button.getBoundingClientRect();
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
}

const queuedLazySetBodies = new Map();
let lazySetBodyQueueActive = false;
const lazySetBodyViewportBuffer = 320;

function isWithinLazySetBodyBuffer(element) {
    const { top, bottom } = element.getBoundingClientRect();

    return bottom >= -lazySetBodyViewportBuffer
        && top <= window.innerHeight + lazySetBodyViewportBuffer;
}

function lazySetBodyDistanceFromViewport(element) {
    const { top, bottom } = element.getBoundingClientRect();

    if (bottom < 0) {
        return -bottom;
    }

    return top > window.innerHeight ? top - window.innerHeight : 0;
}

function appendSessionFragment(container, html) {
    if (!container || !html) {
        return [];
    }

    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const elements = Array.from(template.content.children);

    elements.forEach((element) => {
        container.appendChild(element);

        if (window.Alpine) {
            window.Alpine.initTree(element);
        }
    });

    return elements;
}

function initialAttachmentFormState() {
    return {
        type: 'link',
        label: '',
        url: '',
        file: null,
    };
}

function humanAttachmentSize(sizeBytes) {
    if (!Number.isFinite(sizeBytes) || sizeBytes <= 0) {
        return '';
    }

    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    const kilobytes = sizeBytes / 1024;
    if (kilobytes < 1024) {
        return `${kilobytes.toFixed(1)} KB`;
    }

    return `${(kilobytes / 1024).toFixed(1)} MB`;
}

function canvasBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
                return;
            }

            reject(new Error('Could not create the set image.'));
        }, 'image/png');
    });
}

function truncateCanvasText(context, value, maxWidth) {
    if (context.measureText(value).width <= maxWidth) {
        return value;
    }

    let text = value;
    while (text.length > 0 && context.measureText(`${text}...`).width > maxWidth) {
        text = text.slice(0, -1);
    }

    return `${text}...`;
}

function drawPersonIcon(context, left, top) {
    context.strokeStyle = '#64748b';
    context.lineWidth = 1.75;
    context.beginPath();
    context.arc(left + 7, top + 5, 3.25, 0, Math.PI * 2);
    context.moveTo(left + 1.5, top + 15);
    context.quadraticCurveTo(left + 7, top + 9.5, left + 12.5, top + 15);
    context.stroke();
}

function drawCalendarIcon(context, left, top) {
    context.strokeStyle = '#64748b';
    context.lineWidth = 1.75;
    context.strokeRect(left + 1.5, top + 3, 12, 11);
    context.beginPath();
    context.moveTo(left + 1.5, top + 7);
    context.lineTo(left + 13.5, top + 7);
    context.moveTo(left + 4.5, top + 1);
    context.lineTo(left + 4.5, top + 5);
    context.moveTo(left + 10.5, top + 1);
    context.lineTo(left + 10.5, top + 5);
    context.stroke();
}

function drawBackstageLogo(context, right, top) {
    const size = 28;
    const left = right - 106;
    context.strokeStyle = '#0f172a';
    context.lineWidth = 2;
    context.beginPath();
    context.roundRect(left, top, size, size, 7);
    context.moveTo(left + 8, top + 7);
    context.lineTo(left + 8, top + 21);
    context.moveTo(left + 8, top + 7);
    context.lineTo(left + 16, top + 7);
    context.quadraticCurveTo(left + 21, top + 7, left + 21, top + 11.5);
    context.quadraticCurveTo(left + 21, top + 16, left + 16, top + 16);
    context.lineTo(left + 8, top + 16);
    context.moveTo(left + 8, top + 16);
    context.lineTo(left + 16, top + 16);
    context.quadraticCurveTo(left + 21, top + 16, left + 21, top + 20.5);
    context.quadraticCurveTo(left + 21, top + 25, left + 16, top + 25);
    context.lineTo(left + 8, top + 25);
    context.stroke();
    context.strokeStyle = '#f59e0b';
    context.lineWidth = 1.5;
    context.beginPath();
    context.moveTo(left + 24, top + 3);
    context.lineTo(left + 24, top + 25);
    context.stroke();
    context.fillStyle = '#0f172a';
    context.font = '700 13px Instrument Sans, sans-serif';
    context.fillText('Backstage', left + 36, top + 18);
}

function drawAssignmentPill(context, assignment, left, top, maxWidth) {
    const isOpen = assignment?.state === 'open';
    const isAssigned = assignment?.state === 'user';
    const value = assignment?.display || (isOpen ? 'Open' : '-');
    const availableTextWidth = maxWidth - 18;
    const label = truncateCanvasText(context, value, availableTextWidth);
    const textWidth = context.measureText(label).width;
    const pillWidth = Math.min(maxWidth, textWidth + 18);

    if (!isAssigned && !isOpen) {
        context.fillStyle = '#94a3b8';
        context.fillText(label, left + 10, top + 19);
        return;
    }

    if (isOpen) {
        context.fillStyle = '#fffbeb';
        context.strokeStyle = '#fcd34d';
    } else if (assignment.is_manual) {
        context.fillStyle = '#fff7ed';
        context.strokeStyle = '#fdba74';
    } else {
        context.fillStyle = '#ecfdf5';
        context.strokeStyle = '#a7f3d0';
    }

    context.lineWidth = 1;
    context.beginPath();
    context.roundRect(left, top, pillWidth, 26, 13);
    context.fill();
    context.stroke();
    context.fillStyle = isOpen ? '#92400e' : assignment.is_manual ? '#9a3412' : '#065f46';
    context.fillText(label, left + 9, top + 17.5);
}

async function renderSetSummaryImage(summary, setName, ownerName, sessionDate) {
    const slots = summary.slot_names ?? [];
    const songs = summary.songs ?? [];
    const scale = 2;
    const padding = 32;
    const songColumnWidth = 280;
    const slotColumnWidth = 170;
    const headerHeight = 108;
    const tableHeaderHeight = 42;
    const rowHeight = 56;
    const footerHeight = 40;
    const headerPanelTop = 16;
    const headerPanelHeight = 78;
    const width = (padding * 2) + songColumnWidth + (slots.length * slotColumnWidth);
    const height = headerHeight + tableHeaderHeight + Math.max(1, songs.length) * rowHeight + footerHeight;
    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = height * scale;

    const context = canvas.getContext('2d');
    context.scale(scale, scale);
    context.fillStyle = '#e2e8f0';
    context.fillRect(0, 0, width, height);
    context.fillStyle = '#0ea5e9';
    context.fillRect(0, 0, width, 8);
    context.fillStyle = '#ffffff';
    context.beginPath();
    context.roundRect(padding, headerPanelTop, width - (padding * 2), headerPanelHeight, 10);
    context.fill();
    context.strokeStyle = '#cbd5e1';
    context.lineWidth = 1;
    context.stroke();
    context.fillStyle = '#0369a1';
    context.font = '700 10px Instrument Sans, sans-serif';
    context.fillText('SETLIST', padding + 14, 33);
    context.fillStyle = '#0f172a';
    context.font = '700 24px Instrument Sans, sans-serif';
    context.fillText(setName, padding + 14, 59);
    drawBackstageLogo(context, width - padding, 26);
    context.fillStyle = '#475569';
    context.font = '14px Instrument Sans, sans-serif';
    drawPersonIcon(context, padding + 14, 69);
    context.fillText(ownerName, padding + 33, 84);
    const ownerWidth = context.measureText(ownerName).width;
    const calendarLeft = padding + 33 + ownerWidth + 22;
    drawCalendarIcon(context, calendarLeft, 69);
    context.fillText(sessionDate, calendarLeft + 19, 84);

    const tableTop = headerHeight;
    context.fillStyle = '#0f172a';
    context.beginPath();
    context.roundRect(padding, tableTop, width - (padding * 2), tableHeaderHeight, 8);
    context.fill();
    context.font = '700 12px Instrument Sans, sans-serif';
    context.fillStyle = '#f8fafc';
    context.fillText('ARTIST / TITLE', padding + 14, tableTop + 26);

    slots.forEach((slot, index) => {
        const left = padding + songColumnWidth + (index * slotColumnWidth);
        context.fillText(truncateCanvasText(context, slot.label.toUpperCase(), slotColumnWidth - 24), left + 12, tableTop + 26);
    });

    const rows = songs.length > 0 ? songs : [{ artist: 'No songs in this set yet.', title: '', slot_map: {} }];
    rows.forEach((song, rowIndex) => {
        const top = tableTop + tableHeaderHeight + (rowIndex * rowHeight);
        context.fillStyle = rowIndex % 2 === 0 ? '#ffffff' : '#f8fafc';
        context.beginPath();
        context.roundRect(padding, top + 2, width - (padding * 2), rowHeight - 4, 7);
        context.fill();
        context.strokeStyle = '#f1f5f9';
        context.lineWidth = 1;
        context.beginPath();
        context.roundRect(padding, top + 2, width - (padding * 2), rowHeight - 4, 7);
        context.stroke();
        context.fillStyle = '#0f172a';
        context.font = '600 13px Instrument Sans, sans-serif';
        context.fillText(truncateCanvasText(context, `${song.artist}${song.title ? ` - ${song.title}` : ''}`, songColumnWidth - 26), padding + 14, top + 32);
        context.font = '600 12px Instrument Sans, sans-serif';

        slots.forEach((slot, slotIndex) => {
            const assignment = song.slot_map?.[slot.name];
            const left = padding + songColumnWidth + (slotIndex * slotColumnWidth);
            drawAssignmentPill(context, assignment, left + 10, top + 15, slotColumnWidth - 20);
        });
    });

    const footerTop = height - footerHeight;
    context.fillStyle = '#64748b';
    context.font = '500 11px Instrument Sans, sans-serif';
    context.textAlign = 'center';
    context.fillText(window.location.hostname, width / 2, footerTop + 25);
    context.textAlign = 'start';

    return canvasBlob(canvas);
}

function queueLazySetBody(rootElement, load) {
    queuedLazySetBodies.set(rootElement, load);
    processLazySetBodyQueue();
}

async function processLazySetBodyQueue() {
    if (lazySetBodyQueueActive) {
        return;
    }

    const next = Array.from(queuedLazySetBodies.entries())
        .filter(([rootElement]) => isWithinLazySetBodyBuffer(rootElement))
        .sort(([firstElement], [secondElement]) => lazySetBodyDistanceFromViewport(firstElement) - lazySetBodyDistanceFromViewport(secondElement))[0];

    if (!next) {
        return;
    }

    const [rootElement, load] = next;
    queuedLazySetBodies.delete(rootElement);
    lazySetBodyQueueActive = true;

    try {
        await load();
    } finally {
        lazySetBodyQueueActive = false;
        window.requestAnimationFrame(() => processLazySetBodyQueue());
    }
}

export function sessionSetCard(config) {
    return {
        setBodyUrl: config.setBodyUrl,
        contentLoaded: false,
        contentLoading: false,
        contentLoadError: '',
        lazyObserver: null,
        lazyRootElement: null,
        openSong: false,
        openSongRequest: false,
        actionMenuStyle: '',
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
        requestSongMode: 'manual',
        requestCatalogSongId: '',
        jamStandardSongs: config.jamStandardSongs ?? [],
        requestSongBusy: false,
        requestSongError: '',
        artistLookupUrl: config.artistLookupUrl,
        titleLookupUrl: config.titleLookupUrl,
        songRequestStoreUrl: config.songRequestStoreUrl,
        openSetEdit: false,
        openUnscheduleSet: false,
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
        summaryImageBusy: false,
        summaryImageError: '',
        summaryImageCopied: false,
        openSnapshot: false,
        snapshotImageBlob: null,
        snapshotImageUrl: '',
        snapshotCanShare: false,
        openAttachments: false,
        attachments: [],
        attachmentsLoading: false,
        attachmentsLoaded: false,
        attachmentsError: '',
        attachmentsFeedback: '',
        attachmentCount: Number(config.initialAttachmentCount ?? 0),
        attachmentForm: initialAttachmentFormState(),
        attachmentFormBusy: false,
        attachmentFormError: '',
        deletingAttachmentId: null,
        canManageAttachments: config.canManageAttachments,
        attachmentsListUrl: config.attachmentsListUrl,
        attachmentsStoreUrl: config.attachmentsStoreUrl,
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
            window.dispatchEvent(new CustomEvent('refresh-session-sets', {
                detail: { setId: this.setId },
            }));
        },
        initLazySetCard(rootElement) {
            this.lazyRootElement = rootElement;

            if (this.lazyObserver || !('IntersectionObserver' in window) || !rootElement) {
                return;
            }

            this.lazyObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        queueLazySetBody(rootElement, () => this.loadSetBody(rootElement));
                    }
                });
            }, {
                root: null,
                rootMargin: '320px 0px',
                threshold: 0.01,
            });

            this.lazyObserver.observe(rootElement);
        },
        async loadSetBody(rootElement = null) {
            if (this.contentLoaded || this.contentLoading || !this.setBodyUrl) {
                return;
            }

            this.contentLoading = true;
            this.contentLoadError = '';

            try {
                const response = await fetch(this.setBodyUrl, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load set details');
                }

                const html = await response.text();
                const container = this.$refs.setBodyContainer;

                if (container) {
                    container.innerHTML = html;

                    this.$nextTick(() => {
                        if (window.Alpine) {
                            window.Alpine.initTree(container);
                        }
                    });
                }

                this.contentLoaded = true;

                const observedRoot = rootElement ?? this.lazyRootElement;

                if (this.lazyObserver && observedRoot) {
                    this.lazyObserver.unobserve(observedRoot);
                }
            } catch (e) {
                this.contentLoadError = 'Could not load set details right now.';
            } finally {
                this.contentLoading = false;
            }
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

            window.dispatchEvent(new CustomEvent('song-reorder-start', {
                detail: { setId: this.setId },
            }));

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

            window.dispatchEvent(new CustomEvent('song-order-changed', {
                detail: { setId: this.setId },
            }));

            await this.persistSongOrder();
        },
        closeSessionModals() {
            this.closeSummaryModal();
            this.closeSnapshotModal();
            this.closeAttachmentsModal();
            this.openSetEdit = false;
            this.openUnscheduleSet = false;
            this.openSong = false;
            this.openSongRequest = false;
            this.openCollaborators = false;
            this.resetCollaboratorModal();
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        repositionActionMenu() {
            if (this.openActionMenu) {
                this.positionActionMenu();
            }
        },
        positionActionMenu() {
            this.$nextTick(() => {
                this.actionMenuStyle = viewportActionMenuStyle(this.$refs.actionMenuButton, this.$refs.actionMenu);
            });
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                this.openActionMenu = true;
                this.positionActionMenu();
                return;
            }

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
        async failedResponseMessage(response, fallback) {
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
        async openAttachmentsModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openAttachments = true;
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
            if (this.$refs.attachmentFileInput) {
                this.$refs.attachmentFileInput.value = '';
            }
        },
        attachmentSizeLabel(sizeBytes) {
            return humanAttachmentSize(Number(sizeBytes));
        },
        async loadAttachments() {
            if (!this.attachmentsListUrl) {
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
                        'X-CSRF-TOKEN': config.csrfToken,
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
                        'X-CSRF-TOKEN': config.csrfToken,
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
            } catch (error) {
                this.attachmentFormError = error.message || 'Could not remove attachment. Try again.';
            } finally {
                this.deletingAttachmentId = null;
            }
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

            // Only start song reordering when dragging from the song card header handle.
            if (!event.target.closest('[data-song-drag-handle]')) {
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
            event.dataTransfer.setData('application/x-backstage-song-id', String(songId));
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

            const dragTypes = Array.from(event.dataTransfer?.types ?? []);
            if (this.dragSongId === null || !dragTypes.includes('application/x-backstage-song-id')) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (targetSongId === null || this.dragSongId === targetSongId) {
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
            if (!this.canDragSongs() || this.reorderBusy) {
                this.clearDropPlaceholder();
                return;
            }

            const dragTypes = Array.from(event.dataTransfer?.types ?? []);
            if (this.dragSongId === null || !dragTypes.includes('application/x-backstage-song-id')) {
                return;
            }

            event.preventDefault();

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
                window.dispatchEvent(new CustomEvent('song-reorder-complete', {
                    detail: { setId: this.setId, succeeded: true },
                }));
            } catch (e) {
                this.reorderError = 'Could not save song order. Refresh and try again.';
                window.dispatchEvent(new CustomEvent('song-reorder-complete', {
                    detail: { setId: this.setId, succeeded: false },
                }));
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
        async openSnapshotModal() {
            if (this.summaryImageBusy) {
                return;
            }

            this.summaryImageBusy = true;
            this.summaryImageError = '';
            this.summaryImageCopied = false;

            try {
                const response = await fetch(config.setSummaryUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Could not load the set table.');
                }

                const summary = await response.json();
                const blob = await renderSetSummaryImage(summary, config.setName, config.ownerName, config.sessionDate);

                this.closeSnapshotModal();
                this.snapshotImageBlob = blob;
                this.snapshotImageUrl = URL.createObjectURL(blob);
                this.snapshotCanShare = typeof navigator.canShare === 'function'
                    && navigator.canShare({ files: [new File([blob], this.snapshotFileName(), { type: 'image/png' })] });
                this.openSnapshot = true;
            } catch (e) {
                this.summaryImageError = e.message || 'Could not create the set snapshot.';
            } finally {
                this.summaryImageBusy = false;
            }
        },
        snapshotFileName() {
            const normalizedName = config.setName
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');

            return `${normalizedName || 'set'}-snapshot.png`;
        },
        async copySnapshotImage() {
            if (!this.snapshotImageBlob || !navigator.clipboard?.write || typeof ClipboardItem === 'undefined') {
                this.summaryImageError = 'Image copying is not supported by this browser.';
                return;
            }

            try {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': this.snapshotImageBlob })]);
                this.summaryImageCopied = true;
                setTimeout(() => this.summaryImageCopied = false, 2400);
            } catch (e) {
                this.summaryImageError = e.message || 'Could not copy the set snapshot.';
            }
        },
        downloadSnapshotImage() {
            if (!this.snapshotImageUrl) {
                return;
            }

            const link = document.createElement('a');
            link.href = this.snapshotImageUrl;
            link.download = this.snapshotFileName();
            link.click();
        },
        async shareSnapshotImage() {
            if (!this.snapshotImageBlob || !this.snapshotCanShare) {
                return;
            }

            try {
                await navigator.share({
                    title: `${config.setName} set snapshot`,
                    text: `A set snapshot from ${config.setName}.`,
                    files: [new File([this.snapshotImageBlob], this.snapshotFileName(), { type: 'image/png' })],
                });
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.summaryImageError = e.message || 'Could not share the set snapshot.';
                }
            }
        },
        closeSnapshotModal() {
            this.openSnapshot = false;
            this.summaryImageCopied = false;
            this.snapshotCanShare = false;
            this.snapshotImageBlob = null;

            if (this.snapshotImageUrl) {
                URL.revokeObjectURL(this.snapshotImageUrl);
                this.snapshotImageUrl = '';
            }
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
        openUnscheduleSetModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openUnscheduleSet = true;
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
            } catch (e) {
                this.requestSongError = e.message || 'Could not submit song request. Try again.';
            } finally {
                this.requestSongBusy = false;
            }
        },
        async submitAddSong(event) {
            const formData = new FormData(event.target);
            const hasBandTemplate = Boolean(formData.get('band_template_id'));
            const hasManualSlots = formData.getAll('slot_names[]').length > 0;

            if (!hasBandTemplate && !hasManualSlots && !window.confirm('No slots will be added to this song now. You can add slots later. Continue?')) {
                return;
            }

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
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                this.$refs.songsContainer?.querySelector('[data-empty-songs-state]')?.remove();
                appendSessionFragment(this.$refs.songsContainer, payload.html);
                window.dispatchEvent(new CustomEvent('song-order-changed', {
                    detail: { setId: this.setId },
                }));
                this.openSong = false;
                this.resetSongAutocomplete();
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
            this.requestSongMode = 'manual';
            this.requestCatalogSongId = '';
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
            if (this.requestSongMode !== 'manual') {
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
            if (this.requestSongMode !== 'manual') {
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
        openAttachments: false,
        openActionMenu: false,
        actionMenuStyle: '',
        directLinkCopied: false,
        songCollapsed: false,
        setId: config.setId,
        songId: config.songId,
        songKey: config.songKey,
        canMoveSongUp: config.canMoveSongUp,
        canMoveSongDown: config.canMoveSongDown,
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        actionFeedbackTimer: null,
        reorderBusy: false,
        mobileSongReorderBusy: false,
        reorderError: '',
        reorderFeedback: '',
        attachments: [],
        attachmentsLoading: false,
        attachmentsLoaded: false,
        attachmentsError: '',
        attachmentsFeedback: '',
        attachmentCount: Number(config.initialAttachmentCount ?? 0),
        attachmentForm: initialAttachmentFormState(),
        attachmentFormBusy: false,
        attachmentFormError: '',
        deletingAttachmentId: null,
        toast: { visible: false, type: 'error', message: '' },
        toastTimer: null,
        canReorderSlots: config.canReorderSlots,
        canReorderSongs: config.canReorderSongs,
        canManageAttachments: config.canManageAttachments,
        attachmentsListUrl: config.attachmentsListUrl,
        attachmentsStoreUrl: config.attachmentsStoreUrl,
        isAdminUser: config.isAdminUser,
        jamSessionClosed: config.jamSessionClosed,
        ...baseDragState(),
        dragSlotId: null,
        draggingSlotId: null,
        dropTargetSlotId: null,
        syncMobileSongOrder() {
            const songCards = Array.from(this.$el.parentElement?.querySelectorAll('[data-song-id]') ?? []);
            const songIndex = songCards.indexOf(this.$el);

            this.canMoveSongUp = songIndex > 0;
            this.canMoveSongDown = songIndex >= 0 && songIndex < songCards.length - 1;
        },
        hasOpenDragBlockingModal() {
            return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((el) => window.getComputedStyle(el).display !== 'none');
        },
        canDragSlots() {
            return this.canReorderSlots && !this.hasOpenDragBlockingModal();
        },
        clearActionFeedback() {
            clearTimeout(this.actionFeedbackTimer);
            this.actionFeedbackTimer = null;
            this.actionFeedback = '';
        },
        showActionFeedback(message, duration = 2500) {
            if (!message) {
                this.clearActionFeedback();
                return;
            }

            this.actionFeedback = message;
            clearTimeout(this.actionFeedbackTimer);
            this.actionFeedbackTimer = setTimeout(() => {
                if (this.actionFeedback === message) {
                    this.actionFeedback = '';
                }

                this.actionFeedbackTimer = null;
            }, duration);
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets', {
                detail: { setId: this.setId },
            }));
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

            window.dispatchEvent(new CustomEvent('slot-order-changed', {
                detail: { songId: config.songId },
            }));

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
            this.closeAttachmentsModal();
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
        repositionActionMenu() {
            if (this.openActionMenu) {
                this.positionActionMenu();
            }
        },
        positionActionMenu() {
            this.$nextTick(() => {
                this.actionMenuStyle = viewportActionMenuStyle(this.$refs.actionMenuButton, this.$refs.actionMenu);
            });
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                this.openActionMenu = true;
                this.positionActionMenu();
                return;
            }

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
        async openAttachmentsModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openAttachments = true;
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
            if (this.$refs.attachmentFileInput) {
                this.$refs.attachmentFileInput.value = '';
            }
        },
        attachmentSizeLabel(sizeBytes) {
            return humanAttachmentSize(Number(sizeBytes));
        },
        async loadAttachments() {
            if (!this.attachmentsListUrl) {
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
                        'X-CSRF-TOKEN': config.csrfToken,
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
                        'X-CSRF-TOKEN': config.csrfToken,
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
            } catch (error) {
                this.attachmentFormError = error.message || 'Could not remove attachment. Try again.';
            } finally {
                this.deletingAttachmentId = null;
            }
        },
        clearSlotDropPlaceholder() {
            this.$refs.slotDropPlaceholder?.classList.add('hidden');
        },
        onSlotDragStart(event, slotId) {
            if (!this.canDragSlots()) {
                event.preventDefault();
                return;
            }

            if (isInteractiveDragSource(event)) {
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
            event.dataTransfer.setData('application/x-backstage-slot-id', String(slotId));
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

            const dragTypes = Array.from(event.dataTransfer?.types ?? []);
            if (this.dragSlotId === null || !dragTypes.includes('application/x-backstage-slot-id')) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (targetSlotId === null || this.dragSlotId === targetSlotId) {
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
        onSlotPlaceholderDragOver(event) {
            if (!this.canDragSlots() || this.busyAction) {
                return;
            }

            const dragTypes = Array.from(event.dataTransfer?.types ?? []);
            if (this.dragSlotId === null || !dragTypes.includes('application/x-backstage-slot-id')) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            const slotsContainer = this.$refs.slotsContainer;
            const placeholderEl = this.$refs.slotDropPlaceholder;

            if (!slotsContainer || !placeholderEl) {
                return;
            }

            const draggedEl = slotsContainer.querySelector(`[data-slot-id='${this.dragSlotId}']`);

            if (!draggedEl) {
                return;
            }

            placeholderEl.classList.remove('hidden');
            placeholderEl.querySelector('[data-slot-drop-label]').style.minHeight = `${draggedEl.offsetHeight}px`;
            this.dropTargetSlotId = null;
        },
        async onSlotPlaceholderDrop(event) {
            event.preventDefault();
            await this.onSlotDrop(event);
        },
        async onSlotDrop(event) {
            if (!this.canDragSlots() || this.busyAction) {
                this.clearSlotDropPlaceholder();
                return;
            }

            const dragTypes = Array.from(event.dataTransfer?.types ?? []);
            if (this.dragSlotId === null || !dragTypes.includes('application/x-backstage-slot-id')) {
                return;
            }

            event.preventDefault();

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
            this.clearActionFeedback();

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

                this.showActionFeedback('Slot order saved.');
            } catch (e) {
                this.actionError = 'Could not save slot order. Refresh and try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitAddSlot(event) {
            this.busyAction = true;
            this.actionError = '';
            this.clearActionFeedback();

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

                const payload = await response.json();
                (payload.html ?? []).forEach((html) => appendSessionFragment(this.$refs.slotsContainer, html));
                window.dispatchEvent(new CustomEvent('slot-order-changed', {
                    detail: { songId: config.songId },
                }));
                this.openAddSlot = false;
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
        canChooseBandTemplate: Boolean(config.canChooseBandTemplate),
        requestedSlotNames: Array.isArray(config.requestedSlotNames) ? config.requestedSlotNames : [],
        slotConflicts: config.slotConflicts && typeof config.slotConflicts === 'object' ? config.slotConflicts : {},
        templateSlotNamesByTemplateId: config.templateSlotNamesByTemplateId && typeof config.templateSlotNamesByTemplateId === 'object'
            ? config.templateSlotNamesByTemplateId
            : {},
        templateNamesById: config.templateNamesById && typeof config.templateNamesById === 'object'
            ? config.templateNamesById
            : {},
        approvedSlotNames: [],
        init() {
            this.approvedSlotNames = this.approvedSlotNames.filter((slotName) => this.requestedSlotNames.includes(slotName));
        },
        activeTemplateSlotNames() {
            if (this.bandTemplateId === '') {
                return [];
            }

            return this.templateSlotNamesByTemplateId[this.bandTemplateId] || [];
        },
        hasActiveTemplateSlots() {
            return this.activeTemplateSlotNames().length > 0;
        },
        slotNeedsTemplateAddition(slotName) {
            if (!this.hasActiveTemplateSlots()) {
                return false;
            }

            return !this.activeTemplateSlotNames().includes(slotName);
        },
        hasAnyTemplateAdditions() {
            return this.requestedSlotNames.some((slotName) => this.slotNeedsTemplateAddition(slotName));
        },
        activeTemplateName() {
            if (this.bandTemplateId === '') {
                return 'the band template';
            }

            return this.templateNamesById[this.bandTemplateId] || 'the band template';
        },
        templateAdditionHelperText() {
            return `* Not in ${this.activeTemplateName()}. This slot will be added alongside the slots in the band template.`;
        },
        slotsConflict(firstSlotName, secondSlotName) {
            if (firstSlotName === secondSlotName) {
                return false;
            }

            const firstConflicts = this.slotConflicts[firstSlotName] || [];
            const secondConflicts = this.slotConflicts[secondSlotName] || [];

            return firstConflicts.includes(secondSlotName) || secondConflicts.includes(firstSlotName);
        },
        slotSelectionDisabled(slotName) {
            if (this.busy) {
                return true;
            }

            if (this.approvedSlotNames.includes(slotName)) {
                return false;
            }

            return this.approvedSlotNames.some((selectedSlotName) => this.slotsConflict(selectedSlotName, slotName));
        },
        async respond(status) {
            this.busy = true;
            this.error = '';

            const payload = {
                _method: 'PATCH',
                status,
            };

            if (status === 'accepted' && this.canChooseBandTemplate && this.bandTemplateId !== '') {
                payload.band_template_id = Number(this.bandTemplateId);
            }

            if (status === 'accepted' && this.approvedSlotNames.length > 0) {
                payload.approved_slot_names = [...this.approvedSlotNames];
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

export function sessionAttendanceControl(config) {
    return {
        currentUserId: String(config.currentUserId),
        status: config.status,
        requiresDropoutAction: Boolean(config.requiresDropoutAction),
        sessionClosed: Boolean(config.sessionClosed),
        isPastSession: Boolean(config.isPastSession),
        isAdmin: Boolean(config.isAdmin),
        isSaving: false,
        openDropoutChoices: false,
        dropoutAction: 'keep_claimable',
        openAdminDropoutChoices: false,
        adminDropoutAction: 'keep_claimable',
        adminDropoutUserId: null,
        adminDropoutUserName: '',
        attendanceModalOpen: false,
        attendanceListLoading: false,
        attendanceListError: '',
        attendanceUsers: [],
        async loadAttendanceUsers() {
            this.attendanceListLoading = true;
            this.attendanceListError = '';

            try {
                const response = await fetch(config.attendanceIndexUrl, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'Could not load attendance list.');
                }

                this.attendanceUsers = Array.isArray(payload.users) ? payload.users : [];
                this.sessionClosed = Boolean(payload.session_closed);
            } catch (error) {
                this.attendanceListError = error.message || 'Could not load attendance list.';
            } finally {
                this.attendanceListLoading = false;
            }
        },
        openAttendanceModal() {
            this.attendanceModalOpen = true;
            this.loadAttendanceUsers();
        },
        statusButtonLabel(status) {
            if (this.isPastSession && status === 'not_going') {
                return "Couldn't attend";
            }

            if (this.isPastSession && status === 'going') {
                return 'Attended';
            }

            if (status === 'not_going') {
                return 'Not going';
            }

            if (status === 'going') {
                return 'Going';
            }

            return 'Maybe';
        },
        shouldShowStatusButton(nextStatus) {
            if (!this.isPastSession) {
                return true;
            }

            if (this.status === 'not_going') {
                return nextStatus === 'not_going';
            }

            if (this.status === 'going') {
                return nextStatus === 'going';
            }

            return false;
        },
        hasVisibleStatusButtons() {
            return this.shouldShowStatusButton('not_going')
                || this.shouldShowStatusButton('maybe')
                || this.shouldShowStatusButton('going');
        },
        canManageAttendanceForUser(user) {
            return this.isAdmin
                && !this.isPastSession
                && String(user?.id ?? '') !== this.currentUserId;
        },
        modalStatusLabel(status) {
            if (this.isPastSession && status === 'not_going') {
                return "Couldn't attend";
            }

            if (this.isPastSession && status === 'going') {
                return 'Attended';
            }

            if (status === 'not_going') {
                return 'Not going';
            }

            if (status === 'going') {
                return 'Going';
            }

            return 'Maybe';
        },
        async submitStatus(nextStatus, action = null, promptOnRequirement = false, targetUserId = null) {
            if (this.sessionClosed || this.isPastSession) {
                return;
            }

            this.isSaving = true;

            try {
                const response = await fetch(config.attendanceUpdateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ status: nextStatus, dropout_action: action, user_id: targetUserId }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (promptOnRequirement && payload?.errors?.dropout_action) {
                        this.requiresDropoutAction = true;
                        this.openDropoutChoices = true;
                        return;
                    }

                    const error = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Could not update attendance.';
                    throw new Error(error);
                }

                if (!targetUserId || String(targetUserId) === String(config.currentUserId)) {
                    this.status = payload.status || nextStatus;
                }

                this.requiresDropoutAction = Boolean(payload.requires_dropout_action);
                if (Array.isArray(payload.users)) {
                    this.attendanceUsers = payload.users;
                }
                window.dispatchEvent(new CustomEvent('refresh-session-sets'));
            } catch (error) {
                console.warn('Attendance update failed.', error);
            } finally {
                this.isSaving = false;
            }
        },
        chooseStatus(nextStatus) {
            if (this.sessionClosed || this.isPastSession) {
                return;
            }

            if (nextStatus === 'not_going' && this.status !== 'not_going') {
                if (this.requiresDropoutAction) {
                    this.openDropoutChoices = true;
                    return;
                }

                this.submitStatus(nextStatus, null, true);
                return;
            }

            this.submitStatus(nextStatus);
        },
        confirmDropout() {
            this.openDropoutChoices = false;
            this.submitStatus('not_going', this.dropoutAction);
        },
        openAdminNotGoingPrompt(userId) {
            const user = this.attendanceUsers.find((item) => String(item.id) === String(userId));

            this.adminDropoutUserId = String(userId);
            this.adminDropoutUserName = user?.name || 'this user';
            this.adminDropoutAction = 'keep_claimable';
            this.openAdminDropoutChoices = true;
        },
        confirmAdminDropout() {
            if (!this.adminDropoutUserId) {
                this.openAdminDropoutChoices = false;
                return;
            }

            this.openAdminDropoutChoices = false;
            this.submitStatus('not_going', this.adminDropoutAction, false, this.adminDropoutUserId);
        },
        setUserStatus(userId, nextStatus) {
            if (!this.isAdmin || this.sessionClosed || this.isPastSession) {
                return;
            }

            if (nextStatus === 'not_going') {
                this.openAdminNotGoingPrompt(userId);
                return;
            }

            this.submitStatus(nextStatus, null, false, userId);
        },
        badgeClasses(status) {
            if (status === 'going') {
                return 'border-emerald-200 bg-emerald-50 text-emerald-700';
            }

            if (status === 'maybe') {
                return 'border-slate-200 bg-slate-50 text-slate-700';
            }

            return 'border-slate-300 bg-slate-100 text-slate-700';
        },
    };
}

export function sessionSlotRow(config) {
    return {
        openPropose: false,
        openEditSlot: false,
        openAttachments: false,
        openActionMenu: false,
        actionMenuStyle: '',
        assignedUserName: config.assignedUserName,
        slotLabel: config.slotLabel,
        slotNotes: config.slotNotes || '',
        slotIsOpen: config.slotIsOpen,
        slotIsClaimable: Boolean(config.slotIsClaimable),
        slotIsManuallyClaimable: Boolean(config.slotIsManuallyClaimable),
        assignedUserIsNotGoing: Boolean(config.assignedUserIsNotGoing),
        assignmentIsManual: config.assignmentIsManual,
        initialEditAssignedUserId: config.initialEditAssignedUserId,
        initialEditAssignedUserName: config.initialEditAssignedUserName,
        initialEditManualPerformerName: config.initialEditManualPerformerName,
        editAssignedUserId: config.editAssignedUserId,
        editAssignedUserName: config.initialEditAssignedUserName || config.initialEditManualPerformerName || '',
        currentUserId: config.currentUserId,
        currentUserNotGoing: Boolean(config.currentUserNotGoing),
        assignedToCurrentUser: config.assignedToCurrentUser,
        hasPendingOwnRequest: config.hasPendingOwnRequest,
        setId: config.setId ? String(config.setId) : '',
        canMoveSlotUp: config.canMoveSlotUp,
        canMoveSlotDown: config.canMoveSlotDown,
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        actionFeedbackTimer: null,
        assignmentConflictMessage: '',
        assignmentConflictPending: false,
        assignmentConflictCooldown: false,
        assignmentConflictTimer: null,
        attachments: [],
        attachmentsLoading: false,
        attachmentsLoaded: false,
        attachmentsError: '',
        attachmentsFeedback: '',
        attachmentForm: initialAttachmentFormState(),
        attachmentFormBusy: false,
        attachmentFormError: '',
        deletingAttachmentId: null,
        toast: { visible: false, type: 'error', message: '' },
        toastStyle: '',
        toastTimer: null,
        proposalUserOptions: config.proposalUserOptions,
        users: config.users,
        canManageAttachments: config.canManageAttachments,
        attachmentsListUrl: config.attachmentsListUrl,
        attachmentsStoreUrl: config.attachmentsStoreUrl,
        ...baseDragState(),
        proposeTargetUserId: '',
        proposeTargetUserQuery: '',
        editAssignedUserQuery: config.initialEditAssignedUserName || config.initialEditManualPerformerName || '',
        showEditUserSuggestions: false,
        showProposalUserSuggestions: false,
        proposeMessage: '',
        clearActionFeedback() {
            clearTimeout(this.actionFeedbackTimer);
            this.actionFeedbackTimer = null;
            this.actionFeedback = '';
        },
        showActionFeedback(message, duration = 2500) {
            if (!message) {
                this.clearActionFeedback();
                return;
            }

            this.actionFeedback = message;
            clearTimeout(this.actionFeedbackTimer);
            this.actionFeedbackTimer = setTimeout(() => {
                if (this.actionFeedback === message) {
                    this.actionFeedback = '';
                }

                this.actionFeedbackTimer = null;
            }, duration);
        },
        syncMobileSlotOrder() {
            const slotRows = Array.from(this.$el.parentElement?.querySelectorAll('[data-slot-id]') ?? []);
            const slotIndex = slotRows.indexOf(this.$el);

            this.canMoveSlotUp = slotIndex > 0;
            this.canMoveSlotDown = slotIndex >= 0 && slotIndex < slotRows.length - 1;
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
        filteredEditUsers() {
            const query = this.editAssignedUserQuery.trim().toLowerCase();
            const filtered = query === ''
                ? this.users
                : this.users
                .filter((user) => user.name.toLowerCase().includes(query))
                ;

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
        filteredProposalUsers() {
            const query = this.proposeTargetUserQuery.trim().toLowerCase();
            if (query === '') {
                return [];
            }

            const users = this.proposalUserOptions.filter((user) => user.name.toLowerCase().includes(query));

            return users.slice(0, 16);
        },
        groupedProposalUsers() {
            return this.splitUserGroups(this.filteredProposalUsers());
        },
        hasProposalSuggestions() {
            const groups = this.groupedProposalUsers();

            return groups.available.length + groups.notAttending.length > 0;
        },
        hasAnySelectableProposalUsers() {
            return this.proposalUserOptions.some((user) => this.canSelectUser(user));
        },
        updateProposalUserQuery() {
            const selectedUser = this.proposalUserOptions.find((user) => String(user.id) === String(this.proposeTargetUserId));
            if (!selectedUser || selectedUser.name !== this.proposeTargetUserQuery) {
                this.proposeTargetUserId = '';
            }

            this.showProposalUserSuggestions = true;
        },
        selectProposalUser(user) {
            if (!this.canSelectUser(user)) {
                return;
            }

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
        applySlotPayload(slot) {
            if (!slot || Number(slot.id) !== Number(config.slotId)) {
                return;
            }

            this.slotLabel = slot.label;
            this.slotNotes = slot.notes || '';
            this.slotIsOpen = slot.is_open;
            this.slotIsClaimable = Boolean(slot.is_claimable);
            this.slotIsManuallyClaimable = Boolean(slot.is_claimable_manual);
            this.assignedUserIsNotGoing = Boolean(slot.assigned_user_not_going);
            this.assignmentIsManual = !slot.user_id && Boolean(slot.manual_performer_name);
            this.assignedToCurrentUser = Number(slot.user_id) === Number(this.currentUserId);
            this.assignedUserName = this.assignedToCurrentUser ? 'You' : (slot.user_name || 'Open');
            this.initialEditAssignedUserId = slot.user_id ? String(slot.user_id) : '';
            this.initialEditAssignedUserName = slot.user_id ? (slot.user_name || '') : '';
            this.initialEditManualPerformerName = slot.manual_performer_name || '';
            this.editAssignedUserId = this.initialEditAssignedUserId;
            this.editAssignedUserName = this.initialEditAssignedUserName;
            this.editAssignedUserQuery = this.initialEditAssignedUserName || this.initialEditManualPerformerName;
            this.hasPendingOwnRequest = false;
        },
        syncSlot(slot) {
            this.applySlotPayload(slot);
            window.dispatchEvent(new CustomEvent('slot-updated', { detail: { slot } }));
        },
        owningSetId() {
            if (this.setId) {
                return this.setId;
            }

            const setCard = this.$el?.closest('[data-session-set-card][data-set-id]');

            return setCard?.dataset?.setId || null;
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
            this.closeAttachmentsModal();
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        repositionActionMenu() {
            if (this.openActionMenu) {
                this.positionActionMenu();
            }
        },
        positionActionMenu() {
            this.$nextTick(() => {
                this.actionMenuStyle = viewportActionMenuStyle(this.$refs.actionMenuButton, this.$refs.actionMenu);
            });
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                this.openActionMenu = true;
                this.positionActionMenu();
                return;
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
            this.clearActionFeedback();

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

                this.showActionFeedback('Request sent.');
                this.hasPendingOwnRequest = true;
                window.dispatchEvent(new CustomEvent('refresh-session-sets', {
                    detail: { setId: this.owningSetId() },
                }));
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
            this.clearActionFeedback();

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

                const payload = await response.json();
                this.syncSlot(payload.slot);
                this.showActionFeedback(payload.message || 'Slot assigned to you.');
                window.dispatchEvent(new CustomEvent('refresh-session-activity'));
            } catch (e) {
                this.actionError = e.message || 'Could not take slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async toggleSlotClaimable() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.clearActionFeedback();

            try {
                const response = await fetch(config.toggleSlotClaimableUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({
                        is_claimable_manual: !this.slotIsManuallyClaimable,
                    }),
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not update claimable status. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                const payload = await response.json();
                this.syncSlot(payload.slot);
                this.showActionFeedback(payload.message || 'Slot claimable status updated.');
            } catch (e) {
                this.actionError = e.message || 'Could not update claimable status. Try again.';
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
            this.clearActionFeedback();

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

                this.showActionFeedback('Recommendation sent.');
                this.openPropose = false;
                this.proposeMessage = '';
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
            this.clearActionFeedback();

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

                const payload = await response.json();
                this.syncSlot(payload.slot);
                this.showActionFeedback(payload.message || 'Slot released.');
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
            this.clearActionFeedback();

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
                const payload = await response.json();
                this.syncSlot(payload.slot);
                this.showActionFeedback(payload.message || 'Slot cleared.');
            } catch (e) {
                this.actionError = 'Could not clear slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async copySlotDirectLink() {
            await window.copyShareLink(config.slotDirectUrl);
            this.showActionFeedback('Direct link copied.', 1800);
        },
        async openAttachmentsModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openAttachments = true;
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
            if (this.$refs.attachmentFileInput) {
                this.$refs.attachmentFileInput.value = '';
            }
        },
        attachmentSizeLabel(sizeBytes) {
            return humanAttachmentSize(Number(sizeBytes));
        },
        async loadAttachments() {
            if (!this.attachmentsListUrl) {
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
                this.canManageAttachments = Boolean(payload.can_manage);
                this.attachmentsLoaded = true;
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
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body,
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not add attachment. Try again.');
                    throw new Error(message || 'Could not add attachment. Try again.');
                }

                const payload = await response.json();
                this.attachments = payload.attachments ?? [];
                this.canManageAttachments = Boolean(payload.can_manage);
                this.attachmentForm = initialAttachmentFormState();
                this.attachmentsFeedback = payload.message || 'Attachment added.';
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
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                });

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not remove attachment. Try again.');
                    throw new Error(message || 'Could not remove attachment. Try again.');
                }

                const payload = await response.json();
                this.attachments = payload.attachments ?? [];
                this.canManageAttachments = Boolean(payload.can_manage);
                this.attachmentsFeedback = payload.message || 'Attachment removed.';
            } catch (error) {
                this.attachmentFormError = error.message || 'Could not remove attachment. Try again.';
            } finally {
                this.deletingAttachmentId = null;
            }
        },
        async submitEditSlot(event) {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.clearActionFeedback();

            try {
                const assignment = this.resolveEditedSlotAssignment();
                const formData = new FormData(event.target);
                formData.set('user_id', assignment.user_id);
                formData.set('manual_performer_name', assignment.manual_performer_name);
                if (this.assignmentConflictPending) {
                    formData.set('replace_conflicting_assignment', '1');
                }
                let response = await fetch(config.updateSlotUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: formData,
                });

                if (response.status === 409) {
                    const conflict = await response.json();
                    this.showAssignmentConflict(conflict.message);
                    return;
                }

                if (!response.ok) {
                    const message = await this.failedResponseMessage(response, 'Could not save slot. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                this.openEditSlot = false;
                const payload = await response.json();
                this.syncSlot(payload.slot);
                this.showActionFeedback(payload.message || 'Slot updated.');
                window.dispatchEvent(new CustomEvent('refresh-session-activity'));
            } catch (e) {
                this.actionError = e.message || 'Could not save slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async deleteSlot() {
            if (this.setLocked) {
                return;
            }

            const confirmed = window.confirm('Delete this slot?');
            if (!confirmed) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.clearActionFeedback();

            try {
                const body = new FormData();
                body.set('_method', 'DELETE');

                const response = await fetch(config.destroySlotUrl, {
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

                this.openEditSlot = false;
                this.$el.remove();
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
