import {
	parseConfig,
	getTriggerMode,
	shouldShowInfoIconForTour,
	isVisible,
	setRichTextWithIcons,
	paddedViewportRect,
	layoutCallout,
	resolveAnchorReference,
	routeMatches,
	escapeForAttributeSelector,
} from './shared';
import { FeatureTourRunner } from './runner';
import { FeatureTourPrompt } from './prompt';

class FeatureTourManager {
	constructor(config) {
		this.config = config || {};
		this.runner = null;
		this.prompt = new FeatureTourPrompt();
		this.buttonBindings = [];
		this.dataAttributeTriggerBound = false;
		this.stateUpdateUrl = typeof this.config.state_update_url === 'string' ? this.config.state_update_url : null;
		this.state = {
			completed: { ...(this.config.state?.completed || {}) },
			prompt_dismissed: { ...(this.config.state?.prompt_dismissed || {}) },
			opted_out: { ...(this.config.state?.opted_out || {}) },
		};
		this.queue = [];
		this.queueIndex = -1;
		this.queueActive = false;
		this.queuePaused = false;
		this.queueStartedForRoute = null;
		this.resumeHintRoot = null;
		this.modalStateListenersBound = false;
	}

	getTourModalId(tour) {
		const modalId = typeof tour?.trigger?.modal?.id === 'string'
			? tour.trigger.modal.id.trim()
			: '';

		return modalId === '' ? null : modalId;
	}

	getOpenModalIds() {
		const openModalIds = new Set();

		document.querySelectorAll('[data-feature-tour-modal][data-feature-tour-modal-open="true"]').forEach((element) => {
			const modalId = element.getAttribute('data-feature-tour-modal');

			if (typeof modalId === 'string' && modalId.trim() !== '') {
				openModalIds.add(modalId.trim());
			}
		});

		return openModalIds;
	}

	resolveModalScopeSelector(modalId) {
		if (typeof modalId !== 'string' || modalId.trim() === '') {
			return null;
		}

		const escapedModalId = escapeForAttributeSelector(modalId.trim());

		if (escapedModalId === '') {
			return null;
		}

		return `[data-feature-tour-modal="${escapedModalId}"][data-feature-tour-modal-open="true"]`;
	}

	resolveModalScopeRoot(modalId) {
		const selector = this.resolveModalScopeSelector(modalId);

		if (!selector) {
			return null;
		}

		return document.querySelector(selector);
	}

	buildQueue() {
		const tours = this.config.tours || {};
		return Object.keys(tours)
			.map((tourId) => ({
				tourId,
				tour: tours[tourId],
			}))
			.filter(({ tour }) => this.isTourEligible(tour))
			.filter(({ tourId, tour }) => !this.hasCompleted(tourId, tour) && !this.isOptedOut(tourId, tour))
			.sort((first, second) => {
				const firstPriority = Number(first.tour?.priority ?? 100);
				const secondPriority = Number(second.tour?.priority ?? 100);

				if (firstPriority === secondPriority) {
					return 0;
				}

				return firstPriority - secondPriority;
			});
	}

	beginQueue(queue, { force = false } = {}) {
		this.queue = Array.isArray(queue) ? queue : [];
		this.queueIndex = -1;
		this.queueActive = this.queue.length > 0;
		this.queuePaused = false;
		this.queueStartedForRoute = this.config.current_route || null;

		if (!this.queueActive) {
			return;
		}

		void this.advanceQueue({ force });
	}

	advanceQueue({ force = false } = {}) {
		if (!this.queueActive || this.queuePaused) {
			return;
		}

		this.queueIndex += 1;

		while (this.queueIndex < this.queue.length) {
			const entry = this.queue[this.queueIndex];

			if (!entry) {
				this.queueIndex += 1;
				continue;
			}

			const tourId = entry.tourId;
			const tour = entry.tour;

			if (this.hasCompleted(tourId, tour) || this.isOptedOut(tourId, tour)) {
				this.queueIndex += 1;
				continue;
			}

			if (getTriggerMode(tour) === 'prompt') {
				void this.maybePromptTour(tourId, tour, { force })
					.then((started) => {
						if (!started) {
							if (!this.isPromptDismissed(tourId, tour) && !this.isOptedOut(tourId, tour)) {
								this.queuePaused = true;
							}
							return;
						}
					})
					.catch(() => {
						this.queuePaused = true;
					});
				return;
			}

			this.start(tourId, { force });
			return;
		}

		this.queueActive = false;
		this.queuePaused = false;
		this.queue = [];
		this.queueIndex = -1;
		this.queueStartedForRoute = null;
	}

