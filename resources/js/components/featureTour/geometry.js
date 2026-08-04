import { clampNumber } from './utils';

export function unionRect(rects) {
	if (!Array.isArray(rects) || rects.length === 0) {
		return null;
	}

	const left = Math.min(...rects.map((rect) => rect.left));
	const top = Math.min(...rects.map((rect) => rect.top));
	const right = Math.max(...rects.map((rect) => rect.left + rect.width));
	const bottom = Math.max(...rects.map((rect) => rect.top + rect.height));

	return {
		left,
		top,
		width: Math.max(2, right - left),
		height: Math.max(2, bottom - top),
	};
}

export function drawConnectorBetweenRects({ panelRect, targetRect, line, arrow }) {
	const targetCenter = {
		x: targetRect.left + targetRect.width / 2,
		y: targetRect.top + targetRect.height / 2,
	};

	const startPoint = nearestPointOnRectEdge(panelRect, targetCenter.x, targetCenter.y);
	const endPoint = centerOfNearestRectEdge(targetRect, startPoint.x, startPoint.y);
	const deltaX = endPoint.x - startPoint.x;
	const deltaY = endPoint.y - startPoint.y;
	const angle = Math.atan2(deltaY, deltaX);
	const distance = Math.max(12, Math.hypot(deltaX, deltaY) - 10);

	applyConnectorStyles({ line, arrow, startPoint, angle, distance });
}

function applyConnectorStyles({ line, arrow, startPoint, angle, distance }) {
	if (line) {
		line.style.visibility = 'visible';
		line.style.left = `${startPoint.x}px`;
		line.style.top = `${startPoint.y}px`;
		line.style.width = `${distance}px`;
		line.style.transform = `rotate(${angle}rad)`;
	}

	if (!arrow) {
		return;
	}

	arrow.style.visibility = 'visible';
	arrow.style.left = `${startPoint.x + Math.cos(angle) * distance}px`;
	arrow.style.top = `${startPoint.y + Math.sin(angle) * distance}px`;
	arrow.style.transform = `translate(-50%, -50%) rotate(${angle}rad)`;
}

export function drawConnectorForSide({ panelRect, targetRect, side, line, arrow }) {
	const sidePairs = {
		left: { panelEdge: 'right', targetEdge: 'left' },
		right: { panelEdge: 'left', targetEdge: 'right' },
		top: { panelEdge: 'bottom', targetEdge: 'top' },
		bottom: { panelEdge: 'top', targetEdge: 'bottom' },
	};

	const pair = sidePairs[side];

	if (!pair) {
		drawConnectorBetweenRects({ panelRect, targetRect, line, arrow });
		return;
	}

	const startPoint = centerOfEdge(panelRect, pair.panelEdge);
	const endPoint = centerOfEdge(targetRect, pair.targetEdge);
	const deltaX = endPoint.x - startPoint.x;
	const deltaY = endPoint.y - startPoint.y;
	const angle = Math.atan2(deltaY, deltaX);
	const distance = Math.max(12, Math.hypot(deltaX, deltaY) - 10);

	applyConnectorStyles({ line, arrow, startPoint, angle, distance });
}

function nearestPointOnRectEdge(rect, pointX, pointY) {
	const left = rect.left;
	const right = rect.left + rect.width;
	const top = rect.top;
	const bottom = rect.top + rect.height;
	const clampedX = Math.min(right, Math.max(left, pointX));
	const clampedY = Math.min(bottom, Math.max(top, pointY));
	const distances = [
		{ x: clampedX, y: top, distance: Math.abs(pointY - top) },
		{ x: clampedX, y: bottom, distance: Math.abs(pointY - bottom) },
		{ x: left, y: clampedY, distance: Math.abs(pointX - left) },
		{ x: right, y: clampedY, distance: Math.abs(pointX - right) },
	];

	distances.sort((first, second) => first.distance - second.distance);

	return {
		x: distances[0].x,
		y: distances[0].y,
	};
}

