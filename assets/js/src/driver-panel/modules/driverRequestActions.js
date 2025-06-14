import { showDriverPanelFeedback } from './driverPanelUI.js';
import { startLocationTracking, stopLocationTracking, getCurrentTrackingRequestId } from './driverLocationTracking.js';
// Assumes jQuery is available globally
// Assumes cachilupi_driver_vars is available globally

export const initDriverRequestActions = () => {
    if (typeof cachilupi_driver_vars === 'undefined' || !cachilupi_driver_vars.ajaxurl || !cachilupi_driver_vars.driver_action_nonce) {
        console.error('Driver actions: Missing required JS variables (ajaxurl or nonce).');
        return;
    }

    jQuery(document).on('click', '.button[data-request-id][data-action]', async (e) => {
        e.preventDefault();

        const $button = jQuery(e.currentTarget);
        const requestId = $button.data('request-id');
        const action = $button.data('action');
        const $row = $button.closest('tr');

        if (!action) {
            console.error('Button is missing data-action attribute or it is empty.');
            return;
        }

        console.log(`Handling action for Request ID: ${requestId}, Action: ${action}`);

        const $actionButtonsInRow = $row.find('.button[data-action]');
        $actionButtonsInRow.prop('disabled', true);

        const originalButtonText = $button.text();
        $button.addClass('cachilupi-button--loading').text('Procesando...');

        const formData = new FormData();
        formData.append('action', 'cachilupi_pet_driver_action');
        formData.append('security', cachilupi_driver_vars.driver_action_nonce);
        formData.append('request_id', requestId);
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
                if (responseData.data && responseData.data.new_status_display) {
                    $row.find('.request-status').text(responseData.data.new_status_display);
                }

                $row.find('.button[data-action]').hide(); // Hide all action buttons first

                // Show appropriate buttons based on new state
                if (action === 'accept') {
                    $row.find('.button[data-action="on_the_way"]').show();
                    showDriverPanelFeedback('Solicitud aceptada.', 'success');
                } else if (action === 'reject') {
                    $row.fadeOut('slow', () => { $row.remove(); });
                    showDriverPanelFeedback('Solicitud rechazada.', 'success');
                } else if (action === 'on_the_way') {
                    $row.find('.button[data-action="arrive"]').show();
                    showDriverPanelFeedback('Viaje iniciado.', 'success');
                    startLocationTracking(requestId);
                } else if (action === 'arrive') {
                    $row.find('.button[data-action="picked_up"]').show();
                    // $row.find('.button[data-action="complete"]').show(); // Assuming complete is only after picked_up
                    showDriverPanelFeedback('Llegada confirmada.', 'success');
                    stopLocationTracking(); // Stop tracking on arrival, resume if/when picked_up or if tracking is meant to be continuous
                } else if (action === 'picked_up') {
                    $row.find('.button[data-action="complete"]').show();
                    showDriverPanelFeedback('Mascota recogida.', 'success');
                    startLocationTracking(requestId); // Restart or confirm tracking if stopped on arrival
                } else if (action === 'complete') {
                    showDriverPanelFeedback('Servicio completado.', 'success');
                    stopLocationTracking();
                    // Optionally fade out/remove completed row after a delay
                    // $row.delay(3000).fadeOut('slow', () => { $row.remove(); });
                }

                // If a request being tracked was rejected, stop tracking
                if (action === 'reject' && getCurrentTrackingRequestId() === requestId) {
                    stopLocationTracking();
                }

                // Re-enable only the currently visible action button(s)
                $row.find('.button[data-action]:visible').prop('disabled', false);

            } else {
                const errorMessage = responseData.data?.message || 'Ocurrió un error al procesar la acción.';
                showDriverPanelFeedback(`Error: ${errorMessage}`, 'error');
                $actionButtonsInRow.prop('disabled', false); // Re-enable all on error if action failed
            }
        } catch (error) {
            console.error('Fetch Request Failed:', error);
            showDriverPanelFeedback(`Fallo en la comunicación con el servidor: ${error.message}`, 'error');
            $actionButtonsInRow.prop('disabled', false); // Re-enable all on fetch error
        } finally {
            $button.removeClass('cachilupi-button--loading').text(originalButtonText);
            // Ensure only visible buttons are enabled, others remain disabled (as they were hidden)
            // This is slightly complex due to the hide/show logic. The above re-enabling might be sufficient.
            // $row.find('.button[data-action]:visible').prop('disabled', false);
        }
    });
};
