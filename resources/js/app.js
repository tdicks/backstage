import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';
import { jamStandardsCatalog } from './components/jamStandardsCatalog';
import { registerSessionCards } from './components/sessionCards';
import { slotFinderSongCard, slotFinderSlotCard } from './components/slotFinder';
import { lazySessionSets } from './components/lazySessionSets';
import { getStartedQuest } from './components/getStartedQuest';
import { dashboardActionQueues } from './components/dashboardActionQueues';
import { dashboardGridstackPersistence, initDashboardGridstackPage } from './components/dashboardGridstackPage';
import { liveQuickSet } from './components/liveQuickSet';
import { mySetsLibrary } from './components/mySetsLibrary';
import { plannedSetsPage } from './components/plannedSets';
import { practicePlanPage } from './components/practicePlanPage';
import { adminManualSlotTransfer } from './components/adminManualSlotTransfer';
import { recycleBinPage } from './components/recycleBin';
import { adminNotices } from './components/adminNotices';
import { initFeatureTours } from './components/featureTour';
import { registerApprovalsStore } from './stores/approvals';
import { registerNotificationsStore } from './stores/notifications';
import { registerRecycleBinStore } from './stores/recycleBin';
import { copyShareLink } from './utils/clipboard';
import { isInteractiveDragSource } from './utils/drag';
import { focusSessionFragmentTarget } from './utils/sessionFragments';
import { setDuration } from './utils/setDuration';

window.Alpine = Alpine;
window.copyShareLink = copyShareLink;
window.isInteractiveDragSource = isInteractiveDragSource;
window.focusSessionFragmentTarget = focusSessionFragmentTarget;
window.setDuration = setDuration;

Alpine.plugin(sort);

function readMetaCount(metaName) {
	return Math.max(0, Number(document.querySelector(`meta[name="${metaName}"]`)?.content || 0));
}

function syncDocumentTitle(pendingTotal = null) {
	const appName = document.querySelector('meta[name="backstage-app-name"]')?.content?.trim() || 'Backstage';
	const pageName = document.querySelector('meta[name="backstage-page-name"]')?.content?.trim() || appName;
	const isAuthenticated = document.querySelector('meta[name="backstage-authenticated"]')?.content === '1';
	const metaPendingTotal = readMetaCount('backstage-pending-total');
	const total = Number.isFinite(pendingTotal) ? Math.max(0, Number(pendingTotal)) : metaPendingTotal;
	const prefix = isAuthenticated && total > 0 ? `(${total}) ` : '';
	const sameName = pageName.localeCompare(appName, undefined, { sensitivity: 'accent' }) === 0;

	document.title = sameName ? `${prefix}${appName}` : `${prefix}${pageName} | ${appName}`;
}

function syncPendingTitleFromStores() {
	const unreadCount = Number(Alpine.store('notifications')?.unreadCount ?? readMetaCount('backstage-unread-count'));
	const approvalCount = Number(Alpine.store('approvals')?.count ?? readMetaCount('backstage-approval-count'));
	const pendingTotal = Math.max(0, unreadCount) + Math.max(0, approvalCount);

	document.querySelector('meta[name="backstage-unread-count"]')?.setAttribute('content', String(Math.max(0, unreadCount)));
	document.querySelector('meta[name="backstage-approval-count"]')?.setAttribute('content', String(Math.max(0, approvalCount)));
	document.querySelector('meta[name="backstage-pending-total"]')?.setAttribute('content', String(pendingTotal));

	syncDocumentTitle(pendingTotal);
}

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
registerRecycleBinStore(Alpine);
registerSessionCards(Alpine);

Alpine.data('lazySessionSets', lazySessionSets);
Alpine.data('getStartedQuest', getStartedQuest);
Alpine.data('dashboardActionQueues', dashboardActionQueues);
Alpine.data('dashboardGridstackPersistence', dashboardGridstackPersistence);
Alpine.data('jamStandardsCatalog', jamStandardsCatalog);
Alpine.data('liveQuickSet', liveQuickSet);
Alpine.data('mySetsLibrary', mySetsLibrary);
Alpine.data('plannedSetsPage', plannedSetsPage);
Alpine.data('practicePlanPage', practicePlanPage);
Alpine.data('slotFinderSongCard', slotFinderSongCard);
Alpine.data('slotFinderSlotCard', slotFinderSlotCard);
Alpine.data('adminManualSlotTransfer', adminManualSlotTransfer);
Alpine.data('recycleBinPage', recycleBinPage);
Alpine.data('adminNotices', adminNotices);

window.addEventListener('notifications-updated', () => {
	syncPendingTitleFromStores();
});

window.addEventListener('approvals-count-changed', () => {
	syncPendingTitleFromStores();
});

window.addEventListener('approvals-count-refreshed', () => {
	syncPendingTitleFromStores();
});

window.addEventListener('target-consent-processed', () => {
	window.setTimeout(() => syncPendingTitleFromStores(), 0);
});

window.addEventListener('pending-approval-processed', () => {
	window.setTimeout(() => syncPendingTitleFromStores(), 0);
});

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();

initDashboardGridstackPage();

syncPendingTitleFromStores();
void initFeatureTours();