	resumeQueue() {
		if (!this.queuePaused) {
			return;
		}

		this.queuePaused = false;
		void this.advanceQueue({ force: true });
	}

	getEligiblePromptTours({ modalId = null } = {}) {
		const tours = this.config.tours || {};

		return Object.keys(tours)
			.map((tourId) => ({
				tourId,
				tour: tours[tourId],
			}))
			.filter(({ tour }) => this.isTourEligible(tour, { modalId }) && this.isTourDomReady(tour, { modalId }) && getTriggerMode(tour) === 'prompt')
			.sort((first, second) => Number(first.tour?.priority ?? 100) - Number(second.tour?.priority ?? 100));
	}

	getEligibleInfoIconTours({ modalId = null } = {}) {
		const tours = this.config.tours || {};

		return Object.keys(tours)
			.map((tourId) => ({
				tourId,
				tour: tours[tourId],
			}))
			.filter(({ tourId, tour }) => {
				const mode = getTriggerMode(tour);

				if (mode !== 'info-icon' && mode !== 'info-icon-always') {
					return false;
				}

				if (mode === 'info-icon-always') {
					return this.isTourEligible(tour, { modalId }) && this.isTourDomReady(tour, { modalId });
				}

				return this.isTourEligible(tour, { modalId })
					&& this.isTourDomReady(tour, { modalId })
					&& !this.hasCompleted(tourId, tour)
					&& !this.isOptedOut(tourId, tour);
			})
			.sort((first, second) => Number(first.tour?.priority ?? 100) - Number(second.tour?.priority ?? 100));
	}

	getEligibleConfiguredInfoIconTours({ modalId = null } = {}) {
		const tours = this.config.tours || {};

		return Object.keys(tours)
			.map((tourId) => ({
				tourId,
				tour: tours[tourId],
			}))
			.filter(({ tourId, tour }) => {
				if (!shouldShowInfoIconForTour(tour)) {
					return false;
				}

				if (!this.isTourEligible(tour, { modalId }) || !this.isTourDomReady(tour, { modalId })) {
					return false;
				}

				return !this.hasCompleted(tourId, tour) && !this.isOptedOut(tourId, tour);
			})
			.sort((first, second) => Number(first.tour?.priority ?? 100) - Number(second.tour?.priority ?? 100));
	}

	getEligibleResumeIconTours({ modalId = null } = {}) {
		const eligibleInfoIconTours = this.getEligibleInfoIconTours({ modalId });
		const eligibleConfiguredInfoIconTours = this.getEligibleConfiguredInfoIconTours({ modalId });
		const mergedByTourId = new Map();

		for (const entry of [...eligibleInfoIconTours, ...eligibleConfiguredInfoIconTours]) {
			if (!mergedByTourId.has(entry.tourId)) {
				mergedByTourId.set(entry.tourId, entry);
			}
		}

		return Array.from(mergedByTourId.values())
			.sort((first, second) => Number(first.tour?.priority ?? 100) - Number(second.tour?.priority ?? 100));
	}

	shouldShowModalTrigger(modalId) {
		if (typeof modalId !== 'string' || modalId.trim() === '') {
			return false;
		}

		const trimmedModalId = modalId.trim();
		const eligibleResumeIconTours = this.getEligibleResumeIconTours({ modalId: trimmedModalId })
			.filter(({ tour }) => this.getTourModalId(tour) === trimmedModalId);
		const eligiblePromptTours = this.getEligiblePromptTours({ modalId: trimmedModalId })
			.filter(({ tour }) => this.getTourModalId(tour) === trimmedModalId);
		const shouldShowForDismissedPromptTour = eligiblePromptTours.some(({ tourId, tour }) => {
			return this.isPromptDismissed(tourId, tour) && !this.hasCompleted(tourId, tour) && !this.isOptedOut(tourId, tour);
		});

		return eligibleResumeIconTours.length > 0 || shouldShowForDismissedPromptTour;
	}

