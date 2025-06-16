import { showNewRequestNotification } from './driverPanelUI.js';
// Assumes jQuery for DOM checking and cachilupi_driver_vars globally

const POLLING_INTERVAL = 20000; // 20 seconds
let requestPollingIntervalId = null;
// let lastKnownRequestTimestamp = 0; // If we want to use timestamp based polling

const checkNewRequests = async () => {
    if (typeof cachilupi_driver_vars === 'undefined' || !cachilupi_driver_vars.ajaxurl || !cachilupi_driver_vars.check_new_requests_nonce) {
        console.error('New request poller: Missing required JS variables (ajaxurl or nonce).');
        if (requestPollingIntervalId) {
            clearInterval(requestPollingIntervalId); // Stop polling if config is missing
        }
        return;
    }

    const formData = new FormData();
    formData.append('action', 'cachilupi_check_new_requests');
    formData.append('security', cachilupi_driver_vars.check_new_requests_nonce);
    // formData.append('last_checked_timestamp', lastKnownRequestTimestamp);

    try {
        const response = await fetch(cachilupi_driver_vars.ajaxurl, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            console.warn(`checkNewRequests: Network response was not ok: ${response.statusText}`);
            return;
        }

        const responseData = await response.json();

        if (responseData.success && responseData.data && responseData.data.new_requests_count > 0) {
            showNewRequestNotification(responseData.data.new_requests_count);
            // lastKnownRequestTimestamp = responseData.data.latest_request_timestamp; // Update if using timestamp
        } else if (!responseData.success) {
            console.warn(`checkNewRequests: Server error - ${responseData.data ? responseData.data.message : 'Unknown error'}`);
        }
    } catch (error) {
        console.error('checkNewRequests: Fetch Request Failed:', error);
    }
};

export const initNewRequestPoller = () => {
    // Start polling if the driver panel main table is visible
    if (document.querySelector('table.widefat') !== null) { // Simple check, assumes table is always there on this panel
        if (requestPollingIntervalId) {
            clearInterval(requestPollingIntervalId); // Clear any existing interval
        }
        setTimeout(checkNewRequests, 2000); // Initial check soon after page load
        requestPollingIntervalId = setInterval(checkNewRequests, POLLING_INTERVAL);
    }
};

export const stopNewRequestPoller = () => {
    if (requestPollingIntervalId) {
        clearInterval(requestPollingIntervalId);
        requestPollingIntervalId = null;
        console.log('New request poller stopped.');
    }
};
