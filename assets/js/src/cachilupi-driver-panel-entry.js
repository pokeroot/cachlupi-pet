import { initTabs, showDriverPanelFeedback } from './driver-panel/modules/driverPanelUI.js'; // Assuming showDriverPanelFeedback might be called directly on entry for some reason, or it's just good to have it if general feedback needed.
import { initDriverRequestActions } from './driver-panel/modules/driverRequestActions.js';
import { initNewRequestPoller } from './driver-panel/modules/newRequestPoller.js';
// driverLocationTracking is mostly used by driverRequestActions, so it might not need direct initialization here
// unless there's a reason to start/stop tracking independently of actions from the entry point.

document.addEventListener('DOMContentLoaded', () => {
    console.log('Cachilupi Driver Panel JS (Parcel) Loaded via cachilupi-driver-panel-entry.js - DOMContentLoaded');

    if (typeof cachilupi_driver_vars === 'undefined') {
        console.error('cachilupi_driver_vars is not defined. Aborting driver panel JS initialization.');
        // Display a message to the user if possible, though uiUtils might not be initialized yet.
        const feedbackContainer = document.getElementById('driver-panel-feedback');
        if (feedbackContainer) {
            feedbackContainer.innerHTML = '<div class="cachilupi-feedback cachilupi-feedback--error" role="alert" aria-live="assertive">Error de configuración: Variables del script no disponibles.</div>';
        }
        return;
    }

    initTabs();
    initDriverRequestActions();
    initNewRequestPoller();
    // Any other initializations needed for the driver panel
});
