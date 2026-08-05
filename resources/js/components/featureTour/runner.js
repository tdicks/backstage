import {
	SHOW_CONNECTOR_LINE,
	SHOW_CONNECTOR_ARROW,
	STEP_AFTER_ACTION_DELAY_MS,
	wait,
	clampNumber,
	paddedViewportRect,
	isVisible,
	isDisplayed,
	setRichTextWithIcons,
	resolveAnchorReference,
	unionRect,
	drawConnectorBetweenRects,
	drawConnectorForSide,
	axisForSide,
	rectsIntersect,
	layoutCallout,
	findNonOverlappingFallbackRect,
} from './shared';

export class FeatureTourRunner {
	constructor({ tourId, tour, isAdmin = false, anchors, actions, state, targetScopeSelector = null, queuePosition = null, queueTotal = null }) {
		this.tourId = tourId;
		this.tour = tour;
		this.isAdmin = Boolean(isAdmin);
		this.anchors = anchors || {};
		this.actions = actions || {};
		this.state = state;
		this.targetScopeSelector = typeof targetScopeSelector === 'string' && targetScopeSelector.trim() !== ''
			? targetScopeSelector
			: null;
		this.queuePosition = queuePosition;
		this.queueTotal = queueTotal;
		this.steps = [];
		this.currentStepIndex = 0;
		this.beforeActionsExecutedForStep = null;
		this.afterActionsExecutedForStep = null;
		this.pendingTargetStepIndex = null;
		this.pendingTargetAttempts = 0;
		this.extraCalloutElements = [];
		this.extraConnectorTargets = [];
		this.previousPlacementSide = null;
		this.previousPlacementAxis = null;
		this.activeTargetRect = null;
		this.connectorFollowRafId = null;
		this.connectorFollowUntil = 0;
		this.connectorRevealTimeoutId = null;
		this.pendingAfterActionsTimeoutId = null;
		this.pendingContentSwapTimeoutId = null;
		this.pendingPostLayoutSyncTimeoutId = null;
		this.panelPlacementCacheTimeoutId = null;
		this.lastConnectorAnimationStepIndex = -1;
		this.lastPostLayoutSyncStepIndex = -1;
		this.renderSequence = 0;
		this.panelPlacementByStep = {};
		this.lastPanelPlacement = null;
		this.deferStepContentSwap = false;
		this.pendingRenderWhenVisible = false;
		this._finished = false;
		this.root = null;
		this.active = false;
		this.stackKey = `feature-tour:${tourId}`;
		this.escapeKeyHandler = (event) => {
			if (event.key !== 'Escape' || !this.active || !this.isTopOfModalStack()) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
			this.finish({ markComplete: false });
		};
		this.resizeHandler = () => {
			if (this.connectorFollowRafId !== null || this.connectorRevealTimeoutId !== null) {
				this.updateConnectorsFromLivePanel();
				return;
			}

			void this.renderCurrentStep();
		};
		this.scrollHandler = () => {
			if (this.connectorFollowRafId !== null || this.connectorRevealTimeoutId !== null) {
				this.updateConnectorsFromLivePanel();
				return;
			}

			void this.renderCurrentStep();
		};
		this.visibilityChangeHandler = () => {
			if (!this.active) {
				return;
			}

			if (document.hidden) {
				this.stopConnectorFollow();
				this.pendingRenderWhenVisible = true;
				return;
			}

			if (!this.pendingRenderWhenVisible) {
				return;
			}

			this.pendingRenderWhenVisible = false;
			this.lastConnectorAnimationStepIndex = -1;
			this.renderSequence += 1;
			void this.renderCurrentStep();
		};
		this.windowFocusHandler = () => {
			if (!this.active || document.hidden) {
				return;
			}

			this.pendingRenderWhenVisible = false;
			this.lastConnectorAnimationStepIndex = -1;
			this.renderSequence += 1;
			void this.renderCurrentStep();
		};
	}

	modalStack() {
		if (!Array.isArray(window.__backstageModalStack)) {
			window.__backstageModalStack = [];
		}

		return window.__backstageModalStack;
	}

	pushToModalStack() {
		const stack = this.modalStack();
		const existingIndex = stack.indexOf(this.stackKey);

		if (existingIndex !== -1) {
			stack.splice(existingIndex, 1);
		}

		stack.push(this.stackKey);
	}

	removeFromModalStack() {
		const stack = this.modalStack();
		const existingIndex = stack.lastIndexOf(this.stackKey);

		if (existingIndex !== -1) {
			stack.splice(existingIndex, 1);
		}
	}

	isTopOfModalStack() {
		const stack = this.modalStack();

		return stack.length > 0 && stack[stack.length - 1] === this.stackKey;
	}

	selectVariant() {
		const variants = this.tour?.variants || {};

		for (const variant of Object.values(variants)) {
			const mediaQuery = typeof variant?.media_query === 'string' ? variant.media_query : '(min-width: 0px)';

			if (!window.matchMedia(mediaQuery).matches) {
				continue;
			}

			const steps = (Array.isArray(variant.steps) ? variant.steps : []).filter((step) => {
				if (!step || typeof step !== 'object') {
					return false;
				}

				return !step.admin_only || this.isAdmin;
			});

			if (steps.length > 0) {
				return steps;
			}
		}

		return [];
	}

	hasCompleted() {
		if (!this.state || typeof this.state.hasCompleted !== 'function') {
			return false;
		}

		return this.state.hasCompleted();
	}

	markCompleted() {
		if (!this.state || typeof this.state.markCompleted !== 'function') {
			return;
		}

		void this.state.markCompleted();
	}

