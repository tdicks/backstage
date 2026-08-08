export function dashboardActionQueues(config = {}) {
	return {
		refreshUrl: config.refreshUrl || null,
		htmlKey: typeof config.htmlKey === 'string' && config.htmlKey.length > 0 ? config.htmlKey : 'html',
		busy: false,
		errorMessage: '',
		refreshProvider: null,
		init() {
			this.refreshProvider = async () => {
				const count = await this.refresh(false);
				return { count };
			};

			this.$store?.approvals?.useRefreshProvider(this.refreshProvider);
		},
		destroy() {
			this.$store?.approvals?.clearRefreshProvider(this.refreshProvider);
		},
		async refresh(showLoading = true) {
			if (!this.refreshUrl || this.busy) {
				return Number(this.$store?.approvals?.count || 0);
			}

			if (showLoading) {
				this.busy = true;
			}

			this.errorMessage = '';

			try {
				const response = await fetch(this.refreshUrl, {
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});

				if (!response.ok) {
					throw new Error('Could not refresh approvals right now.');
				}

				const payload = await response.json();

				const html = payload?.[this.htmlKey];

				if (this.$refs.actionQueuesContent && typeof html === 'string') {
					this.$refs.actionQueuesContent.innerHTML = html;
					this.$nextTick(() => {
						if (window.Alpine) {
							window.Alpine.initTree(this.$refs.actionQueuesContent);
						}
					});
				}

				const count = Number(payload.count || 0);
				this.$store?.approvals?.setCount(count);

				return count;
			} catch (e) {
				this.errorMessage = e?.message || 'Could not refresh approvals right now.';
				return Number(this.$store?.approvals?.count || 0);
			} finally {
				if (showLoading) {
					this.busy = false;
				}
			}
		},
	};
}
