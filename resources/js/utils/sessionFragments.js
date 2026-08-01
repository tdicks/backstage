let focusingFragment = false;

function highlightAndScrollTarget(target) {
	window.setTimeout(() => {
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		target.classList.remove('session-fragment-highlight');
		void target.offsetWidth;
		target.classList.add('session-fragment-highlight');
	}, 50);
}

async function openSetCardIfNeeded(setCard) {
	if (!setCard || !window.Alpine) {
		return;
	}

	const setCardData = window.Alpine.$data(setCard);
	if (!setCardData) {
		return;
	}

	setCardData.setCollapsed = false;

	if (typeof setCardData.loadSetBody === 'function') {
		await setCardData.loadSetBody(setCard);
	}
}

export async function focusSessionFragmentTarget() {
	const targetId = window.location.hash.slice(1);

	if (!targetId || focusingFragment) {
		return;
	}

	focusingFragment = true;

	try {
		let target = document.getElementById(targetId);

		if (target) {
			const setCard = target.closest('[data-session-set-card]');
			const songCard = target.closest('[data-session-song-card]');

			if (setCard && window.Alpine) {
				const setCardData = window.Alpine.$data(setCard);
				if (setCardData) {
					setCardData.setCollapsed = false;
					if (!setCardData.contentLoaded && typeof setCardData.loadSetBody === 'function') {
						await setCardData.loadSetBody(setCard);
						target = document.getElementById(targetId) || target;
					}
				}
			}

			if (songCard && window.Alpine) {
				const songCardData = window.Alpine.$data(songCard);
				if (songCardData) {
					songCardData.songCollapsed = false;
				}
			}

			highlightAndScrollTarget(target);
			return;
		}

		if (targetId.startsWith('set-')) {
			const setCard = document.getElementById(targetId);
			await openSetCardIfNeeded(setCard);
			const refreshedTarget = document.getElementById(targetId);
			if (refreshedTarget) {
				highlightAndScrollTarget(refreshedTarget);
			}
			return;
		}

		if (!targetId.startsWith('song-') && !targetId.startsWith('slot-')) {
			return;
		}

		const setCards = Array.from(document.querySelectorAll('[data-session-set-card]'));
		for (const setCard of setCards) {
			await openSetCardIfNeeded(setCard);
			const refreshedTarget = document.getElementById(targetId);
			if (refreshedTarget) {
				const songCard = refreshedTarget.closest('[data-session-song-card]');
				if (songCard && window.Alpine) {
					const songCardData = window.Alpine.$data(songCard);
					if (songCardData) {
						songCardData.songCollapsed = false;
					}
				}

				highlightAndScrollTarget(refreshedTarget);
				break;
			}
		}
	} finally {
		focusingFragment = false;
	}
}