	resolveTargetReference(anchorOrSelector) {
		return resolveAnchorReference(this.anchors, anchorOrSelector);
	}

	resolveScopeRoot() {
		if (!this.targetScopeSelector) {
			return null;
		}

		const scopeRoot = document.querySelector(this.targetScopeSelector);

		if (!scopeRoot || !isVisible(scopeRoot)) {
			return null;
		}

		return scopeRoot;
	}

	queryElementsForSelector(selector) {
		const scopeRoot = this.resolveScopeRoot();

		if (scopeRoot) {
			const scopedElements = Array.from(scopeRoot.querySelectorAll(selector));

			if (scopedElements.length > 0) {
				return scopedElements;
			}
		}

		return Array.from(document.querySelectorAll(selector));
	}

	querySingleElement(selector, { requireDisplayed = false } = {}) {
		const [firstMatch] = this.queryElementsForSelector(selector);

		if (!firstMatch) {
			return null;
		}

		if (!requireDisplayed) {
			return firstMatch;
		}

		if (isDisplayed(firstMatch)) {
			return firstMatch;
		}

		for (const element of this.queryElementsForSelector(selector)) {
			if (isDisplayed(element)) {
				return element;
			}
		}

		return null;
	}

	resolveTargetElements(anchorOrSelector, { requireVisible = false } = {}) {
		const targetReference = this.resolveTargetReference(anchorOrSelector);

		if (!targetReference) {
			return {
				selector: null,
				view: 'individual',
				elements: [],
			};
		}

		const elements = this.queryElementsForSelector(targetReference.selector).filter((element) => {
			if (!requireVisible) {
				return true;
			}

			return isVisible(element);
		});

		return {
			selector: targetReference.selector,
			view: targetReference.view,
			elements,
		};
	}

	async runAction(actionName) {
		const action = this.actions?.[actionName];

		if (!action || typeof action !== 'object') {
			return;
		}

		const type = action.type;
		const waitMs = Number(action.wait_ms || 120);
		const maxAttempts = Math.max(1, Number(action.max_attempts || 1));
		const clickCount = Math.max(1, Number(action.click_count || 1));
		const desiredCheckedState = action.checked !== false;
		const targetSelector = this.resolveTargetReference(action.target)?.selector || null;
		const untilVisibleSelector = this.resolveTargetReference(action.until_visible)?.selector || null;

		if (type === 'ensure-visible') {
			for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
				const visibleTarget = untilVisibleSelector
					? this.querySingleElement(untilVisibleSelector, { requireDisplayed: true })
					: null;

				if (visibleTarget) {
					return;
				}

				const trigger = targetSelector ? this.querySingleElement(targetSelector) : null;

				if (!trigger) {
					return;
				}

				// Let the current UI event complete before synthetic clicks run.
				await wait(0);

				for (let click = 0; click < clickCount; click += 1) {
					trigger.click();
				}

				const pollIntervalMs = 40;
				const visibilitySettleMs = Math.max(waitMs, 260);
				let elapsedMs = 0;

				while (elapsedMs < visibilitySettleMs) {
					await wait(pollIntervalMs);
					elapsedMs += pollIntervalMs;

					const polledTarget = untilVisibleSelector
						? this.querySingleElement(untilVisibleSelector, { requireDisplayed: true })
						: null;

					if (polledTarget) {
						return;
					}
				}
			}
			return;
		}

		if (type === 'click') {
			const trigger = targetSelector ? this.querySingleElement(targetSelector) : null;

			if (!trigger) {
				return;
			}

			// Let the current UI event complete before synthetic clicks run.
			await wait(0);

			for (let click = 0; click < clickCount; click += 1) {
				trigger.click();
			}

			await wait(waitMs);
		}

