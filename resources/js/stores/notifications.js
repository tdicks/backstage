const REFRESH_INTERVAL_MS = 30000;
const TOAST_DISPLAY_DURATION_MS = 5000;

function urlBase64ToUint8Array(base64String) {
	const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
	const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
	const rawData = window.atob(base64);
	const outputArray = new Uint8Array(rawData.length);

	for (let i = 0; i < rawData.length; ++i) {
		outputArray[i] = rawData.charCodeAt(i);
	}

	return outputArray;
}

export function registerNotificationsStore(Alpine) {
	Alpine.store('notifications', {
		items: [],
		unreadCount: 0,
		indexUrl: null,
		seenUrlTemplate: null,
		dismissUrlTemplate: null,
		initialized: false,
		intervalId: null,
		notificationCursor: null,
		notificationCursorIds: [],
		toasts: [],
		toastTimers: {},
		pushPublicKey: null,
		pushServiceWorkerUrl: '/push-sw.js',
		pushSubscribeUrl: null,
		pushUnsubscribeUrl: null,
		pushSyncAttempted: false,

		async init({
			items = [],
			unreadCount = 0,
			indexUrl = null,
			seenUrlTemplate = null,
			dismissUrlTemplate = null,
			pushPublicKey = null,
			pushServiceWorkerUrl = '/push-sw.js',
			pushSubscribeUrl = null,
			pushUnsubscribeUrl = null,
		} = {}) {
			this.indexUrl = indexUrl || this.indexUrl;
			this.seenUrlTemplate = seenUrlTemplate || this.seenUrlTemplate;
			this.dismissUrlTemplate = dismissUrlTemplate || this.dismissUrlTemplate;
			this.pushPublicKey = pushPublicKey || this.pushPublicKey;
			this.pushServiceWorkerUrl = pushServiceWorkerUrl || this.pushServiceWorkerUrl;
			this.pushSubscribeUrl = pushSubscribeUrl || this.pushSubscribeUrl;
			this.pushUnsubscribeUrl = pushUnsubscribeUrl || this.pushUnsubscribeUrl;
			this.items = items;
			this.unreadCount = Number(unreadCount || 0);
			this.updateCursor(items);

			if (!this.initialized) {
				this.initialized = true;
				this.intervalId = window.setInterval(() => this.refresh(), REFRESH_INTERVAL_MS);
			}

			await this.refresh({ showPopups: false });
			await this.syncPushSubscription();
		},

		canUsePush() {
			if (!this.pushPublicKey || !this.pushSubscribeUrl || !this.pushUnsubscribeUrl) {
				return false;
			}

			if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
				return false;
			}

			if (!(window.isSecureContext || window.location.hostname === 'localhost')) {
				return false;
			}

			return true;
		},

		pushPermissionState() {
			if (!('Notification' in window)) {
				return 'unsupported';
			}

			return Notification.permission;
		},

		canRequestPushPermission() {
			return this.canUsePush() && this.pushPermissionState() === 'default';
		},

		async requestPushPermissionAndSync() {
			if (!this.canUsePush()) {
				return 'unsupported';
			}

			if (this.pushPermissionState() === 'granted') {
				this.pushSyncAttempted = false;
				await this.syncPushSubscription();
				return 'granted';
			}

			if (this.pushPermissionState() === 'denied') {
				return 'denied';
			}

			try {
				const permission = await Notification.requestPermission();

				if (permission === 'granted') {
					this.pushSyncAttempted = false;
					await this.syncPushSubscription();
				}

				return permission;
			} catch (e) {
				return 'default';
			}
		},

		async syncPushSubscription() {
			if (this.pushSyncAttempted || !this.canUsePush()) {
				return;
			}

			this.pushSyncAttempted = true;

			try {
				const registration = await navigator.serviceWorker.register(this.pushServiceWorkerUrl);
				const subscription = await registration.pushManager.getSubscription();

				if (Notification.permission === 'granted') {
					const activeSubscription = subscription || await registration.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: urlBase64ToUint8Array(this.pushPublicKey),
					});

					await this.storePushSubscription(activeSubscription);
					return;
				}

				if (subscription) {
					await this.removePushSubscription(subscription.endpoint);
					await subscription.unsubscribe();
				}
			} catch (e) {}
		},

		async storePushSubscription(subscription) {
			if (!subscription || !this.pushSubscribeUrl) {
				return;
			}

			const payload = subscription.toJSON();

			await fetch(this.pushSubscribeUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
				},
				body: JSON.stringify(payload),
			});
		},

		async removePushSubscription(endpoint) {
			if (!endpoint || !this.pushUnsubscribeUrl) {
				return;
			}

			await fetch(this.pushUnsubscribeUrl, {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
				},
				body: JSON.stringify({ endpoint }),
			});
		},

		async refresh({ showPopups = true } = {}) {
			if (!this.indexUrl || document.hidden) {
				return;
			}

			try {
				const url = new URL(this.indexUrl, window.location.origin);

				if (this.notificationCursor) {
					url.searchParams.set('after', this.notificationCursor);
					this.notificationCursorIds.forEach((id) => url.searchParams.append('known_ids[]', id));
				}

				const response = await fetch(url, {
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
			const previousIds = this.items.map((item) => item.id);

			this.items = [...notifications, ...this.items]
				.filter((item, index, items) => items.findIndex((candidate) => candidate.id === item.id) === index)
				.sort((first, second) => new Date(second.created_at) - new Date(first.created_at));
			this.unreadCount = Number(payload.unread_count || 0);
			this.updateCursor(this.items);

			if (showPopups) {
				notifications
					.filter((item) => item.should_popup && !item.seen && !previousIds.includes(item.id))
					.forEach((item) => this.queueToast(item));
			}

			window.dispatchEvent(new CustomEvent('notifications-updated'));
		},

		updateCursor(items) {
			const latestNotification = items.reduce((latest, item) => {
				if (!latest || new Date(item.created_at) > new Date(latest.created_at)) {
					return item;
				}

				return latest;
			}, null);

			this.notificationCursor = latestNotification?.created_at || null;
			this.notificationCursorIds = this.notificationCursor
				? items.filter((item) => item.created_at === this.notificationCursor).map((item) => item.id)
				: [];
		},

		async markSeen(id) {
			const notification = this.items.find((item) => item.id === id);

			if (!notification || notification.seen || !this.seenUrlTemplate) {
				return;
			}

			notification.seen = true;
			this.unreadCount = Math.max(0, this.unreadCount - 1);
			window.dispatchEvent(new CustomEvent('notifications-updated'));

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
			if (this.toasts.some((item) => item.id === notification.id)) {
				return;
			}

			this.showToast(notification);
		},

		showToast(notification) {
			this.toasts = [...this.toasts, notification];
			this.toastTimers[notification.id] = window.setTimeout(() => this.closeToast(notification.id), TOAST_DISPLAY_DURATION_MS);
		},

		closeToast(id = null) {
			if (id === null && this.toasts.length > 0) {
				id = this.toasts[0].id;
			}

			if (id === null) {
				return;
			}

			if (this.toastTimers[id]) {
				window.clearTimeout(this.toastTimers[id]);
				delete this.toastTimers[id];
			}

			this.toasts = this.toasts.filter((toast) => toast.id !== id);
		},

		async dismissAll() {
			const ids = this.items.map((item) => item.id);
			await Promise.all(ids.map((id) => this.dismiss(id)));
		},

		urlFor(template, id) {
			return template.replace('__NOTIFICATION_ID__', encodeURIComponent(id));
		},
	});
}
