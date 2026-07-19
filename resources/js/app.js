import Alpine from 'alpinejs';
import { lazySessionSets } from './components/lazySessionSets';
import { copyShareLink } from './utils/clipboard';
import { focusSessionFragmentTarget } from './utils/sessionFragments';

window.Alpine = Alpine;
window.copyShareLink = copyShareLink;
window.focusSessionFragmentTarget = focusSessionFragmentTarget;

Alpine.data('lazySessionSets', lazySessionSets);

window.addEventListener('hashchange', () => window.focusSessionFragmentTarget());

Alpine.start();
