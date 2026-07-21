export function isInteractiveDragSource(event) {
    return typeof event.composedPath === 'function'
        && event.composedPath().some((element) => element instanceof Element && element.matches('button, a, input, select, textarea, label'));
}
