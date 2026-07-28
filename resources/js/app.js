import Alpine from 'alpinejs';
import { registerSessionCards } from './components/sessionCards';
import { lazySessionSets } from './components/lazySessionSets';
import { registerApprovalsStore } from './stores/approvals';
import { registerNotificationsStore } from './stores/notifications';
import { copyShareLink } from './utils/clipboard';
import { isInteractiveDragSource } from './utils/drag';
import { focusSessionFragmentTarget } from './utils/sessionFragments';

window.Alpine = Alpine;
window.copyShareLink = copyShareLink;
window.isInteractiveDragSource = isInteractiveDragSource;
window.focusSessionFragmentTarget = focusSessionFragmentTarget;

function syncModalScrollLock() {
	const hasOpenModal = Array.from(document.querySelectorAll('[data-modal-overlay]')).some((overlay) => {
		return window.getComputedStyle(overlay).display !== 'none';
	});

	document.documentElement.classList.toggle('modal-scroll-locked', hasOpenModal);
	document.body.classList.toggle('modal-scroll-locked', hasOpenModal);
}

new MutationObserver(syncModalScrollLock).observe(document.body, {
	attributes: true,
	childList: true,
	subtree: true,
	attributeFilter: ['class', 'style'],
});

syncModalScrollLock();

registerApprovalsStore(Alpine);
registerNotificationsStore(Alpine);
registerSessionCards(Alpine);

Alpine.data('lazySessionSets', lazySessionSets);

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();
