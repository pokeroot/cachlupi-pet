import { showToast } from './uiUtils.js';

let clientRequestsStatusInterval = null;

const updateClientRequestsTable = (statuses) => {
    if (!statuses || !Array.isArray(statuses)) {
        console.warn('updateClientRequestsTable: `statuses` is undefined or not an array. Called with:', statuses);
        return;
    }

    const followButtonText = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_follow_driver)
                           ? cachilupi_pet_vars.text_follow_driver
                           : 'Seguir Viaje';

    document.querySelectorAll('.cachilupi-client-requests-panel table.widefat tbody tr').forEach(rowEl => {
        const requestId = rowEl.dataset.requestId;
        if (typeof requestId === 'undefined') {
            console.warn('updateClientRequestsTable: Row found with undefined request-id.');
            return; // Skip this row
        }

        const currentStatusCell = rowEl.querySelector('td.request-status');
        const currentFollowButtonCell = rowEl.querySelector('td[data-label="Seguimiento:"]'); // Corrected selector
        const requestUpdate = statuses.find(req => String(req.request_id) === String(requestId));

        if (requestUpdate && currentStatusCell && currentFollowButtonCell) {
            const statusSpan = currentStatusCell.querySelector('span');
            const oldStatusDisplay = statusSpan ? statusSpan.textContent : '';
            const { status_slug: newStatusSlugFromServer, status_display: newStatusDisplay, driver_id: driverIdForButton } = requestUpdate;

            if (statusSpan && oldStatusDisplay !== newStatusDisplay) {
                statusSpan.textContent = newStatusDisplay;
                // Remove old status class and add new one
                const classPrefix = 'request-status-';
                currentStatusCell.classList.forEach(cls => {
                    if (cls.startsWith(classPrefix)) {
                        currentStatusCell.classList.remove(cls);
                    }
                });
                currentStatusCell.classList.add(`${classPrefix}${newStatusSlugFromServer}`);

                if (oldStatusDisplay && oldStatusDisplay !== '--' && oldStatusDisplay !== newStatusDisplay) {
                    showToast(`Tu solicitud #${requestId} ahora está: ${newStatusDisplay}`, 'info');
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
                    followCellHTML = '--'; // Default case
                    break;
            }
            currentFollowButtonCell.innerHTML = followCellHTML;
        }
    });
};

const fetchClientRequestsStatus = async () => {
    if (document.querySelector('.cachilupi-client-requests-panel') === null) {
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
    const panel = document.querySelector('.cachilupi-client-requests-panel');
    if (!panel) return;

    const tabWrapper = panel.querySelector('.nav-tab-wrapper');

    if (tabWrapper) {
        tabWrapper.addEventListener('click', (event) => {
            const clickedTab = event.target.closest('a.nav-tab');
            if (!clickedTab || !tabWrapper.contains(clickedTab)) return; // Ensure click is on a tab within this wrapper

            event.preventDefault();

            tabWrapper.querySelectorAll('a.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
            panel.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');

            clickedTab.classList.add('nav-tab-active');
            const activeContentID = clickedTab.getAttribute('href');
            if (activeContentID && activeContentID.startsWith('#')) {
                const activeContentElement = panel.querySelector(activeContentID); // Query within the panel
                if (activeContentElement) {
                    activeContentElement.style.display = 'block';
                } else {
                    console.error(`Client panel tab content not found for ID: ${activeContentID}`);
                }
            } else {
                 console.error(`Invalid href for tab: ${activeContentID}`);
            }
        });
    }

    // Polling for request status
    fetchClientRequestsStatus(); // Initial fetch
    if (clientRequestsStatusInterval) clearInterval(clientRequestsStatusInterval);
    clientRequestsStatusInterval = setInterval(fetchClientRequestsStatus, 20000);
};
