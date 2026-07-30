function extractResponseMessage(payload, fallbackMessage) {
    const validationErrors = Object.values(payload?.errors || {}).flat();

    return validationErrors[0] || payload?.message || fallbackMessage;
}

export function slotFinderSongCard(config) {
    return {
        removed: false,
        removing: false,
        songKey: config.songKey || null,
        setKey: config.setKey || null,
        init() {
            window.addEventListener('slot-finder-song-taken', (event) => {
                if (event.detail.songKey === this.songKey) {
                    this.removeSong();
                }
            });
        },
        removeSong() {
            if (this.removed || this.removing) {
                return;
            }

            this.removing = true;
            window.setTimeout(() => {
                this.removed = true;
                window.dispatchEvent(new CustomEvent('slot-finder-song-removed', {
                    detail: {
                        songKey: this.songKey,
                        setKey: this.setKey,
                    },
                }));
            }, 280);
        },
    };
}

export function slotFinderSlotCard(config) {
    return {
        busy: false,
        removed: false,
        removing: false,
        freeForAll: Boolean(config.freeForAll),
        songKey: config.songKey || null,
        slotId: config.slotId || null,
        pendingRequestUrl: config.pendingRequestUrl || null,
        requested: Boolean(config.requested),
        selectedSlotId: null,
        feedback: '',
        feedbackTimer: null,
        error: '',
        init() {
            window.addEventListener('slot-finder-free-for-all-selected', (event) => {
                if (event.detail.songKey === this.songKey && event.detail.slotId !== this.slotId) {
                    this.removing = true;
                }
            });

            window.addEventListener('slot-finder-free-for-all-failed', (event) => {
                if (event.detail.songKey === this.songKey) {
                    this.removing = false;
                }
            });
        },
        clearFeedback() {
            clearTimeout(this.feedbackTimer);
            this.feedbackTimer = null;
            this.feedback = '';
        },
        showFeedback(message, duration = 2500) {
            if (!message) {
                this.clearFeedback();

                return;
            }

            this.feedback = message;
            clearTimeout(this.feedbackTimer);
            this.feedbackTimer = setTimeout(() => {
                if (this.feedback === message) {
                    this.feedback = '';
                }

                this.feedbackTimer = null;
            }, duration);
        },
        async submit(url, fallbackMessage) {
            if (this.busy || this.removed) {
                return null;
            }

            this.busy = true;
            this.error = '';

            try {
                const response = await fetch(url, {
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
                    const payload = await response.json().catch(() => ({}));
                    const message = extractResponseMessage(payload, fallbackMessage);

                    throw new Error(message || fallbackMessage);
                }

                return await response.json();
            } catch (error) {
                this.error = error.message || fallbackMessage;
                return null;
            } finally {
                this.busy = false;
            }
        },
        async submitWithMethod(url, method, body, fallbackMessage) {
            if (this.busy || this.removed) {
                return null;
            }

            this.busy = true;
            this.error = '';

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify(body),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const message = extractResponseMessage(payload, fallbackMessage);

                    throw new Error(message || fallbackMessage);
                }

                return await response.json();
            } catch (error) {
                this.error = error.message || fallbackMessage;
                return null;
            } finally {
                this.busy = false;
            }
        },
        async takeSlot() {
            if (this.freeForAll && this.songKey) {
                window.dispatchEvent(new CustomEvent('slot-finder-free-for-all-selected', {
                    detail: {
                        songKey: this.songKey,
                        slotId: this.slotId,
                    },
                }));
            }

            const payload = await this.submit(config.takeUrl, 'Could not take this slot. Try again.');

            if (!payload) {
                if (this.freeForAll && this.songKey) {
                    window.dispatchEvent(new CustomEvent('slot-finder-free-for-all-failed', {
                        detail: {
                            songKey: this.songKey,
                        },
                    }));
                }

                return;
            }

            this.showFeedback(payload.message || 'Slot taken.');

            await new Promise((resolve) => {
                window.setTimeout(resolve, 2600);
            });

            if (this.removed) {
                return;
            }

            if (this.freeForAll && this.songKey) {
                window.dispatchEvent(new CustomEvent('slot-finder-song-taken', {
                    detail: {
                        songKey: this.songKey,
                        setKey: config.setKey || null,
                    },
                }));
            }
            this.removing = true;
            window.setTimeout(() => {
                this.removed = true;
            }, 280);
        },
        async activate() {
            if (this.busy || this.removed) {
                return;
            }

            if (this.freeForAll) {
                await this.takeSlot();

                return;
            }

            if (! this.requested) {
                await this.requestSlot();
            }
        },
        async requestSlot() {
            if (this.requested) {
                return;
            }

            const payload = await this.submit(config.requestUrl, 'Could not request this slot. Try again.');

            if (!payload) {
                return;
            }

            this.requested = true;
        },
        async cancelRequest() {
            if (! this.requested || ! this.pendingRequestUrl) {
                return;
            }

            const payload = await this.submitWithMethod(
                this.pendingRequestUrl,
                'PATCH',
                { status: 'rejected' },
                'Could not cancel this request. Try again.'
            );

            if (!payload) {
                return;
            }

            this.requested = false;
        },
    };
}