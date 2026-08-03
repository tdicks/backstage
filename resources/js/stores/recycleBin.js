export function registerRecycleBinStore(Alpine) {
	Alpine.store('recycleBin', {
		count: 0,
		url: null,
		initialized: false,
		intervalId: null,

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
			} catch (e) {}

			return this.count;
		},

		setCount(count, { notify = true } = {}) {
			const previousCount = this.count;
			this.count = Number(count || 0);

			if (notify && previousCount !== this.count) {
				window.dispatchEvent(new CustomEvent('recycle-bin-count-changed', {
					detail: {
						count: this.count,
						previousCount,
					},
				}));
			}
		},
	});
}
