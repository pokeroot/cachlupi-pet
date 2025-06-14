import { showGlobalToast } from './uiUtils.js'; // Assuming showGlobalToast is preferred from uiUtils

let clientRequestsStatusInterval = null;

const updateClientRequestsTable = (statuses) => {
    if (!statuses || !Array.isArray(statuses)) {
        console.warn('updateClientRequestsTable: `statuses` is undefined or not an array. Called with:', statuses);
        return;
    }

    const followButtonText = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_follow_driver)
                           ? cachilupi_pet_vars.text_follow_driver
                           : 'Seguir Viaje';

    jQuery('.cachilupi-client-requests-panel table.widefat tbody tr').each(function() {
        const $row = jQuery(this);
        const requestId = $row.data('request-id');
        if (typeof requestId === 'undefined') {
            console.warn('updateClientRequestsTable: Row found with undefined request-id.');
            return;
        }

        const currentStatusCell = $row.find('td.request-status');
        const currentFollowButtonCell = $row.find('td[data-label="Seguimiento:"]');
        const requestUpdate = statuses.find(req => String(req.request_id) === String(requestId));

        if (requestUpdate) {
            const oldStatusDisplay = currentStatusCell.find('span').text();
            const { status_slug: newStatusSlugFromServer, status_display: newStatusDisplay, driver_id: driverIdForButton } = requestUpdate;

            if (oldStatusDisplay !== newStatusDisplay) {
                currentStatusCell.find('span').text(newStatusDisplay);
                currentStatusCell.removeClass((index, className) => {
                    return (className.match(/(^|\s)request-status-\S+/g) || []).join(' ');
                }).addClass(`request-status-${newStatusSlugFromServer}`);

                if (oldStatusDisplay && oldStatusDisplay !== '--' && oldStatusDisplay !== newStatusDisplay) {
                    showGlobalToast(`Tu solicitud #${requestId} ahora está: ${newStatusDisplay}`, 'info');
                }
            }

            let followCellHTML = '--';
            const statusSlugForSwitch = newStatusSlugFromServer;

            switch (statusSlugForSwitch) {
                case 'on_the_way':
                case 'picked_up':
                    if (driverIdForButton) {
                        const buttonText = statusSlugForSwitch === 'picked_up' ? `${followButtonText} (Mascota a Bordo)` : followButtonText;
                        followCellHTML = `<button class="button cachilupi-follow-driver-btn" data-request-id="${requestId}">${buttonText}</button>`;
                    } else {
                        followCellHTML = 'Información no disponible';
                    }
                    break;
                case 'pending':
                    followCellHTML = 'Disponible cuando se acepte el viaje';
                    break;
                case 'accepted':
                    followCellHTML = 'Disponible cuando el viaje inicie';
                    break;
                case 'arrived':
                    followCellHTML = 'Conductor en origen, esperando recogida';
                    break;
                case 'completed':
                    followCellHTML = 'Viaje finalizado';
                    break;
                case 'rejected':
                    followCellHTML = 'Viaje rechazado';
                    break;
                default:
                    followCellHTML = '--';
                    break;
            }
            currentFollowButtonCell.html(followCellHTML);
        }
    });
};

const fetchClientRequestsStatus = async () => {
    if (jQuery('.cachilupi-client-requests-panel').length === 0) {
        if (clientRequestsStatusInterval) {
            clearInterval(clientRequestsStatusInterval);
            clientRequestsStatusInterval = null;
        }
        return;
    }
    if (typeof cachilupi_pet_vars === 'undefined' || !cachilupi_pet_vars.ajaxurl || !cachilupi_pet_vars.get_requests_status_nonce) {
        console.error('Client request status: Missing required JS variables (ajaxurl or nonce).');
        return;
    }

    const url = new URL(cachilupi_pet_vars.ajaxurl);
    url.searchParams.append('action', 'cachilupi_get_client_requests_status');
    url.searchParams.append('security', cachilupi_pet_vars.get_requests_status_nonce);

    try {
        const response = await fetch(url);
        if (!response.ok) {
            let errorMsg = `Error HTTP: ${response.status}`;
            try {
                const errorText = await response.text();
                console.error('fetchClientRequestsStatus: Non-OK HTTP response text:', errorText);
                const errorData = JSON.parse(errorText); // Attempt to parse, might fail
                errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
            } catch (e) { /* Ignore parse error, use original errorMsg */ }
            throw new Error(errorMsg);
        }
        const responseData = await response.json();
        if (responseData.success && responseData.data) {
            updateClientRequestsTable(responseData.data);
        } else {
            console.warn('fetchClientRequestsStatus: Response not successful or data missing.', responseData);
        }
    } catch (error) {
        console.error('fetchClientRequestsStatus: Error during fetch:', error);
    }
};

export const initClientRequestsDisplay = () => {
    // Tab Switching Logic
    if (jQuery('.cachilupi-client-requests-panel .nav-tab-wrapper').length > 0) {
        jQuery(document).on('click', '.cachilupi-client-requests-panel .nav-tab-wrapper a.nav-tab', function(e) {
            e.preventDefault();
            const $thisClickedTab = jQuery(this);
            const $tabWrapper = $thisClickedTab.closest('.nav-tab-wrapper');
            const $panel = $thisClickedTab.closest('.cachilupi-client-requests-panel');

            $tabWrapper.find('a.nav-tab').removeClass('nav-tab-active');
            $panel.find('.tab-content').hide();
            $thisClickedTab.addClass('nav-tab-active');
            const activeContentID = $thisClickedTab.attr('href');
            if (jQuery(activeContentID).length) {
                jQuery(activeContentID).show();
            } else {
                console.error(`Client panel tab content not found for ID: ${activeContentID}`);
            }
        });
    }

    // Polling for request status
    if (jQuery('.cachilupi-client-requests-panel').length > 0) {
        fetchClientRequestsStatus(); // Initial fetch
        if (clientRequestsStatusInterval) clearInterval(clientRequestsStatusInterval); // Clear existing if any
        clientRequestsStatusInterval = setInterval(fetchClientRequestsStatus, 20000); // Poll every 20 seconds
    }
};
