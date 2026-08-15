import { GridStack } from 'gridstack';

const VISIBILITY_STORAGE_KEY = 'dashboard.gridstack.visibility.v1';

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

export function initDashboardGridstackPage() {
	const roots = Array.from(document.querySelectorAll('[data-dashboard-gridstack]'));

	for (const root of roots) {
		if (!(root instanceof HTMLElement)) {
			continue;
		}

		const container = root.querySelector('[data-gridstack-canvas]');
		const toggleButton = document.querySelector('[data-gridstack-toggle]');
		const widgetsButton = document.querySelector('[data-gridstack-widgets-toggle]');
		const lockedIcon = toggleButton?.querySelector('[data-gridstack-toggle-locked-icon]');
		const unlockedIcon = toggleButton?.querySelector('[data-gridstack-toggle-unlocked-icon]');
		const layoutSaveUrl = root.dataset.layoutSaveUrl || null;
		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

		if (!(container instanceof HTMLElement)) {
			continue;
		}

		const grid = GridStack.init({
			column: 12,
			margin: 16,
			cellHeight: 90,
			float: false,
			animate: true,
			disableDrag: true,
			disableResize: true,
		}, container);

		let layoutUnlocked = false;
		let persistTimer = null;
		let saveLayoutTimer = null;
		let saveLayoutController = null;
		let desktopLayout = [];
		const hiddenWidgets = new Set();
		const widgetPositions = new Map();
		const widgetElements = new Map();

		const persistLayoutToServer = (layout) => {
			if (!layoutSaveUrl) {
				return;
			}

			if (saveLayoutTimer) {
				window.clearTimeout(saveLayoutTimer);
			}

			saveLayoutTimer = window.setTimeout(async () => {
				if (saveLayoutController) {
					saveLayoutController.abort();
				}

				saveLayoutController = new AbortController();

				try {
					await fetch(layoutSaveUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
							'X-CSRF-TOKEN': csrfToken,
						},
						body: JSON.stringify({ layout }),
						signal: saveLayoutController.signal,
					});
				} catch (error) {
					if (error?.name !== 'AbortError') {
						console.error('Could not persist dashboard layout.', error);
					}
				}
			}, 200);
		};

		const readStoredVisibility = () => {
			try {
				const rawVisibility = window.localStorage.getItem(VISIBILITY_STORAGE_KEY);
				const parsedVisibility = rawVisibility ? JSON.parse(rawVisibility) : {};
				return parsedVisibility && typeof parsedVisibility === 'object' ? parsedVisibility : {};
			} catch {
				return {};
			}
		};

		const rememberWidgetPosition = (widgetId) => {
			if (!widgetId || hiddenWidgets.has(widgetId)) {
				return;
			}

			const node = grid.engine.nodes.find((candidate) => {
				const candidateId = typeof candidate?.id === 'string'
					? candidate.id
					: candidate?.el?.getAttribute?.('gs-id');

				return candidateId === widgetId;
			});

			if (!node) {
				return;
			}

			widgetPositions.set(widgetId, {
				x: Math.max(0, Number(node.x) || 0),
				y: Math.max(0, Number(node.y) || 0),
				w: Math.max(1, Number(node.w) || 1),
				h: Math.max(1, Number(node.h) || 1),
			});
		};

		const updateWidgetHiddenFlags = () => {
			const widgets = Array.from(container.querySelectorAll('.grid-stack-item[gs-id]'));

			for (const widget of widgets) {
				const widgetId = widget.getAttribute('gs-id');
				if (!widgetId) {
					continue;
				}

				if (hiddenWidgets.has(widgetId)) {
					widget.setAttribute('data-widget-hidden', 'true');
				} else {
					widget.removeAttribute('data-widget-hidden');
				}
			}
		};

		const findWidgetElement = (widgetId) => {
			if (!widgetId) {
				return null;
			}

			const cachedWidget = widgetElements.get(widgetId);
			if (cachedWidget instanceof HTMLElement) {
				return cachedWidget;
			}

			const resolvedWidget = container.querySelector(`[gs-id="${widgetId}"]`);
			if (resolvedWidget instanceof HTMLElement) {
				widgetElements.set(widgetId, resolvedWidget);
				return resolvedWidget;
			}

			return null;
		};

		const hideWidget = (widgetId) => {
			if (!widgetId || hiddenWidgets.has(widgetId)) {
				return;
			}

			const widget = findWidgetElement(widgetId);
			if (!(widget instanceof HTMLElement)) {
				return;
			}

			rememberWidgetPosition(widgetId);
			grid.removeWidget(widget, false, false);
			widget.style.display = 'none';
			hiddenWidgets.add(widgetId);
			updateWidgetHiddenFlags();
			persistLayout();
		};

		const showWidget = (widgetId) => {
			if (!widgetId) {
				return;
			}

			const widget = findWidgetElement(widgetId);
			if (!(widget instanceof HTMLElement)) {
				return;
			}

			const layout = widgetPositions.get(widgetId) || {
				x: Math.max(0, Number(widget.getAttribute('gs-x')) || 0),
				y: Math.max(0, Number(widget.getAttribute('gs-y')) || 0),
				w: Math.max(1, Number(widget.getAttribute('gs-w')) || 1),
				h: Math.max(1, Number(widget.getAttribute('gs-h')) || 1),
			};

			if (widget.parentElement !== container) {
				container.append(widget);
			}

			widget.classList.add('grid-stack-item');
			widget.style.display = '';
			grid.makeWidget(widget);
			grid.update(widget, layout);
			hiddenWidgets.delete(widgetId);
			updateWidgetHiddenFlags();
			persistLayout();
		};

		const applyVisibilityMap = (visibilityMap) => {
			if (!visibilityMap || typeof visibilityMap !== 'object') {
				return;
			}

			for (const [widgetId, isVisible] of Object.entries(visibilityMap)) {
				if (isVisible) {
					showWidget(widgetId);
				} else {
					hideWidget(widgetId);
				}
			}

			updateWidgetHiddenFlags();
		};

		root.__dashboardGridstackVisibility = {
			setWidgetVisibility(widgetId, visible) {
				if (visible) {
					showWidget(widgetId);
				} else {
					hideWidget(widgetId);
				}
			},
			applyVisibilityMap,
		};

		const applyLayoutMode = () => {
			grid.enableMove(layoutUnlocked);
			grid.enableResize(layoutUnlocked);
			root.classList.toggle('dashboard-gridstack-editing', layoutUnlocked);
			if (widgetsButton instanceof HTMLElement) {
				widgetsButton.classList.toggle('hidden', !layoutUnlocked);
			}
			if (toggleButton instanceof HTMLElement) {
				const label = layoutUnlocked ? 'Lock layout' : 'Unlock layout';
				toggleButton.setAttribute('aria-label', label);
				toggleButton.setAttribute('aria-pressed', layoutUnlocked.toString());
				toggleButton.setAttribute('title', label);
				lockedIcon?.classList.toggle('hidden', layoutUnlocked);
				unlockedIcon?.classList.toggle('hidden', !layoutUnlocked);
			}
			root.dispatchEvent(new CustomEvent('dashboard-gridstack:mode-changed', {
				detail: { unlocked: layoutUnlocked },
			}));
		};

		const persistLayout = () => {
			if (grid.getColumn() !== 12) {
				return;
			}

			if (persistTimer) {
				window.clearTimeout(persistTimer);
			}

			persistTimer = window.setTimeout(() => {
				const layout = serializeGridLayout(grid);
				persistLayoutToServer(layout);
				root.dispatchEvent(new CustomEvent('dashboard-gridstack:layout-changed', {
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

			widgetElements.set(node.id, element);

			grid.update(element, {
				x: node.x,
				y: node.y,
				w: node.w,
				h: node.h,
			});
			widgetPositions.set(node.id, {
				x: node.x,
				y: node.y,
				w: node.w,
				h: node.h,
			});
		}

		for (const widget of Array.from(container.querySelectorAll('.grid-stack-item[gs-id]'))) {
			const widgetId = widget.getAttribute('gs-id');
			if (!widgetId) {
				continue;
			}

			widgetElements.set(widgetId, widget);

			if (!widgetPositions.has(widgetId)) {
				widgetPositions.set(widgetId, {
					x: Math.max(0, Number(widget.getAttribute('gs-x')) || 0),
					y: Math.max(0, Number(widget.getAttribute('gs-y')) || 0),
					w: Math.max(1, Number(widget.getAttribute('gs-w')) || 1),
					h: Math.max(1, Number(widget.getAttribute('gs-h')) || 1),
				});
			}
		}

		const mobileLayoutMedia = window.matchMedia('(max-width: 767px)');
		const applyResponsiveLayout = () => {
			if (mobileLayoutMedia.matches) {
				if (grid.getColumn() === 12) {
					desktopLayout = serializeGridLayout(grid);
					grid.column(1, 'list');
				}

				return;
			}

			if (grid.getColumn() !== 1) {
				return;
			}

			grid.column(12, 'none');
			grid.batchUpdate();

			for (const node of desktopLayout) {
				const widget = findWidgetElement(node.id);

				if (widget instanceof HTMLElement) {
					grid.update(widget, node);
				}
			}

			grid.batchUpdate(false);
		};

		applyVisibilityMap(readStoredVisibility());

		if (toggleButton instanceof HTMLElement) {
			toggleButton.addEventListener('click', () => {
				layoutUnlocked = !layoutUnlocked;
				applyLayoutMode();
			});
		}

		if (widgetsButton instanceof HTMLButtonElement) {
			widgetsButton.addEventListener('click', () => {
				window.dispatchEvent(new CustomEvent('dashboard-gridstack:open-widget-chooser'));
			});
		}

		root.addEventListener('dashboard-gridstack:set-widget-visibility', (event) => {
			const widgetId = event?.detail?.id;
			const visible = Boolean(event?.detail?.visible);

			if (visible) {
				showWidget(widgetId);
			} else {
				hideWidget(widgetId);
			}
		});

		root.addEventListener('dashboard-gridstack:apply-visibility', (event) => {
			applyVisibilityMap(event?.detail?.visibility || {});
		});

		grid.on('dragstop', persistLayout);
		grid.on('resizestop', persistLayout);
		grid.on('change', persistLayout);
		grid.on('change', () => {
			if (grid.getColumn() !== 12) {
				return;
			}

			for (const node of serializeGridLayout(grid)) {
				widgetPositions.set(node.id, {
					x: node.x,
					y: node.y,
					w: node.w,
					h: node.h,
				});
			}
		});
		container.addEventListener('pointerup', persistLayout, true);

		desktopLayout = serializeGridLayout(grid);
		mobileLayoutMedia.addEventListener('change', applyResponsiveLayout);
		applyResponsiveLayout();

		applyLayoutMode();
		updateWidgetHiddenFlags();
	}
}

export function dashboardGridstackPersistence() {
	return {
		visibilityById: {},
		widgetCatalog: [],
		layoutUnlocked: false,
		showWidgetChooser: false,
		resetAllWidgetsVisible() {
			const nextVisibility = {};

			for (const widget of this.widgetCatalog) {
				nextVisibility[widget.id] = true;
			}

			this.visibilityById = nextVisibility;
		},
		ensureCatalogDefaults() {
			for (const widget of this.widgetCatalog) {
				if (typeof this.visibilityById[widget.id] !== 'boolean') {
					this.visibilityById[widget.id] = true;
				}
			}
		},
		persistVisibility() {
			window.localStorage.setItem(VISIBILITY_STORAGE_KEY, JSON.stringify(this.visibilityById));
		},
		isWidgetVisible(widgetId) {
			if (typeof this.visibilityById[widgetId] !== 'boolean') {
				return true;
			}

			return this.visibilityById[widgetId];
		},
		setWidgetVisibility(widgetId, visible) {
			this.visibilityById = {
				...this.visibilityById,
				[widgetId]: Boolean(visible),
			};
			this.persistVisibility();

			const visibilityApi = this.$el.__dashboardGridstackVisibility;
			if (visibilityApi && typeof visibilityApi.setWidgetVisibility === 'function') {
				visibilityApi.setWidgetVisibility(widgetId, Boolean(visible));
				return;
			}

			this.$el.dispatchEvent(new CustomEvent('dashboard-gridstack:set-widget-visibility', {
				detail: { id: widgetId, visible: Boolean(visible) },
			}));
		},
		hideWidget(widgetId) {
			this.setWidgetVisibility(widgetId, false);
		},
		openWidgetChooser() {
			if (!this.layoutUnlocked) {
				return;
			}

			this.showWidgetChooser = true;
		},
		closeWidgetChooser() {
			this.showWidgetChooser = false;
		},
		init() {
			try {
				const rawCatalog = this.$el.dataset.widgetCatalogJson || '[]';
				const parsedCatalog = JSON.parse(rawCatalog);
				this.widgetCatalog = Array.isArray(parsedCatalog)
					? parsedCatalog.filter((widget) => typeof widget?.id === 'string' && typeof widget?.label === 'string')
					: [];
			} catch {
				this.widgetCatalog = [];
			}

			try {
				const rawVisibility = window.localStorage.getItem(VISIBILITY_STORAGE_KEY);
				const parsedVisibility = rawVisibility ? JSON.parse(rawVisibility) : {};
				this.visibilityById = parsedVisibility && typeof parsedVisibility === 'object' ? parsedVisibility : {};
			} catch {
				this.visibilityById = {};
			}

			this.resetAllWidgetsVisible();
			this.persistVisibility();

			this.$el.addEventListener('dashboard-gridstack:mode-changed', (event) => {
				this.layoutUnlocked = Boolean(event?.detail?.unlocked);
				if (!this.layoutUnlocked) {
					this.closeWidgetChooser();
				}
			});

			window.addEventListener('dashboard-gridstack:open-widget-chooser', () => {
				this.openWidgetChooser();
			});

			this.$nextTick(() => {
				const visibilityApi = this.$el.__dashboardGridstackVisibility;
				if (visibilityApi && typeof visibilityApi.applyVisibilityMap === 'function') {
					visibilityApi.applyVisibilityMap(this.visibilityById);
					return;
				}

				this.$el.dispatchEvent(new CustomEvent('dashboard-gridstack:apply-visibility', {
					detail: { visibility: this.visibilityById },
				}));
			});

		},
	};
}
