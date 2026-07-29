self.addEventListener('push', (event) => {
	if (!event.data) {
		return;
	}

	let payload = {};

	try {
		payload = event.data.json();
	} catch (error) {
		payload = { body: event.data.text() };
	}

	const title = payload.title || 'Backstage';
	const options = {
		body: payload.body || '',
		data: {
			actionUrl: payload.action_url || '/',
		},
		icon: '/favicon.ico',
		badge: '/favicon.ico',
		tag: payload.type_key || 'backstage-notification',
		renotify: false,
	};

	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();

	const actionUrl = event.notification?.data?.actionUrl || '/';

	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
			const normalizedActionUrl = new URL(actionUrl, self.location.origin).toString();

			for (const client of windowClients) {
				if (client.url === normalizedActionUrl && 'focus' in client) {
					return client.focus();
				}
			}

			if (clients.openWindow) {
				return clients.openWindow(normalizedActionUrl);
			}

			return undefined;
		})
	);
});
