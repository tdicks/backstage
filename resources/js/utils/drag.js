export function isInteractiveDragSource(event) {
    return event.composedPath?.()
        ?.some((element) => element instanceof Element && element.matches('button, a, input, select, textarea, label')) ?? false;
}
