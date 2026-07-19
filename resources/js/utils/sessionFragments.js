export function focusSessionFragmentTarget() {
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
}
