import { INLINE_ICON_TOKENS } from './icons';

export function setRichTextWithIcons(element, text) {
	element.replaceChildren();

	if (typeof text !== 'string' || text === '') {
		return;
	}

	const tokenPattern = /\[icon:([a-z0-9-]+)\]/gi;
	let cursor = 0;
	let match;

	while ((match = tokenPattern.exec(text)) !== null) {
		const tokenStart = match.index;
		const tokenEnd = tokenPattern.lastIndex;

		if (tokenStart > cursor) {
			element.appendChild(document.createTextNode(text.slice(cursor, tokenStart)));
		}

		const iconName = (match[1] || '').toLowerCase();
		const icon = INLINE_ICON_TOKENS[iconName];

		if (!icon) {
			element.appendChild(document.createTextNode(text.slice(tokenStart, tokenEnd)));
		} else {
			try {
				element.appendChild(createInlineIconElement(icon));
			} catch {
				element.appendChild(document.createTextNode(text.slice(tokenStart, tokenEnd)));
			}
		}

		cursor = tokenEnd;
	}

	if (cursor < text.length) {
		element.appendChild(document.createTextNode(text.slice(cursor)));
	}
}

function createInlineIconElement(icon) {
	const iconWrap = document.createElement('span');
	iconWrap.className = 'feature-tour-inline-icon';
	iconWrap.setAttribute('role', 'img');
	iconWrap.setAttribute('aria-label', icon.label);
	iconWrap.setAttribute('title', icon.label);

	const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	svg.setAttribute('viewBox', '0 0 20 20');
	svg.setAttribute('fill', 'currentColor');
	svg.setAttribute('width', '1em');
	svg.setAttribute('height', '1em');
	svg.setAttribute('aria-hidden', 'true');
	svg.setAttribute('data-slot', 'icon');
	svg.setAttribute('class', icon.className);

	for (const pathConfig of icon.paths) {
		const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		path.setAttribute('d', pathConfig.d);

		if (pathConfig.fillRule) {
			path.setAttribute('fill-rule', pathConfig.fillRule);
		}

		if (pathConfig.clipRule) {
			path.setAttribute('clip-rule', pathConfig.clipRule);
		}

		svg.appendChild(path);
	}

	iconWrap.appendChild(svg);
	return iconWrap;
}
