import { GridStack } from 'gridstack';

const LAYOUT_STORAGE_KEY = 'dashboard.fresh-gridstack.layout.v1';

function sanitizeLayout(layout) {
	if (!Array.isArray(layout)) {
		return [];
	}

	return layout
		.map((node) => {
			const id = typeof node?.id === 'string' ? node.id : null;
			if (!id) {
				return null;
			}

			return {
				id,
				x: Math.max(0, Number(node.x) || 0),
				y: Math.max(0, Number(node.y) || 0),
				w: Math.max(1, Number(node.w) || 1),
				h: Math.max(1, Number(node.h) || 1),
			};
		})
		.filter(Boolean);
}

function serializeGridLayout(grid) {
	const nodes = Array.isArray(grid?.engine?.nodes) ? grid.engine.nodes : [];

	return nodes
		.map((node) => {
			const id = typeof node?.id === 'string'
				? node.id
				: node?.el?.getAttribute?.('gs-id');

			if (!id) {
				return null;
			}

			return {
				id,
				x: Math.max(0, Number(node.x) || 0),
				y: Math.max(0, Number(node.y) || 0),
				w: Math.max(1, Number(node.w) || 1),
				h: Math.max(1, Number(node.h) || 1),
			};
		})
		.filter(Boolean);
}

export function initDashboardFreshGridstackPage() {
	const roots = Array.from(document.querySelectorAll('[data-dashboard-fresh-gridstack]'));

	for (const root of roots) {
		if (!(root instanceof HTMLElement)) {
			continue;
		}

		const container = root.querySelector('[data-fresh-gridstack-canvas]');
		const summary = root.querySelector('[data-fresh-gridstack-summary]');
		const toggleButton = root.querySelector('[data-fresh-gridstack-toggle]');

		if (!(container instanceof HTMLElement) || !(summary instanceof HTMLElement) || !(toggleButton instanceof HTMLButtonElement)) {
			continue;
		}

		const grid = GridStack.init({
			column: 12,
			margin: 16,
			cellHeight: 90,
			float: true,
			animate: true,
			disableDrag: true,
			disableResize: true,
		}, container);

		let layoutUnlocked = false;
		let persistTimer = null;

		const applyLayoutMode = () => {
			grid.enableMove(layoutUnlocked);
			grid.enableResize(layoutUnlocked);
			root.classList.toggle('fresh-gridstack-editing', layoutUnlocked);
			summary.textContent = layoutUnlocked
				? 'Layout unlocked: drag and resize placeholders.'
				: 'Layout locked: click Unlock layout to move placeholders.';
			toggleButton.textContent = layoutUnlocked ? 'Lock layout' : 'Unlock layout';
		};

		const persistLayout = () => {
			if (persistTimer) {
				window.clearTimeout(persistTimer);
			}

			persistTimer = window.setTimeout(() => {
				const layout = serializeGridLayout(grid);
				root.dispatchEvent(new CustomEvent('fresh-gridstack:layout-changed', {
					detail: { layout },
				}));
			}, 120);
		};

		let initialLayout = [];

		try {
			initialLayout = sanitizeLayout(JSON.parse(root.dataset.initialLayoutJson || '[]'));
		} catch {
			initialLayout = [];
		}

		for (const node of initialLayout) {
			const element = container.querySelector(`[gs-id="${node.id}"]`);
			if (!(element instanceof HTMLElement)) {
				continue;
			}

			grid.update(element, {
				x: node.x,
				y: node.y,
				w: node.w,
				h: node.h,
			});
		}

		toggleButton.addEventListener('click', () => {
			layoutUnlocked = !layoutUnlocked;
			applyLayoutMode();
		});

		grid.on('dragstop', persistLayout);
		grid.on('resizestop', persistLayout);
		grid.on('change', persistLayout);
		container.addEventListener('pointerup', persistLayout, true);

		applyLayoutMode();
	}
}

export function dashboardFreshGridstackPersistence() {
	return {
		initialLayoutJson: '[]',
		init() {
			try {
				const raw = window.localStorage.getItem(LAYOUT_STORAGE_KEY);
				const parsedLayout = raw ? JSON.parse(raw) : [];
				this.initialLayoutJson = JSON.stringify(sanitizeLayout(parsedLayout));
			} catch {
				this.initialLayoutJson = '[]';
			}

			this.$el.addEventListener('fresh-gridstack:layout-changed', (event) => {
				const layout = sanitizeLayout(event.detail?.layout ?? []);
				window.localStorage.setItem(LAYOUT_STORAGE_KEY, JSON.stringify(layout));
			});
		},
	};
}
