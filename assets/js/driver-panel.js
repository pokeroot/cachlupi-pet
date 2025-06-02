(function($) { // IIFE remains for jQuery DOM usage
    jQuery(document).ready(($) => { // Arrow function for ready callback
        if (typeof cachilupi_driver_vars === 'undefined') {
            console.error('cachilupi_driver_vars is not defined. wp_localize_script might not be working.');
            return; // Exit if config is missing
        }

        const showDriverPanelFeedback = (message, type = 'success') => {
            let $feedbackContainer = $('#driver-panel-feedback');
            if (!$feedbackContainer.length) {
                // If the specific container doesn't exist, create it or find a suitable fallback.
                // For now, let's assume it should exist or be created by other means if necessary.
                // As a simple fallback, create it after the main title if a .wrap h1 exists.
                const $mainTitle = $('.wrap h1').first();
                if ($mainTitle.length) {
                    $feedbackContainer = $('<div>').attr('id', 'driver-panel-feedback');
                    $mainTitle.after($feedbackContainer);
                } else {
                    // If no .wrap h1, prepend to .wrap or body as a last resort (less ideal)
                    // This part might need theme-specific adjustments if #driver-panel-feedback isn't guaranteed.
                    // For now, we'll log an error if no good place is found.
                    console.error("Feedback container #driver-panel-feedback not found and couldn't be created.");
                    return;
                }
            }

            $feedbackContainer.empty(); // Clear previous messages

            const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`;
            const messageElement = $('<div>')
                .addClass(feedbackClass)
                .text(message);

            // ARIA roles for accessibility
            if (type === 'error') {
                messageElement.attr('role', 'alert');
                messageElement.attr('aria-live', 'assertive'); // Ensures it's read immediately by screen readers
            } else {
                messageElement.attr('role', 'status');
                messageElement.attr('aria-live', 'polite'); // Read when the user is idle
            }

            messageElement.appendTo($feedbackContainer);

            setTimeout(() => {
                messageElement.fadeOut('slow', () => {
                    messageElement.remove();
                });
            }, 5000); // Auto-hide after 5 seconds
        };

        let locationWatchId = null;
        let currentTrackingRequestId = null;

        $(document).on('click', '.button[data-request-id][data-action]', async (e) => { // async for await, arrow function
            e.preventDefault();

            const $button = $(e.currentTarget); // Use e.currentTarget instead of $(this)
            const requestId = $button.data('request-id');
            const action = $button.data('action');
            const $row = $button.closest('tr');

            if (!action) {
                console.error('Button is missing data-action attribute or it is empty.');
                return;
            }

            console.log(`Handling action for Request ID: ${requestId} Action: ${action}`);

            // Disable all buttons in the row to prevent multiple clicks
            const $actionButtonsInRow = $row.find('.button[data-action]');
            $actionButtonsInRow.prop('disabled', true);

            // Add loading state to the clicked button
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
                    throw new Error(`Network response was not ok: ${response.statusText}`);
                }

                const responseData = await response.json();
                console.log('Fetch Success:', responseData);

                if (responseData.success) {
                    if (responseData.data && responseData.data.new_status_display) {
                        $row.find('.request-status').text(responseData.data.new_status_display);
                    }

                    $row.find('.button[data-action]').hide();

                    if (action === 'accept') {
                        $row.find('.button[data-action="on_the_way"]').show();
                        showDriverPanelFeedback('Solicitud aceptada.', 'success');
                    } else if (action === 'reject') {
                        $row.fadeOut('slow', () => {
                            $row.remove(); // Use $row directly
                        });
                        showDriverPanelFeedback('Solicitud rechazada.', 'success');
                    } else if (action === 'on_the_way') {
                        $row.find('.button[data-action="arrive"]').show();
                        showDriverPanelFeedback('Viaje iniciado.', 'success');
                        startLocationTracking(requestId);
                    } else if (action === 'arrive') {
                        $row.find('.button[data-action="complete"]').show();
                        showDriverPanelFeedback('Llegada confirmada.', 'success');
                        stopLocationTracking();
                    } else if (action === 'complete') {
                        showDriverPanelFeedback('Servicio completado.', 'success');
                        stopLocationTracking();
                        // $row.delay(3000).fadeOut('slow', () => { $row.remove(); });
                    }

                    if (action === 'reject' && currentTrackingRequestId === requestId) {
                        stopLocationTracking();
                    }

                    $row.find('.button[data-action]:visible').prop('disabled', false);
                } else {
                    const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al procesar la acción.';
                    showDriverPanelFeedback(`Error: ${errorMessage}`, 'error');
                    console.error('Fetch Error Response:', errorMessage);
                    // $button.prop('disabled', false); // Handled by finally
                }
            } catch (error) {
                console.error('Fetch Request Failed:', error);
                showDriverPanelFeedback(`Fallo en la comunicación con el servidor: ${error.message}`, 'error');
                // $button.prop('disabled', false); // Handled by finally
            } finally {
                // Restore the clicked button state (text, loading class)
                $button.removeClass('cachilupi-button--loading').text(originalButtonText);

                // Re-enable appropriate buttons.
                // If the row still exists (i.e., action was not 'reject' or a similar row-removing action)
                if ($row.closest('body').length) {
                    // If the original button is still meant to be visible (e.g., an error occurred, or it's an action that doesn't hide itself)
                    if ($button.is(':visible')) {
                        $button.prop('disabled', false);
                    }
                    // Ensure any other buttons that became visible (due to state change) are enabled,
                    // and those that were hidden remain disabled (or are handled by earlier .hide())
                    $row.find('.button[data-action]:visible').prop('disabled', false);
                }
                // If a 'reject' action was successful, the row is removed, so no buttons in it need specific re-enabling.
            }
        });

        // --- startLocationTracking, stopLocationTracking, sendLocationUpdate will be refactored next ---
        // For now, keeping them as they are to do this step-by-step.
        const startLocationTracking = (requestId) => {
            if (navigator.geolocation) {
                stopLocationTracking(); // Stop any previous tracking
                currentTrackingRequestId = requestId;
                console.log(`Iniciando seguimiento de ubicación para Request ID: ${currentTrackingRequestId}`);

                locationWatchId = navigator.geolocation.watchPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        console.log(`Ubicación obtenida: ${lat}, ${lon} para Request ID: ${currentTrackingRequestId}`);
                        sendLocationUpdate(currentTrackingRequestId, lat, lon);
                    },
                    (error) => {
                        console.error(`Error al obtener ubicación del conductor: ${error.message}, Code: ${error.code}`);
                        let userMessage = 'Error al obtener ubicación: ';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                userMessage += 'Permiso denegado. Por favor, habilita los servicios de ubicación.';
                                stopLocationTracking(); // Stop trying if permission is denied
                                break;
                            case error.POSITION_UNAVAILABLE:
                                userMessage += 'Información de ubicación no disponible. Verifica tu señal GPS.';
                                break;
                            case error.TIMEOUT:
                                userMessage += 'Se agotó el tiempo de espera para obtener la ubicación.';
                                break;
                            default:
                                userMessage += error.message;
                                break;
                        }
                        showDriverPanelFeedback(userMessage, 'error');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
                showDriverPanelFeedback(`Compartiendo ubicación para la solicitud #${requestId}.`, 'info');
            } else {
                console.error('Geolocalización no es soportada por este navegador.');
                showDriverPanelFeedback('Geolocalización no soportada.', 'error');
            }
        };

        const stopLocationTracking = () => {
            if (locationWatchId !== null) {
                navigator.geolocation.clearWatch(locationWatchId);
                locationWatchId = null;
                console.log(`Seguimiento de ubicación detenido para Request ID: ${currentTrackingRequestId}`);
                if (currentTrackingRequestId) {
                    showDriverPanelFeedback(`Se ha detenido el compartir ubicación para la solicitud #${currentTrackingRequestId}.`, 'info');
                }
                currentTrackingRequestId = null; // Clear the ID once tracking is stopped
            }
        };

        const sendLocationUpdate = async (requestId, latitude, longitude) => {
            if (!requestId) {
                console.warn('sendLocationUpdate: requestId is missing.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cachilupi_update_driver_location');
            formData.append('security', cachilupi_driver_vars.update_location_nonce);
            formData.append('request_id', requestId);
            formData.append('latitude', latitude);
            formData.append('longitude', longitude);

            try {
                const response = await fetch(cachilupi_driver_vars.ajaxurl, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    // Try to get error message from response body if available
                    let errorData = null;
                    try {
                        errorData = await response.json();
                    } catch (e) {
                        // Ignore if response is not json
                    }
                    const serverMessage = errorData && errorData.data && errorData.data.message ? errorData.data.message : response.statusText;
                    throw new Error(`Network response was not ok: ${serverMessage}`);
                }

                const responseData = await response.json();

                if (responseData.success) {
                    console.log('Ubicación del conductor actualizada en el servidor.');
                    // Optionally, provide feedback if the status code indicates "no_change"
                    if (responseData.data && responseData.data.status_code === 'no_change') {
                        console.log('Ubicación sin cambios significativos en el servidor.');
                    }
                } else {
                    const message = responseData.data && responseData.data.message ? responseData.data.message : 'Error desconocido del servidor.';
                    console.warn(`Fallo al actualizar ubicación del conductor en el servidor: ${message}`);
                    // Do not show feedback for every location update failure to avoid spamming the UI,
                    // but log it for debugging. The watchPosition error handler will show more persistent errors.
                }
            } catch (error) {
                console.error('Error Fetch al enviar actualización de ubicación:', error.message);
                // Similarly, avoid spamming UI for transient network issues during background tracking.
                // showDriverPanelFeedback(`Error de red al actualizar ubicación: ${error.message}`, 'error');
            }
        };
    });
})(jQuery);
