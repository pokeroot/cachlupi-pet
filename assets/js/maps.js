(function($) {
    // Set Mapbox access token
    if (typeof mapboxgl !== 'undefined') {
        mapboxgl.accessToken = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) ? cachilupi_pet_vars.mapbox_access_token : null;
        if (!mapboxgl.accessToken) {
            console.warn('Mapbox Access Token no está configurado. Las funcionalidades del mapa estarán deshabilitadas.');
        }
    }


    jQuery(document).ready(function($) {
        console.log('maps.js: Document ready. Initializing...');
        // Check if the map element exists
        var mapElement = document.getElementById('cachilupi-pet-map'); // Updated ID

        // --- Declare variables in a higher scope ---
        var map = null; // Initialize map variable
        var pickupGeocoderContainer = document.getElementById('pickup-geocoder-container'); // Get container reference early
        var dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container'); // Get container reference early
        var serviceDateInput = document.getElementById('service-date'); // Get input reference early
        var serviceTimeInput = document.getElementById('service-time'); // Get input reference early
        var submitButton = document.getElementById('submit-service-request'); // Get button reference early
        var petTypeSelect = document.getElementById('cachilupi-pet-pet-type'); // Updated ID // Get select reference early
        var notesTextArea = document.getElementById('cachilupi-pet-notes'); // Updated ID // Get textarea reference early
        var distanceElement = document.getElementById('cachilupi-pet-distance'); // Updated ID // Get the distance element early

        var pickupGeocoder = null; // Declare geocoder instances here
        var dropoffGeocoder = null;
        // Variables for geocoder input elements will be assigned later or retrieved by ID
        // These are declared here to be accessible by both map and no-map contexts after geocoder init or fallback
        var pickupGeocoderInput = null;
        var dropoffGeocoderInput = null;



        // Global variable for coordinates and markers
        var pickupCoords = null;
        var dropoffCoords = null;
        var pickupMarker = null;
        var dropoffMarker = null;

        // --- Actualización de Estado de Solicitudes del Cliente ---
        var clientRequestsStatusInterval = null;

        // --- End variable declarations ---


        if (mapElement) {
            if (typeof mapboxgl !== 'undefined' && mapboxgl.accessToken) {
                // Initialize the map
                map = new mapboxgl.Map({ // Assign to the higher-scoped variable
                    container: 'cachilupi-pet-map', // Updated ID
                    style: 'mapbox://styles/mapbox/streets-v11',
                    center: [-70.6693, -33.4489],
                    zoom: 10
                });

                // Invalidate map size on window resize
                window.addEventListener('resize', function() {
                    if (map) map.resize(); // Check if map is initialized
                });


                // Helper function to decode polyline (Keep existing function)
                function decodePolyline(encoded) {
                    var len = encoded.length,
                        index = 0,
                        array = [],
                        lat = 0,
                        lng = 0;

                    while (index < len) {
                        var b, shift = 0,
                            result = 0;
                        do {
                            b = encoded.charCodeAt(index++) - 63;
                            result |= (b & 0x1f) << shift;
                            shift += 5;
                        } while (b >= 0x20);
                        var dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
                        lat += dlat;

                        shift = 0;
                        result = 0;
                        do {
                            b = encoded.charCodeAt(index++) - 63;
                            result |= (b & 0x1f) << shift;
                            shift += 5;
                        } while (b >= 0x20);
                        var dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
                        lng += dlng;
                        array.push([lng * 1e-5, lat * 1e-5]);
                    }
                    return array;
                }

                // Function to get the route and distance and draw the route on the map
                function getRouteAndDistance() {
                    if (map && pickupCoords && dropoffCoords) { // Check if map is initialized
                        var url = 'https://api.mapbox.com/directions/v5/mapbox/driving/' + pickupCoords.lng + ',' + pickupCoords.lat + ';' + dropoffCoords.lng + ',' + dropoffCoords.lat + '?access_token=' + mapboxgl.accessToken;

                        // Add loading indicator for distance calculation
                        if (distanceElement) {
                            distanceElement.textContent = 'Calculando distancia...';
                            distanceElement.classList.add('loading'); // Add a loading class for styling
                        }


                        $.ajax({
                            url: url,
                            method: 'GET',
                            success: function(response) {
                                if (response && response.routes && response.routes.length > 0) {
                                    var route = response.routes[0].geometry;
                                    var decodedRoute = decodePolyline(route);
                                    var distanceMeters = response.routes[0].distance; // Distance in meters
                                    var distanceKm = (distanceMeters / 1000).toFixed(1); // Convert to km with 1 decimal

                                    // Update the distance in the HTML and remove loading class
                                    if (distanceElement) {
                                        distanceElement.textContent = 'Distancia estimada: ' + distanceKm + ' km';
                                        distanceElement.classList.remove('loading');
                                    }

                                    var geojson = {
                                        type: 'Feature',
                                        properties: {},
                                        geometry: {
                                            type: 'LineString',
                                            coordinates: decodedRoute
                                        }
                                    };

                                    // Check if source and layer exists and act
                                    if (map.getSource('route')) {
                                        map.getSource('route').setData(geojson);
                                    } else {
                                        map.addSource('route', {
                                            type: 'geojson',
                                            data: geojson
                                        });
                                        map.addLayer({
                                            id: 'route',
                                            type: 'line',
                                            source: 'route',
                                            layout: {
                                                'line-join': 'round',
                                                'line-cap': 'round'
                                            },
                                            paint: {
                                                'line-color': '#3887be',
                                                'line-width': 5,
                                                'line-opacity': 0.75
                                            }
                                        });
                                    }

                                    // Fit the map to the route
                                    var bounds = new mapboxgl.LngLatBounds();
                                    for (var i = 0; i < decodedRoute.length; i++) {
                                        bounds.extend(decodedRoute[i]);
                                    }
                                    map.fitBounds(bounds, {
                                        padding: 40 // Increased padding
                                    });
                                } else {
                                     if (distanceElement) {
                                        distanceElement.textContent = 'No se pudo calcular la distancia.';
                                        distanceElement.classList.remove('loading');
                                    }
                                }
                            },
                            error: function(error) {
                                console.error('Error getting directions:', error);
                                 if (distanceElement) {
                                    distanceElement.textContent = 'Error al calcular la distancia.';
                                    distanceElement.classList.remove('loading');
                                }
                            }
                        });
                    } else {
                        // Clear distance if locations are not set
                         if (distanceElement) {
                             distanceElement.textContent = '';
                             distanceElement.classList.remove('loading');
                         }
                         // Remove route layer if exists
                         if (map && map.getLayer('route')) { // Check if map is initialized
                            map.removeLayer('route');
                         }
                         if (map && map.getSource('route')) { // Check if map is initialized
                            map.removeSource('route');
                         }
                    }
                }

                // Function to dynamically load the Mapbox Geocoder (Keep existing function)
                function loadMapboxGeocoder(callback) {
                    if (typeof MapboxGeocoder !== 'undefined') {
                        callback();
                        return;
                    }
                    var script = document.createElement('script');
                    script.src = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
                    script.onload = callback;
                    document.head.appendChild(script);
                }

                loadMapboxGeocoder(function() {

                    // Get the container divs - Already done in higher scope

                    if (pickupGeocoderContainer) {
                        pickupGeocoder = new MapboxGeocoder({ // Assign to higher-scoped variable
                            accessToken: mapboxgl.accessToken,
                            placeholder: 'Buscar Lugar de Recogida', // Updated placeholder
                            mapboxgl: mapboxgl,
                            bbox: [-75.6, -55.9, -66.4, -17.5], // Bounding box for Chile
                            country: 'cl',
                            limit: 5 // Increased limit for more suggestions
                        });
                        pickupGeocoder.addTo(pickupGeocoderContainer);

                        // Add id to the input field generated by the geocoder
                        pickupGeocoderInput = pickupGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input'); 
                        if (pickupGeocoderInput) {
                            pickupGeocoderInput.id = 'pickup-location-input';
                            pickupGeocoderInput.classList.add('form-control'); // Add form-control class
                             // Add event listener for input changes to trigger validation
                            pickupGeocoderInput.addEventListener('input', validateForm);
                            pickupGeocoderInput.addEventListener('blur', validateForm); // Validate on blur too
                        }


                        pickupGeocoder.on('result', function(event) {
                            var lngLat = event.result.geometry.coordinates;
                            pickupCoords = {
                                lng: lngLat[0],
                                lat: lngLat[1]
                            };

                            // Remove previous pickup marker if exist
                            if (pickupMarker) {
                                pickupMarker.remove();
                            }
                            // Add new pickup marker with custom style (optional, can be done with CSS)
                            pickupMarker = new mapboxgl.Marker({ color: '#0073aa' }) // Custom marker color
                            .setLngLat(lngLat)
                            .addTo(map);

                            getRouteAndDistance(); // Call to update route and distance
                            validateForm(); // Validate form after selecting location
                        });

                         // Clear coords and marker when the input is cleared
                        pickupGeocoder.on('clear', function() {
                            pickupCoords = null;
                            if (pickupMarker) {
                                pickupMarker.remove();
                                pickupMarker = null;
                            }
                            getRouteAndDistance(); // Clear route and distance
                            validateForm(); // Validate form after clearing
                        });

                    } else {
                        console.error('Pickup geocoder container not found!');
                    }

                    // Add geocoding control for drop-off location
                    if (dropoffGeocoderContainer) { // Use dropoffGeocoderContainer variable
                        dropoffGeocoder = new MapboxGeocoder({ // Assign to higher-scoped variable
                            accessToken: mapboxgl.accessToken,
                            mapboxgl: mapboxgl,
                            bbox: [-75.6, -55.9, -66.4, -17.5], // Bounding box for Chile
                            country: 'cl',
                            limit: 5 // Increased limit for more suggestions
                        });
                        dropoffGeocoder.addTo(dropoffGeocoderContainer); // Use dropoffGeocoderContainer variable
                        dropoffGeocoder.setPlaceholder('Buscar Lugar de Destino'); // Updated placeholder

                        // Add id to the input field generated by the geocoder
                        dropoffGeocoderInput = dropoffGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input'); 
                        if (dropoffGeocoderInput) {
                            dropoffGeocoderInput.id = 'dropoff-location-input';
                             dropoffGeocoderInput.classList.add('form-control'); // Add form-control class
                              // Add event listener for input changes to trigger validation
                            dropoffGeocoderInput.addEventListener('input', validateForm);
                             dropoffGeocoderInput.addEventListener('blur', validateForm); // Validate on blur too
                        }


                        dropoffGeocoder.on('result', function(event) {
                            var lngLat = event.result.geometry.coordinates;
                            dropoffCoords = {
                                lng: lngLat[0],
                                lat: lngLat[1]
                            };

                            // Remove previous dropoff marker if exists
                            if (dropoffMarker) {
                                dropoffMarker.remove();
                            }
                            // Add new dropoff marker with custom style (optional, can be done with CSS)
                            dropoffMarker = new mapboxgl.Marker({ color: '#d32f2f' }) // Custom marker color
                                .setLngLat(lngLat)
                                .addTo(map);

                            getRouteAndDistance(); // Call to update route and distance
                            validateForm(); // Validate form after selecting location
                        });

                         // Clear coords and marker when the input is cleared
                        dropoffGeocoder.on('clear', function() {
                            dropoffCoords = null;
                            if (dropoffMarker) {
                                dropoffMarker.remove();
                                dropoffMarker = null;
                            }
                            getRouteAndDistance(); // Clear route and distance
                            validateForm(); // Validate form after clearing
                        });

                        } else {
                        console.error('Dropoff geocoder container not found!');
                    }

                }); // End of loadMapboxGeocoder callback


                // Function to show feedback messages (success or error)
                function showFeedbackMessage(message, type = 'success') {
                    // Remove any existing feedback messages
                    $('.feedback-message').remove();

                    var messageElement = $('<div>').addClass('feedback-message').addClass(type)
                        .text(message);

                    // Append the message after the submit button or within a designated area
                    if (submitButton) {
                        $(submitButton).after(messageElement);
                    } else {
                         $('.cachilupi-booking-panel').prepend(messageElement); // Prepend to the main panel if button not found
                    }

                    // Automatically hide the message after a few seconds
                    setTimeout(function() {
                        messageElement.fadeOut('slow', function() {
                            $(this).remove();
                        });
                    }, 5000); // 5 seconds
                }


                // Function to show/hide error messages next to fields
                function showError(fieldElement, message) {
                     // Find the parent form-group
                    var formGroup = $(fieldElement).closest('.form-group');
                     if (!formGroup.length) {
                        // If not in a form-group, try the parent node
                        formGroup = $(fieldElement).parent();
                     }

                    // Determine the actual input element for geocoders
                    var targetElement = fieldElement;
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0); // Get the input element
                        if (!targetElement) { // Fallback if input not found
                             targetElement = fieldElement;
                        }
                    }


                    // Check if an error message already exists for this field within the form-group
                    var existingError = formGroup.find('.error-message');
                    if (!existingError.length) {
                        // Create a new error message element
                        var errorSpan = $('<span>').addClass('error-message').text(message);
                        // Insert the error message after the field element or inside the form-group
                         $(targetElement).after(errorSpan); // Insert after the target element (input or container)
                    } else {
                         // Update the existing error message
                        existingError.text(message);
                    }

                    // Add error class to the target element (input) and its label
                    $(targetElement).addClass('input-error');
                    formGroup.find('label').addClass('label-error');
                }

                function hideError(fieldElement) {
                     // Find the parent form-group
                    var formGroup = $(fieldElement).closest('.form-group');
                     if (!formGroup.length) {
                        // If not in a form-group, try the parent node
                        formGroup = $(fieldElement).parent();
                     }

                     // Determine the actual input element for geocoders
                     var targetElement = fieldElement;
                     if ($(fieldElement).hasClass('geocoder-container')) {
                         targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0); // Get the input element
                         if (!targetElement) { // Fallback if input not found
                              targetElement = fieldElement;
                         }
                     }

                    // Remove the error message
                    // Target the error message relative to the formGroup, not the fieldElement, in case it was added after the input
                    formGroup.find('.error-message').remove();


                    // Remove error class from the target element (input) and its label
                    $(targetElement).removeClass('input-error');
                    formGroup.find('label').removeClass('label-error');
                }


                 // Function to validate the form fields and enable/disable the button
                function validateForm(isMapContext = true) { // Added parameter
                   var isValid = true;

                    // Validate pickup location
                    // Use the globally scoped pickupGeocoderInput and dropoffGeocoderInput
                    // which are populated either by geocoder init or in handleFormWithoutMap


                    // Check if pickupCoords are set OR if the input value is not empty (in case geocoder didn't fire result event)
                    // Validation now relies only on the input value since geolocation button is removed
                    if (!pickupGeocoderInput || !pickupGeocoderInput.value.trim() || (isMapContext && !pickupCoords)) {
                         showError(pickupGeocoderContainer, 'Este campo es obligatorio.'); // Use container for geocoder error
                         isValid = false;
                    } else {
                         hideError(pickupGeocoderContainer);
                    }


                    // Validate dropoff location
                    // Check if dropoffCoords are set OR if the input value is not empty
                    if (!dropoffGeocoderInput || !dropoffGeocoderInput.value.trim() || (isMapContext && !dropoffCoords)) {
                         showError(dropoffGeocoderContainer, 'Este campo es obligatorio.'); // Use container for geocoder error
                         isValid = false;
                    } else {
                         hideError(dropoffGeocoderContainer);
                    }
                    // Validate date and time
                    if (!serviceDateInput || !serviceDateInput.value) { // Use serviceDateInput variable
                        if(serviceDateInput) showError(serviceDateInput, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                         if(serviceDateInput) hideError(serviceDateInput);
                    }

                    if (!serviceTimeInput || !serviceTimeInput.value) { // Use serviceTimeInput variable
                        if(serviceTimeInput) showError(serviceTimeInput, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                         if(serviceTimeInput) hideError(serviceTimeInput);
                    }


                    // Validate pet type
                    if (petTypeSelect && !petTypeSelect.value) { // Use petTypeSelect variable
                        showError(petTypeSelect, 'Este campo es obligatorio.');
                        isValid = false;
                    } else if(petTypeSelect) {
                         hideError(petTypeSelect);
                    }

                    // Enable or disable the submit button based on validation
                    if (submitButton) { // Use submitButton variable
                         submitButton.disabled = !isValid;
                    }                    

                    return isValid; // Return the overall validation status
                }


                // Add event listeners to input fields for validation
                if (serviceDateInput) { // Use serviceDateInput variable
                    serviceDateInput.addEventListener('input', function() { validateForm(true); });
                     // Set min date to today for date input
                    var today = new Date();
                    var dd = String(today.getDate()).padStart(2, '0');
                    var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
                    var year = today.getFullYear();
                    serviceDateInput.setAttribute('min', year + '-' + mm + '-' + dd);

                }
                if (serviceTimeInput) { // Use serviceTimeInput variable
                     serviceTimeInput.addEventListener('input', function() { validateForm(true); });
                      // Add blur listener to time input to round to nearest 5 minutes
                     serviceTimeInput.addEventListener('blur', function() {
                         var timeValue = this.value;
                         if (timeValue) {
                            var [hours, minutes] = timeValue.split(':');
                            minutes = parseInt(minutes, 10);
                            var roundedMinutes = Math.round(minutes / 5) * 5;
                             // Handle rounding up to the next hour if minutes become 60
                             if (roundedMinutes === 60) {
                                 hours = (parseInt(hours, 10) + 1) % 24; // Increment hour, wrap around at 24
                                 roundedMinutes = 0; // Reset minutes to 0
                             }
                             var newTime = `${hours.toString().padStart(2, '0')}:${roundedMinutes.toString().padStart(2, '0')}`;
                            this.value = newTime;
                         }
                         validateForm(true); // Validate after rounding
                     });
                }
                 if (petTypeSelect) { // Use petTypeSelect variable
                    petTypeSelect.addEventListener('change', function() { validateForm(true); });
                 }
                if (notesTextArea) { // Use notesTextArea variable
                    notesTextArea.addEventListener('input', function() { validateForm(true); }); // Optional: validate notes if required
                }


                // Add a click event listener to the submit button
                if (submitButton) { // Use submitButton variable
                    submitButton.addEventListener('click', function(event) {
                         // Prevent the default form submission
                         event.preventDefault();

                         // Perform final validation before submission
                         if (!validateForm(true)) { // Pass true for map context
                             // showFeedbackMessage('Por favor, completa todos los campos requeridos.', 'error'); // Validation already shows specific errors
                             return; // Stop if form is not valid
                         }


                        // Change the button text and add loading-spinner
                        submitButton.disabled = true;
                        submitButton.textContent = 'Enviando Solicitud...';
                        submitButton.classList.add('loading-spinner');
                        $('.feedback-message').remove(); // Clear previous messages


                        // Get the values from the input fields, textareas and select
                        // Use the already cached global/higher-scope variables

                        var pickupAddress = pickupGeocoderInput ? pickupGeocoderInput.value : '';
                        var dropoffAddress = dropoffGeocoderInput ? dropoffGeocoderInput.value : '';
                        var petType = petTypeSelect ? petTypeSelect.value : '';
                        var notes = notesTextArea ? notesTextArea.value : '';
                        var serviceDate = serviceDateInput ? serviceDateInput.value : '';
                        var serviceTime = serviceTimeInput ? serviceTimeInput.value : '';

                        // Get date and time and format them for server consumption

                        var scheduledDateTime;
                        // Parse and format the selected date and time as "YYYY-MM-DD HH:mm:ss"
                         if (serviceDate && serviceTime) {
                            scheduledDateTime = serviceDate + ' ' + serviceTime + ':00';
                        } else {
                             // This case should be caught by validateForm, but as a fallback
                            showFeedbackMessage('Error interno: Falta fecha o hora.', 'error');
                             //Restore button status
                            submitButton.classList.remove('loading-spinner');
                            submitButton.disabled = false;
                            submitButton.textContent = 'Solicitar Servicio';
                            return;
                        }


                        // Create an object to hold the service request data
                        var serviceRequestData = {
                            scheduled_date_time: scheduledDateTime, // Add scheduled date and time
                            pickup_address: pickupAddress,
                            pickup_lat: pickupCoords ? pickupCoords.lat : 0.0,
                            pickup_lon: pickupCoords ? pickupCoords.lng : 0.0,
                            dropoff_address: dropoffAddress,
                            dropoff_lat: dropoffCoords ? dropoffCoords.lat : 0.0,
                            dropoff_lon: dropoffCoords ? dropoffCoords.lng : 0.0,
                            pet_type: petType,
                            notes: notes,
                            action: 'cachilupi_pet_submit_request', // Updated action name
                            security: cachilupi_pet_vars.submit_request_nonce, // Updated vars variable

                        };

                        console.log('Sending Service Request Data (Map Context):', serviceRequestData);
                        // Send the data to the backend using AJAX
                        $.ajax({
                            url: cachilupi_pet_vars.ajaxurl, // Updated vars variable // The AJAX URL from wp_localize_script
                            type: 'POST',
                            data: serviceRequestData,
                            success: function(response) {
                                // Handle successful and error response from the backend
                                console.log('AJAX Response:', response);
                                if (response.success) {
                                    showFeedbackMessage('Solicitud enviada con éxito.', 'success'); // Removed "Redirigiendo..." as redirection is handled separately

                                    // Optional: Clear form fields after success
                                    // Use cached global variables

                                    if (pickupGeocoderInput) pickupGeocoderInput.value = ''; // Use pickupGeocoderInput variable
                                    if (dropoffGeocoderInput) dropoffGeocoderInput.value = ''; // Use dropoffGeocoderInput variable
                                    if (serviceDateInput) serviceDateInput.value = ''; // Use serviceDateInput variable
                                    if (serviceTimeInput) serviceTimeInput.value = ''; // Use serviceTimeInput variable
                                    if (petTypeSelect) petTypeSelect.value = ''; // Use petTypeSelect variable
                                    if (notesTextArea) notesTextArea.value = ''; // Use notesTextArea variable

                                     // Clear markers and route after successful submission
                                    if (pickupMarker) pickupMarker.remove();
                                    if (dropoffMarker) dropoffMarker.remove();
                                     // Check if source and layer exists before removing
                                     if (map && map.getLayer('route')) map.removeLayer('route'); // Check if map is initialized
                                     if (map && map.getSource('route')) map.removeSource('route'); // Check if map is initialized

                                    pickupCoords = null;
                                    dropoffCoords = null;
                                     if (distanceElement) distanceElement.textContent = ''; // Clear distance info // Use distanceElement variable


                                    // Validate form to reset button state
                                     validateForm(true); // Pass true for map context


                                    // No automatic redirection here. The user stays on the page
                                    // or you can add a specific redirect if needed, e.g., to a confirmation page.
                                    // setTimeout(function() {
                                    //      window.location.href = cachilupi_pet_vars.home_url; // Example redirect // Updated vars variable
                                    // }, 2000); // 2 seconds delay

                                } else {
                                     // Show error message from the server response
                                    var errorMessage = response.data && response.data.message ? response.data.message : 'Ocurrió un error al guardar la solicitud.';
                                    showFeedbackMessage(errorMessage, 'error');
                                }
                            },
                            complete: function() {
                                // This will run after success or error
                                if (submitButton) { // Check if button exists
                                    submitButton.classList.remove('loading-spinner'); // Use submitButton variable
                                    submitButton.disabled = false; // Use submitButton variable
                                    submitButton.textContent = 'Solicitar Servicio'; // Restore button text // Use submitButton variable
                                }
                            },
                            error: function(error) {
                                // Handle AJAX errors
                                showFeedbackMessage('Error de comunicación con el servidor. Inténtalo de nuevo.', 'error');
                                console.error('AJAX Error:', error);
                                // Ensure button state is restored on AJAX error
                                if (submitButton) { // Check if button exists
                                    submitButton.classList.remove('loading-spinner'); // Use submitButton variable
                                    submitButton.disabled = false; // Use submitButton variable
                                    submitButton.textContent = 'Solicitar Servicio'; // Use submitButton variable
                                }
                            }
                        });
                    });
                }

                // Initial validation call to set initial button state
                validateForm(true); // Pass true for map context


            } else {
                console.error('Mapbox GL JS is not loaded.');
                 // Fallback validation and submission handling if Mapbox GL JS is not loaded or token is missing
                 handleFormWithoutMap();
            }
        } else {
            console.log('Map element not found. Mapbox scripts and styles will not be enqueued.');
             // If map element doesn't exist, we still need to handle form validation and submission
             handleFormWithoutMap();
        }

         // Function to handle form validation and submission when map is not present
         function handleFormWithoutMap() {
             // Ensure form elements are available (they are already declared in higher scope)
             // If they weren't found initially, try to get them again.
             if (!serviceDateInput) serviceDateInput = document.getElementById('service-date');
             if (!serviceTimeInput) serviceTimeInput = document.getElementById('service-time');
             if (!petTypeSelect) petTypeSelect = document.getElementById('cachilupi-pet-pet-type');
             if (!notesTextArea) notesTextArea = document.getElementById('cachilupi-pet-notes');
             if (!submitButton) submitButton = document.getElementById('submit-service-request');
             pickupGeocoderInput = document.getElementById('pickup-location-input');
             dropoffGeocoderInput = document.getElementById('dropoff-location-input');
             if (!pickupGeocoderContainer) pickupGeocoderContainer = document.getElementById('pickup-geocoder-container');
             if (!dropoffGeocoderContainer) dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container');
             if (!distanceElement) distanceElement = document.getElementById('cachilupi-pet-distance');

             // Use the unified validateForm function with isMapContext = false
             const validateFormNoMap = () => validateForm(false);

             // Add event listeners to input fields for validation (using the non-map validation function)
             if (pickupGeocoderInput) { // Use pickupGeocoderInput variable
                 pickupGeocoderInput.addEventListener('input', validateFormNoMap);
                 pickupGeocoderInput.addEventListener('blur', validateFormNoMap);
             }
             if (dropoffGeocoderInput) { // Use dropoffGeocoderInput variable
                 dropoffGeocoderInput.addEventListener('input', validateFormNoMap);
                 dropoffGeocoderInput.addEventListener('blur', validateFormNoMap);
             }
             if (serviceDateInput) { // Use serviceDateInput variable
                 serviceDateInput.addEventListener('input', validateFormNoMap);
                  // Set min date to today for date input
                 var today = new Date();
                 var dd = String(today.getDate()).padStart(2, '0');
                 var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
                 var year = today.getFullYear();
                 serviceDateInput.setAttribute('min', year + '-' + mm + '-' + dd);
             }
             if (serviceTimeInput) { // Use serviceTimeInput variable
                 serviceTimeInput.addEventListener('input', validateFormNoMap);
                  // Add blur listener to time input to round to nearest 5 minutes
                 serviceTimeInput.addEventListener('blur', function() {
                     var timeValue = this.value;
                     if (timeValue) {
                        var [hours, minutes] = timeValue.split(':');
                        minutes = parseInt(minutes, 10);
                        var roundedMinutes = Math.round(minutes / 5) * 5;
                         if (roundedMinutes === 60) {
                             hours = (parseInt(hours, 10) + 1) % 24; // Increment hour, wrap around at 24
                             roundedMinutes = 0; // Reset minutes to 0
                         }
                         var newTime = `${hours.toString().padStart(2, '0')}:${roundedMinutes.toString().padStart(2, '0')}`;
                        this.value = newTime;
                     }
                     validateFormNoMap(); // Validate after rounding
                 });
             }
             if (petTypeSelect) { // Use petTypeSelect variable
                 petTypeSelect.addEventListener('change', validateFormNoMap);
             }
             if (notesTextArea) { // Use notesTextArea variable
                 notesTextArea.addEventListener('input', validateFormNoMap); // Optional: validate notes if required
             }


             // Add a click event listener to the submit button for non-map case
             if (submitButton) { // Use submitButton variable
                  submitButton.addEventListener('click', function(event) {
                      event.preventDefault();
                       if (validateForm(false)) { // Call unified validation with isMapContext = false
                          // Handle AJAX submission here, similar to the map case but without map coords
                           // Show loading state on button
                            submitButton.disabled = true;
                            submitButton.textContent = 'Enviando Solicitud...';
                            submitButton.classList.add('loading-spinner');
                            $('.feedback-message').remove(); // Clear previous messages

                           // Get the values from the input fields
                            var pickupAddress = pickupGeocoderInput ? pickupGeocoderInput.value : '';
                            var dropoffAddress = dropoffGeocoderInput ? dropoffGeocoderInput.value : '';
                            var petType = petTypeSelect ? petTypeSelect.value : '';
                            var notes = notesTextArea ? notesTextArea.value : '';
                            var serviceDate = serviceDateInput ? serviceDateInput.value : '';
                            var serviceTime = serviceTimeInput ? serviceTimeInput.value : '';

                            var scheduledDateTime = (serviceDate && serviceTime) ? serviceDate + ' ' + serviceTime + ':00' : '';


                           // Create an object to hold the service request data (without map coords)
                            var serviceRequestData = {
                                scheduled_date_time: scheduledDateTime,
                                pickup_address: pickupAddress,
                                pickup_lat: 0.0, 
                                pickup_lon: 0.0, 
                                dropoff_address: dropoffAddress,
                                dropoff_lat: 0.0, 
                                dropoff_lon: 0.0, 
                                pet_type: petType,
                                notes: notes,
                                action: 'cachilupi_pet_submit_request', // Updated action name
                                security: cachilupi_pet_vars.submit_request_nonce, // Updated vars variable
                            };

                           console.log('Sending Service Request Data (Non-Map Context):', serviceRequestData);

                           // Send the data to the backend using AJAX
                            $.ajax({
                                url: cachilupi_pet_vars.ajaxurl, // Updated vars variable
                                type: 'POST',
                                data: serviceRequestData,
                                success: function(response) {
                                    console.log('AJAX Response (Without Map):', response);
                                    if (response.success) {
                                        showFeedbackMessage('Solicitud enviada con éxito.', 'success');
                                         // Optional: Clear form fields after success
                                        if (pickupGeocoderInput) pickupGeocoderInput.value = '';
                                        if (dropoffGeocoderInput) dropoffGeocoderInput.value = '';
                                        if (serviceDateInput) serviceDateInput.value = '';
                                        if (serviceTimeInput) serviceTimeInput.value = '';
                                        if (petTypeSelect) petTypeSelect.value = '';
                                        if (notesTextArea) notesTextArea.value = '';
                                        if (distanceElement) distanceElement.textContent = '';

                                        validateForm(false); // Validate to reset button state

                                    } else {
                                         var errorMessage = response.data && response.data.message ? response.data.message : 'Ocurrió un error al guardar la solicitud.';
                                        showFeedbackMessage(errorMessage, 'error');
                                    }
                                },
                                complete: function() {
                                    if (submitButton) {
                                        submitButton.classList.remove('loading-spinner');
                                        submitButton.disabled = false;
                                        submitButton.textContent = 'Solicitar Servicio';
                                    }
                                },
                                error: function(error) {
                                    showFeedbackMessage('Error de comunicación con el servidor. Inténtalo de nuevo.', 'error');
                                    console.error('AJAX Error (Without Map):', error);
                                    if (submitButton) {
                                        submitButton.classList.remove('loading-spinner');
                                        submitButton.disabled = false;
                                        submitButton.textContent = 'Solicitar Servicio';
                                    }
                                }
                            });
                       }
                  });
             }
             // Initial validation call for non-map case
             validateForm(false); // Call unified validation with isMapContext = false
         }

        // --- Seguimiento del Conductor para el Cliente ---
        var clientFollowMap = null; // Variable para guardar la instancia del mapa del modal
        var followInterval = null;  // Variable para el intervalo de actualización de ubicación
        var currentFollowingRequestId = null; // Para saber qué solicitud se está siguiendo
        var driverMarker = null; // Variable para el marcador del conductor en el mapa de seguimiento

        // Event listener para el botón "Seguir Viaje"
        console.log('maps.js: Setting up "Seguir Viaje" button listener.');
        // Usamos delegación de eventos por si la tabla de solicitudes se carga dinámicamente
        $(document).on('click', '.cachilupi-follow-driver-btn', function() {
            var $button = $(this);
            console.log('Botón "Seguir Viaje" clickeado.');
            currentFollowingRequestId = $button.data('request-id');
            console.log('Request ID:', currentFollowingRequestId);

            if (!currentFollowingRequestId) {
                console.error('Error: No se pudo obtener el ID de la solicitud para seguir.');
                alert(cachilupi_pet_vars.text_driver_location_not_available || 'No se pudo obtener el ID de la solicitud.');
                return;
            }
            // Mostrar el modal
            $('#cachilupi-follow-modal').show(); 
            console.log('Modal de seguimiento mostrado.');

            // Inicializar el mapa en el modal si aún no existe o si es una solicitud diferente
            // Asegurarse que el token está seteado
            if (typeof mapboxgl !== 'undefined' && !mapboxgl.accessToken && cachilupi_pet_vars.mapbox_access_token) {
                mapboxgl.accessToken = cachilupi_pet_vars.mapbox_access_token;
            }
            
            if (typeof mapboxgl === 'undefined' || !mapboxgl.accessToken) {
                console.error("Mapbox GL JS no está cargado o falta el token de acceso.");
                console.log("Token actual de Mapbox:", mapboxgl.accessToken);
                alert("Error al cargar el mapa: Mapbox no está disponible.");
                $('#cachilupi-follow-modal').hide();
                return;
            }

            if (!clientFollowMap || clientFollowMap.getContainer().id !== 'cachilupi-client-follow-map') {
                if (clientFollowMap) { // Si había un mapa anterior, removerlo
                    clientFollowMap.remove();
                    clientFollowMap = null;
                }
                try {
                    clientFollowMap = new mapboxgl.Map({
                        container: 'cachilupi-client-follow-map',
                        style: 'mapbox://styles/mapbox/streets-v11', // o tu estilo preferido
                        center: [-70.6693, -33.4489], // Centro inicial Chile, se ajustará
                        zoom: 9
                    });
                    clientFollowMap.addControl(new mapboxgl.NavigationControl());
                    // Es importante redimensionar el mapa DESPUÉS de que el modal es visible y el contenedor del mapa tiene dimensiones.
                    clientFollowMap.on('load', function() {
                        clientFollowMap.resize();
                    });
                } catch (e) {
                    console.error("Error inicializando el mapa de seguimiento del cliente:", e);
                    alert("Error al cargar el mapa de seguimiento.");
                    $('#cachilupi-follow-modal').hide();
                    return;
                }
            }
            
            // Asegurarse de que el mapa se redimensiona si ya estaba inicializado pero el modal se reabre
            if (clientFollowMap) {
                clientFollowMap.resize();
            }
            
            // Limpiar intervalo anterior si existía
            if (followInterval) {
                clearInterval(followInterval);
            }

            // Empezar a pedir la ubicación
            console.log('Iniciando fetch de ubicación para request ID:', currentFollowingRequestId);
            fetchDriverLocationForClient(currentFollowingRequestId); // Primera llamada inmediata
            followInterval = setInterval(function() {
                fetchDriverLocationForClient(currentFollowingRequestId);
            }, 15000); // Cada 15 segundos (ajusta según necesites)
        });

        // Event listener para el botón de cerrar modal
        $('#cachilupi-close-follow-modal').on('click', function() {
            $('#cachilupi-follow-modal').hide();
            console.log('Modal de seguimiento cerrado.');
            if (followInterval) {
                clearInterval(followInterval);
                followInterval = null;
            }
            currentFollowingRequestId = null;
            if (driverMarker) {
                driverMarker.remove();
                driverMarker = null;
            }
        });

        function fetchDriverLocationForClient(requestId) {
            if (!requestId) { console.log('fetchDriverLocationForClient: No requestId'); return; }

            $.ajax({
                url: cachilupi_pet_vars.ajaxurl,
                type: 'GET',
                data: {
                    action: 'cachilupi_get_driver_location',
                    request_id: requestId,
                    security: cachilupi_pet_vars.get_location_nonce 
                },
                success: function(response) {
                    if (response.success && response.data.latitude && response.data.longitude) {
                        console.log('Ubicación recibida:', response.data);
                        var driverPosition = [parseFloat(response.data.longitude), parseFloat(response.data.latitude)];

                        if (!driverMarker) {
                            driverMarker = new mapboxgl.Marker().setLngLat(driverPosition).addTo(clientFollowMap);
                        } else {
                            driverMarker.setLngLat(driverPosition);
                        }
                        clientFollowMap.flyTo({ center: driverPosition, zoom: 15 });
                        $('#cachilupi-follow-modal-title').text((cachilupi_pet_vars.text_follow_driver || 'Siguiendo Viaje') + ' ID: ' + requestId);
                    } else {
                        console.warn('Ubicación del conductor no disponible:', response.data ? response.data.message : 'Sin respuesta del servidor.');
                        $('#cachilupi-follow-modal-title').text((cachilupi_pet_vars.text_driver_location_not_available || 'Ubicación no disponible') + ' (ID: ' + requestId + ')');
                        if (driverMarker) { driverMarker.remove(); driverMarker = null; }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error al obtener ubicación del conductor:', textStatus, errorThrown);
                    $('#cachilupi-follow-modal-title').text('Error al obtener ubicación');
                }
            });
        }

        // Función para mostrar notificaciones toast globales
        function showGlobalToast(message, type = 'info', duration = 4000) {
            // Remover toasts existentes para evitar solapamiento
            $('.cachilupi-toast-notification').remove();

            var toast = $('<div>')
                .addClass('cachilupi-toast-notification')
                .addClass(type) // success, error, info
                .text(message)
                .appendTo('body'); // Añadir al body para asegurar visibilidad

            // Forzar reflow para que la transición funcione al añadir la clase 'show'
            toast.width();

            toast.addClass('show');

            setTimeout(function() {
                toast.removeClass('show');
                setTimeout(function() {
                    toast.remove();
                }, 500); // Esperar que la transición de opacidad termine
            }, duration);
        }

        function updateClientRequestsTable(statuses) {
        if (!statuses || statuses.length === 0) {
            // console.log('No hay estados para actualizar o respuesta vacía.');
            return;
        }

        $('.cachilupi-client-requests-panel table.widefat tbody tr').each(function() {
            var $row = $(this);
            var requestId = $row.data('request-id');
            var currentStatusCell = $row.find('td.request-status');
            // Encuentra la celda de "Seguimiento" por su data-label, ya que no tiene una clase única
            var currentFollowButtonCell = $row.find('td[data-label="Seguimiento:"]');


            var requestUpdate = statuses.find(function(req) {
                // Asegurar que la comparación sea correcta (ambos números o ambos strings)
                return String(req.request_id) === String(requestId);
            });

            if (requestUpdate) {
                var oldStatusDisplay = currentStatusCell.text();
                // Actualizar texto del estado si es diferente
                if (currentStatusCell.text() !== requestUpdate.status_display) {
                    currentStatusCell.text(requestUpdate.status_display);
                    // Mostrar toast si el estado cambió y no es el estado inicial o un estado "menor"
                    if (oldStatusDisplay !== requestUpdate.status_display && oldStatusDisplay !== '--') { // Evitar toast en la carga inicial si ya está actualizado
                        showGlobalToast('Tu solicitud #' + requestId + ' ahora está: ' + requestUpdate.status_display, 'info');
                    }
                    console.log('Solicitud ID ' + requestId + ' estado actualizado a: ' + requestUpdate.status_display);
                }

                // Actualizar visibilidad del botón "Seguir Viaje"
                var followButton = $row.find('.cachilupi-follow-driver-btn');
                if (requestUpdate.status_slug === 'on_the_way' && requestUpdate.driver_id) {
                    if (!followButton.length) { // Si el botón no existe, créalo y añádelo
                        var newButtonHTML = '<button class="button cachilupi-follow-driver-btn" data-request-id="' + requestId + '">' + (cachilupi_pet_vars.text_follow_driver || 'Seguir Viaje') + '</button>';
                        currentFollowButtonCell.html(newButtonHTML);
                        if (requestUpdate.status_slug === 'on_the_way') { // Solo mostrar toast si realmente es "on_the_way"
                             showGlobalToast('¡El conductor para tu solicitud #' + requestId + ' está en camino!', 'success');
                        }
                        console.log('Solicitud ID ' + requestId + ': Botón "Seguir Viaje" añadido.');
                    } else if (!followButton.is(':visible')) {
                        followButton.show();
                         console.log('Solicitud ID ' + requestId + ': Botón "Seguir Viaje" mostrado.');
                    }
                } else {
                    if (followButton.length && followButton.is(':visible')) {
                        followButton.hide();
                        console.log('Solicitud ID ' + requestId + ': Botón "Seguir Viaje" ocultado.');
                    } else if (followButton.length === 0 && currentFollowButtonCell.text().trim() !== '--') {
                        // Si el botón nunca existió y el estado no es 'on_the_way', asegurar el placeholder
                        currentFollowButtonCell.html('--');
                    }
                }
            }
        });
    }

    function fetchClientRequestsStatus() {
        // Solo ejecutar si el panel de solicitudes del cliente está visible/existe
        if ($('.cachilupi-client-requests-panel').length === 0) {
            if (clientRequestsStatusInterval) {
                clearInterval(clientRequestsStatusInterval); // Detener sondeo si el panel no está
                clientRequestsStatusInterval = null;
                console.log('maps.js: Panel de solicitudes del cliente no encontrado. Sondeo de estado detenido.');
            }
            return;
        }

        $.ajax({
            url: cachilupi_pet_vars.ajaxurl,
            type: 'GET',
            data: {
                action: 'cachilupi_get_client_requests_status',
                security: cachilupi_pet_vars.get_requests_status_nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // console.log('Estados de solicitudes del cliente obtenidos:', response.data);
                    updateClientRequestsTable(response.data);
                } else {
                    console.warn('No se pudieron obtener los estados de las solicitudes del cliente o no se recibieron datos.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error al obtener los estados de las solicitudes del cliente:', textStatus, errorThrown);
            }
        });
    }

    // Iniciar sondeo si el panel de solicitudes del cliente existe en la página
    if ($('.cachilupi-client-requests-panel').length > 0) {
        console.log('maps.js: Panel de solicitudes del cliente encontrado. Iniciando sondeo de estado.');
        fetchClientRequestsStatus(); // Obtención inicial
        clientRequestsStatusInterval = setInterval(fetchClientRequestsStatus, 20000); // Sondear cada 20 segundos (ajusta según necesidad)
    } else {
        // console.log('maps.js: Panel de solicitudes del cliente no encontrado. Sondeo de estado no iniciado.');
    }


    });
})(jQuery);
