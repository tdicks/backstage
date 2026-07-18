import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.focusSessionFragmentTarget = () => {
	const targetId = window.location.hash.slice(1);

	if (!targetId) {
		return;
	}

	const target = document.getElementById(targetId);

	if (!target) {
		return;
	}

	const setCard = target.closest('[data-session-set-card]');
	const songCard = target.closest('[data-session-song-card]');

	if (setCard && window.Alpine) {
		window.Alpine.$data(setCard).setCollapsed = false;
	}

	if (songCard && window.Alpine) {
		window.Alpine.$data(songCard).songCollapsed = false;
	}

	window.setTimeout(() => {
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		target.classList.remove('session-fragment-highlight');
		void target.offsetWidth;
		target.classList.add('session-fragment-highlight');
	}, 50);
};

Alpine.data('lazySessionSets', (url) => ({
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
}));

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();
