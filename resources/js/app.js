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

registerApprovalsStore(Alpine);
registerNotificationsStore(Alpine);
registerSessionCards(Alpine);

Alpine.data('lazySessionSets', lazySessionSets);

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();
