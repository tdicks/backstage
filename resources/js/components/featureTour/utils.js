import { SUPPORTED_TRIGGER_MODES, SUPPORTED_ANCHOR_VIEWS } from './constants';

export function wait(ms) {
	return new Promise((resolve) => window.setTimeout(resolve, ms));
}

export function clampNumber(value, min, max) {
	return Math.min(max, Math.max(min, value));
}

export function paddedViewportRect(rect, { padding = 6, margin = 8 } = {}) {
	const rawLeft = rect.left - padding;
	const rawTop = rect.top - padding;
	const rawWidth = rect.width + padding * 2;
	const rawHeight = rect.height + padding * 2;

	const maxWidth = Math.max(2, window.innerWidth - margin * 2);
	const maxHeight = Math.max(2, window.innerHeight - margin * 2);
	const width = Math.max(2, Math.min(rawWidth, maxWidth));
	const height = Math.max(2, Math.min(rawHeight, maxHeight));
	const left = clampNumber(rawLeft, margin, Math.max(margin, window.innerWidth - margin - width));
	const top = clampNumber(rawTop, margin, Math.max(margin, window.innerHeight - margin - height));

	return {
		top,
		left,
		width,
		height,
	};
}

export function isVisible(element) {
	if (!element) {
		return false;
	}

	const styles = window.getComputedStyle(element);

	if (styles.display === 'none' || styles.visibility === 'hidden' || styles.opacity === '0') {
		return false;
	}

	const rect = element.getBoundingClientRect();
	return rect.width > 0 && rect.height > 0;
}

export function isDisplayed(element) {
	if (!element) {
		return false;
	}

	const styles = window.getComputedStyle(element);

	if (styles.display === 'none' || styles.visibility === 'hidden') {
		return false;
	}

	const rect = element.getBoundingClientRect();
	return rect.width > 0 && rect.height > 0;
}

export function parseConfig() {
	const configEl = document.getElementById('feature-tour-config');

	if (!configEl) {
		return null;
	}

	try {
		const parsed = JSON.parse(configEl.textContent || '{}');
		return parsed && typeof parsed === 'object' ? parsed : null;
	} catch {
		return null;
	}
}

export function wildcardToRegExp(pattern) {
	const escaped = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
	return new RegExp(`^${escaped}$`);
}

export function routeMatches(routeName, patterns) {
	if (!Array.isArray(patterns) || patterns.length === 0) {
		return true;
	}

	if (!routeName) {
		return false;
	}

	return patterns.some((pattern) => {
		if (typeof pattern !== 'string' || pattern.trim() === '') {
			return false;
		}

		return wildcardToRegExp(pattern).test(routeName);
	});
}

export function getTriggerMode(tour) {
	const mode = typeof tour?.trigger?.mode === 'string' ? tour.trigger.mode : 'auto';

	if (!SUPPORTED_TRIGGER_MODES.includes(mode)) {
		return 'auto';
	}

	return mode;
}

export function shouldShowInfoIconForTour(tour) {
	return tour?.trigger?.show_info_icon === true;
}

export function normalizeAnchorView(view) {
	if (typeof view !== 'string') {
		return 'individual';
	}

	const normalized = view.trim().toLowerCase();

	if (!SUPPORTED_ANCHOR_VIEWS.includes(normalized)) {
		return 'individual';
	}

	return normalized;
}

export function resolveAnchorReference(anchors, anchorOrSelector) {
	if (typeof anchorOrSelector !== 'string' || anchorOrSelector.trim() === '') {
		return null;
	}

	if (anchorOrSelector.startsWith('[') || anchorOrSelector.startsWith('.') || anchorOrSelector.startsWith('#')) {
		return {
			selector: anchorOrSelector,
			view: 'individual',
		};
	}

	const anchor = anchors?.[anchorOrSelector];

	if (typeof anchor === 'string') {
		return {
			selector: anchor,
			view: 'individual',
		};
	}

	if (!anchor || typeof anchor !== 'object' || typeof anchor.selector !== 'string') {
		return null;
	}

	return {
		selector: anchor.selector,
		view: normalizeAnchorView(anchor.view),
	};
}

export function escapeForAttributeSelector(value) {
	if (typeof value !== 'string') {
		return '';
	}

	if (window.CSS && typeof window.CSS.escape === 'function') {
		return window.CSS.escape(value);
	}

	return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}
