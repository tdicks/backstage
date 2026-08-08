import { createDashboardAttachmentState } from './widgetState/dashboardAttachmentState';
import { createDashboardLiveState } from './widgetState/dashboardLiveState';
import { createDashboardWidgetLayoutState } from './widgetState/dashboardWidgetLayoutState';

export function dashboardPage(config = {}) {
return {
currentUserId: Number(config.currentUserId || 0),
...createDashboardWidgetLayoutState(config),
...createDashboardAttachmentState(config),
...createDashboardLiveState(config),
init() {
this.initWidgetLayoutState();
this.initLiveDashboardState();

window.requestAnimationFrame(() => {
this.layoutReady = true;
});

window.addEventListener('beforeunload', () => {
this.disposeWidgetLayoutState();
this.disposeLiveDashboardState();
}, { once: true });
},
};
}

// Temporary alias while Blade x-data still references the preview name.
export function dashboardLayoutPreviewPage(config = {}) {
return dashboardPage(config);
}