		if (type === 'set-checked') {
			const trigger = targetSelector ? this.querySingleElement(targetSelector) : null;

			if (!(trigger instanceof HTMLInputElement)) {
				return;
			}

			if (trigger.type === 'radio' && desiredCheckedState === false) {
				return;
			}

			if (trigger.checked === desiredCheckedState) {
				return;
			}

			await wait(0);
			trigger.click();
			await wait(waitMs);
		}
	}

	async runStepActionsForHook(stepIndex, hookName, renderToken = null) {
		const step = this.steps[stepIndex];

		if (!step) {
			return true;
		}

		for (const actionName of step[hookName] || []) {
			await this.runAction(actionName);

			if (renderToken !== null && (renderToken !== this.renderSequence || !this.active)) {
				return false;
			}
		}

		return true;
	}

	async runBeforeActionsForStep(stepIndex, renderToken = null) {
		return this.runStepActionsForHook(stepIndex, 'before', renderToken);
	}

	async runAfterActionsForStep(stepIndex, renderToken = null) {
		return this.runStepActionsForHook(stepIndex, 'after', renderToken);
	}

	async runNextActionsForStep(stepIndex, renderToken = null) {
		return this.runStepActionsForHook(stepIndex, 'next', renderToken);
	}

	async runBackActionsForStep(stepIndex, renderToken = null) {
		return this.runStepActionsForHook(stepIndex, 'back', renderToken);
	}

	buildUi() {
		this.root = document.createElement('div');
		this.root.className = 'feature-tour-root';

		this.overlay = document.createElement('div');
		this.overlay.className = 'feature-tour-overlay';

		this.highlight = document.createElement('div');
		this.highlight.className = 'feature-tour-highlight';
		this.highlight.style.opacity = '0';
		this.highlight.style.visibility = 'hidden';

		if (SHOW_CONNECTOR_LINE) {
			this.line = document.createElement('div');
			this.line.className = 'feature-tour-line';
			this.line.style.opacity = '0';
			this.line.style.visibility = 'hidden';
		} else {
			this.line = null;
		}

		if (SHOW_CONNECTOR_ARROW) {
			this.arrow = document.createElement('div');
			this.arrow.className = 'feature-tour-arrow';
			this.arrow.style.opacity = '0';
			this.arrow.style.visibility = 'hidden';
		} else {
			this.arrow = null;
		}

		this.panel = document.createElement('section');
		this.panel.className = 'feature-tour-panel';
		this.panel.setAttribute('role', 'dialog');
		this.panel.setAttribute('aria-modal', 'true');
		this.panel.style.opacity = '0';
		this.panel.style.transform = 'translateY(8px) scale(0.98)';
		this.panel.style.visibility = 'hidden';

		this.stepLabel = document.createElement('p');
		this.stepLabel.className = 'feature-tour-step-label';

		this.title = document.createElement('h3');
		this.title.className = 'feature-tour-title';

		this.body = document.createElement('p');
		this.body.className = 'feature-tour-body';

		this.actionsWrap = document.createElement('div');
		this.actionsWrap.className = 'feature-tour-actions';

		this.closeButton = document.createElement('button');
		this.closeButton.type = 'button';
		this.closeButton.className = 'feature-tour-close';
		this.closeButton.setAttribute('aria-label', 'Close tour');
		this.closeButton.textContent = '×';
		this.closeButton.addEventListener('click', () => this.finish({ markComplete: false }));

		this.backButton = document.createElement('button');
		this.backButton.type = 'button';
		this.backButton.className = 'feature-tour-button feature-tour-button-secondary';
		this.backButton.textContent = 'Back';
		this.backButton.addEventListener('click', () => this.back());

		this.nextButton = document.createElement('button');
		this.nextButton.type = 'button';
		this.nextButton.className = 'feature-tour-button feature-tour-button-primary';
		this.nextButton.addEventListener('click', () => this.next());

		this.actionsWrap.append(this.backButton, this.nextButton);
		this.panel.append(this.closeButton, this.stepLabel, this.title, this.body, this.actionsWrap);
		this.root.append(this.overlay, this.highlight, this.panel);

		if (this.line) {
			this.root.append(this.line);
		}

		if (this.arrow) {
			this.root.append(this.arrow);
		}
	}

	clearExtraCalloutElements() {
		for (const element of this.extraCalloutElements) {
			if (element?.parentNode) {
				element.parentNode.removeChild(element);
			}
		}

		this.extraCalloutElements = [];
		this.extraConnectorTargets = [];
	}

	stopConnectorFollow() {
		if (this.connectorFollowRafId !== null) {
			window.cancelAnimationFrame(this.connectorFollowRafId);
			this.connectorFollowRafId = null;
		}

		if (this.connectorRevealTimeoutId !== null) {
			window.clearTimeout(this.connectorRevealTimeoutId);
			this.connectorRevealTimeoutId = null;
		}

		this.connectorFollowUntil = 0;
	}

	stopPendingAfterActions() {
		if (this.pendingAfterActionsTimeoutId !== null) {
			window.clearTimeout(this.pendingAfterActionsTimeoutId);
			this.pendingAfterActionsTimeoutId = null;
		}
	}

	stopPendingPostLayoutSync() {
		if (this.pendingPostLayoutSyncTimeoutId !== null) {
			window.clearTimeout(this.pendingPostLayoutSyncTimeoutId);
			this.pendingPostLayoutSyncTimeoutId = null;
		}
	}

	schedulePostLayoutSync(renderToken, stepIndex) {
		this.stopPendingPostLayoutSync();
		const postLayoutDelayMs = 160;

		this.pendingPostLayoutSyncTimeoutId = window.setTimeout(() => {
			this.pendingPostLayoutSyncTimeoutId = null;

			if (!this.active || document.hidden || renderToken !== this.renderSequence || this.currentStepIndex !== stepIndex) {
				return;
			}

			this.lastConnectorAnimationStepIndex = -1;
			this.renderSequence += 1;
			void this.renderCurrentStep();
		}, postLayoutDelayMs);
	}

	stopPendingContentSwap() {
		if (this.pendingContentSwapTimeoutId !== null) {
			window.clearTimeout(this.pendingContentSwapTimeoutId);
			this.pendingContentSwapTimeoutId = null;
		}
	}

	applyStepText(step) {
		const stepLabel = `Step ${this.currentStepIndex + 1} of ${this.steps.length}`;
		this.stepLabel.textContent = this.queueTotal && this.queueTotal > 1 && this.queuePosition
			? `Tour ${this.queuePosition} of ${this.queueTotal} · ${stepLabel}`
			: stepLabel;
		setRichTextWithIcons(this.title, step.title);
		setRichTextWithIcons(this.body, step.body);
		this.backButton.disabled = this.currentStepIndex === 0;
		this.nextButton.textContent = this.currentStepIndex === this.steps.length - 1 ? 'Done' : 'Next';
	}

	stopPendingPanelPlacementCache() {
		if (this.panelPlacementCacheTimeoutId !== null) {
			window.clearTimeout(this.panelPlacementCacheTimeoutId);
			this.panelPlacementCacheTimeoutId = null;
		}
	}

	collectArrowElements() {
		const arrows = [this.arrow];

		for (const connector of this.extraConnectorTargets) {
			arrows.push(connector.arrow);
		}

		return arrows.filter(Boolean);
	}

	hideArrowsImmediately() {
		for (const arrow of this.collectArrowElements()) {
			arrow.classList.remove('feature-tour-arrow-fade-in');
			arrow.style.display = 'none';
			arrow.style.opacity = '0';
			arrow.style.visibility = 'hidden';
		}
	}

	showArrowsImmediately() {
		if (this.activeTargetRect) {
			this.updateConnectorsFromLivePanel();
		}

		for (const arrow of this.collectArrowElements()) {
			arrow.classList.remove('feature-tour-arrow-fade-in');
			arrow.style.display = 'block';
			arrow.style.visibility = 'visible';
			arrow.style.opacity = '1';
		}
	}

	fadeInArrows() {
		if (this.activeTargetRect) {
			this.updateConnectorsFromLivePanel();
		}

		for (const arrow of this.collectArrowElements()) {
			arrow.classList.remove('feature-tour-arrow-fade-in');
			arrow.style.display = 'block';
			arrow.style.opacity = '0';
			arrow.style.visibility = 'visible';
		}

		window.setTimeout(() => {
			for (const arrow of this.collectArrowElements()) {
				void arrow.offsetWidth;
				arrow.classList.add('feature-tour-arrow-fade-in');
			}
		}, 16);
	}

	setConnectorOpacity(opacity) {
		if (this.line) {
			this.line.style.opacity = opacity;
		}

		for (const connector of this.extraConnectorTargets) {
			if (connector.line) {
				connector.line.style.opacity = opacity;
			}
		}
	}

	updateConnectorsFromLivePanel() {
		if (!this.activeTargetRect) {
			return;
		}

		if (!SHOW_CONNECTOR_LINE && !SHOW_CONNECTOR_ARROW) {
			return;
		}

		const panelRect = this.panel.getBoundingClientRect();
		const normalizedPanelRect = {
			top: panelRect.top,
			left: panelRect.left,
			width: panelRect.width,
			height: panelRect.height,
		};

		drawConnectorForSide({
			panelRect: normalizedPanelRect,
			targetRect: this.activeTargetRect,
			side: this.previousPlacementSide,
			line: this.line,
			arrow: this.arrow,
		});

		for (const connector of this.extraConnectorTargets) {
			drawConnectorBetweenRects({
				panelRect: normalizedPanelRect,
				targetRect: connector.targetRect,
				line: connector.line,
				arrow: connector.arrow,
			});
		}
	}

	startConnectorFollow(durationMs = 260) {
		this.stopConnectorFollow();

		const start = window.performance.now();
		this.connectorFollowUntil = start + durationMs;
		this.setConnectorOpacity('0');
		this.hideArrowsImmediately();
		this.connectorRevealTimeoutId = window.setTimeout(() => {
			if (!this.active || !this.activeTargetRect) {
				return;
			}

			this.updateConnectorsFromLivePanel();
			this.setConnectorOpacity('1');
			this.fadeInArrows();
			this.connectorRevealTimeoutId = null;
		}, durationMs);

		const tick = (now) => {
			if (!this.active || !this.activeTargetRect) {
				this.stopConnectorFollow();
				return;
			}

			this.updateConnectorsFromLivePanel();

			if (now < this.connectorFollowUntil) {
				this.connectorFollowRafId = window.requestAnimationFrame(tick);
				return;
			}

			this.connectorFollowRafId = null;
		};

		this.connectorFollowRafId = window.requestAnimationFrame(tick);
	}

	hideCalloutElements() {
		this.stopPendingAfterActions();
		this.stopPendingContentSwap();
		this.stopPendingPostLayoutSync();
		this.stopPendingPanelPlacementCache();
		this.stopConnectorFollow();
		this.activeTargetRect = null;
		this.clearExtraCalloutElements();
		this.highlight.style.opacity = '0';
		this.highlight.style.visibility = 'hidden';
		if (this.line) {
			this.line.style.opacity = '0';
			this.line.style.visibility = 'hidden';
		}

		if (this.arrow) {
			this.arrow.style.opacity = '0';
			this.arrow.style.visibility = 'hidden';
		}

		this.panel.style.opacity = '0';
		this.panel.style.transform = 'translateY(8px) scale(0.98)';
		this.panel.style.visibility = 'hidden';
	}

	async start({ force = false } = {}) {
		if (this.active) {
			return;
		}

		this.steps = this.selectVariant();
		this.previousPlacementSide = null;
		this.previousPlacementAxis = null;
		this.lastConnectorAnimationStepIndex = -1;

		if (this.steps.length === 0) {
			return;
		}

		if (!force && this.hasCompleted()) {
			return;
		}

		this.buildUi();
		document.body.appendChild(this.root);
		this.pushToModalStack();
		window.addEventListener('resize', this.resizeHandler);
		window.addEventListener('scroll', this.scrollHandler, true);
		window.addEventListener('keydown', this.escapeKeyHandler, true);
		document.addEventListener('visibilitychange', this.visibilityChangeHandler, true);
		window.addEventListener('focus', this.windowFocusHandler, true);
		this.active = true;

		if (document.hidden) {
			this.pendingRenderWhenVisible = true;
			return;
		}

		await this.renderCurrentStep();
	}

	async renderCurrentStep() {
		if (document.hidden) {
			this.pendingRenderWhenVisible = true;
			return;
		}

		const renderToken = ++this.renderSequence;
		const step = this.steps[this.currentStepIndex];

		if (!step) {
			this.finish({ markComplete: true });
			return;
		}

		if (this.beforeActionsExecutedForStep !== this.currentStepIndex) {
			this.beforeActionsExecutedForStep = this.currentStepIndex;

			const beforeActionsCompleted = await this.runBeforeActionsForStep(this.currentStepIndex, renderToken);

			if (!beforeActionsCompleted) {
				return;
			}
		}

		this.stopPendingContentSwap();
		const shouldDeferStepText = this.deferStepContentSwap
			&& this.panel?.style.visibility !== 'hidden'
			&& this.panel?.style.opacity !== '0';
		this.deferStepContentSwap = false;

		if (!shouldDeferStepText) {
			this.applyStepText(step);
		}

		const hasTarget = typeof step?.target === 'string' && step.target.trim() !== '';

		if (!hasTarget) {
			this.pendingTargetAttempts = 0;
			this.pendingTargetStepIndex = null;
			this.lastPostLayoutSyncStepIndex = -1;
			this.renderUntargetedStep();

			if (shouldDeferStepText) {
				const scheduledStepIndex = this.currentStepIndex;
				const panelTransitionMs = 230;

				this.pendingContentSwapTimeoutId = window.setTimeout(() => {
					this.pendingContentSwapTimeoutId = null;

					if (!this.active || renderToken !== this.renderSequence || this.currentStepIndex !== scheduledStepIndex) {
						return;
					}

					this.applyStepText(step);
					this.positionPanelCentered();
				}, panelTransitionMs);
			}

			this.scheduleAfterActionsForCurrentStep(renderToken);
			return;
		}

		const targetResolution = this.resolveTargetElements(step.target, { requireVisible: true });
		const viewMode = targetResolution.view;
		const visibleTargets = targetResolution.elements;
		const primaryTarget = visibleTargets[0] || null;

		if (this.pendingTargetStepIndex !== this.currentStepIndex) {
			this.pendingTargetStepIndex = this.currentStepIndex;
			this.pendingTargetAttempts = 0;
		}

		if (!primaryTarget) {
			const maxTargetWaitAttempts = 24;
			const targetRetryDelayMs = 100;
			this.pendingTargetAttempts += 1;
			this.showPanelWhileResolvingTarget();

			if (this.pendingTargetAttempts <= maxTargetWaitAttempts) {
				await wait(targetRetryDelayMs);

				if (renderToken !== this.renderSequence || !this.active) {
					return;
				}

				await this.renderCurrentStep();
				return;
			}

			this.pendingTargetAttempts = 0;
			this.pendingTargetStepIndex = null;
			this.afterActionsExecutedForStep = null;
			this.currentStepIndex += 1;
			await this.renderCurrentStep();
			return;
		}

		this.pendingTargetAttempts = 0;
		this.pendingTargetStepIndex = null;

		primaryTarget.scrollIntoView({ behavior: 'auto', block: 'center', inline: 'center' });
		await wait(40);

		if (renderToken !== this.renderSequence || !this.active) {
			return;
		}

		this.renderStepTargets(visibleTargets, viewMode);

		if (this.lastPostLayoutSyncStepIndex !== this.currentStepIndex) {
			this.lastPostLayoutSyncStepIndex = this.currentStepIndex;
			this.schedulePostLayoutSync(renderToken, this.currentStepIndex);
		}

		if (shouldDeferStepText) {
			const scheduledStepIndex = this.currentStepIndex;
			const panelTransitionMs = 230;

			this.pendingContentSwapTimeoutId = window.setTimeout(() => {
				this.pendingContentSwapTimeoutId = null;

				if (!this.active || renderToken !== this.renderSequence || this.currentStepIndex !== scheduledStepIndex) {
					return;
				}

				this.applyStepText(step);

				if (this.activeTargetRect) {
					this.positionPanelAndArrow(this.activeTargetRect, this.activeTargetRect);
				}
			}, panelTransitionMs);
		}

		this.scheduleAfterActionsForCurrentStep(renderToken);
	}

	renderUntargetedStep() {
		this.stopConnectorFollow();
		this.activeTargetRect = null;
		this.clearExtraCalloutElements();
		this.overlay.style.background = 'rgb(2 6 23 / 0.6)';
		this.highlight.style.opacity = '0';
		this.highlight.style.visibility = 'hidden';

		if (this.line) {
			this.line.style.opacity = '0';
			this.line.style.visibility = 'hidden';
		}

		if (this.arrow) {
			this.arrow.style.opacity = '0';
			this.arrow.style.visibility = 'hidden';
		}

		this.positionPanelCentered();
	}

	showPanelWhileResolvingTarget() {
		this.stopConnectorFollow();
		this.activeTargetRect = null;
		this.clearExtraCalloutElements();
		this.overlay.style.background = 'transparent';
		this.highlight.style.opacity = '0';
		this.highlight.style.visibility = 'hidden';

		if (this.line) {
			this.line.style.opacity = '0';
			this.line.style.visibility = 'hidden';
		}

		if (this.arrow) {
			this.arrow.style.opacity = '0';
			this.arrow.style.visibility = 'hidden';
		}

		if (this.positionPanelFromCachedPlacement()) {
			return;
		}

		this.positionPanelCentered();
	}

	positionPanelCentered(avoidRect = null) {
		const viewportMargin = 12;
		const panelWidth = Math.min(360, window.innerWidth - 24);

		this.panel.style.width = `${panelWidth}px`;
		this.panel.style.top = `${viewportMargin}px`;
		this.panel.style.left = `${viewportMargin}px`;
		this.panel.style.visibility = 'hidden';

		const measuredPanelRect = this.panel.getBoundingClientRect();
		const centeredLeft = Math.max(
			viewportMargin,
			Math.min(
				window.innerWidth - measuredPanelRect.width - viewportMargin,
				(window.innerWidth - measuredPanelRect.width) / 2
			)
		);
		const centeredTop = Math.max(
			viewportMargin,
			Math.min(
				window.innerHeight - measuredPanelRect.height - viewportMargin,
				(window.innerHeight - measuredPanelRect.height) / 2
			)
		);

		this.panel.style.left = `${centeredLeft}px`;
		this.panel.style.top = `${centeredTop}px`;

		if (avoidRect) {
			const panelRect = this.panel.getBoundingClientRect();
			const fallbackRect = findNonOverlappingFallbackRect({
				panelSize: {
					width: panelRect.width,
					height: panelRect.height,
				},
				targetRect: avoidRect,
				viewportMargin,
			});

			if (!fallbackRect) {
				// If every safe side is exhausted, place the panel in the middle of the
				// highlighted area instead of hiding the tour.
				const centeredOnTargetLeft = clampNumber(
					avoidRect.left + ((avoidRect.width - panelRect.width) / 2),
					viewportMargin,
					Math.max(viewportMargin, window.innerWidth - panelRect.width - viewportMargin)
				);
				const centeredOnTargetTop = clampNumber(
					avoidRect.top + ((avoidRect.height - panelRect.height) / 2),
					viewportMargin,
					Math.max(viewportMargin, window.innerHeight - panelRect.height - viewportMargin)
				);

				this.panel.style.left = `${centeredOnTargetLeft}px`;
				this.panel.style.top = `${centeredOnTargetTop}px`;
			} else {
				this.panel.style.left = `${fallbackRect.left}px`;
				this.panel.style.top = `${fallbackRect.top}px`;
			}
		}

		this.panel.style.transform = 'translateY(0) scale(1)';
		this.panel.style.opacity = '1';
		this.panel.style.visibility = 'visible';
		return true;
	}

	cacheCurrentPanelPlacement() {
		const panelRect = this.panel.getBoundingClientRect();

		if (panelRect.width <= 0 || panelRect.height <= 0) {
			return;
		}

		if (this.activeTargetRect && rectsIntersect({
			top: panelRect.top,
			left: panelRect.left,
			width: panelRect.width,
			height: panelRect.height,
		}, this.activeTargetRect)) {
			return;
		}

		const placement = {
			top: panelRect.top,
			left: panelRect.left,
			width: panelRect.width,
		};

		this.panelPlacementByStep[this.currentStepIndex] = placement;
		this.lastPanelPlacement = placement;
	}

	schedulePanelPlacementCache() {
		this.stopPendingPanelPlacementCache();
		const stepIndex = this.currentStepIndex;

		this.panelPlacementCacheTimeoutId = window.setTimeout(() => {
			this.panelPlacementCacheTimeoutId = null;

			if (!this.active || this.currentStepIndex !== stepIndex) {
				return;
			}

			this.cacheCurrentPanelPlacement();
		}, 320);
	}

	scheduleAfterActionsForCurrentStep(renderToken) {
		this.stopPendingAfterActions();

		const stepIndex = this.currentStepIndex;
		const step = this.steps[stepIndex];

		if (!step || this.afterActionsExecutedForStep === stepIndex || (step.after || []).length === 0) {
			return;
		}

		this.pendingAfterActionsTimeoutId = window.setTimeout(async () => {
			this.pendingAfterActionsTimeoutId = null;

			if (!this.active || this.currentStepIndex !== stepIndex || renderToken !== this.renderSequence) {
				return;
			}

			this.afterActionsExecutedForStep = stepIndex;
			const afterActionsCompleted = await this.runAfterActionsForStep(stepIndex, renderToken);

			if (!afterActionsCompleted) {
				return;
			}

			if (!this.active || this.currentStepIndex !== stepIndex || renderToken !== this.renderSequence) {
				return;
			}

			void this.renderCurrentStep();
		}, STEP_AFTER_ACTION_DELAY_MS);
	}

	positionPanelFromCachedPlacement(avoidRect = null) {
		const cachedPlacement = this.panelPlacementByStep[this.currentStepIndex] || this.lastPanelPlacement;

		if (!cachedPlacement) {
			return false;
		}

		const viewportMargin = 12;
		const panelWidth = Math.min(cachedPlacement.width, window.innerWidth - 24);
		this.panel.style.width = `${panelWidth}px`;
		this.panel.style.top = `${viewportMargin}px`;
		this.panel.style.left = `${viewportMargin}px`;
		this.panel.style.visibility = 'hidden';

		const measuredPanelRect = this.panel.getBoundingClientRect();
		const maxLeft = Math.max(viewportMargin, window.innerWidth - panelWidth - viewportMargin);
		const maxTop = Math.max(viewportMargin, window.innerHeight - measuredPanelRect.height - viewportMargin);
		const clampedLeft = clampNumber(cachedPlacement.left, viewportMargin, maxLeft);
		const clampedTop = clampNumber(cachedPlacement.top, viewportMargin, maxTop);

		this.panel.style.left = `${clampedLeft}px`;
		this.panel.style.top = `${clampedTop}px`;

		if (avoidRect) {
			const panelRect = this.panel.getBoundingClientRect();
			const overlapsTarget = rectsIntersect(
				{
					top: panelRect.top,
					left: panelRect.left,
					width: panelRect.width,
					height: panelRect.height,
				},
				avoidRect
			);

			if (overlapsTarget) {
				return false;
			}
		}

		this.panel.style.transform = 'translateY(0) scale(1)';
		this.panel.style.opacity = '1';
		this.panel.style.visibility = 'visible';

		return true;
	}

	renderMultipleBackdrop(highlightRects) {
		if (!this.root || !this.highlight || !Array.isArray(highlightRects) || highlightRects.length === 0) {
			return;
		}

		const viewportWidth = window.innerWidth;
		const viewportHeight = window.innerHeight;

		if (viewportWidth <= 0 || viewportHeight <= 0) {
			return;
		}

		const clampedRects = highlightRects
			.map((rect) => {
				const left = clampNumber(rect.left, 0, viewportWidth);
				const right = clampNumber(rect.left + rect.width, 0, viewportWidth);
				const top = clampNumber(rect.top, 0, viewportHeight);
				const bottom = clampNumber(rect.top + rect.height, 0, viewportHeight);

				if (right <= left || bottom <= top) {
					return null;
				}

				return {
					left,
					right,
					top,
					bottom,
				};
			})
			.filter(Boolean);

		if (clampedRects.length === 0) {
			return;
		}

		const roundedRectPath = (left, top, right, bottom, radius) => {
			const width = right - left;
			const height = bottom - top;
			const clampedRadius = Math.max(0, Math.min(radius, width / 2, height / 2));

			if (clampedRadius <= 0) {
				return `M ${left} ${top} H ${right} V ${bottom} H ${left} Z`;
			}

			const r = clampedRadius;

			return [
				`M ${left + r} ${top}`,
				`H ${right - r}`,
				`A ${r} ${r} 0 0 1 ${right} ${top + r}`,
				`V ${bottom - r}`,
				`A ${r} ${r} 0 0 1 ${right - r} ${bottom}`,
				`H ${left + r}`,
				`A ${r} ${r} 0 0 1 ${left} ${bottom - r}`,
				`V ${top + r}`,
				`A ${r} ${r} 0 0 1 ${left + r} ${top}`,
				'Z',
			].join(' ');
		};

		const svgNamespace = 'http://www.w3.org/2000/svg';
		const backdropSvg = document.createElementNS(svgNamespace, 'svg');
		backdropSvg.setAttribute('class', 'feature-tour-backdrop-svg');
		backdropSvg.setAttribute('viewBox', `0 0 ${viewportWidth} ${viewportHeight}`);
		backdropSvg.setAttribute('width', `${viewportWidth}`);
		backdropSvg.setAttribute('height', `${viewportHeight}`);
		backdropSvg.setAttribute('aria-hidden', 'true');
		backdropSvg.style.position = 'fixed';
		backdropSvg.style.inset = '0';
		backdropSvg.style.pointerEvents = 'none';

		const path = document.createElementNS(svgNamespace, 'path');
		const backdropPath = [`M 0 0 H ${viewportWidth} V ${viewportHeight} H 0 Z`];
		const holeRadius = 12;

		for (const rect of clampedRects) {
			backdropPath.push(roundedRectPath(rect.left, rect.top, rect.right, rect.bottom, holeRadius));
		}

		path.setAttribute('d', backdropPath.join(' '));
		path.setAttribute('fill-rule', 'evenodd');
		path.setAttribute('fill', 'rgba(0, 0, 0, 0.6)');

		backdropSvg.append(path);
		this.root.insertBefore(backdropSvg, this.highlight);
		this.extraCalloutElements.push(backdropSvg);
	}

	renderStepTargets(visibleTargets, viewMode) {
		this.clearExtraCalloutElements();
		this.overlay.style.background = 'transparent';
		this.highlight.className = 'feature-tour-highlight';

		const paddedRects = visibleTargets
			.map((target) => paddedViewportRect(target.getBoundingClientRect()));

		if (paddedRects.length === 0) {
			this.hideCalloutElements();
			return;
		}

		if (viewMode === 'surround') {
			const boundingRect = unionRect(paddedRects);

			if (!boundingRect) {
				this.hideCalloutElements();
				return;
			}

			this.highlight.style.top = `${boundingRect.top}px`;
			this.highlight.style.left = `${boundingRect.left}px`;
			this.highlight.style.width = `${boundingRect.width}px`;
			this.highlight.style.height = `${boundingRect.height}px`;
			this.highlight.style.visibility = 'visible';
			this.highlight.style.opacity = '1';
			this.positionPanelAndArrow(boundingRect);
			return;
		}

		const primaryRect = paddedRects[0];
		const combinedRect = unionRect(paddedRects) || primaryRect;
		const fallbackRect = viewMode === 'multiple' ? combinedRect : primaryRect;
		const shouldUseMultipleBackdrop = viewMode === 'multiple' && paddedRects.length > 1;

		if (shouldUseMultipleBackdrop) {
			this.highlight.className = 'feature-tour-highlight feature-tour-highlight-no-dim';
			this.renderMultipleBackdrop(paddedRects);
		}

		this.highlight.style.top = `${primaryRect.top}px`;
		this.highlight.style.left = `${primaryRect.left}px`;
		this.highlight.style.width = `${primaryRect.width}px`;
		this.highlight.style.height = `${primaryRect.height}px`;
		this.highlight.style.visibility = 'visible';
		this.highlight.style.opacity = '1';
		this.positionPanelAndArrow(primaryRect, fallbackRect);

		if (viewMode !== 'multiple' || paddedRects.length <= 1) {
			return;
		}

		const panelRect = this.panel.getBoundingClientRect();

		for (const targetRect of paddedRects.slice(1)) {
			const extraHighlight = document.createElement('div');
			extraHighlight.className = 'feature-tour-highlight-secondary';
			extraHighlight.style.top = `${targetRect.top}px`;
			extraHighlight.style.left = `${targetRect.left}px`;
			extraHighlight.style.width = `${targetRect.width}px`;
			extraHighlight.style.height = `${targetRect.height}px`;

			const extraLine = SHOW_CONNECTOR_LINE ? document.createElement('div') : null;

			if (extraLine) {
				extraLine.className = 'feature-tour-line';
				extraLine.style.opacity = this.connectorFollowRafId !== null ? '0' : '1';
			}

			const extraArrow = SHOW_CONNECTOR_ARROW ? document.createElement('div') : null;

			if (extraArrow) {
				extraArrow.className = 'feature-tour-arrow';
				extraArrow.style.opacity = this.connectorFollowRafId !== null ? '0' : '1';
				extraArrow.style.visibility = this.connectorFollowRafId !== null ? 'hidden' : 'visible';
				extraArrow.style.display = this.connectorFollowRafId !== null ? 'none' : 'block';
			}

			drawConnectorBetweenRects({
				panelRect,
				targetRect,
				line: extraLine,
				arrow: extraArrow,
			});

			this.extraConnectorTargets.push({
				line: extraLine,
				arrow: extraArrow,
				targetRect,
			});

			this.root.append(extraHighlight);

			if (extraLine) {
				this.root.append(extraLine);
			}

			if (extraArrow) {
				this.root.append(extraArrow);
			}

			this.extraCalloutElements.push(extraHighlight);

			if (extraLine) {
				this.extraCalloutElements.push(extraLine);
			}

			if (extraArrow) {
				this.extraCalloutElements.push(extraArrow);
			}
		}
	}

	positionPanelAndArrow(targetRect, fallbackRect = targetRect) {
		const layout = layoutCallout({
			panel: this.panel,
			line: this.line,
			arrow: this.arrow,
			targetRect,
			panelWidth: Math.min(360, window.innerWidth - 24),
			preferredSide: this.previousPlacementSide,
			preferredAxis: this.previousPlacementAxis,
		});

		if (!layout) {
			this.activeTargetRect = null;
			this.stopConnectorFollow();

			if (this.positionPanelFromCachedPlacement(fallbackRect)) {
				return;
			}

			if (this.positionPanelCentered(fallbackRect)) {
				return;
			}

			this.hideCalloutElements();
			return;
		}

		this.previousPlacementSide = layout.side;
		this.previousPlacementAxis = layout.axis;
		this.activeTargetRect = layout.targetRect;
		this.schedulePanelPlacementCache();

		if (this.lastConnectorAnimationStepIndex !== this.currentStepIndex) {
			this.lastConnectorAnimationStepIndex = this.currentStepIndex;
			this.startConnectorFollow(300);
			return;
		}

		if (this.connectorFollowRafId !== null || this.connectorRevealTimeoutId !== null) {
			this.updateConnectorsFromLivePanel();
			return;
		}

		this.stopConnectorFollow();
		this.updateConnectorsFromLivePanel();
		this.setConnectorOpacity('1');
		this.showArrowsImmediately();
	}

	async next() {
		const currentStepIndex = this.currentStepIndex;

		const nextActionsCompleted = await this.runNextActionsForStep(currentStepIndex);

		if (!nextActionsCompleted || !this.active || currentStepIndex !== this.currentStepIndex) {
			return;
		}

		if (this.currentStepIndex >= this.steps.length - 1) {
			this.finish({ markComplete: true });
			return;
		}

		this.renderSequence += 1;
		this.stopPendingAfterActions();
		this.stopPendingContentSwap();
		this.stopPendingPostLayoutSync();
		this.hideArrowsImmediately();
		this.deferStepContentSwap = true;
		this.lastPostLayoutSyncStepIndex = -1;
		this.currentStepIndex += 1;
		this.beforeActionsExecutedForStep = null;
		this.afterActionsExecutedForStep = null;
		void this.renderCurrentStep();
	}

	async back() {
		if (this.currentStepIndex <= 0) {
			return;
		}

		const currentStepIndex = this.currentStepIndex;
		const backActionsCompleted = await this.runBackActionsForStep(currentStepIndex);

		if (!backActionsCompleted || !this.active || currentStepIndex !== this.currentStepIndex) {
			return;
		}

		this.renderSequence += 1;
		this.stopPendingAfterActions();
		this.stopPendingContentSwap();
		this.stopPendingPostLayoutSync();
		this.hideArrowsImmediately();
		const targetStepIndex = currentStepIndex - 1;
		const beforeActionsCompleted = await this.runBeforeActionsForStep(currentStepIndex);

		if (!beforeActionsCompleted || !this.active) {
			return;
		}

		this.currentStepIndex = targetStepIndex;
		this.beforeActionsExecutedForStep = this.currentStepIndex;
		this.afterActionsExecutedForStep = null;
		this.deferStepContentSwap = true;
		this.lastPostLayoutSyncStepIndex = -1;
		void this.renderCurrentStep();
	}

	finish({ markComplete }) {
		if (this._finished) {
			return;
		}

		this._finished = true;

		if (markComplete) {
			this.markCompleted();
		}

		window.removeEventListener('resize', this.resizeHandler);
		window.removeEventListener('scroll', this.scrollHandler, true);
		window.removeEventListener('keydown', this.escapeKeyHandler, true);
		document.removeEventListener('visibilitychange', this.visibilityChangeHandler, true);
		window.removeEventListener('focus', this.windowFocusHandler, true);
		this.active = false;
		this.removeFromModalStack();
		this.stopPendingAfterActions();
		this.stopPendingContentSwap();
		this.stopPendingPostLayoutSync();
		this.stopPendingPanelPlacementCache();
		this.stopConnectorFollow();
		this.pendingRenderWhenVisible = false;

		if (this.root?.parentNode) {
			this.root.parentNode.removeChild(this.root);
		}

		this.clearExtraCalloutElements();

		if (typeof this.state?.onFinish === 'function') {
			this.state.onFinish({ markComplete });
		}
	}
}