function centerOfNearestRectEdge(rect, pointX, pointY) {
	const left = rect.left;
	const right = rect.left + rect.width;
	const top = rect.top;
	const bottom = rect.top + rect.height;
	const centerX = rect.left + rect.width / 2;
	const centerY = rect.top + rect.height / 2;
	const edges = [
		{ side: 'top', x: centerX, y: top, distance: Math.abs(pointY - top) },
		{ side: 'bottom', x: centerX, y: bottom, distance: Math.abs(pointY - bottom) },
		{ side: 'left', x: left, y: centerY, distance: Math.abs(pointX - left) },
		{ side: 'right', x: right, y: centerY, distance: Math.abs(pointX - right) },
	];

	edges.sort((first, second) => first.distance - second.distance);

	return {
		x: edges[0].x,
		y: edges[0].y,
		side: edges[0].side,
	};
}

export function axisForSide(side) {
	return side === 'left' || side === 'right' ? 'horizontal' : 'vertical';
}

export function rectsIntersect(first, second) {
	return !(
		first.left + first.width <= second.left ||
		second.left + second.width <= first.left ||
		first.top + first.height <= second.top ||
		second.top + second.height <= first.top
	);
}

export function overlapArea(first, second) {
	const overlapWidth = Math.max(
		0,
		Math.min(first.left + first.width, second.left + second.width) - Math.max(first.left, second.left)
	);
	const overlapHeight = Math.max(
		0,
		Math.min(first.top + first.height, second.top + second.height) - Math.max(first.top, second.top)
	);

	return overlapWidth * overlapHeight;
}

function centerOfEdge(rect, side) {
	if (side === 'left') {
		return { x: rect.left, y: rect.top + rect.height / 2 };
	}

	if (side === 'right') {
		return { x: rect.left + rect.width, y: rect.top + rect.height / 2 };
	}

	if (side === 'top') {
		return { x: rect.left + rect.width / 2, y: rect.top };
	}

	return { x: rect.left + rect.width / 2, y: rect.top + rect.height };
}

export function inferSideFromRects(panelRect, targetRect) {
	const panelCenterX = panelRect.left + panelRect.width / 2;
	const panelCenterY = panelRect.top + panelRect.height / 2;
	const targetCenterX = targetRect.left + targetRect.width / 2;
	const targetCenterY = targetRect.top + targetRect.height / 2;
	const deltaX = panelCenterX - targetCenterX;
	const deltaY = panelCenterY - targetCenterY;

	if (Math.abs(deltaX) >= Math.abs(deltaY)) {
		return deltaX < 0 ? 'left' : 'right';
	}

	return deltaY < 0 ? 'top' : 'bottom';
}

