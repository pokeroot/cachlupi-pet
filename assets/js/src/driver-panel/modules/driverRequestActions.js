import { showDriverPanelFeedback } from './driverPanelUI.js';
import { startLocationTracking, stopLocationTracking, getCurrentTrackingRequestId } from './driverLocationTracking.js';

// Assumes cachilupi_driver_vars is available globally

export const initDriverRequestActions = () => {
    if (typeof cachilupi_driver_vars === 'undefined' || !cachilupi_driver_vars.ajaxurl || !cachilupi_driver_vars.driver_action_nonce) {
        console.error('Driver actions: Missing required JS variables (ajaxurl or nonce).');
        return;
    }

    document.addEventListener('click', async (event) => {
        const clickedButton = event.target.closest('.button[data-request-id][data-action]');
        if (!clickedButton) return;

        event.preventDefault();

        const requestId = clickedButton.dataset.requestId;
        const action = clickedButton.dataset.action;
        const row = clickedButton.closest('tr');

        if (!action) {
            console.error('Button is missing data-action attribute or it is empty.');
            return;
        }
        // Ensure requestId is an integer if it's used in comparisons like getCurrentTrackingRequestId() === requestId
        const numericRequestId = parseInt(requestId, 10);


        console.log(`Handling action for Request ID: ${numericRequestId}, Action: ${action}`);

        const actionButtonsInRow = row.querySelectorAll('.button[data-action]');
        actionButtonsInRow.forEach(btn => btn.disabled = true);

        const originalButtonText = clickedButton.textContent;
        clickedButton.classList.add('cachilupi-button--loading');
        clickedButton.textContent = 'Procesando...';

        const formData = new FormData();
        formData.append('action', 'cachilupi_pet_driver_action');
        formData.append('security', cachilupi_driver_vars.driver_action_nonce);
        formData.append('request_id', numericRequestId);
        formData.append('driver_action', action);

        try {
            const response = await fetch(cachilupi_driver_vars.ajaxurl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                let errorData = null;
                try { errorData = await response.json(); } catch (ex) { /* ignore */ }
                const serverMessage = errorData?.data?.message || response.statusText;
                throw new Error(`Network response was not ok: ${serverMessage}`);
            }

            const responseData = await response.json();
            console.log('Fetch Success:', responseData);

            if (responseData.success) {
                const statusElement = row.querySelector('.request-status');
                if (statusElement && responseData.data && responseData.data.new_status_display) {
                    statusElement.textContent = responseData.data.new_status_display;
                }

                actionButtonsInRow.forEach(btn => btn.style.display = 'none'); // Hide all action buttons first

                // Show appropriate buttons based on new state
                const showButton = (actionName) => {
                    const btnToShow = row.querySelector(`.button[data-action="${actionName}"]`);
                    if (btnToShow) btnToShow.style.display = ''; // Reset to default display (inline-block or block)
                };

                if (action === 'accept') {
                    showButton('on_the_way');
                    showDriverPanelFeedback('Solicitud aceptada.', 'success');
                } else if (action === 'reject') {
                    // FadeOut and remove simulation
                    row.style.transition = 'opacity 0.5s ease-out';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                    showDriverPanelFeedback('Solicitud rechazada.', 'success');
                } else if (action === 'on_the_way') {
                    showButton('arrive');
                    showDriverPanelFeedback('Viaje iniciado.', 'success');
                    startLocationTracking(numericRequestId);
                } else if (action === 'arrive') {
                    showButton('picked_up');
                    // showButton('complete'); // Assuming complete is only after picked_up
                    showDriverPanelFeedback('Llegada confirmada.', 'success');
                    stopLocationTracking();
                } else if (action === 'picked_up') {
                    showButton('complete');
                    showDriverPanelFeedback('Mascota recogida.', 'success');
                    startLocationTracking(numericRequestId);
                } else if (action === 'complete') {
                    showDriverPanelFeedback('Servicio completado.', 'success');
                    stopLocationTracking();
                    // Optionally fade out/remove completed row after a delay
                    // setTimeout(() => {
                    //     row.style.transition = 'opacity 0.5s ease-out';
                    //     row.style.opacity = '0';
                    //     setTimeout(() => row.remove(), 500);
                    // }, 3000);
                }

                // If a request being tracked was rejected, stop tracking
                if (action === 'reject' && getCurrentTrackingRequestId() === numericRequestId) {
                    stopLocationTracking();
                }

                // Re-enable only the currently visible action button(s)
                row.querySelectorAll('.button[data-action]').forEach(btn => {
                    if (btn.style.display !== 'none') {
                        btn.disabled = false;
                    }
                });

            } else {
                const errorMessage = responseData.data?.message || 'Ocurrió un error al procesar la acción.';
                showDriverPanelFeedback(`Error: ${errorMessage}`, 'error');
                actionButtonsInRow.forEach(btn => btn.disabled = false); // Re-enable all on error if action failed
            }
        } catch (error) {
            console.error('Fetch Request Failed:', error);
            showDriverPanelFeedback(`Fallo en la comunicación con el servidor: ${error.message}`, 'error');
            actionButtonsInRow.forEach(btn => btn.disabled = false); // Re-enable all on fetch error
        } finally {
            clickedButton.classList.remove('cachilupi-button--loading');
            clickedButton.textContent = originalButtonText;
            // Final check on button states based on visibility
            row.querySelectorAll('.button[data-action]').forEach(btn => {
                 if (btn.style.display === 'none') {
                    btn.disabled = true; // Ensure hidden buttons are disabled
                 } else if (clickedButton !== btn) { // If it's not the main action button that was just processed
                    btn.disabled = false; // Ensure other visible buttons are enabled
                 } else if (clickedButton === btn && btn.style.display !== 'none') { // If it IS the main button and it's visible
                    btn.disabled = false;
                 }
            });
        }
    });
};
