function normalizeWidgetDefinitions(definitions, fallbackOrderIds = []) {
	if (!Array.isArray(definitions)) {
		definitions = [];
	}

	const normalized = definitions
		.map((definition, index) => {
			if (!definition || typeof definition !== 'object' || typeof definition.id !== 'string' || definition.id.length === 0) {
				return null;
			}

			const defaults = definition.defaults && typeof definition.defaults === 'object' ? definition.defaults : {};
			const defaultOrder = Number.isInteger(defaults.order) ? defaults.order : index;
			const defaultSize = Number.isInteger(defaults.size) && defaults.size >= 1 && defaults.size <= 3 ? defaults.size : 1;
			const defaultHeight = Number.isInteger(defaults.height) && defaults.height >= 1 ? defaults.height : 1;
			const defaultColumn = Number.isInteger(defaults.column) && defaults.column >= 1 && defaults.column <= 3 ? defaults.column : 1;
			const defaultRow = Number.isInteger(defaults.row) && defaults.row >= 1 ? defaults.row : 1;

			return {
				id: definition.id,
				label: typeof definition.label === 'string' ? definition.label : definition.id,
				defaultOrder,
				defaultSize,
				defaultHeight,
				defaultColumn,
				defaultRow,
			};
		})
		.filter(Boolean);

	if (normalized.length > 0) {
		return normalized;
	}

	return (Array.isArray(fallbackOrderIds) ? fallbackOrderIds : [])
		.filter((widgetId) => typeof widgetId === 'string' && widgetId.length > 0)
		.map((widgetId, index) => ({
			id: widgetId,
			label: widgetId,
			defaultOrder: index,
			defaultSize: 1,
			defaultHeight: 1,
			defaultColumn: 1,
			defaultRow: index + 1,
		}));
}