export function findNonOverlappingFallbackRect({ panelSize, targetRect, viewportMargin = 12, gap = 22 }) {
	const minX = viewportMargin;
	const minY = viewportMargin;
	const maxX = Math.max(minX, window.innerWidth - panelSize.width - viewportMargin);
	const maxY = Math.max(minY, window.innerHeight - panelSize.height - viewportMargin);
	const targetCenterX = targetRect.left + targetRect.width / 2;
	const targetCenterY = targetRect.top + targetRect.height / 2;
	const expandedTargetRect = {
		top: Math.max(0, targetRect.top - 10),
		left: Math.max(0, targetRect.left - 10),
		width: targetRect.width + 20,
		height: targetRect.height + 20,
	};

	const clampRect = (left, top) => ({
		left: clampNumber(left, minX, maxX),
		top: clampNumber(top, minY, maxY),
		width: panelSize.width,
		height: panelSize.height,
	});

	const candidates = [
		clampRect(targetRect.left - panelSize.width - gap, targetCenterY - panelSize.height / 2),
		clampRect(targetRect.left + targetRect.width + gap, targetCenterY - panelSize.height / 2),
		clampRect(targetCenterX - panelSize.width / 2, targetRect.top - panelSize.height - gap),
		clampRect(targetCenterX - panelSize.width / 2, targetRect.top + targetRect.height + gap),
		clampRect(minX, minY),
		clampRect(maxX, minY),
		clampRect(minX, maxY),
		clampRect(maxX, maxY),
		clampRect((window.innerWidth - panelSize.width) / 2, minY),
		clampRect((window.innerWidth - panelSize.width) / 2, maxY),
		clampRect(minX, (window.innerHeight - panelSize.height) / 2),
		clampRect(maxX, (window.innerHeight - panelSize.height) / 2),
	];

	const uniqueCandidates = [];
	const seen = new Set();

	for (const candidate of candidates) {
		const key = `${Math.round(candidate.left)}:${Math.round(candidate.top)}`;

		if (seen.has(key)) {
			continue;
		}

		seen.add(key);
		uniqueCandidates.push(candidate);
	}

	const ranked = uniqueCandidates
		.map((candidate) => {
			const overlap = overlapArea(candidate, expandedTargetRect);
			const candidateCenterX = candidate.left + candidate.width / 2;
			const candidateCenterY = candidate.top + candidate.height / 2;
			const distance = Math.hypot(candidateCenterX - targetCenterX, candidateCenterY - targetCenterY);

			return {
				candidate,
				overlap,
				distance,
			};
		})
		.sort((first, second) => {
			if (first.overlap === second.overlap) {
				return first.distance - second.distance;
			}

			return first.overlap - second.overlap;
		});

	if (ranked.length === 0 || ranked[0].overlap > 0) {
		return null;
	}

	return ranked[0].candidate;
}

