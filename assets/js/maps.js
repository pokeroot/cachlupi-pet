(function($) { // IIFE remains for jQuery DOM usage
    // Set Mapbox access token
    if (typeof mapboxgl !== 'undefined') {
        mapboxgl.accessToken = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) ? cachilupi_pet_vars.mapbox_access_token : null;
        if (!mapboxgl.accessToken) {
            console.warn('Mapbox Access Token no está configurado. Las funcionalidades del mapa estarán deshabilitadas.');
        }
    }

    jQuery(document).ready(($) => { // Arrow function for ready callback

        // Tab Switching Logic for Client Panel Requests List
        // Check if the client request panel and tabs are present
        if ($('.cachilupi-client-requests-panel .nav-tab-wrapper').length > 0) {
            // PHP should already set the first tab as active and its content visible.
            // This JS handles subsequent clicks.

            $(document).on('click', '.cachilupi-client-requests-panel .nav-tab-wrapper a.nav-tab', function(e) {
                e.preventDefault();

                const $thisClickedTab = $(this); // `this` refers to the clicked DOM element
                const $tabWrapper = $thisClickedTab.closest('.nav-tab-wrapper');
                const $panel = $thisClickedTab.closest('.cachilupi-client-requests-panel');

                // Remove active class from sibling tabs and hide all tab content within this panel
                $tabWrapper.find('a.nav-tab').removeClass('nav-tab-active');
                $panel.find('.tab-content').hide(); // Hide all tab content sections within this specific panel

                // Add active class to the clicked tab and show its corresponding content
                $thisClickedTab.addClass('nav-tab-active');
                const activeContentID = $thisClickedTab.attr('href');

                if ($(activeContentID).length) {
                    $(activeContentID).show();
                } else {
                    console.error(`Client panel tab content not found for ID: ${activeContentID}`);
                }
            });
        }

        const mapElement = document.getElementById('cachilupi-pet-map');

        // --- Declare variables ---
        let map = null; // Reassigned
        const pickupGeocoderContainer = document.getElementById('pickup-geocoder-container'); // Assigned once
        const dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container'); // Assigned once
        const serviceDateInput = document.getElementById('service-date'); // Assigned once
        const serviceTimeInput = document.getElementById('service-time'); // Assigned once
        const submitButton = document.getElementById('submit-service-request'); // Assigned once
        const petTypeSelect = document.getElementById('cachilupi-pet-pet-type'); // Assigned once
        const notesTextArea = document.getElementById('cachilupi-pet-notes'); // Assigned once
        const petInstructionsTextArea = document.getElementById('cachilupi-pet-instructions'); // Assigned once
        const distanceElement = document.getElementById('cachilupi-pet-distance'); // Assigned once

        let pickupGeocoder = null; // Reassigned
        let dropoffGeocoder = null; // Reassigned
        let pickupGeocoderInput = null; // Reassigned
        let dropoffGeocoderInput = null; // Reassigned

        let pickupCoords = null; // Reassigned
        let dropoffCoords = null; // Reassigned
        let pickupMarker = null; // Reassigned
        let dropoffMarker = null; // Reassigned

        let clientRequestsStatusInterval = null; // Reassigned
        // --- End variable declarations ---

        // Initialize Flatpickr for Date Input
        if (document.getElementById('service-date')) {
            flatpickr("#service-date", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y", // Example: August 10, 2024
                minDate: "today",
                locale: 'es', // Assuming Spanish locale is enqueued
                onChange: (selectedDates, dateStr, instance) => {
                    validateForm(mapElement && mapboxgl && mapboxgl.accessToken); // Re-validate on change
                }
            });
        }

        // Initialize Flatpickr for Time Input
        if (document.getElementById('service-time')) {
            flatpickr("#service-time", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                altInput: true,
                altFormat: "h:i K", // Example: 03:30 PM
                time_24hr: false,
                minuteIncrement: 15,
                locale: 'es', // Assuming Spanish locale is enqueued
                // minTime: "08:00", // Optional: if service hours are fixed
                // maxTime: "21:45",
                onChange: (selectedDates, dateStr, instance) => {
                    validateForm(mapElement && mapboxgl && mapboxgl.accessToken); // Re-validate on change
                }
            });
        }


        if (mapElement) {
            if (typeof mapboxgl !== 'undefined' && mapboxgl.accessToken) {
                const defaultCenter = [-70.6693, -33.4489]; // Santiago, Chile
                const defaultZoom = 10;

                map = new mapboxgl.Map({
                    container: 'cachilupi-pet-map',
                    style: 'mapbox://styles/mapbox/streets-v11',
                    center: defaultCenter,
                    zoom: defaultZoom
                });

                // Attempt to geolocate user for initial map centering
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            if (map) { // Check if map is initialized
                                const { longitude, latitude } = position.coords;
                                map.setCenter([longitude, latitude]);
                                map.setZoom(12); // Zoom in a bit more if location is found
                            }
                        },
                        (error) => {
                            console.warn(`Error getting user location: ${error.message}. Using default center.`);
                            // Map already initialized with default center, so no action needed here for error
                        },
                        { timeout: 5000 } // Optional: timeout for geolocation
                    );
                } else {
                    console.warn('Geolocation is not supported by this browser. Using default center.');
                }


                window.addEventListener('resize', () => {
                    if (map) map.resize();
                });

                const decodePolyline = (encoded) => {
                    const len = encoded.length;
                    let index = 0;
                    const array = [];
                    let lat = 0;
                    let lng = 0;

                    while (index < len) {
                        let b, shift = 0, result = 0; // b, shift, result are reset each outer loop pass
                        do {
                            b = encoded.charCodeAt(index++) - 63;
                            result |= (b & 0x1f) << shift;
                            shift += 5;
                        } while (b >= 0x20);
                        const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
                        lat += dlat;

                        shift = 0; // Reset for longitude decoding
                        result = 0; // Reset for longitude decoding
                        do {
                            b = encoded.charCodeAt(index++) - 63;
                            result |= (b & 0x1f) << shift;
                            shift += 5;
                        } while (b >= 0x20);
                        const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
                        lng += dlng;
                        array.push([lng * 1e-5, lat * 1e-5]);
                    }
                    return array;
                };

                const getRouteAndDistance = async () => {
                    if (map && pickupCoords && dropoffCoords) {
                        const url = `https://api.mapbox.com/directions/v5/mapbox/driving/${pickupCoords.lng},${pickupCoords.lat};${dropoffCoords.lng},${dropoffCoords.lat}?geometries=polyline&access_token=${mapboxgl.accessToken}`;

                        if (distanceElement) {
                            distanceElement.textContent = 'Calculando distancia...';
                            distanceElement.classList.add('loading');
                        }

                        try {
                            const response = await fetch(url);
                            if (!response.ok) {
                                throw new Error(`Mapbox API error: ${response.statusText}`);
                            }
                            const data = await response.json();

                            if (data && data.routes && data.routes.length > 0) {
                                const routeGeometry = data.routes[0].geometry; // Renamed 'route' to 'routeGeometry' to avoid conflict
                                const decodedRoute = decodePolyline(routeGeometry);
                                const distanceMeters = data.routes[0].distance;
                                const distanceKm = (distanceMeters / 1000).toFixed(1);

                                if (distanceElement) {
                                    distanceElement.textContent = `Distancia estimada: ${distanceKm} km`;
                                    distanceElement.classList.remove('loading');
                                }

                                const geojson = {
                                    type: 'Feature',
                                    properties: {},
                                    geometry: {
                                        type: 'LineString',
                                        coordinates: decodedRoute
                                    }
                                };

                                if (map.getSource('route')) {
                                    map.getSource('route').setData(geojson);
                                } else {
                                    map.addSource('route', { type: 'geojson', data: geojson });
                                    map.addLayer({
                                        id: 'route',
                                        type: 'line',
                                        source: 'route',
                                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                                        paint: { 'line-color': '#3887be', 'line-width': 5, 'line-opacity': 0.75 }
                                    });
                                }

                                const bounds = new mapboxgl.LngLatBounds();
                                decodedRoute.forEach(coord => bounds.extend(coord));
                                map.fitBounds(bounds, { padding: 40 });
                            } else {
                                if (distanceElement) {
                                    distanceElement.textContent = 'No se pudo calcular la distancia.';
                                    distanceElement.classList.remove('loading');
                                }
                                console.warn('No routes found in Mapbox response.');
                            }
                        } catch (error) {
                            console.error('Error getting directions:', error);
                            if (distanceElement) {
                                distanceElement.textContent = 'Error al calcular la distancia.';
                                distanceElement.classList.remove('loading');
                            }
                            showFeedbackMessage(`Error al obtener ruta: ${error.message}`, 'error');
                        }
                    } else {
                        if (distanceElement) {
                            distanceElement.textContent = '';
                            distanceElement.classList.remove('loading');
                        }
                        if (map && map.getLayer('route')) map.removeLayer('route');
                        if (map && map.getSource('route')) map.removeSource('route');
                    }
                };

                const loadMapboxGeocoder = (callback) => {
                    if (typeof MapboxGeocoder !== 'undefined') {
                        callback();
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
                    script.onload = callback;
                    document.head.appendChild(script);
                };

                loadMapboxGeocoder(() => {
                    const pickupPlaceholder = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_pickup_placeholder_detailed) ? cachilupi_pet_vars.text_pickup_placeholder_detailed : 'Lugar de Recogida: Ingresa la dirección completa...';
                    const dropoffPlaceholder = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_dropoff_placeholder_detailed) ? cachilupi_pet_vars.text_dropoff_placeholder_detailed : 'Lugar de Destino: ¿A dónde irá tu mascota?';

                    if (pickupGeocoderContainer) {
                        pickupGeocoder = new MapboxGeocoder({
                            accessToken: mapboxgl.accessToken,
                            placeholder: pickupPlaceholder,
                            mapboxgl: mapboxgl,
                            bbox: [-75.6, -55.9, -66.4, -17.5], // Limit to Chile
                            country: 'cl',
                            limit: 5
                        });
                        pickupGeocoder.addTo(pickupGeocoderContainer);
                        // pickupGeocoderInput is assigned here, used later for aria-labelledby
                        pickupGeocoderInput = pickupGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
                        if (pickupGeocoderInput) {
                            // The ID 'pickup-location-input' is used by the label's 'for' attribute in PHP.
                            // Mapbox geocoder creates its own input, so we assign the ID here if possible,
                            // or rely solely on aria-labelledby if ID assignment is problematic.
                            // For now, we assume the label's `for` might not directly work.
                            // pickupGeocoderInput.id = 'pickup-location-input'; // This might be overwritten or cause issues.
                            pickupGeocoderInput.classList.add('form-control'); // For styling consistency
                            pickupGeocoderInput.addEventListener('input', () => validateForm(true));
                            pickupGeocoderInput.addEventListener('blur', () => validateForm(true));

                            // Set aria-labelledby
                            if (document.getElementById('pickup-location-label')) {
                                pickupGeocoderInput.setAttribute('aria-labelledby', 'pickup-location-label');
                            }
                        }

                        pickupGeocoder.on('result', (event) => {
                            const [lng, lat] = event.result.geometry.coordinates;
                            pickupCoords = { lng, lat };
                            if (pickupMarker) pickupMarker.remove();

                            const pickupIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" fill="#0073aa"><path d="M12 2L2 7v13h20V7L12 2zm0 2.09L19.22 7H4.78L12 4.09zM4 9h16v10H4V9zm2 1v2h2v-2H6zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2z"/></svg>`;
                            const elPickup = document.createElement('div');
                            elPickup.innerHTML = pickupIconSvg;
                            elPickup.style.width = '30px';
                            elPickup.style.height = '30px';
                            elPickup.style.cursor = 'pointer';

                            pickupMarker = new mapboxgl.Marker(elPickup).setLngLat([lng, lat]).addTo(map);
                            getRouteAndDistance();
                            validateForm(true);
                        });

                        pickupGeocoder.on('clear', () => {
                            pickupCoords = null;
                            if (pickupMarker) { pickupMarker.remove(); pickupMarker = null; }
                            getRouteAndDistance();
                            validateForm(true);
                        });
                    } else {
                        console.error('Pickup geocoder container not found!');
                    }

                    if (dropoffGeocoderContainer) {
                        dropoffGeocoder = new MapboxGeocoder({
                            accessToken: mapboxgl.accessToken,
                            mapboxgl: mapboxgl,
                            placeholder: dropoffPlaceholder,
                            bbox: [-75.6, -55.9, -66.4, -17.5], // Limit to Chile
                            country: 'cl',
                            limit: 5
                        });
                        dropoffGeocoder.addTo(dropoffGeocoderContainer);
                        // dropoffGeocoderInput is assigned here
                        dropoffGeocoderInput = dropoffGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
                        if (dropoffGeocoderInput) {
                            // dropoffGeocoderInput.id = 'dropoff-location-input'; // Similar to pickup, ID might be tricky.
                            dropoffGeocoderInput.classList.add('form-control');
                            dropoffGeocoderInput.addEventListener('input', () => validateForm(true));
                            dropoffGeocoderInput.addEventListener('blur', () => validateForm(true));

                            // Set aria-labelledby
                            if (document.getElementById('dropoff-location-label')) {
                                dropoffGeocoderInput.setAttribute('aria-labelledby', 'dropoff-location-label');
                            }
                        }

                        dropoffGeocoder.on('result', (event) => {
                            const [lng, lat] = event.result.geometry.coordinates;
                            dropoffCoords = { lng, lat };
                            if (dropoffMarker) dropoffMarker.remove();

                            const dropoffIconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" fill="#d32f2f"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"/></svg>`;
                            const elDropoff = document.createElement('div');
                            elDropoff.innerHTML = dropoffIconSvg;
                            elDropoff.style.width = '30px';
                            elDropoff.style.height = '30px';
                            elDropoff.style.cursor = 'pointer';

                            dropoffMarker = new mapboxgl.Marker(elDropoff).setLngLat([lng, lat]).addTo(map);
                            getRouteAndDistance();
                            validateForm(true);
                        });

                        dropoffGeocoder.on('clear', () => {
                            dropoffCoords = null;
                            if (dropoffMarker) { dropoffMarker.remove(); dropoffMarker = null; }
                            getRouteAndDistance();
                            validateForm(true);
                        });
                    } else {
                        console.error('Dropoff geocoder container not found!');
                    }
                });

                const showFeedbackMessage = (message, type = 'success') => {
                    // Remove any existing standardized feedback messages first
                    $('.cachilupi-feedback').remove(); // Target the new base class for removal

                    const feedbackClass = `cachilupi-feedback cachilupi-feedback--${type}`;
                    const messageElement = $('<div>')
                        .addClass(feedbackClass)
                        .text(message);

                    // ARIA roles for accessibility
                    if (type === 'error') {
                        messageElement.attr('role', 'alert');
                        messageElement.attr('aria-live', 'assertive');
                    } else {
                        messageElement.attr('role', 'status');
                        messageElement.attr('aria-live', 'polite');
                    }

                    const $bookingForm = $('.cachilupi-booking-form');

                    if (submitButton && $(submitButton).length) {
                        $(submitButton).after(messageElement);
                    } else if ($bookingForm.length) {
                        $bookingForm.prepend(messageElement);
                    } else {
                        $('body').prepend(messageElement);
                        console.warn('Submit button or booking form not found for feedback message. Appended to body.');
                    }

                    setTimeout(() => {
                        messageElement.fadeOut('slow', () => messageElement.remove());
                    }, 5000);
                };

                // This is the new Toast function
                const showCachilupiToast = (message, type = 'success', duration = 4000) => {
                    // Remover toasts existentes para evitar acumulación
                    $('.cachilupi-toast-notification').remove();

                    const toast = $('<div></div>')
                        .addClass('cachilupi-toast-notification')
                        .addClass(type) // success, error, info
                        .text(message);

                    $('body').append(toast);

                    // Forzar reflow para asegurar que la animación de entrada se ejecute
                    // toast.width();

                    // Añadir clase 'show' para activar la animación de entrada
                    setTimeout(() => { // Added a slight delay for CSS transition
                        toast.addClass('show');
                    }, 100);


                    // Auto-dismiss
                    if (duration > 0) {
                        setTimeout(() => {
                            toast.removeClass('show');
                            // Esperar a que la animación de salida termine antes de remover el elemento
                            setTimeout(() => {
                                toast.remove();
                            }, 300); // Debe coincidir con la duración de la transición en CSS
                        }, duration);
                    }
                };

                const showError = (fieldElement, message) => {
                    let formGroup = $(fieldElement).closest('.form-group');
                    if (!formGroup.length) formGroup = $(fieldElement).parent();
                    let targetElement = fieldElement;
                    // Special handling for geocoder containers to target the actual input within them
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0) || fieldElement;
                    }
                    let existingError = formGroup.find('.error-message');
                    if (!existingError.length) {
                        const errorSpan = $('<span>').addClass('error-message').text(message);
                        // Insert after the geocoder container, not inside it, or after the input itself
                        if ($(fieldElement).hasClass('geocoder-container')) {
                            $(fieldElement).after(errorSpan);
                        } else {
                            $(targetElement).after(errorSpan);
                        }
                    } else {
                        existingError.text(message);
                    }
                    $(targetElement).addClass('input-error'); // Add error class to the input
                    formGroup.find('label').addClass('label-error'); // Add error class to the label
                };

                const hideError = (fieldElement) => {
                    let formGroup = $(fieldElement).closest('.form-group');
                    if (!formGroup.length) formGroup = $(fieldElement).parent();
                    let targetElement = fieldElement;
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0) || fieldElement;
                    }
                    // Remove error message associated with the form group
                    formGroup.find('.error-message').remove();
                    // If error message was directly after geocoder container, remove it
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        $(fieldElement).next('.error-message').remove();
                    }
                    $(targetElement).removeClass('input-error');
                    formGroup.find('label').removeClass('label-error');
                };

                // const showCachilupiToast = (message, type = 'success', duration = 4000) => { // This is the duplicate
                //     // Remove any existing toasts
                //     $('.cachilupi-toast-notification').remove();
                //
                //     const toast = $('<div></div>')
                //         .addClass('cachilupi-toast-notification')
                //         .addClass(type) // 'success' or 'error' or 'info'
                //         .text(message);
                //
                //     $('body').append(toast);
                //
                //     // Trigger the animation
                //     setTimeout(() => {
                //         toast.addClass('show');
                //     }, 100); // Small delay to allow CSS transition
                //
                //     // Auto-dismiss
                //     if (duration > 0) {
                //         setTimeout(() => {
                //             toast.removeClass('show');
                //             setTimeout(() => {
                //                 toast.remove();
                //             }, 300); // Wait for fade out animation
                //         }, duration);
                //     }
                // }


                const validateForm = (isMapContext = true) => {
                    let isValid = true;
                    if (!pickupGeocoderInput || !pickupGeocoderInput.value.trim() || (isMapContext && !pickupCoords)) {
                        if (pickupGeocoderContainer) showError(pickupGeocoderContainer, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                        if (pickupGeocoderContainer) hideError(pickupGeocoderContainer);
                    }
                    if (!dropoffGeocoderInput || !dropoffGeocoderInput.value.trim() || (isMapContext && !dropoffCoords)) {
                        if (dropoffGeocoderContainer) showError(dropoffGeocoderContainer, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                        if (dropoffGeocoderContainer) hideError(dropoffGeocoderContainer);
                    }
                    if (!serviceDateInput || !serviceDateInput.value) {
                        if (serviceDateInput) showError(serviceDateInput, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                        if (serviceDateInput) hideError(serviceDateInput);
                    }
                    if (!serviceTimeInput || !serviceTimeInput.value) {
                        if (serviceTimeInput) showError(serviceTimeInput, 'Este campo es obligatorio.');
                        isValid = false;
                    } else {
                        if (serviceTimeInput) hideError(serviceTimeInput);
                    }
                    if (petTypeSelect && !petTypeSelect.value) {
                        showError(petTypeSelect, 'Este campo es obligatorio.');
                        isValid = false;
                    } else if (petTypeSelect) {
                        hideError(petTypeSelect);
                    }
                    if (submitButton) submitButton.disabled = !isValid;
                    return isValid;
                };

                if (serviceDateInput) {
                    serviceDateInput.addEventListener('input', () => validateForm(true));
                    const today = new Date();
                    const dd = String(today.getDate()).padStart(2, '0');
                    const mm = String(today.getMonth() + 1).padStart(2, '0');
                    const year = today.getFullYear();
                    serviceDateInput.setAttribute('min', `${year}-${mm}-${dd}`);
                }
                if (serviceTimeInput) {
                    serviceTimeInput.addEventListener('input', () => validateForm(true));
                    serviceTimeInput.addEventListener('blur', function() { // Keep `this` for input value, or use event.target if arrow fn
                        const timeValue = this.value;
                        if (timeValue) {
                            let [hours, minutes] = timeValue.split(':'); // Can use const if not reassigned, but let is fine for split parts
                            minutes = parseInt(minutes, 10);
                            let roundedMinutes = Math.round(minutes / 5) * 5;
                            if (roundedMinutes === 60) {
                                hours = (parseInt(hours, 10) + 1) % 24;
                                roundedMinutes = 0;
                            }
                            this.value = `${String(hours).padStart(2, '0')}:${String(roundedMinutes).padStart(2, '0')}`;
                        }
                        validateForm(true); // `this` here refers to serviceTimeInput
                    });
                }
                if (petTypeSelect) petTypeSelect.addEventListener('change', () => validateForm(true));
                if (notesTextArea) notesTextArea.addEventListener('input', () => validateForm(true));
                if (petInstructionsTextArea) petInstructionsTextArea.addEventListener('input', () => validateForm(true));

                if (submitButton) {
                    submitButton.addEventListener('click', async (event) => { 
                        event.preventDefault();
                        if (!validateForm(true)) {
                            // showCachilupiToast is already called by validateForm if it returns false during submission attempt.
                            // No need to call it again here if validateForm itself handles it.
                            // However, the prompt asks to ensure validateForm uses it.
                            // The current validateForm does not use showCachilupiToast, it uses showError.
                            // So, we'll add the toast here as requested.
                            showCachilupiToast('Por favor, corrige los errores en el formulario.', 'error');
                            return;
                        }

                        const $button = $(submitButton); // Use jQuery object for consistency
                        const originalButtonText = $button.text();
                        $button.prop('disabled', true).text('Enviando Solicitud...').addClass('loading');

                        // Remove old inline feedback if any, toasts will be used for submission feedback
                        $('.cachilupi-feedback').remove();

                        const pickupAddress = pickupGeocoderInput ? pickupGeocoderInput.value : '';
                        const dropoffAddress = dropoffGeocoderInput ? dropoffGeocoderInput.value : '';
                        const petType = petTypeSelect ? petTypeSelect.value : '';
                        const notes = notesTextArea ? notesTextArea.value : '';
                        const petInstructions = petInstructionsTextArea ? petInstructionsTextArea.value : '';
                        const serviceDate = serviceDateInput ? serviceDateInput.value : '';
                        const serviceTime = serviceTimeInput ? serviceTimeInput.value : '';
                        let scheduledDateTime;

                        if (serviceDate && serviceTime) {
                            scheduledDateTime = `${serviceDate} ${serviceTime}:00`;
                        } else {
                            showFeedbackMessage('Error interno: Falta fecha o hora.', 'error');
                            submitButton.classList.remove('loading-spinner');
                            submitButton.disabled = false;
                            submitButton.textContent = 'Solicitar Servicio';
                            return;
                        }

                        const serviceRequestData = {
                            scheduled_date_time: scheduledDateTime,
                            pickup_address: pickupAddress,
                            pickup_lat: pickupCoords ? pickupCoords.lat : 0.0,
                            pickup_lon: pickupCoords ? pickupCoords.lng : 0.0,
                            dropoff_address: dropoffAddress,
                            dropoff_lat: dropoffCoords ? dropoffCoords.lat : 0.0,
                            dropoff_lon: dropoffCoords ? dropoffCoords.lng : 0.0,
                            pet_type: petType,
                            notes: notes,
                            pet_instructions: petInstructions,
                        };

                        const formData = new FormData();
                        formData.append('action', 'cachilupi_pet_submit_request');
                        formData.append('security', cachilupi_pet_vars.submit_request_nonce);
                        for (const key in serviceRequestData) {
                            formData.append(key, serviceRequestData[key]);
                        }

                        let fetchResponse; 

                        try {
                            fetchResponse = await fetch(cachilupi_pet_vars.ajaxurl, { 
                                method: 'POST',
                                body: formData
                            });

                            if (!fetchResponse.ok) {
                                let errorMsg = `Error HTTP: ${fetchResponse.status}`;
                                try {
                                    const errorText = await fetchResponse.text();
                                    console.error("Raw error response from server (Map Context):", errorText);
                                    try {
                                        const errorData = JSON.parse(errorText); // Try to parse as JSON
                                        errorMsg = errorData.data && errorData.data.message ? errorData.data.message : `Error del servidor: ${errorText.substring(0,100)}`;
                                    } catch (e) { // If not JSON
                                        errorMsg = `Error del servidor: ${errorText.substring(0,100)}`;
                                    }
                                } catch (e) { /* Ignore if reading text also fails */ }
                                throw new Error(errorMsg);
                            }

                            const responseData = await fetchResponse.json(); 

                            if (responseData.success) {
                                showCachilupiToast(responseData.data.message || 'Solicitud enviada con éxito.', 'success');
                                // Reset form fields
                                if (pickupGeocoder) pickupGeocoder.clear();
                                if (dropoffGeocoder) dropoffGeocoder.clear(); // Clear Mapbox geocoder
                                // For Flatpickr instances, use their clear method
                                if (window.flatpickr && serviceDateInput._flatpickr) serviceDateInput._flatpickr.clear();
                                if (window.flatpickr && serviceTimeInput._flatpickr) serviceTimeInput._flatpickr.clear();

                                // Fallback if Flatpickr instance not available on element, or for other fields
                                if (pickupGeocoderInput) pickupGeocoderInput.value = '';
                                if (dropoffGeocoderInput) dropoffGeocoderInput.value = '';
                                if (serviceDateInput && !serviceDateInput._flatpickr) serviceDateInput.value = '';
                                if (serviceTimeInput) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = '';
                                if (serviceTimeInput && !serviceTimeInput._flatpickr) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = ''; // Reset select
                                if (notesTextArea) notesTextArea.value = '';
                                if (petInstructionsTextArea) petInstructionsTextArea.value = '';

                                // Clear map markers and route
                                if (pickupMarker) pickupMarker.remove();
                                if (dropoffMarker) dropoffMarker.remove();
                                if (map && map.getSource('route')) {
                                    map.removeLayer('route');
                                    map.removeSource('route');
                                }
                                pickupCoords = null;
                                dropoffCoords = null;
                                if (distanceElement) distanceElement.textContent = '';

                                validateForm(true); // Re-validate to update button state (should be disabled)
                            } else {
                                const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al guardar la solicitud.';
                                showCachilupiToast(errorMessage, 'error');
                            }
                        } catch (error) {
                            console.error('Fetch Error (Map Context):', error);
                            showCachilupiToast(`Error de comunicación: ${error.message}`, 'error');
                        } finally {
                            if ($button) {
                                $button.removeClass('loading').text(originalButtonText).prop('disabled', false);
                                // Re-validate after form reset or error to set button state correctly
                                validateForm(true);
                            }
                        }
                    });
                }
                validateForm(true);
            } else {
                console.error('Mapbox GL JS is not loaded or access token missing.');
                handleFormWithoutMap();
            }
        } else {
            handleFormWithoutMap();
        }

        const handleFormWithoutMap = () => { 
            if (!serviceDateInput) serviceDateInput = document.getElementById('service-date');
            if (!serviceTimeInput) serviceTimeInput = document.getElementById('service-time');
            if (!petTypeSelect) petTypeSelect = document.getElementById('cachilupi-pet-pet-type');
            if (!notesTextArea) notesTextArea = document.getElementById('cachilupi-pet-notes');
            if (!petInstructionsTextArea) petInstructionsTextArea = document.getElementById('cachilupi-pet-instructions');
            if (!submitButton) submitButton = document.getElementById('submit-service-request');
            pickupGeocoderInput = document.getElementById('pickup-location-input'); 
            dropoffGeocoderInput = document.getElementById('dropoff-location-input');
            if (!pickupGeocoderContainer) pickupGeocoderContainer = document.getElementById('pickup-geocoder-container'); 
            if (!dropoffGeocoderContainer) dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container');
            if (!distanceElement) distanceElement = document.getElementById('cachilupi-pet-distance');

            const validateFormNoMap = () => validateForm(false);

            if (pickupGeocoderInput) {
                pickupGeocoderInput.addEventListener('input', validateFormNoMap);
                pickupGeocoderInput.addEventListener('blur', validateFormNoMap);
            }
            if (dropoffGeocoderInput) {
                dropoffGeocoderInput.addEventListener('input', validateFormNoMap);
                dropoffGeocoderInput.addEventListener('blur', validateFormNoMap);
            }
            if (localServiceDateInput) { // Use local scoped variable
                localServiceDateInput.addEventListener('input', validateFormNoMap);
                // Apply min date for non-Flatpickr date input
                if (!localServiceDateInput._flatpickr) { // Check if it's not a flatpickr instance
                     const today = new Date();
                     const dd = String(today.getDate()).padStart(2, '0');
                     const mm = String(today.getMonth() + 1).padStart(2, '0');
                     const year = today.getFullYear();
                     localServiceDateInput.setAttribute('min', `${year}-${mm}-${dd}`);
                }
            }
            if (localServiceTimeInput) { // Use local scoped variable
                localServiceTimeInput.addEventListener('input', validateFormNoMap);
                localServiceTimeInput.addEventListener('blur', function() { // Keep `this` for input value
                    const timeValue = this.value;
                    if (timeValue) {
                        let [hours, minutes] = timeValue.split(':');
                        minutes = parseInt(minutes, 10);
                        let roundedMinutes = Math.round(minutes / 5) * 5;
                        if (roundedMinutes === 60) {
                            hours = (parseInt(hours, 10) + 1) % 24;
                            roundedMinutes = 0;
                        }
                        this.value = `${String(hours).padStart(2, '0')}:${String(roundedMinutes).padStart(2, '0')}`;
                    }
                    validateFormNoMap();
                });
            }
            if (localPetTypeSelect) localPetTypeSelect.addEventListener('change', validateFormNoMap); // Use local
            if (localNotesTextArea) localNotesTextArea.addEventListener('input', validateFormNoMap); // Use local
            if (localPetInstructionsTextArea) localPetInstructionsTextArea.addEventListener('input', validateFormNoMap); // Use local


            if (submitButton) {
                submitButton.addEventListener('click', async (event) => { 
                    event.preventDefault();
                        if (validateForm(false)) {
                            const $button = $(submitButton);
                            const originalButtonText = $button.text();
                            $button.prop('disabled', true).text('Enviando Solicitud...').addClass('loading');

                            $('.cachilupi-feedback').remove();

                        const pickupAddress = pickupGeocoderInput ? pickupGeocoderInput.value : '';
                        const dropoffAddress = dropoffGeocoderInput ? dropoffGeocoderInput.value : '';
                        const petType = petTypeSelect ? petTypeSelect.value : '';
                        const notes = notesTextArea ? notesTextArea.value : '';
                        const petInstructions = petInstructionsTextArea ? petInstructionsTextArea.value : '';
                        const serviceDate = serviceDateInput ? serviceDateInput.value : '';
                        const serviceTime = serviceTimeInput ? serviceTimeInput.value : '';
                        const scheduledDateTime = (serviceDate && serviceTime) ? `${serviceDate} ${serviceTime}:00` : '';

                        const serviceRequestData = {
                            scheduled_date_time: scheduledDateTime,
                            pickup_address: pickupAddress, pickup_lat: 0.0, pickup_lon: 0.0,
                            dropoff_address: dropoffAddress, dropoff_lat: 0.0, dropoff_lon: 0.0,
                            pet_type: petType, notes: notes, pet_instructions: petInstructions,
                        };

                        const formData = new FormData();
                        formData.append('action', 'cachilupi_pet_submit_request');
                        formData.append('security', cachilupi_pet_vars.submit_request_nonce);
                        for (const key in serviceRequestData) {
                            formData.append(key, serviceRequestData[key]);
                        }
                        let fetchResponse; 

                        try {
                            fetchResponse = await fetch(cachilupi_pet_vars.ajaxurl, { 
                                method: 'POST',
                                body: formData
                            });

                            if (!fetchResponse.ok) {
                                let errorMsg = `Error HTTP: ${fetchResponse.status}`;
                                try {
                                    const errorText = await fetchResponse.text();
                                    console.error("Raw error response from server (Non-Map Context):", errorText);
                                    try {
                                     const errorData = JSON.parse(errorText);
                                     errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
                                    } catch(e){
                                        errorMsg = `Server error: ${fetchResponse.statusText}. Check console for raw response.`;
                                    }
                                } catch (e) {  }
                                throw new Error(errorMsg);
                            }

                            const responseData = await fetchResponse.json(); 

                            if (responseData.success) {
                                // Replaced showFeedbackMessage with showCachilupiToast
                                showCachilupiToast('Solicitud enviada con éxito.', 'success');
                                if (pickupGeocoderInput) pickupGeocoderInput.value = '';
                                if (dropoffGeocoderInput) dropoffGeocoderInput.value = '';
                                if (serviceDateInput) serviceDateInput.value = '';
                                if (serviceTimeInput) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = '';
                                if (serviceDateInput && !serviceDateInput._flatpickr) serviceDateInput.value = '';
                                if (serviceTimeInput && !serviceTimeInput._flatpickr) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = '';
                                if (notesTextArea) notesTextArea.value = '';
                                if (petInstructionsTextArea) petInstructionsTextArea.value = '';
                                if (distanceElement) distanceElement.textContent = '';
                                validateForm(false); // Re-validate
                            } else {
                                const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al guardar la solicitud.';
                                showCachilupiToast(errorMessage, 'error');
                            }
                        } catch (error) {
                            console.error('Fetch Error (Non-Map Context):', error);
                            showCachilupiToast(`Error de comunicación: ${error.message}`, 'error');
                        } finally {
                           if ($button) {
                                $button.removeClass('loading').text(originalButtonText).prop('disabled', false);
                                validateForm(false); // Re-validate after form reset or error
                            }
                        }
                    } else {
                         // This case is when validateForm(false) initially returns false
                         showCachilupiToast('Por favor, corrige los errores en el formulario.', 'error');
                    }
                });
            }
            validateForm(false); // Initial validation for non-map context
        };
        // --- End handleFormWithoutMap ---

        // Initialize form validation (call once to set initial button state)
        // This needs to be called after all input elements are potentially defined (Mapbox geocoder inputs specifically)
        // The `loadMapboxGeocoder` callback or a timeout could be a place for this.
        // For now, validateForm is called within event listeners and before submission.
        // If Flatpickr is present, its onChange also calls validateForm.
        // If no Mapbox, handleFormWithoutMap calls validateForm.

        // --- Seguimiento del Conductor para el Cliente ---
        let clientFollowMap = null;
        let followInterval = null;
        let currentFollowingRequestId = null;
        let driverMarker = null;

        $(document).on('click', '.cachilupi-follow-driver-btn', function() { 
            const $button = $(this); 
            currentFollowingRequestId = $button.data('request-id');

            if (!currentFollowingRequestId) {
                console.error('Error: No se pudo obtener el ID de la solicitud para seguir.');
                alert(cachilupi_pet_vars.text_driver_location_not_available || 'No se pudo obtener el ID de la solicitud.');
                return;
            }
            $('#cachilupi-follow-modal').show();

            if (typeof mapboxgl !== 'undefined' && !mapboxgl.accessToken && cachilupi_pet_vars.mapbox_access_token) {
                mapboxgl.accessToken = cachilupi_pet_vars.mapbox_access_token;
            }

            if (typeof mapboxgl === 'undefined' || !mapboxgl.accessToken) {
                console.error("Mapbox GL JS no está cargado o falta el token de acceso.");
                alert("Error al cargar el mapa: Mapbox no está disponible.");
                $('#cachilupi-follow-modal').hide();
                return;
            }

            if (!clientFollowMap || clientFollowMap.getContainer().id !== 'cachilupi-client-follow-map') {
                if (clientFollowMap) { clientFollowMap.remove(); clientFollowMap = null; }
                try {
                    clientFollowMap = new mapboxgl.Map({
                        container: 'cachilupi-client-follow-map',
                        style: 'mapbox://styles/mapbox/streets-v11',
                        center: [-70.6693, -33.4489], zoom: 9
                    });
                    clientFollowMap.addControl(new mapboxgl.NavigationControl());
                    clientFollowMap.on('load', () => clientFollowMap.resize() ); 
                } catch (e) {
                    console.error("Error inicializando el mapa de seguimiento del cliente:", e);
                    alert("Error al cargar el mapa de seguimiento.");
                    $('#cachilupi-follow-modal').hide();
                    return;
                }
            }

            if (clientFollowMap) clientFollowMap.resize();
            if (followInterval) clearInterval(followInterval);

            fetchDriverLocationForClient(currentFollowingRequestId);
            followInterval = setInterval(() => fetchDriverLocationForClient(currentFollowingRequestId), 15000); 
        });

        $('#cachilupi-close-follow-modal').on('click', () => { 
            $('#cachilupi-follow-modal').hide();
            if (followInterval) { clearInterval(followInterval); followInterval = null; }
            currentFollowingRequestId = null;
            if (driverMarker) { driverMarker.remove(); driverMarker = null; }
        });

        const fetchDriverLocationForClient = async (requestId) => {
            if (!requestId) {
                console.warn('fetchDriverLocationForClient: No requestId provided.');
                return;
            }

            const url = new URL(cachilupi_pet_vars.ajaxurl);
            url.searchParams.append('action', 'cachilupi_get_driver_location');
            url.searchParams.append('request_id', requestId);
            url.searchParams.append('security', cachilupi_pet_vars.get_location_nonce);

            try {
                const response = await fetch(url);

                if (!response.ok) {
                    let errorMsg = `Error HTTP: ${response.status}`;
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
                    } catch (e) { /* Ignore */ }
                    throw new Error(errorMsg);
                }

                const responseData = await response.json();

                if (responseData.success && responseData.data.latitude && responseData.data.longitude) {
                    const { longitude, latitude } = responseData.data; // Destructuring
                    const driverPosition = [parseFloat(longitude), parseFloat(latitude)];
                    if (clientFollowMap && typeof clientFollowMap.getStyle === 'function') {
                        if (!driverMarker) {
                            driverMarker = new mapboxgl.Marker().setLngLat(driverPosition).addTo(clientFollowMap);
                        } else {
                            driverMarker.setLngLat(driverPosition);
                        }
                        clientFollowMap.flyTo({ center: driverPosition, zoom: 15 });
                        $('#cachilupi-follow-modal-title').text(`${cachilupi_pet_vars.text_follow_driver || 'Siguiendo Viaje'} ID: ${requestId}`);
                    } else {
                        console.warn('Mapa de seguimiento no disponible para actualizar marcador.');
                    }
                } else {
                    console.warn('Ubicación del conductor no disponible:', responseData.data ? responseData.data.message : 'Respuesta del servidor no exitosa.');
                    $('#cachilupi-follow-modal-title').text(`${cachilupi_pet_vars.text_driver_location_not_available || 'Ubicación no disponible'} (ID: ${requestId})`);
                    if (driverMarker) { driverMarker.remove(); driverMarker = null; }
                }
            } catch (error) {
                console.error('Error al obtener ubicación del conductor:', error.message);
                $('#cachilupi-follow-modal-title').text('Error al obtener ubicación');
            }
        };

        const showGlobalToast = (message, type = 'info', duration = 4000) => {
            $('.cachilupi-toast-notification').remove();
            const toast = $('<div>').addClass('cachilupi-toast-notification').addClass(type).text(message).appendTo('body');
            // toast.width(); // Force reflow - can be problematic
            setTimeout(() => toast.addClass('show'), 10); // Ensure transition by adding class after element is in DOM
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 500);
            }, duration);
        };

        const updateClientRequestsTable = (statuses) => {
            if (!statuses || !Array.isArray(statuses)) {
                console.warn('updateClientRequestsTable: `statuses` is undefined or not an array. Called with:', statuses);
                return;
            }
            
            const followButtonText = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_follow_driver)
                               ? cachilupi_pet_vars.text_follow_driver
                               : 'Seguir Viaje';

            $('.cachilupi-client-requests-panel table.widefat tbody tr').each(function() { // Keep `this` for jQuery context
                const $row = $(this);
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
                    const { status_slug: newStatusSlugFromServer, status_display: newStatusDisplay, driver_id: driverIdForButton } = requestUpdate; // Destructuring

                    if (oldStatusDisplay !== newStatusDisplay) {
                        currentStatusCell.find('span').text(newStatusDisplay);
                        currentStatusCell.removeClass (function (index, className) {
                            return (className.match (/(^|\s)request-status-\S+/g) || []).join(' ');
                        }).addClass(`request-status-${newStatusSlugFromServer}`);

                        if (oldStatusDisplay && oldStatusDisplay !== '--' && oldStatusDisplay !== newStatusDisplay) {
                            showGlobalToast(`Tu solicitud #${requestId} ahora está: ${newStatusDisplay}`, 'info');
                        }
                    }
                    
                    let followCellHTML = '--';
                    const statusSlugForSwitch = newStatusSlugFromServer;

                    switch (statusSlugForSwitch) {
                        case 'on_the_way':
                        case 'picked_up': // Combined case as button is similar
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
            if ($('.cachilupi-client-requests-panel').length === 0) {
                if (clientRequestsStatusInterval) {
                    clearInterval(clientRequestsStatusInterval);
                    clientRequestsStatusInterval = null;
                }
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
                        try {
                            const errorData = JSON.parse(errorText);
                            errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
                        } catch (e_json) {
                             errorMsg = `Server error (${response.status}). Check console for raw response.`;
                        }
                    } catch (e_text) { /* Ignore */ }
                    throw new Error(errorMsg);
                }
                const responseData = await response.json();
                
                if (responseData.success && responseData.data) {
                    updateClientRequestsTable(responseData.data);
                } else {
                    console.warn('fetchClientRequestsStatus: Response not successful or data missing. Full responseData:', responseData);
                }
            } catch (error) {
                console.error('fetchClientRequestsStatus: Error during fetch:', error);
            }
        };

        if ($('.cachilupi-client-requests-panel').length > 0) {
            fetchClientRequestsStatus();
            clientRequestsStatusInterval = setInterval(fetchClientRequestsStatus, 20000);
        }
    });
})(jQuery);
