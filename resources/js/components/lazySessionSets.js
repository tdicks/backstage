export function lazySessionSets(url) {
	return {
		loaded: false,
		refreshing: false,
		error: '',
		async init() {
			await this.refresh();
		},
		async refresh() {
			if (this.refreshing) {
				return;
			}

			this.refreshing = true;
			this.error = '';

			try {
				const response = await fetch(url, {
					headers: {
						'Accept': 'text/html',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Failed to load session sets');
				}

				const html = await response.text();
				const container = this.$refs.setsContainer;
				container.innerHTML = html;
				this.loaded = true;

				this.$nextTick(() => {
					if (window.Alpine) {
						window.Alpine.initTree(container);
					}

					window.focusSessionFragmentTarget();
				});
			} catch (e) {
				this.error = 'Could not load sets right now. Refresh the page to try again.';
			} finally {
				this.refreshing = false;
			}
		},
	};
}