	updateResumeTriggerVisibility() {
		const eligibleGlobalResumeIconTours = this.getEligibleResumeIconTours().filter(({ tour }) => this.getTourModalId(tour) === null);
		const eligibleGlobalPromptTours = this.getEligiblePromptTours().filter(({ tour }) => this.getTourModalId(tour) === null);
		const shouldShowForInfoIconTours = eligibleGlobalResumeIconTours.length > 0;
		const shouldShowForDismissedPromptTour = eligibleGlobalPromptTours.some(({ tourId, tour }) => {
			return this.isPromptDismissed(tourId, tour) && !this.hasCompleted(tourId, tour) && !this.isOptedOut(tourId, tour);
		});
		const shouldShow = shouldShowForInfoIconTours || shouldShowForDismissedPromptTour;

		document.querySelectorAll('[data-feature-tour-resume-trigger]').forEach((element) => {
			element.style.display = shouldShow ? 'inline-flex' : 'none';
			element.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
		});

		document.querySelectorAll('[data-feature-tour-modal-trigger]').forEach((element) => {
			const modalId = (element.getAttribute('data-feature-tour-modal-trigger') || '').trim();
			const shouldShowModalTourIcon = this.shouldShowModalTrigger(modalId);

			element.style.display = shouldShowModalTourIcon ? 'inline-flex' : 'none';
			element.setAttribute('aria-hidden', shouldShowModalTourIcon ? 'false' : 'true');
		});
	}

	hasDeferredPromptTour() {
		return this.getEligiblePromptTours().some(({ tourId, tour }) => {
			if (this.getTourModalId(tour) !== null) {
				return false;
			}

			return this.isPromptDismissed(tourId, tour) && !this.hasCompleted(tourId, tour) && !this.isOptedOut(tourId, tour);
		});
	}

	getOnceKey(tourId, tour) {
		return tour?.once_key || tourId;
	}

	csrfToken() {
		return document.querySelector('meta[name="csrf-token"]')?.content || '';
	}

	async persistState(onceKey, action) {
		if (!this.config.authenticated || !this.stateUpdateUrl) {
			return;
		}

		try {
			const response = await fetch(this.stateUpdateUrl, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': this.csrfToken(),
				},
				body: JSON.stringify({
					once_key: onceKey,
					action,
				}),
			});

			if (!response.ok) {
				return;
			}

			const payload = await response.json();
			const completed = payload?.state?.completed;
			const promptDismissed = payload?.state?.prompt_dismissed;

			if (completed && typeof completed === 'object') {
				this.state.completed = { ...completed };
			}

