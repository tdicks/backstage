import Alpine from 'alpinejs';
import { registerSessionCards } from './components/sessionCards';
import { lazySessionSets } from './components/lazySessionSets';
import { registerApprovalsStore } from './stores/approvals';
import { copyShareLink } from './utils/clipboard';
import { focusSessionFragmentTarget } from './utils/sessionFragments';

window.Alpine = Alpine;
window.copyShareLink = copyShareLink;
window.focusSessionFragmentTarget = focusSessionFragmentTarget;

registerApprovalsStore(Alpine);
registerSessionCards(Alpine);

Alpine.data('lazySessionSets', lazySessionSets);

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();