export function layoutCallout({ panel, line, arrow, targetRect, panelWidth, preferredSide = null, preferredAxis = null }) {
	const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
	const viewportMargin = 12;
	const preferredGap = 34;
	const minimumGap = 22;

	panel.style.width = `${panelWidth}px`;
	panel.style.top = `${viewportMargin}px`;
	panel.style.left = `${viewportMargin}px`;
	panel.style.visibility = 'hidden';

	const measuredPanelRect = panel.getBoundingClientRect();
	const panelSize = {
		width: measuredPanelRect.width,
		height: measuredPanelRect.height,
	};

	const minX = viewportMargin;
	const minY = viewportMargin;
	const maxX = Math.max(minX, window.innerWidth - panelSize.width - viewportMargin);
	const maxY = Math.max(minY, window.innerHeight - panelSize.height - viewportMargin);
	const targetCenterX = targetRect.left + targetRect.width / 2;
	const targetCenterY = targetRect.top + targetRect.height / 2;

	const expandedTargetRect = {
		top: Math.max(0, targetRect.top - 10),
		left: Math.max(0, targetRect.left - 10),
		width: targetRect.width + 20,
		height: targetRect.height + 20,
	};

	const sidePairs = {
		left: { panelEdge: 'right', targetEdge: 'left' },
		right: { panelEdge: 'left', targetEdge: 'right' },
		top: { panelEdge: 'bottom', targetEdge: 'top' },
		bottom: { panelEdge: 'top', targetEdge: 'bottom' },
	};

	const buildCandidate = (side, gap, allowMainAxisClamp = false) => {
		let left = 0;
		let top = 0;
		let separation = 0;
		let straight = false;
		let mainAxisConstrained = false;

		if (side === 'left') {
			const rawLeft = targetRect.left - panelSize.width - gap;

			if (!allowMainAxisClamp && (rawLeft < minX || rawLeft > maxX)) {
				return null;
			}

			left = allowMainAxisClamp ? clamp(rawLeft, minX, maxX) : rawLeft;
			mainAxisConstrained = left !== rawLeft;
			top = clamp(targetCenterY - panelSize.height / 2, minY, maxY);
			separation = targetRect.left - (left + panelSize.width);
			straight = Math.abs((top + panelSize.height / 2) - targetCenterY) < 1;
		} else if (side === 'right') {
			const rawLeft = targetRect.left + targetRect.width + gap;

			if (!allowMainAxisClamp && (rawLeft < minX || rawLeft > maxX)) {
				return null;
			}

			left = allowMainAxisClamp ? clamp(rawLeft, minX, maxX) : rawLeft;
			mainAxisConstrained = left !== rawLeft;
			top = clamp(targetCenterY - panelSize.height / 2, minY, maxY);
			separation = left - (targetRect.left + targetRect.width);
			straight = Math.abs((top + panelSize.height / 2) - targetCenterY) < 1;
		} else if (side === 'top') {
			const rawTop = targetRect.top - panelSize.height - gap;

			if (!allowMainAxisClamp && (rawTop < minY || rawTop > maxY)) {
				return null;
			}

			top = allowMainAxisClamp ? clamp(rawTop, minY, maxY) : rawTop;
			mainAxisConstrained = top !== rawTop;
			left = clamp(targetCenterX - panelSize.width / 2, minX, maxX);
			separation = targetRect.top - (top + panelSize.height);
			straight = Math.abs((left + panelSize.width / 2) - targetCenterX) < 1;
		} else {
			const rawTop = targetRect.top + targetRect.height + gap;

			if (!allowMainAxisClamp && (rawTop < minY || rawTop > maxY)) {
				return null;
			}

			top = allowMainAxisClamp ? clamp(rawTop, minY, maxY) : rawTop;
			mainAxisConstrained = top !== rawTop;
			left = clamp(targetCenterX - panelSize.width / 2, minX, maxX);
			separation = top - (targetRect.top + targetRect.height);
			straight = Math.abs((left + panelSize.width / 2) - targetCenterX) < 1;
		}

		const rect = {
			top,
			left,
			width: panelSize.width,
			height: panelSize.height,
		};

		const intersectionArea = overlapArea(rect, expandedTargetRect);
		const pairs = sidePairs[side];
		const startPoint = centerOfEdge(rect, pairs.panelEdge);
		const endPoint = centerOfEdge(targetRect, pairs.targetEdge);
		const dx = endPoint.x - startPoint.x;
		const dy = endPoint.y - startPoint.y;
		const distance = Math.hypot(dx, dy);
		const axis = axisForSide(side);

		return {
			side,
			axis,
			rect,
			separation,
			straight,
			mainAxisConstrained,
			intersectionArea,
			startPoint,
			endPoint,
			distance,
			score:
				(straight ? 0 : 900) +
				(mainAxisConstrained ? 800 : 0) +
				(preferredSide && side === preferredSide ? -260 : 0) +
				(preferredAxis && axis === preferredAxis ? -90 : 0) +
				Math.abs(preferredGap - Math.max(separation, 0)) * 6 +
				distance * 0.2 +
				Math.max(0, distance - 420) * 4,
		};
	};

	const buildSet = (gap, allowMainAxisClamp) => {
		return ['left', 'right', 'top', 'bottom']
			.map((side) => buildCandidate(side, gap, allowMainAxisClamp))
			.filter((candidate) => candidate !== null);
	};

	const chooseCandidate = () => {
		const preferredStrictCandidates = buildSet(preferredGap, false);
		const strictPreferred = preferredStrictCandidates
			.filter((candidate) => candidate.intersectionArea === 0 && candidate.separation >= minimumGap)
			.sort((first, second) => first.score - second.score);

		if (strictPreferred.length > 0) {
			return strictPreferred[0];
		}

		const minimumStrictCandidates = buildSet(minimumGap, false);
		const strictMinimum = minimumStrictCandidates
			.filter((candidate) => candidate.intersectionArea === 0 && candidate.separation >= minimumGap)
			.sort((first, second) => first.score - second.score);

		if (strictMinimum.length > 0) {
			return strictMinimum[0];
		}

		const preferredClampedCandidates = buildSet(preferredGap, true);
		const minimumClampedCandidates = buildSet(minimumGap, true);

		const noOverlap = [...preferredClampedCandidates, ...minimumClampedCandidates]
			.filter((candidate) => candidate.intersectionArea === 0)
			.sort((first, second) => first.score - second.score);

		if (noOverlap.length > 0) {
			return noOverlap[0];
		}

		return [...preferredClampedCandidates, ...minimumClampedCandidates].sort((first, second) => {
			if (first.intersectionArea === second.intersectionArea) {
				return first.score - second.score;
			}

			return first.intersectionArea - second.intersectionArea;
		})[0];
	};

	const chosen = chooseCandidate();

	if (!chosen) {
		if (line) {
			line.style.opacity = '0';
			line.style.visibility = 'hidden';
		}

		if (arrow) {
			arrow.style.opacity = '0';
			arrow.style.visibility = 'hidden';
		}

		panel.style.opacity = '0';
		panel.style.transform = 'translateY(8px) scale(0.98)';
		panel.style.visibility = 'hidden';
		return null;
	}

	panel.style.top = `${chosen.rect.top}px`;
	panel.style.left = `${chosen.rect.left}px`;

	const updatedPanelRect = panel.getBoundingClientRect();
	const panelStillOverlapsTarget = rectsIntersect(
		{
			top: updatedPanelRect.top,
			left: updatedPanelRect.left,
			width: updatedPanelRect.width,
			height: updatedPanelRect.height,
		},
		expandedTargetRect
	);

	if (panelStillOverlapsTarget) {
		const fallbackRect = findNonOverlappingFallbackRect({
			panelSize: {
				width: updatedPanelRect.width,
				height: updatedPanelRect.height,
			},
			targetRect,
			viewportMargin,
			gap: minimumGap,
		});

		if (!fallbackRect) {
			if (line) {
				line.style.opacity = '0';
				line.style.visibility = 'hidden';
			}

			if (arrow) {
				arrow.style.opacity = '0';
				arrow.style.visibility = 'hidden';
			}

			panel.style.opacity = '0';
			panel.style.transform = 'translateY(8px) scale(0.98)';
			panel.style.visibility = 'hidden';
			return null;
		}

		panel.style.top = `${fallbackRect.top}px`;
		panel.style.left = `${fallbackRect.left}px`;
		const fallbackPanelRect = panel.getBoundingClientRect();
		const inferredSide = inferSideFromRects(
			{
				top: fallbackPanelRect.top,
				left: fallbackPanelRect.left,
				width: fallbackPanelRect.width,
				height: fallbackPanelRect.height,
			},
			targetRect
		);

		drawConnectorForSide({
			panelRect: {
				top: fallbackPanelRect.top,
				left: fallbackPanelRect.left,
				width: fallbackPanelRect.width,
				height: fallbackPanelRect.height,
			},
			targetRect,
			side: inferredSide,
			line,
			arrow,
		});

		panel.style.transform = 'translateY(0) scale(1)';
		panel.style.opacity = '1';
		panel.style.visibility = 'visible';

		return {
			side: inferredSide,
			axis: axisForSide(inferredSide),
			panelRect: {
				top: fallbackPanelRect.top,
				left: fallbackPanelRect.left,
				width: fallbackPanelRect.width,
				height: fallbackPanelRect.height,
			},
			targetRect,
		};
	}

	drawConnectorForSide({
		panelRect: {
			top: updatedPanelRect.top,
			left: updatedPanelRect.left,
			width: updatedPanelRect.width,
			height: updatedPanelRect.height,
		},
		targetRect,
		side: chosen.side,
		line,
		arrow,
	});
	panel.style.transform = 'translateY(0) scale(1)';
	panel.style.opacity = '1';
	panel.style.visibility = 'visible';

	return {
		side: chosen.side,
		axis: chosen.axis,
		panelRect: {
			top: updatedPanelRect.top,
			left: updatedPanelRect.left,
			width: updatedPanelRect.width,
			height: updatedPanelRect.height,
		},
		targetRect,
	};
}
