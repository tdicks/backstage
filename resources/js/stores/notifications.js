const REFRESH_INTERVAL_MS = 30000;
const TOAST_DISPLAY_DURATION_MS = 5000;

export function registerNotificationsStore(Alpine) {
	Alpine.store('notifications', {
		items: [],
		unreadCount: 0,
		indexUrl: null,
		seenUrlTemplate: null,
		dismissUrlTemplate: null,
		initialized: false,
		intervalId: null,
		knownIds: [],
		activeToast: null,
		toastQueue: [],
		toastTimer: null,

		async init({
			items = [],
			unreadCount = 0,
			indexUrl = null,
			seenUrlTemplate = null,
			dismissUrlTemplate = null,
		} = {}) {
			this.indexUrl = indexUrl || this.indexUrl;
			this.seenUrlTemplate = seenUrlTemplate || this.seenUrlTemplate;
			this.dismissUrlTemplate = dismissUrlTemplate || this.dismissUrlTemplate;
			this.items = items;
			this.unreadCount = Number(unreadCount || 0);
			this.knownIds = items.map((item) => item.id);

			if (!this.initialized) {
				this.initialized = true;
				this.intervalId = window.setInterval(() => this.refresh(), REFRESH_INTERVAL_MS);
			}

			await this.refresh({ showPopups: false });
		},

		async refresh({ showPopups = true } = {}) {
			if (!this.indexUrl || document.hidden) {
				return;
			}

			try {
				const response = await fetch(this.indexUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					return;
				}

				const payload = await response.json();
				this.applyPayload(payload, { showPopups });
			} catch (e) {}
		},

		applyPayload(payload, { showPopups = true } = {}) {
			const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
			const previousIds = [...this.knownIds];

			this.items = notifications;
			this.unreadCount = Number(payload.unread_count || 0);
			this.knownIds = notifications.map((item) => item.id);

			if (showPopups) {
				notifications
					.filter((item) => item.should_popup && !item.seen && !previousIds.includes(item.id))
					.forEach((item) => this.queueToast(item));
			}

			window.dispatchEvent(new CustomEvent('notifications-updated'));
		},

		async markSeen(id) {
			const notification = this.items.find((item) => item.id === id);

			if (!notification || notification.seen || !this.seenUrlTemplate) {
				return;
			}

			notification.seen = true;
			this.unreadCount = Math.max(0, this.unreadCount - 1);

			try {
				await fetch(this.urlFor(this.seenUrlTemplate, id), {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
					},
				});
			} catch (e) {}
		},

		async dismiss(id) {
			const index = this.items.findIndex((item) => item.id === id);

			if (index === -1 || !this.dismissUrlTemplate) {
				return;
			}

			const [notification] = this.items.splice(index, 1);

			if (!notification.seen) {
				this.unreadCount = Math.max(0, this.unreadCount - 1);
			}

			try {
				await fetch(this.urlFor(this.dismissUrlTemplate, id), {
					method: 'PATCH',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
					},
				});
			} catch (e) {}

			window.dispatchEvent(new CustomEvent('notifications-updated'));
		},

		queueToast(notification) {
			if (this.activeToast && this.activeToast.id === notification.id) {
				return;
			}

			if (this.toastQueue.some((item) => item.id === notification.id)) {
				return;
			}

			if (!this.activeToast) {
				this.showToast(notification);
				return;
			}

			this.toastQueue.push(notification);
		},

		showToast(notification) {
			this.activeToast = notification;
			window.clearTimeout(this.toastTimer);
			this.toastTimer = window.setTimeout(() => this.advanceToast(), TOAST_DISPLAY_DURATION_MS);
		},

		advanceToast() {
			this.activeToast = null;

			if (this.toastQueue.length > 0) {
				this.showToast(this.toastQueue.shift());
			}
		},

		closeToast() {
			window.clearTimeout(this.toastTimer);
			this.advanceToast();
		},

		urlFor(template, id) {
			return template.replace('__NOTIFICATION_ID__', encodeURIComponent(id));
		},
	});
}
