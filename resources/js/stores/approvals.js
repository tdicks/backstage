export function registerApprovalsStore(Alpine) {
	Alpine.store('approvals', {
		count: 0,
		url: null,
		initialized: false,
		intervalId: null,
		refreshProvider: null,

		async init({ count = 0, url = null } = {}) {
			this.url = url || this.url;
			this.setCount(count, { notify: false });

			if (!this.initialized) {
				this.initialized = true;
				this.intervalId = window.setInterval(() => this.refresh(), 30000);
			}

			await this.refresh();
		},

		async refresh() {
			if (!this.url || document.hidden) {
				return this.count;
			}

			try {
				if (this.refreshProvider) {
					const payload = await this.refreshProvider();

					if (payload && typeof payload.count !== 'undefined') {
						this.setCount(Number(payload.count || 0));
						return this.count;
					}
				}

				const response = await fetch(this.url, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					return this.count;
				}

				const payload = await response.json();
				this.setCount(Number(payload.count || 0));
				window.dispatchEvent(new CustomEvent('approvals-count-refreshed', {
					detail: {
						count: this.count,
					},
				}));
			} catch (e) {}

			return this.count;
		},

		useRefreshProvider(provider) {
			this.refreshProvider = provider;
		},

		clearRefreshProvider(provider) {
			if (this.refreshProvider === provider) {
				this.refreshProvider = null;
			}
		},

		decrement() {
			this.setCount(Math.max(0, this.count - 1));
		},

		setCount(count, { notify = true } = {}) {
			const previousCount = this.count;
			this.count = Number(count || 0);

			if (notify && previousCount !== this.count) {
				window.dispatchEvent(new CustomEvent('approvals-count-changed', {
					detail: {
						count: this.count,
						previousCount,
					},
				}));
			}
		},
	});
}
