(function($) { // Añade esta línea al principio

    jQuery(document).ready(function($) {
        // Check if cachilupi_driver_vars is defined (should be by wp_localize_script)
        if (typeof cachilupi_driver_vars !== 'undefined') {

            // Function to show feedback messages in the driver panel
            function showDriverPanelFeedback(message, type = 'success') {
                var $feedbackContainer = $('#driver-panel-feedback'); // Assumes an element with this ID exists
                if (!$feedbackContainer.length) {
                    // Fallback: create a container if it doesn't exist, or prepend to .wrap
                    $feedbackContainer = $('<div>').attr('id', 'driver-panel-feedback').addClass('feedback-messages-container').css('margin-bottom', '15px');
                    $('.wrap h1').first().after($feedbackContainer); // Insert after the main title
                }
                $feedbackContainer.empty(); // Clear previous messages

                var messageElement = $('<div>').addClass('feedback-message').addClass(type)
                    .text(message)
                    .appendTo($feedbackContainer);

                setTimeout(function() {
                    messageElement.fadeOut('slow', function() {
                        $(this).remove();
                    });
                }, 5000); // 5 seconds
            }

            // Variables para el seguimiento de ubicación del conductor
            var locationWatchId = null;
            var currentTrackingRequestId = null;

            // Add click listeners to all action buttons using the data-action attribute for broader selection
            // Ensure your buttons in HTML have the `data-action` attribute
            $(document).on('click', '.button[data-request-id][data-action]', function(e) {
                e.preventDefault(); // Prevent default button behavior

                var $button = $(this);
                var requestId = $button.data('request-id');
                var action = $button.data('action'); // Determine the action using the data-action attribute
                var $row = $button.closest('tr'); // Get the table row for this request

                if (!action) {
                    console.error('Button is missing data-action attribute or it is empty.');
                    return; // Exit if no action is determined
                }

                console.log('Handling action for Request ID: ' + requestId + ' Action: ' + action);

                // Disable all action buttons in the current row while processing
                $row.find('.button[data-action]').prop('disabled', true);

                // Perform AJAX request
                $.ajax({
                    url: cachilupi_driver_vars.ajaxurl, // WordPress AJAX URL
                    type: 'POST',
                    data: {
                        action: 'cachilupi_pet_driver_action', // The AJAX action defined in PHP
                        security: cachilupi_driver_vars.driver_action_nonce, // The nonce for verification
                        request_id: requestId,
                        driver_action: action // The specific action (accept, reject, arrive, complete)
                    },
                    success: function(response) {
                        console.log('AJAX Success:', response);

                        if (response.success) {
                            // Update status text with the translated version from PHP
                            if (response.data && response.data.new_status_display) {
                                $row.find('.request-status').text(response.data.new_status_display);
                            }

                            // Hide all action buttons in the row first, then show the appropriate ones
                            $row.find('.button[data-action]').hide();

                            if (action === 'accept') {
                                // Status is 'accepted'. Show "Iniciar Viaje" button.
                                $row.find('.button[data-action="on_the_way"]').show();
                                showDriverPanelFeedback('Solicitud aceptada.', 'success');
                            } else if (action === 'reject') {
                                // Request is rejected, remove the row
                                $row.fadeOut('slow', function() {
                                    $(this).remove();
                                });
                                showDriverPanelFeedback('Solicitud rechazada.', 'success');
                            } else if (action === 'on_the_way') {
                                // Status is 'on_the_way'. Show "He Llegado al Origen" button.
                                $row.find('.button[data-action="arrive"]').show();
                                showDriverPanelFeedback('Viaje iniciado.', 'success');
                                // Iniciar seguimiento de ubicación
                                startLocationTracking(requestId);
                            } else if (action === 'arrive') {
                                // Status is 'arrived'. Show "Completar Viaje" button.
                                $row.find('.button[data-action="complete"]').show();
                                showDriverPanelFeedback('Llegada confirmada.', 'success');
                                // Detener seguimiento de ubicación
                                stopLocationTracking();
                            } else if (action === 'complete') {
                                // All buttons remain hidden, or row could be removed/styled differently
                                showDriverPanelFeedback('Servicio completado.', 'success');
                                // Detener seguimiento de ubicación si aún estaba activo
                                stopLocationTracking();
                                // Example: Fade out completed row after a delay if desired
                                // $row.delay(3000).fadeOut('slow', function() { $(this).remove(); });
                            }

                            // Si la acción fue 'reject' y el viaje estaba siendo rastreado, detenerlo.
                            // Esto es un caso borde, ya que 'reject' usualmente es para 'pending'.
                            if (action === 'reject' && currentTrackingRequestId === requestId) {
                                stopLocationTracking();
                            }

                            // Ensure any newly shown buttons are enabled
                            $row.find('.button[data-action]:visible').prop('disabled', false);
                        } else {
                            var errorMessage = response.data && response.data.message ? response.data.message : 'Ocurrió un error al procesar la acción.';
                            showDriverPanelFeedback('Error: ' + errorMessage, 'error');
                            console.error('AJAX Error Response:', errorMessage);
                            // Re-enable the clicked button if there was an error
                            $button.prop('disabled', false);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // Handle AJAX request errors
                        console.error('AJAX Request Failed:', textStatus, errorThrown, jqXHR.responseText);
                        showDriverPanelFeedback('Fallo en la comunicación con el servidor: ' + textStatus, 'error');
                    },
                    complete: function() {
                        // Re-enable visible buttons in the row, unless the row was removed (e.g., after reject)
                        if ($row.closest('body').length) { // Check if row still exists in DOM
                            $row.find('.button[data-action]:visible').prop('disabled', false);
                        }
                    }
                });
            });

            function startLocationTracking(requestId) {
                if (navigator.geolocation) {
                    // Detener cualquier seguimiento anterior
                    stopLocationTracking();
                    currentTrackingRequestId = requestId;
                    console.log('Iniciando seguimiento de ubicación para Request ID:', currentTrackingRequestId);

                    locationWatchId = navigator.geolocation.watchPosition(
                        function(position) {
                            var lat = position.coords.latitude;
                            var lon = position.coords.longitude;
                            console.log('Ubicación obtenida:', lat, lon, 'para Request ID:', currentTrackingRequestId);
                            sendLocationUpdate(currentTrackingRequestId, lat, lon);
                        },
                        function(error) {
                            console.error('Error al obtener ubicación del conductor:', error.message, 'Code:', error.code);
                            let userMessage = 'Error al obtener ubicación: ';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    userMessage += 'Permiso denegado. Por favor, habilita los servicios de ubicación en tu navegador/dispositivo.';
                                    // Detener el seguimiento si el permiso es denegado, ya que no funcionará.
                                    stopLocationTracking();
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    userMessage += 'Información de ubicación no disponible en este momento. Verifica tu señal GPS.';
                                    break;
                                case error.TIMEOUT:
                                    userMessage += 'Se agotó el tiempo de espera para obtener la ubicación. Intenta moverte a un lugar con mejor señal.';
                                    break;
                                default:
                                    userMessage += error.message;
                                    break;
                            }
                            showDriverPanelFeedback(userMessage, 'error');
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 15000, // Tiempo máximo para obtener una posición (incrementado ligeramente)
                            maximumAge: 0 // No usar caché de posición
                        }
                    );
                } else {
                    console.error('Geolocalización no es soportada por este navegador.');
                    showDriverPanelFeedback('Geolocalización no soportada.', 'error');
                }
                showDriverPanelFeedback('Compartiendo ubicación para la solicitud #' + requestId + '.', 'info');
            }

            function stopLocationTracking() {
                if (locationWatchId !== null) {
                    navigator.geolocation.clearWatch(locationWatchId);
                    locationWatchId = null;
                    console.log('Seguimiento de ubicación detenido para Request ID:', currentTrackingRequestId);
                    if (currentTrackingRequestId) {
                        showDriverPanelFeedback('Se ha detenido el compartir ubicación para la solicitud #' + currentTrackingRequestId + '.', 'info');
                    }
                    currentTrackingRequestId = null;
                }
            }

            function sendLocationUpdate(requestId, latitude, longitude) {
                if (!requestId) return;
                $.ajax({
                    url: cachilupi_driver_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cachilupi_update_driver_location',
                        security: cachilupi_driver_vars.update_location_nonce,
                        request_id: requestId,
                        latitude: latitude,
                        longitude: longitude
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Ubicación del conductor actualizada en el servidor.');
                        } else {
                            console.warn('Fallo al actualizar ubicación del conductor en el servidor:', response.data ? response.data.message : 'Error desconocido');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error AJAX al enviar actualización de ubicación:', textStatus, errorThrown);
                    }
                });
            }


        } else {
            console.error('cachilupi_driver_vars is not defined. wp_localize_script might not be working.');
        }
    });

})(jQuery); // Añade esta línea al final