export function createDashboardWidgetLayoutState(config = {}) {
	return {
		widgetOrderUpdateUrl: config.widgetOrderUpdateUrl || null,
		initialWidgetOrderIds: Array.isArray(config.widgetOrderIds) ? config.widgetOrderIds : [],
		initialWidgetDefinitions: Array.isArray(config.widgetDefinitions) ? config.widgetDefinitions : [],
		initialWidgetSizes: config.widgetSizes && typeof config.widgetSizes === 'object' ? config.widgetSizes : {},
		initialWidgetHeights: config.widgetHeights && typeof config.widgetHeights === 'object' ? config.widgetHeights : {},
		initialWidgetPositions: config.widgetPositions && typeof config.widgetPositions === 'object' ? config.widgetPositions : {},
		widgetOrderSaveTimer: null,
		activeResize: null,
		activeMove: null,
		activeDragWidgetId: null,
		widgetDefinitions: [],
		widgetOrderIds: [],
		widgetSizes: {},
		widgetHeights: {},
		widgetPositions: {},
		isMobileLayout: false,
		layoutReady: false,
		layoutDebugEnabled: Boolean(config.debugEnabled),
		layoutDebugPanelOpen: false,
		layoutDebugLastMove: null,
		widgetViewportResizeListener: null,
		get widgetPanels() {
			return this.widgetOrderIds
				.map((widgetId) => {
					const definition = this.widgetDefinitions.find((widget) => widget.id === widgetId);
					if (!definition) {
						return null;
					}

					return {
						...definition,
						size: this.widgetSizes[widgetId] || definition.defaultSize || 1,
					};
				})
				.filter(Boolean);
		},
		initWidgetLayoutState() {
			this.widgetDefinitions = normalizeWidgetDefinitions(this.initialWidgetDefinitions, this.initialWidgetOrderIds);
			this.initializeWidgetLayout();
			this.$nextTick(() => {
				this.normalizeVisibleWidgetLayout();
			});

			this.widgetViewportResizeListener = () => {
				this.isMobileLayout = this.isMobileViewport();
			};

			window.addEventListener('resize', this.widgetViewportResizeListener);
		},
		disposeWidgetLayoutState() {
			if (this.widgetViewportResizeListener) {
				window.removeEventListener('resize', this.widgetViewportResizeListener);
				this.widgetViewportResizeListener = null;
			}
		},
		initializeWidgetLayout() {
			const configuredOrder = Array.isArray(this.initialWidgetOrderIds) ? this.initialWidgetOrderIds : [];
			const knownWidgetIds = this.widgetDefinitions.map((widget) => widget.id);
			const filteredConfiguredOrder = configuredOrder.filter((widgetId) => knownWidgetIds.includes(widgetId));
			const fallbackOrder = this.widgetDefinitions
				.map((widget) => widget.id)
				.sort((firstId, secondId) => {
					const firstWidget = this.widgetDefinitions.find((widget) => widget.id === firstId);
					const secondWidget = this.widgetDefinitions.find((widget) => widget.id === secondId);
					return (firstWidget?.defaultOrder ?? 0) - (secondWidget?.defaultOrder ?? 0);
				});

			this.widgetOrderIds = filteredConfiguredOrder.length > 0
				? [...filteredConfiguredOrder, ...fallbackOrder.filter((widgetId) => !filteredConfiguredOrder.includes(widgetId))]
				: fallbackOrder;

			this.widgetSizes = {};
			this.widgetHeights = {};
			this.widgetPositions = {};
			for (const widget of this.widgetDefinitions) {
				this.widgetSizes[widget.id] = widget.defaultSize || 1;
				this.widgetHeights[widget.id] = widget.defaultHeight || 1;
				this.widgetPositions[widget.id] = {
					column: widget.defaultColumn || 1,
					row: widget.defaultRow || 1,
				};
			}

			for (const widget of this.widgetDefinitions) {
				const configuredSize = this.initialWidgetSizes[widget.id];
				if (Number.isInteger(configuredSize) && configuredSize >= 1 && configuredSize <= 3) {
					this.widgetSizes[widget.id] = configuredSize;
				}

				const configuredHeight = this.initialWidgetHeights[widget.id];
				if (Number.isInteger(configuredHeight) && configuredHeight >= 1) {
					this.widgetHeights[widget.id] = configuredHeight;
				}

				const configuredPosition = this.initialWidgetPositions[widget.id];
				if (configuredPosition && typeof configuredPosition === 'object') {
					const configuredColumn = configuredPosition.column;
					const configuredRow = configuredPosition.row;

					if (Number.isInteger(configuredColumn) && configuredColumn >= 1 && configuredColumn <= 3 && Number.isInteger(configuredRow) && configuredRow >= 1) {
						this.widgetPositions[widget.id] = {
							column: configuredColumn,
							row: configuredRow,
						};
					}
				}
			}

			this.widgetPositions = this.buildWidgetPlacementMap({ positions: this.widgetPositions }).placements;
			this.widgetOrderIds = this.widgetIdsByCanvasOrder(this.widgetPositions);

			this.isMobileLayout = this.isMobileViewport();
		},
		normalizeVisibleWidgetLayout() {
			const visibleIds = this.visibleWidgetIds();
			if (!Array.isArray(visibleIds) || visibleIds.length === 0) {
				return;
			}

			const visibleOrder = this.widgetIdsByCanvasOrder(this.widgetPositions).filter((widgetId) => visibleIds.includes(widgetId));
			if (visibleOrder.length === 0) {
				return;
			}

			const placementMap = this.buildWidgetPlacementMap({
				order: visibleOrder,
				sizes: this.widgetSizes,
				heights: this.widgetHeights,
				positions: this.widgetPositions,
			});
			const compactVisibleOrder = this.widgetIdsByCanvasOrder(placementMap.placements);
			const hiddenOrder = this.widgetOrderIds.filter((widgetId) => !compactVisibleOrder.includes(widgetId));

			this.widgetPositions = {
				...this.widgetPositions,
				...placementMap.placements,
			};
			this.widgetOrderIds = [...compactVisibleOrder, ...hiddenOrder];
		},
		toggleLayoutDebugPanel() {
			if (!this.layoutDebugEnabled) {
				return;
			}

			this.layoutDebugPanelOpen = !this.layoutDebugPanelOpen;
		},
		visibleLayoutRowRange() {
			const visibleIds = this.visibleWidgetIds();
			if (!Array.isArray(visibleIds) || visibleIds.length === 0) {
				return { minRow: 0, maxRow: 0 };
			}

			const visibleSet = new Set(visibleIds);
			let minRow = Number.POSITIVE_INFINITY;
			let maxRow = 0;

			for (const [widgetId, position] of Object.entries(this.widgetPositions)) {
				if (!visibleSet.has(widgetId) || !position || !Number.isInteger(position.row)) {
					continue;
				}

				const rowSpan = Math.max(1, this.widgetHeights[widgetId] || 1);
				minRow = Math.min(minRow, position.row);
				maxRow = Math.max(maxRow, position.row + rowSpan - 1);
			}

			if (!Number.isFinite(minRow)) {
				return { minRow: 0, maxRow: 0 };
			}

			return { minRow, maxRow };
		},
		reorderWidget(widgetId, position) {
			const currentIndex = this.widgetOrderIds.indexOf(widgetId);
			if (currentIndex === -1 || !Number.isInteger(position) || position < 0) {
				return;
			}

			const nextOrder = [...this.widgetOrderIds];
			nextOrder.splice(currentIndex, 1);
			nextOrder.splice(Math.min(position, nextOrder.length), 0, widgetId);
			this.widgetOrderIds = nextOrder;
			this.widgetPositions = this.buildWidgetPlacementMap({ order: nextOrder, positions: this.widgetPositions }).placements;
			this.persistWidgetOrder();
		},
		widgetSortConfig() {
			return {
				disabled: !this.isMobileLayout,
				ghostClass: 'widget-drop-placeholder',
				chosenClass: 'widget-drag-chosen',
				dragClass: 'widget-drag-active',
				filter: 'a, button, input, textarea, select, option, label, summary, [contenteditable="true"], [data-widget-resize-handle], [data-no-widget-drag], [data-widget-drag-disabled="true"], [data-widget-drag-disabled="true"] *',
				preventOnFilter: false,
				onStart: (event) => {
					this.activeDragWidgetId = event?.item?.dataset?.widgetId || null;
				},
				onEnd: () => {
					this.activeDragWidgetId = null;
				},
			};
		},
		buildWidgetPlacementMap(overrides = {}) {
			const sizes = overrides.sizes ?? this.widgetSizes;
			const heights = overrides.heights ?? this.widgetHeights;
			const order = overrides.order ?? this.widgetOrderIds;
			const positions = overrides.positions ?? this.widgetPositions;
			const excludedWidgetId = overrides.excludedWidgetId ?? null;
			const placements = {};
			const occupiedCells = new Set();

			for (const widgetId of order) {
				if (widgetId === excludedWidgetId) {
					continue;
				}

				const columnSpan = Math.min(3, Math.max(1, sizes[widgetId] || 1));
				const rowSpan = Math.max(1, heights[widgetId] || 1);
				const preferredPosition = positions[widgetId] && typeof positions[widgetId] === 'object' ? positions[widgetId] : null;
				const preferredColumn = Number.isInteger(preferredPosition?.column) ? preferredPosition.column : 1;
				const preferredRow = Number.isInteger(preferredPosition?.row) ? preferredPosition.row : 1;
				const position = this.findNextWidgetPlacement(occupiedCells, columnSpan, rowSpan, preferredColumn, preferredRow);

				placements[widgetId] = {
					column: position.column,
					row: position.row,
					columnSpan,
					rowSpan,
				};

				this.fillWidgetPlacement(occupiedCells, position.column, position.row, columnSpan, rowSpan);
			}

			const compactPlacements = this.collapseEmptyRows(placements);
			const compactOccupiedCells = this.occupiedCellsFromPlacements(compactPlacements);

			return { placements: compactPlacements, occupiedCells: compactOccupiedCells };
		},
		occupiedCellsFromPlacements(placements) {
			const occupiedCells = new Set();

			for (const placement of Object.values(placements)) {
				if (!placement) {
					continue;
				}

				this.fillWidgetPlacement(occupiedCells, placement.column, placement.row, placement.columnSpan, placement.rowSpan);
			}

			return occupiedCells;
		},
		occupiedRowsFromPlacements(placements) {
			const occupiedRows = new Set();

			for (const placement of Object.values(placements)) {
				if (!placement) {
					continue;
				}

				for (let rowOffset = 0; rowOffset < placement.rowSpan; rowOffset += 1) {
					occupiedRows.add(placement.row + rowOffset);
				}
			}

			return occupiedRows;
		},
		maxOccupiedRow(placements) {
			let maxRow = 0;

			for (const placement of Object.values(placements)) {
				if (!placement) {
					continue;
				}

				maxRow = Math.max(maxRow, placement.row + placement.rowSpan - 1);
			}

			return maxRow;
		},
		collapseEmptyRows(placements) {
			const compactPlacements = Object.fromEntries(
				Object.entries(placements).map(([widgetId, placement]) => [widgetId, { ...placement }]),
			);

			let hasGap = true;
			while (hasGap) {
				hasGap = false;
				const maxRow = this.maxOccupiedRow(compactPlacements);
				const occupiedRows = this.occupiedRowsFromPlacements(compactPlacements);

				for (let row = 1; row <= maxRow; row += 1) {
					if (occupiedRows.has(row)) {
						continue;
					}

					for (const placement of Object.values(compactPlacements)) {
						if (placement.row > row) {
							placement.row -= 1;
						}
					}

					hasGap = true;
					break;
				}
			}

			return compactPlacements;
		},
		findNextWidgetPlacement(occupiedCells, columnSpan, rowSpan, preferredColumn = 1, preferredRow = 1) {
			let row = Math.max(1, preferredRow);

			while (true) {
				if (this.canWidgetFitAtPosition(occupiedCells, preferredColumn, row, columnSpan, rowSpan)) {
					return { column: preferredColumn, row };
				}

				for (let column = 1; column <= 4 - columnSpan; column += 1) {
					if (this.canWidgetFitAtPosition(occupiedCells, column, row, columnSpan, rowSpan)) {
						return { column, row };
					}
				}

				row += 1;
			}
		},
		canWidgetFitAtPosition(occupiedCells, column, row, columnSpan, rowSpan) {
			if (column < 1 || column + columnSpan - 1 > 3 || row < 1 || rowSpan < 1) {
				return false;
			}

			for (let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1) {
				for (let columnOffset = 0; columnOffset < columnSpan; columnOffset += 1) {
					if (occupiedCells.has(`${column + columnOffset}:${row + rowOffset}`)) {
						return false;
					}
				}
			}

			return true;
		},
		fillWidgetPlacement(occupiedCells, column, row, columnSpan, rowSpan) {
			for (let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1) {
				for (let columnOffset = 0; columnOffset < columnSpan; columnOffset += 1) {
					occupiedCells.add(`${column + columnOffset}:${row + rowOffset}`);
				}
			}
		},
		widgetIdsByCanvasOrder(positions) {
			const fallbackOrder = this.widgetDefinitions
				.map((widget) => widget.id)
				.reduce((carry, widgetId, index) => ({ ...carry, [widgetId]: index }), {});

			return [...this.widgetDefinitions.map((widget) => widget.id)].sort((firstId, secondId) => {
				const firstPosition = positions[firstId] || { column: 1, row: 1 };
				const secondPosition = positions[secondId] || { column: 1, row: 1 };

				if (firstPosition.row !== secondPosition.row) {
					return firstPosition.row - secondPosition.row;
				}

				if (firstPosition.column !== secondPosition.column) {
					return firstPosition.column - secondPosition.column;
				}

				return (fallbackOrder[firstId] || 0) - (fallbackOrder[secondId] || 0);
			});
		},
		canvasMetrics() {
			const previewRoot = document.querySelector('[data-dashboard-layout-preview]');
			const grid = previewRoot?.querySelector('[data-widget-grid]');

			if (!(grid instanceof HTMLElement)) {
				return null;
			}

			const gridStyles = window.getComputedStyle(grid);
			const columnGap = Number.parseFloat(gridStyles.columnGap);
			const rowGap = Number.parseFloat(gridStyles.rowGap);
			const autoRows = Number.parseFloat(gridStyles.gridAutoRows);
			const normalizedColumnGap = Number.isFinite(columnGap) ? columnGap : 0;
			const normalizedRowGap = Number.isFinite(rowGap) ? rowGap : 0;
			const normalizedAutoRows = Number.isFinite(autoRows) && autoRows > 0 ? autoRows : 0;
			const columnWidth = (grid.clientWidth - (normalizedColumnGap * 2)) / 3;

			if (!Number.isFinite(columnWidth) || columnWidth <= 0 || normalizedAutoRows <= 0) {
				return null;
			}

			return {
				grid,
				columnWidth,
				rowHeight: normalizedAutoRows,
				columnGap: normalizedColumnGap,
				rowGap: normalizedRowGap,
			};
		},
		resolveCanvasCellFromPointer(clientX, clientY, columnSpan, rowSpan) {
			const metrics = this.canvasMetrics();
			if (!metrics) {
				return null;
			}

			const rect = metrics.grid.getBoundingClientRect();
			const horizontalStep = metrics.columnWidth + metrics.columnGap;
			const verticalStep = metrics.rowHeight + metrics.rowGap;
			const nextColumn = Math.min(4 - columnSpan, Math.max(1, Math.floor((clientX - rect.left) / horizontalStep) + 1));
			const nextRow = Math.max(1, Math.floor((clientY - rect.top) / verticalStep) + 1);

			return {
				column: nextColumn,
				row: nextRow,
			};
		},
		widgetMoveActivationDistance() {
			return 16;
		},
		widgetMoveStepResistance() {
			return 1.08;
		},
		calculateResistiveCellOffset(delta, step, resistance = 1) {
			if (!Number.isFinite(delta) || !Number.isFinite(step) || step <= 0) {
				return 0;
			}

			const resistedStep = step * Math.max(1, resistance);
			if (delta >= 0) {
				return Math.floor(delta / resistedStep);
			}

			return Math.ceil(delta / resistedStep);
		},
		visibleWidgetIds() {
			const visibleIds = Array.from(document.querySelectorAll('[data-widget-card][data-widget-id]'))
				.map((element) => element.getAttribute('data-widget-id'))
				.filter((widgetId) => typeof widgetId === 'string' && widgetId.length > 0);

			if (visibleIds.length > 0) {
				return visibleIds;
			}

			return this.widgetDefinitions.map((widget) => widget.id);
		},
		maxOccupiedRowForLayout(positions = {}, heights = {}, excludedWidgetId = null, includedWidgetIds = null) {
			let maxRow = 0;
			const includedSet = Array.isArray(includedWidgetIds) && includedWidgetIds.length > 0
				? new Set(includedWidgetIds)
				: null;

			for (const [widgetId, position] of Object.entries(positions)) {
				if (widgetId === excludedWidgetId) {
					continue;
				}

				if (includedSet && !includedSet.has(widgetId)) {
					continue;
				}

				if (!position || !Number.isInteger(position.row)) {
					continue;
				}

				const rowSpan = Math.max(1, heights[widgetId] || 1);
				maxRow = Math.max(maxRow, position.row + rowSpan - 1);
			}

			return maxRow;
		},
		resolveCanvasCellFromDragDelta(activeMove, deltaX, deltaY) {
			if (!activeMove) {
				return null;
			}

			const metrics = this.canvasMetrics();
			if (!metrics) {
				return null;
			}

			const horizontalStep = metrics.columnWidth + metrics.columnGap;
			const verticalStep = metrics.rowHeight + metrics.rowGap;
			const resistance = this.widgetMoveStepResistance();
			const columnOffset = this.calculateResistiveCellOffset(deltaX, horizontalStep, resistance);
			const rowOffset = this.calculateResistiveCellOffset(deltaY, verticalStep, resistance);
			const activeWidgetIds = activeMove.visibleWidgetIds || this.visibleWidgetIds();
			const maxTargetRow = this.maxOccupiedRowForLayout(activeMove.originalPositions || this.widgetPositions, this.widgetHeights, activeMove.widgetId, activeWidgetIds) + 1;

			if (this.layoutDebugEnabled) {
				this.layoutDebugLastMove = {
					widgetId: activeMove.widgetId,
					deltaX: Math.round(deltaX),
					deltaY: Math.round(deltaY),
					columnOffset,
					rowOffset,
					maxTargetRow,
					visibleWidgetIds: activeWidgetIds,
				};
			}

			return {
				column: Math.min(4 - activeMove.columnSpan, Math.max(1, activeMove.startColumn + columnOffset)),
				row: Math.min(maxTargetRow, Math.max(1, activeMove.startRow + rowOffset)),
			};
		},
		placeWidgetOnCanvas(widgetId, targetColumn, targetRow, sizes = this.widgetSizes, heights = this.widgetHeights, preferredPositions = this.widgetPositions, orderOverride = null) {
			const baseOrder = Array.isArray(orderOverride) && orderOverride.length > 0
				? [widgetId, ...orderOverride.filter((candidateId) => candidateId !== widgetId)]
				: [widgetId, ...this.widgetIdsByCanvasOrder(preferredPositions).filter((candidateId) => candidateId !== widgetId)];
			const nextPositions = {
				...preferredPositions,
				[widgetId]: {
					column: targetColumn,
					row: targetRow,
				},
			};
			const placementMap = this.buildWidgetPlacementMap({
				order: baseOrder,
				sizes,
				heights,
				positions: nextPositions,
			});
			const activeOrder = this.widgetIdsByCanvasOrder(placementMap.placements);
			const hiddenOrder = this.widgetOrderIds.filter((candidateId) => !activeOrder.includes(candidateId));

			return {
				positions: {
					...preferredPositions,
					...placementMap.placements,
				},
				order: [...activeOrder, ...hiddenOrder],
			};
		},
		layoutForResizedWidget(widgetId, targetSize, targetHeight, baseState = null) {
			const sourceState = baseState || {
				positions: this.widgetPositions,
				order: this.widgetOrderIds,
				sizes: this.widgetSizes,
				heights: this.widgetHeights,
			};
			const anchorPosition = sourceState.positions[widgetId] || { column: 1, row: 1 };
			const nextSizes = {
				...sourceState.sizes,
				[widgetId]: Math.min(3, Math.max(1, targetSize)),
			};
			const nextHeights = {
				...sourceState.heights,
				[widgetId]: Math.max(1, targetHeight),
			};
			const anchoredColumn = Math.min(anchorPosition.column, 4 - nextSizes[widgetId]);
			const nextLayout = this.placeWidgetOnCanvas(
				widgetId,
				anchoredColumn,
				anchorPosition.row,
				nextSizes,
				nextHeights,
				sourceState.positions,
			);

			return {
				sizes: nextSizes,
				heights: nextHeights,
				positions: nextLayout.positions,
				order: nextLayout.order,
			};
		},
		measureResizeAvailability(widgetId) {
			const currentLayout = this.buildWidgetPlacementMap({ positions: this.widgetPositions });
			const currentPlacement = currentLayout.placements[widgetId];
			if (!currentPlacement) {
				return null;
			}

			const staticLayout = this.buildWidgetPlacementMap({ excludedWidgetId: widgetId, positions: this.widgetPositions });

			return {
				originColumn: currentPlacement.column,
				originRow: currentPlacement.row,
				occupiedCells: staticLayout.occupiedCells,
			};
		},
		clampWidgetSizeToAvailability(targetSize, targetHeight, resizeState) {
			let clampedSize = Math.min(3, Math.max(1, targetSize));

			while (clampedSize > 1 && !this.canWidgetFitAtPosition(resizeState.occupiedCells, resizeState.originColumn, resizeState.originRow, clampedSize, targetHeight)) {
				clampedSize -= 1;
			}

			if (!this.canWidgetFitAtPosition(resizeState.occupiedCells, resizeState.originColumn, resizeState.originRow, clampedSize, targetHeight)) {
				return 1;
			}

			return clampedSize;
		},
		clampWidgetHeightToAvailability(targetSize, targetHeight, resizeState) {
			let clampedHeight = Math.max(1, targetHeight);

			while (clampedHeight > 1 && !this.canWidgetFitAtPosition(resizeState.occupiedCells, resizeState.originColumn, resizeState.originRow, targetSize, clampedHeight)) {
				clampedHeight -= 1;
			}

			return Math.max(1, clampedHeight);
		},
		async persistWidgetOrder() {
			if (!this.widgetOrderUpdateUrl || !this.csrfToken) {
				return;
			}

			if (this.widgetOrderSaveTimer) {
				window.clearTimeout(this.widgetOrderSaveTimer);
			}

			this.widgetOrderSaveTimer = window.setTimeout(async () => {
				const widgetLayout = this.serializeWidgetLayout();

				try {
					const response = await fetch(this.widgetOrderUpdateUrl, {
						method: 'POST',
						headers: {
							'Accept': 'application/json',
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': this.csrfToken,
							'X-Requested-With': 'XMLHttpRequest',
						},
						body: JSON.stringify({
							widget_order: this.widgetOrderIds,
							widget_sizes: this.widgetSizes,
							widget_heights: this.widgetHeights,
							widget_positions: this.widgetPositions,
							widget_layout: widgetLayout,
						}),
					});

					if (!response.ok) {
						return;
					}

					const payload = await response.json();
					if (Array.isArray(payload?.widget_order)) {
						this.widgetOrderIds = payload.widget_order;
					}

					if (payload?.widget_layout && typeof payload.widget_layout === 'object') {
						this.hydrateWidgetLayout(payload.widget_layout);
					}

					if (payload?.widget_sizes && typeof payload.widget_sizes === 'object') {
						for (const widget of this.widgetDefinitions) {
							const savedSize = payload.widget_sizes[widget.id];
							if (Number.isInteger(savedSize) && savedSize >= 1 && savedSize <= 3) {
								this.widgetSizes[widget.id] = savedSize;
							}
						}
					}

					if (payload?.widget_heights && typeof payload.widget_heights === 'object') {
						for (const widget of this.widgetDefinitions) {
							const savedHeight = payload.widget_heights[widget.id];
							if (Number.isInteger(savedHeight) && savedHeight >= 1) {
								this.widgetHeights[widget.id] = savedHeight;
							}
						}
					}

					if (payload?.widget_positions && typeof payload.widget_positions === 'object') {
						for (const widget of this.widgetDefinitions) {
							const savedPosition = payload.widget_positions[widget.id];
							if (savedPosition && typeof savedPosition === 'object' && Number.isInteger(savedPosition.column) && savedPosition.column >= 1 && savedPosition.column <= 3 && Number.isInteger(savedPosition.row) && savedPosition.row >= 1) {
								this.widgetPositions[widget.id] = {
									column: savedPosition.column,
									row: savedPosition.row,
								};
							}
						}
					}
				} catch {
					// Best-effort persistence; the preview already updated locally.
				}
			}, 150);
		},
		serializeWidgetLayout() {
			const layout = {};

			for (const [index, widgetId] of this.widgetOrderIds.entries()) {
				const definition = this.widgetDefinitions.find((widget) => widget.id === widgetId);
				const size = this.widgetSizes[widgetId] || definition?.defaultSize || 1;
				const height = this.widgetHeights[widgetId] || definition?.defaultHeight || 1;
				layout[widgetId] = {
					order: index,
					size,
					height,
					column: this.widgetPositions[widgetId]?.column || definition?.defaultColumn || 1,
					row: this.widgetPositions[widgetId]?.row || definition?.defaultRow || 1,
				};
			}

			return layout;
		},
		hydrateWidgetLayout(layout) {
			for (const widget of this.widgetDefinitions) {
				const savedWidget = layout[widget.id];
				if (!savedWidget || typeof savedWidget !== 'object') {
					continue;
				}

				const savedSize = savedWidget.size;
				if (Number.isInteger(savedSize) && savedSize >= 1 && savedSize <= 3) {
					this.widgetSizes[widget.id] = savedSize;
				}

				const savedHeight = savedWidget.height;
				if (Number.isInteger(savedHeight) && savedHeight >= 1) {
					this.widgetHeights[widget.id] = savedHeight;
				}

				const savedColumn = savedWidget.column;
				const savedRow = savedWidget.row;
				if (Number.isInteger(savedColumn) && savedColumn >= 1 && savedColumn <= 3 && Number.isInteger(savedRow) && savedRow >= 1) {
					this.widgetPositions[widget.id] = {
						column: savedColumn,
						row: savedRow,
					};
				}
			}

			this.widgetPositions = this.buildWidgetPlacementMap({ positions: this.widgetPositions }).placements;
			this.widgetOrderIds = this.widgetIdsByCanvasOrder(this.widgetPositions);
		},
		isMobileViewport() {
			return window.matchMedia('(max-width: 1023px)').matches || window.innerWidth < 1024;
		},
		widgetContainerClasses(widgetId) {
			if (this.isMobileLayout) {
				return 'w-full';
			}

			const size = this.widgetSizes[widgetId] || 1;
			if (size === 2) {
				return 'w-full lg:col-span-2';
			}

			if (size === 3) {
				return 'w-full lg:col-span-3';
			}

			return 'w-full lg:col-span-1';
		},
		widgetStackClasses(widgetId) {
			if (this.isMobileLayout) {
				return '';
			}

			return 'dashboard-widget-stack-item';
		},
		widgetDragClasses(widgetId) {
			if (this.isMobileLayout) {
				return 'cursor-default select-none';
			}

			if (this.activeMove?.widgetId === widgetId && this.activeMove.dragging) {
				return 'dashboard-widget-moving-card cursor-grabbing select-none ring-2 ring-sky-300 shadow-lg';
			}

			if (this.activeResize?.widgetId === widgetId) {
				return 'dashboard-widget-resizing-card cursor-default select-none ring-2 ring-sky-300 shadow-lg';
			}

			if (this.isWidgetDisplaced(widgetId)) {
				return 'dashboard-widget-displaced-card cursor-grab select-none ring-1 ring-amber-300 shadow-md';
			}

			return 'cursor-grab select-none transition-all duration-300 ease-out hover:-translate-y-0.5 hover:ring-1 hover:ring-slate-300 hover:shadow-md active:cursor-grabbing';
		},
		widgetGridClasses() {
			if (this.isMobileLayout || (!this.activeResize && !this.activeDragWidgetId && !this.activeMove?.dragging)) {
				return '';
			}

			return 'dashboard-widget-grid-guides';
		},
		activePreviewPositions() {
			if (this.activeResize?.originalPositions) {
				return this.activeResize.originalPositions;
			}

			if (this.activeMove?.originalPositions) {
				return this.activeMove.originalPositions;
			}

			return null;
		},
		isWidgetResizing(widgetId) {
			return this.activeResize?.widgetId === widgetId;
		},
		isWidgetDisplaced(widgetId) {
			const originalPositions = this.activePreviewPositions();
			if (!originalPositions || this.isMobileLayout) {
				return false;
			}

			if (this.activeMove?.widgetId === widgetId || this.activeResize?.widgetId === widgetId) {
				return false;
			}

			const originalPosition = originalPositions[widgetId];
			const currentPosition = this.widgetPositions[widgetId];
			if (!originalPosition || !currentPosition) {
				return false;
			}

			return originalPosition.column !== currentPosition.column || originalPosition.row !== currentPosition.row;
		},
		widgetDisplacementSummary(widgetId) {
			if (!this.isWidgetDisplaced(widgetId)) {
				return '';
			}

			const position = this.widgetPositions[widgetId] || { column: 1, row: 1 };
			return `Shifts to C${position.column} R${position.row}`;
		},
		widgetResizeSummary(widgetId) {
			const width = this.widgetSizes[widgetId] || 1;
			const height = this.widgetHeights[widgetId] || 1;
			const widthLabel = width === 1 ? '1 column' : `${width} columns`;
			const heightLabel = height === 1 ? '1 row' : `${height} rows`;

			return `${widthLabel} x ${heightLabel}`;
		},
		widgetOrderStyle(widgetId) {
			const index = this.widgetOrderIds.indexOf(widgetId);
			const columnSpan = this.widgetSizes[widgetId] || 1;
			const rowSpan = this.widgetHeights[widgetId] || 1;
			const column = this.widgetPositions[widgetId]?.column || 1;
			const row = this.widgetPositions[widgetId]?.row || 1;
			if (this.isMobileLayout) {
				return `order: ${index >= 0 ? index : 0}; --dashboard-widget-columns: ${columnSpan}; --dashboard-widget-rows: ${rowSpan}; --dashboard-widget-mobile-rows: ${rowSpan};`;
			}

			return `order: ${index >= 0 ? index : 0}; --dashboard-widget-columns: ${columnSpan}; --dashboard-widget-rows: ${rowSpan}; grid-column: ${column} / span ${columnSpan}; grid-row: ${row} / span ${rowSpan};`;
		},
		widgetCanvasPlaceholderStyle() {
			if (this.isMobileLayout || !this.activeMove?.dragging) {
				return 'display: none;';
			}

			return `--dashboard-widget-columns: ${this.activeMove.columnSpan}; --dashboard-widget-rows: ${this.activeMove.rowSpan}; grid-column: ${this.activeMove.previewColumn} / span ${this.activeMove.columnSpan}; grid-row: ${this.activeMove.previewRow} / span ${this.activeMove.rowSpan};`;
		},
		widgetBodyClasses(widgetId) {
			const themeClass = widgetId === 'right-now'
				? 'dashboard-widget-scroll dashboard-widget-scroll-dark'
				: 'dashboard-widget-scroll dashboard-widget-scroll-light';

			if (!this.isMobileLayout) {
				return `${themeClass} h-full min-h-0 overflow-y-auto pr-1`;
			}

			return `${themeClass} dashboard-widget-mobile-body overflow-y-auto pr-1`;
		},
		guardWidgetDragFromScrollbar(widgetId, event) {
			if (this.isMobileLayout) {
				return;
			}

			const body = event.currentTarget;
			if (!(body instanceof HTMLElement)) {
				return;
			}

			const card = body.closest('[data-widget-card]');
			if (!(card instanceof HTMLElement)) {
				return;
			}

			const verticalScrollbarWidth = body.offsetWidth - body.clientWidth;
			const horizontalScrollbarHeight = body.offsetHeight - body.clientHeight;
			if (verticalScrollbarWidth <= 0 && horizontalScrollbarHeight <= 0) {
				return;
			}

			const rect = body.getBoundingClientRect();
			const isInVerticalScrollbar = verticalScrollbarWidth > 0 && event.clientX >= rect.right - verticalScrollbarWidth;
			const isInHorizontalScrollbar = horizontalScrollbarHeight > 0 && event.clientY >= rect.bottom - horizontalScrollbarHeight;

			if (!isInVerticalScrollbar && !isInHorizontalScrollbar) {
				return;
			}

			card.dataset.widgetDragDisabled = 'true';
			window.setTimeout(() => {
				delete card.dataset.widgetDragDisabled;
			}, 0);
		},
		isWidgetMoveExcludedTarget(target) {
			if (!(target instanceof Element)) {
				return false;
			}

			return target.closest('a, button, input, textarea, select, option, label, summary, [contenteditable="true"], [data-widget-resize-handle], [data-no-widget-drag], [data-widget-drag-disabled="true"]') !== null;
		},
		startWidgetMove(widgetId, event) {
			if (this.isMobileLayout || this.activeResize || this.isWidgetMoveExcludedTarget(event.target)) {
				return;
			}

			const card = event.currentTarget;
			if (!(card instanceof HTMLElement) || card.dataset.widgetDragDisabled === 'true') {
				return;
			}

			event.preventDefault();
			const pointerTarget = event.currentTarget;
			if (pointerTarget instanceof HTMLElement && typeof pointerTarget.setPointerCapture === 'function') {
				pointerTarget.setPointerCapture(event.pointerId);
			}

			card.dataset.widgetDragDisabled = 'true';
			const startPosition = this.widgetPositions[widgetId] || { column: 1, row: 1 };
			this.activeMove = {
				widgetId,
				pointerId: event.pointerId,
				originX: event.clientX,
				originY: event.clientY,
				startColumn: startPosition.column,
				startRow: startPosition.row,
				previewColumn: startPosition.column,
				previewRow: startPosition.row,
				columnSpan: this.widgetSizes[widgetId] || 1,
				rowSpan: this.widgetHeights[widgetId] || 1,
				originalPositions: { ...this.widgetPositions },
				originalOrder: [...this.widgetOrderIds],
				visibleWidgetIds: this.visibleWidgetIds(),
				dragging: false,
				hasChanged: false,
			};

			const handlePointerMove = (moveEvent) => {
				if (!this.activeMove || moveEvent.pointerId !== this.activeMove.pointerId) {
					return;
				}

				const deltaX = moveEvent.clientX - this.activeMove.originX;
				const deltaY = moveEvent.clientY - this.activeMove.originY;
				if (!this.activeMove.dragging && Math.hypot(deltaX, deltaY) < this.widgetMoveActivationDistance()) {
					return;
				}

				this.activeMove.dragging = true;
				document.body.classList.add('widget-moving');

				const nextCell = this.resolveCanvasCellFromDragDelta(this.activeMove, deltaX, deltaY);
				if (!nextCell) {
					return;
				}

				const nextLayout = this.placeWidgetOnCanvas(
					this.activeMove.widgetId,
					nextCell.column,
					nextCell.row,
					this.widgetSizes,
					this.widgetHeights,
					this.widgetPositions,
					this.activeMove.visibleWidgetIds,
				);
				this.activeMove.previewColumn = nextCell.column;
				this.activeMove.previewRow = nextCell.row;
				this.widgetPositions = nextLayout.positions;
				this.widgetOrderIds = nextLayout.order;
				this.activeMove.hasChanged = JSON.stringify(nextLayout.positions) !== JSON.stringify(this.activeMove.originalPositions)
					|| JSON.stringify(nextLayout.order) !== JSON.stringify(this.activeMove.originalOrder);
			};

			const finishMove = (finishEvent) => {
				if (!this.activeMove || finishEvent.pointerId !== this.activeMove.pointerId) {
					return;
				}

				document.removeEventListener('pointermove', handlePointerMove);
				document.removeEventListener('pointerup', finishMove);
				document.removeEventListener('pointercancel', finishMove);
				document.body.classList.remove('widget-moving');

				const shouldPersist = this.activeMove.hasChanged;
				const originalPositions = this.activeMove.originalPositions;
				const originalOrder = this.activeMove.originalOrder;
				card.dataset.widgetDragDisabled = 'false';
				window.setTimeout(() => {
					delete card.dataset.widgetDragDisabled;
				}, 0);

				if (!shouldPersist) {
					this.widgetPositions = originalPositions;
					this.widgetOrderIds = originalOrder;
				}

				this.activeMove = null;

				if (shouldPersist) {
					this.persistWidgetOrder();
				}
			};

			document.addEventListener('pointermove', handlePointerMove);
			document.addEventListener('pointerup', finishMove);
			document.addEventListener('pointercancel', finishMove);
		},
		setWidgetSize(widgetId, size) {
			if (!Number.isInteger(size) || size < 1 || size > 3) {
				return;
			}

			if (this.widgetSizes[widgetId] === size) {
				return;
			}

			this.widgetSizes[widgetId] = size;
			const nextLayout = this.layoutForResizedWidget(widgetId, size, this.widgetHeights[widgetId] || 1);
			this.widgetSizes = nextLayout.sizes;
			this.widgetHeights = nextLayout.heights;
			this.widgetPositions = nextLayout.positions;
			this.widgetOrderIds = nextLayout.order;
			this.persistWidgetOrder();
		},
		setWidgetSizeLocal(widgetId, size) {
			if (!Number.isInteger(size) || size < 1 || size > 3) {
				return;
			}

			this.widgetSizes[widgetId] = size;
		},
		cycleWidgetSize(widgetId) {
			const currentSize = this.widgetSizes[widgetId] || 1;
			const cycle = [1, 2, 3];
			const currentIndex = cycle.indexOf(currentSize);
			const nextIndex = (currentIndex + 1) % cycle.length;
			this.setWidgetSize(widgetId, cycle[nextIndex]);
		},
		setWidgetHeight(widgetId, height) {
			if (!Number.isInteger(height) || height < 1) {
				return;
			}

			if (this.widgetHeights[widgetId] === height) {
				return;
			}

			this.widgetHeights[widgetId] = height;
			const nextLayout = this.layoutForResizedWidget(widgetId, this.widgetSizes[widgetId] || 1, height);
			this.widgetSizes = nextLayout.sizes;
			this.widgetHeights = nextLayout.heights;
			this.widgetPositions = nextLayout.positions;
			this.widgetOrderIds = nextLayout.order;
			this.persistWidgetOrder();
		},
		setWidgetHeightLocal(widgetId, height) {
			if (!Number.isInteger(height) || height < 1) {
				return;
			}

			this.widgetHeights[widgetId] = height;
		},
		startWidgetResize(widgetId, axis, event) {
			if (this.isMobileLayout || !['x', 'y', 'xy'].includes(axis)) {
				return;
			}

			const previewRoot = document.querySelector('[data-dashboard-layout-preview]');
			const grid = previewRoot?.querySelector('[data-widget-grid]');
			if (!(grid instanceof HTMLElement)) {
				return;
			}

			const gridStyles = window.getComputedStyle(grid);
			const columnGap = Number.parseFloat(gridStyles.columnGap);
			const rowGap = Number.parseFloat(gridStyles.rowGap);
			const autoRows = Number.parseFloat(gridStyles.gridAutoRows);
			const normalizedColumnGap = Number.isFinite(columnGap) ? columnGap : 0;
			const normalizedRowGap = Number.isFinite(rowGap) ? rowGap : 0;
			const normalizedAutoRows = Number.isFinite(autoRows) && autoRows > 0 ? autoRows : 0;
			const columnWidth = (grid.clientWidth - (normalizedColumnGap * 2)) / 3;
			if (!Number.isFinite(columnWidth) || columnWidth <= 0 || normalizedAutoRows <= 0) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			const pointerTarget = event.currentTarget;
			if (pointerTarget instanceof HTMLElement && typeof pointerTarget.setPointerCapture === 'function') {
				pointerTarget.setPointerCapture(event.pointerId);
			}

			const startSize = this.widgetSizes[widgetId] || 1;
			const startHeight = this.widgetHeights[widgetId] || 1;
			const resizeAvailability = this.measureResizeAvailability(widgetId);
			if (!resizeAvailability) {
				return;
			}

			this.activeResize = {
				widgetId,
				axis,
				pointerId: event.pointerId,
				originX: event.clientX,
				originY: event.clientY,
				startSize,
				startHeight,
				originalSizes: { ...this.widgetSizes },
				originalHeights: { ...this.widgetHeights },
				originalPositions: { ...this.widgetPositions },
				originalOrder: [...this.widgetOrderIds],
				columnStep: columnWidth + normalizedColumnGap,
				rowStep: normalizedAutoRows + normalizedRowGap,
				originColumn: resizeAvailability.originColumn,
				originRow: resizeAvailability.originRow,
				occupiedCells: resizeAvailability.occupiedCells,
				hasChanged: false,
			};

			document.body.classList.add('widget-resizing');

			const handlePointerMove = (moveEvent) => {
				if (!this.activeResize || moveEvent.pointerId !== this.activeResize.pointerId) {
					return;
				}

				const deltaX = moveEvent.clientX - this.activeResize.originX;
				const deltaY = moveEvent.clientY - this.activeResize.originY;
				let nextSize = this.activeResize.startSize;
				let nextHeight = this.activeResize.startHeight;

				if (this.activeResize.axis === 'x' || this.activeResize.axis === 'xy') {
					nextSize = Math.min(4 - this.activeResize.originColumn, Math.max(1, this.activeResize.startSize + Math.round(deltaX / this.activeResize.columnStep)));
				}

				if (this.activeResize.axis === 'y' || this.activeResize.axis === 'xy') {
					nextHeight = Math.max(1, this.activeResize.startHeight + Math.round(deltaY / this.activeResize.rowStep));
				}

				const previewLayout = this.layoutForResizedWidget(widgetId, nextSize, nextHeight, {
					positions: this.activeResize.originalPositions,
					order: this.activeResize.originalOrder,
					sizes: this.activeResize.originalSizes,
					heights: this.activeResize.originalHeights,
				});

				this.widgetSizes = previewLayout.sizes;
				this.widgetHeights = previewLayout.heights;
				this.widgetPositions = previewLayout.positions;
				this.widgetOrderIds = previewLayout.order;
				this.activeResize.hasChanged = JSON.stringify(previewLayout.sizes) !== JSON.stringify(this.activeResize.originalSizes)
					|| JSON.stringify(previewLayout.heights) !== JSON.stringify(this.activeResize.originalHeights)
					|| JSON.stringify(previewLayout.positions) !== JSON.stringify(this.activeResize.originalPositions)
					|| JSON.stringify(previewLayout.order) !== JSON.stringify(this.activeResize.originalOrder);
			};

			const finishResize = (finishEvent) => {
				if (!this.activeResize || finishEvent.pointerId !== this.activeResize.pointerId) {
					return;
				}

				const shouldPersist = this.activeResize.hasChanged;
				document.removeEventListener('pointermove', handlePointerMove);
				document.removeEventListener('pointerup', finishResize);
				document.removeEventListener('pointercancel', finishResize);
				document.body.classList.remove('widget-resizing');

				if (!shouldPersist) {
					this.widgetSizes = this.activeResize.originalSizes;
					this.widgetHeights = this.activeResize.originalHeights;
					this.widgetPositions = this.activeResize.originalPositions;
					this.widgetOrderIds = this.activeResize.originalOrder;
				}

				this.activeResize = null;

				if (shouldPersist) {
					this.persistWidgetOrder();
				}
			};

			document.addEventListener('pointermove', handlePointerMove);
			document.addEventListener('pointerup', finishResize);
			document.addEventListener('pointercancel', finishResize);
		},
		increaseWidgetHeight(widgetId) {
			const currentHeight = this.widgetHeights[widgetId] || 1;
			this.setWidgetHeight(widgetId, currentHeight + 1);
		},
		decreaseWidgetHeight(widgetId) {
			const currentHeight = this.widgetHeights[widgetId] || 1;
			this.setWidgetHeight(widgetId, Math.max(1, currentHeight - 1));
		},
		widgetSizeIcon(size) {
			return `${size || 1}C`;
		},
		widgetSizeLabel(size) {
			const normalizedSize = size || 1;
			return normalizedSize === 1 ? '1 column' : `${normalizedSize} columns`;
		},
		widgetHeightIcon(height) {
			return `${height || 1}R`;
		},
		widgetHeightLabel(height) {
			const normalizedHeight = height || 1;
			return normalizedHeight === 1 ? '1 row' : `${normalizedHeight} rows`;
		},
	};
}