			if (promptDismissed && typeof promptDismissed === 'object') {
				this.state.prompt_dismissed = { ...promptDismissed };
			}
		} catch {
			// Best-effort persistence: in-memory state still updates immediately.
		}
	}

	hasCompleted(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		return this.state.completed?.[onceKey] === true;
	}

	isPromptDismissed(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		return this.state.prompt_dismissed?.[onceKey] === true;
	}

	isOptedOut(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		return this.state.opted_out?.[onceKey] === true;
	}

	markPromptDismissed(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		this.state.prompt_dismissed[onceKey] = true;
		this.updateResumeTriggerVisibility();
		void this.persistState(onceKey, 'dismiss_prompt');
	}

	clearPromptDismissed(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		delete this.state.prompt_dismissed[onceKey];
		this.updateResumeTriggerVisibility();
		void this.persistState(onceKey, 'clear_prompt_dismissal');
	}

	markOptedOut(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		this.state.opted_out[onceKey] = true;
		delete this.state.prompt_dismissed[onceKey];
		this.updateResumeTriggerVisibility();
		void this.persistState(onceKey, 'opt_out');
	}

	clearOptedOut(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		delete this.state.opted_out[onceKey];
		this.updateResumeTriggerVisibility();
		void this.persistState(onceKey, 'clear_opt_out');
	}

	markCompleted(tourId, tour) {
		const onceKey = this.getOnceKey(tourId, tour);
		this.state.completed[onceKey] = true;
		delete this.state.prompt_dismissed[onceKey];
		delete this.state.opted_out[onceKey];
		this.updateResumeTriggerVisibility();
		void this.persistState(onceKey, 'complete');
	}

	closeResumeHint() {
		if (this.resumeHintRoot?.parentNode) {
			this.resumeHintRoot.parentNode.removeChild(this.resumeHintRoot);
		}

		this.resumeHintRoot = null;
	}

	showResumeHint(tour) {
		const message = typeof tour?.trigger?.prompt?.resume_hint === 'string' && tour.trigger.prompt.resume_hint.trim() !== ''
			? tour.trigger.prompt.resume_hint
			: 'No problem, click the info icon in the navigation bar when you are ready to start the tour.';

		const visibleTrigger = Array.from(document.querySelectorAll('[data-feature-tour-resume-trigger]')).find((element) => {
			return isVisible(element);
		});

		if (!visibleTrigger) {
			return;
		}

		visibleTrigger.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

		this.closeResumeHint();

		const root = document.createElement('div');
		root.className = 'feature-tour-resume-tip-root';

		const overlay = document.createElement('button');
		overlay.type = 'button';
		overlay.className = 'feature-tour-resume-tip-overlay';
		overlay.setAttribute('aria-label', 'Close feature tour hint');
		overlay.addEventListener('click', () => this.closeResumeHint());

		const highlight = document.createElement('div');
		highlight.className = 'feature-tour-highlight';

		const line = document.createElement('div');
		line.className = 'feature-tour-line';

		const arrow = document.createElement('div');
		arrow.className = 'feature-tour-arrow';

		const panel = document.createElement('section');
		panel.className = 'feature-tour-panel feature-tour-resume-tip-panel';
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-modal', 'true');

		const body = document.createElement('p');
		body.className = 'feature-tour-body';
		setRichTextWithIcons(body, message);

		const actions = document.createElement('div');
		actions.className = 'feature-tour-actions';

		const done = document.createElement('button');
		done.type = 'button';
		done.className = 'feature-tour-button feature-tour-button-primary';
		done.textContent = 'Got it';
		done.addEventListener('click', () => this.closeResumeHint());

		actions.appendChild(done);
		panel.append(body, actions);
		root.append(overlay, highlight, line, arrow, panel);
		document.body.appendChild(root);
		this.resumeHintRoot = root;

		const targetRect = visibleTrigger.getBoundingClientRect();
		const paddedRect = paddedViewportRect(targetRect);

		highlight.style.top = `${paddedRect.top}px`;
		highlight.style.left = `${paddedRect.left}px`;
		highlight.style.width = `${paddedRect.width}px`;
		highlight.style.height = `${paddedRect.height}px`;

		layoutCallout({
			panel,
			line,
			arrow,
			targetRect: paddedRect,
			panelWidth: Math.min(300, window.innerWidth - 24),
		});
	}

	resolveSelector(anchorOrSelector) {
		return resolveAnchorReference(this.config.anchors, anchorOrSelector)?.selector || null;
	}

	resolveTargetReference(anchorOrSelector) {
		return resolveAnchorReference(this.config.anchors, anchorOrSelector);
	}

	selectVariantSteps(tour) {
		const variants = tour?.variants || {};

		for (const variant of Object.values(variants)) {
			const mediaQuery = typeof variant?.media_query === 'string' ? variant.media_query : '(min-width: 0px)';

			if (!window.matchMedia(mediaQuery).matches) {
				continue;
			}

			const steps = Array.isArray(variant.steps) ? variant.steps : [];

			if (steps.length > 0) {
				return steps;
			}
		}

		return [];
	}

	isTourDomReady(tour, { modalId = null } = {}) {
		const steps = this.selectVariantSteps(tour);

		if (steps.length === 0) {
			return false;
		}

		const effectiveModalId = modalId || this.getTourModalId(tour);
		const modalScopeRoot = effectiveModalId ? this.resolveModalScopeRoot(effectiveModalId) : null;

		if (effectiveModalId && !modalScopeRoot) {
			return false;
		}

		return steps.every((step) => {
			const target = typeof step?.target === 'string' ? step.target.trim() : '';

			if (target === '') {
				return true;
			}

			const selector = this.resolveTargetReference(step?.target)?.selector || null;

			if (!selector) {
				return false;
			}

			if (modalScopeRoot && modalScopeRoot.querySelector(selector) !== null) {
				return true;
			}

			return document.querySelector(selector) !== null;
		});
	}

	isTourEligible(tour, { modalId = null } = {}) {
		if (tour.authenticated && !this.config.authenticated) {
			return false;
		}

		if (tour.admin_only && !this.config.is_admin) {
			return false;
		}

		if (!routeMatches(this.config.current_route || null, tour.routes || [])) {
			return false;
		}

		const tourModalId = this.getTourModalId(tour);

		if (tourModalId === null) {
			return modalId === null;
		}

		if (modalId !== null) {
			return tourModalId === modalId;
		}

		return this.getOpenModalIds().has(tourModalId);
	}

	start(tourId, { force = false, modalId = null } = {}) {
		const tours = this.config.tours || {};
		const tour = tours[tourId];

		if (!tour || typeof tour !== 'object') {
			return;
		}

		if (!this.isTourEligible(tour, { modalId })) {
			return;
		}

		if (!this.isTourDomReady(tour, { modalId })) {
			return;
		}

		const effectiveModalId = modalId || this.getTourModalId(tour);

		const mode = getTriggerMode(tour);
		const alwaysVisibleInfoIconMode = mode === 'info-icon-always';

		if (!force && !alwaysVisibleInfoIconMode && this.isOptedOut(tourId, tour)) {
			return;
		}

		if (this.runner?.active) {
			return;
		}

		this.runner = new FeatureTourRunner({
			tourId,
			tour,
			isAdmin: Boolean(this.config.is_admin),
			anchors: this.config.anchors || {},
			actions: this.config.actions || {},
			targetScopeSelector: this.resolveModalScopeSelector(effectiveModalId),
			queuePosition: this.queueActive && this.queueIndex >= 0 ? this.queueIndex + 1 : null,
			queueTotal: this.queueActive ? this.queue.length : null,
			state: {
				hasCompleted: () => this.hasCompleted(tourId, tour),
				markCompleted: () => this.markCompleted(tourId, tour),
				onFinish: ({ markComplete }) => {
					if (markComplete && this.queueActive && this.queueStartedForRoute === (this.config.current_route || null)) {
						this.advanceQueue({ force: false });
					}
				},
			},
		});

		this.clearPromptDismissed(tourId, tour);
		if (force) {
			this.clearOptedOut(tourId, tour);
		}
		void this.runner.start({ force: force || alwaysVisibleInfoIconMode });
	}

	bindButtonTriggers(eligibleTours) {
		for (const binding of this.buttonBindings) {
			document.removeEventListener(binding.event, binding.handler, true);
		}

		this.buttonBindings = [];

		for (const { tourId, tour } of eligibleTours) {
			if (getTriggerMode(tour) !== 'button') {
				continue;
			}

			const target = tour?.trigger?.button?.target;
			const selector = this.resolveSelector(target);

			if (!selector) {
				continue;
			}

			const eventName = typeof tour?.trigger?.button?.event === 'string' && tour.trigger.button.event.trim() !== ''
				? tour.trigger.button.event.trim()
				: 'click';

			const handler = (event) => {
				const hit = event.target instanceof Element ? event.target.closest(selector) : null;

				if (!hit) {
					return;
				}

				this.start(tourId);
			};

			document.addEventListener(eventName, handler, true);
			this.buttonBindings.push({ event: eventName, handler });
		}
	}

	bindDataAttributeTrigger() {
		if (this.dataAttributeTriggerBound) {
			return;
		}

		document.addEventListener('click', (event) => {
			const firstLauncherTrigger = event.target instanceof Element
				? event.target.closest('[data-feature-tour-launch], [data-feature-tour-start-first-prompt]')
				: null;

			if (firstLauncherTrigger) {
				const force = firstLauncherTrigger.getAttribute('data-feature-tour-force') === '1';
				const modalId = (firstLauncherTrigger.getAttribute('data-feature-tour-modal-launch') || '').trim() || null;

				if (!this.startFirstInfoIconEligible({ force, modalId })) {
					this.startFirstPromptEligible({ force, modalId });
				}

				return;
			}

			const trigger = event.target instanceof Element
				? event.target.closest('[data-feature-tour-start]')
				: null;

			if (!trigger) {
				return;
			}

			const tourId = (trigger.getAttribute('data-feature-tour-start') || '').trim();

			if (tourId === '') {
				return;
			}

			const force = trigger.getAttribute('data-feature-tour-force') === '1';
			this.start(tourId, { force });
		}, true);

		this.dataAttributeTriggerBound = true;

		if (!this.modalStateListenersBound) {
			document.addEventListener('modal-opened', () => {
				window.setTimeout(() => this.updateResumeTriggerVisibility(), 0);
			}, true);

			document.addEventListener('modal-closed', () => {
				window.setTimeout(() => this.updateResumeTriggerVisibility(), 0);
			}, true);

			this.modalStateListenersBound = true;
		}
	}

	async maybePromptTour(tourId, tour, { force = false, modalId = null } = {}) {
		if (!force && (this.hasCompleted(tourId, tour) || this.isPromptDismissed(tourId, tour) || this.isOptedOut(tourId, tour))) {
			return false;
		}

		const promptConfig = tour?.trigger?.prompt || {};
		const decision = await this.prompt.show({
			title: promptConfig.title || 'Take a quick tour?',
			question: promptConfig.question || 'Would you like a quick feature tour?',
			confirmLabel: promptConfig.confirm_label || 'Start tour',
			cancelLabel: promptConfig.cancel_label || 'Not now',
			optOutLabel: promptConfig.opt_out_label || 'Not interested',
		});

		if (decision === 'dismiss_prompt') {
			this.markPromptDismissed(tourId, tour);
			this.showResumeHint(tour);
			return false;
		}

		if (decision === 'opt_out') {
			this.markOptedOut(tourId, tour);
			return false;
		}

		if (decision !== 'start') {
			return false;
		}

		this.start(tourId, { force, modalId });
		return true;
	}

	startFirstPromptEligible({ force = false, modalId = null } = {}) {
		const promptTour = this.getEligiblePromptTours({ modalId })[0];

		if (!promptTour) {
			return false;
		}

		this.start(promptTour.tourId, { force, modalId });
		return true;
	}

	startFirstInfoIconEligible({ force = false, modalId = null } = {}) {
		const infoIconTour = this.getEligibleResumeIconTours({ modalId })[0];

		if (!infoIconTour) {
			return false;
		}

		this.start(infoIconTour.tourId, { force, modalId });

		return true;
	}

	startFirstEligible({ force = false } = {}) {
		const tours = this.config.tours || {};
		const eligibleTours = Object.keys(tours)
			.map((tourId) => ({
				tourId,
				tour: tours[tourId],
			}))
			.filter(({ tour }) => this.isTourEligible(tour) && this.isTourDomReady(tour))
			.sort((first, second) => {
				const firstPriority = Number(first.tour?.priority ?? 100);
				const secondPriority = Number(second.tour?.priority ?? 100);

				if (firstPriority === secondPriority) {
					return 0;
				}

				return firstPriority - secondPriority;
			});
		const immediateTours = eligibleTours.filter(({ tour }) => {
			if (this.getTourModalId(tour) !== null) {
				return false;
			}

			const mode = getTriggerMode(tour);

			return mode === 'auto' || mode === 'prompt';
		});
		const buttonTours = eligibleTours.filter(({ tour }) => getTriggerMode(tour) === 'button');
		const infoIconTours = eligibleTours.filter(({ tour }) => {
			if (this.getTourModalId(tour) !== null) {
				return false;
			}

			const mode = getTriggerMode(tour);
			return mode === 'info-icon' || mode === 'info-icon-always';
		});

		this.bindButtonTriggers(buttonTours);
		this.bindDataAttributeTrigger();
		this.updateResumeTriggerVisibility();

		if (!force && this.hasDeferredPromptTour()) {
			return;
		}

		this.beginQueue(immediateTours, { force });

		if (infoIconTours.length > 0) {
			this.updateResumeTriggerVisibility();
		}
	}
}

export function initFeatureTours() {
	const config = parseConfig();

	if (!config) {
		return;
	}

	const manager = new FeatureTourManager(config);

	window.featureTour = {
		start: (tourId, options = {}) => manager.start(tourId, options),
		replay: (tourId) => manager.start(tourId, { force: true }),
		startFirstInfoIcon: (options = {}) => manager.startFirstInfoIconEligible(options),
		startFirstPrompt: (options = {}) => manager.startFirstPromptEligible(options),
		refreshResumeTrigger: () => manager.updateResumeTriggerVisibility(),
	};

	manager.startFirstEligible();
}
