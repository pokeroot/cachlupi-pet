(function($) { // IIFE remains for jQuery DOM usage
    // Set Mapbox access token
    if (typeof mapboxgl !== 'undefined') {
        mapboxgl.accessToken = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) ? cachilupi_pet_vars.mapbox_access_token : null;
        if (!mapboxgl.accessToken) {
            console.warn('Mapbox Access Token no está configurado. Las funcionalidades del mapa estarán deshabilitadas.');
        }
    }

    jQuery(document).ready(($) => { // Arrow function for ready callback
        const mapElement = document.getElementById('cachilupi-pet-map');

        // --- Declare variables ---
        let map = null;
        let pickupGeocoderContainer = document.getElementById('pickup-geocoder-container');
        let dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container');
        let serviceDateInput = document.getElementById('service-date');
        let serviceTimeInput = document.getElementById('service-time');
        let submitButton = document.getElementById('submit-service-request');
        let petTypeSelect = document.getElementById('cachilupi-pet-pet-type');
        let notesTextArea = document.getElementById('cachilupi-pet-notes');
        let petInstructionsTextArea = document.getElementById('cachilupi-pet-instructions');
        let distanceElement = document.getElementById('cachilupi-pet-distance');

        let pickupGeocoder = null;
        let dropoffGeocoder = null;
        let pickupGeocoderInput = null;
        let dropoffGeocoderInput = null;

        let pickupCoords = null;
        let dropoffCoords = null;
        let pickupMarker = null;
        let dropoffMarker = null;

        let clientRequestsStatusInterval = null;
        // --- End variable declarations ---

        if (mapElement) {
            if (typeof mapboxgl !== 'undefined' && mapboxgl.accessToken) {
                map = new mapboxgl.Map({
                    container: 'cachilupi-pet-map',
                    style: 'mapbox://styles/mapbox/streets-v11',
                    center: [-70.6693, -33.4489],
                    zoom: 10
                });

                window.addEventListener('resize', () => {
                    if (map) map.resize();
                });

                const decodePolyline = (encoded) => {
                    let len = encoded.length,
                        index = 0,
                        array = [],
                        lat = 0,
                        lng = 0;

                    while (index < len) {
                        let b, shift = 0, result = 0;
                        do {
                            b = encoded.charCodeAt(index++) - 63;
                            result |= (b & 0x1f) << shift;
                            shift += 5;
                        } while (b >= 0x20);
                        const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
                        lat += dlat;

                        shift = 0;
                        result = 0;
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
                                const route = data.routes[0].geometry; // Ensure 'geometries=polyline' is in request
                                const decodedRoute = decodePolyline(route);
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
                    if (pickupGeocoderContainer) {
                        pickupGeocoder = new MapboxGeocoder({
                            accessToken: mapboxgl.accessToken,
                            placeholder: 'Buscar Lugar de Recogida',
                            mapboxgl: mapboxgl,
                            bbox: [-75.6, -55.9, -66.4, -17.5],
                            country: 'cl',
                            limit: 5
                        });
                        pickupGeocoder.addTo(pickupGeocoderContainer);
                        pickupGeocoderInput = pickupGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
                        if (pickupGeocoderInput) {
                            pickupGeocoderInput.id = 'pickup-location-input';
                            pickupGeocoderInput.classList.add('form-control');
                            pickupGeocoderInput.addEventListener('input', () => validateForm(true));
                            pickupGeocoderInput.addEventListener('blur', () => validateForm(true));
                        }

                        pickupGeocoder.on('result', (event) => {
                            const lngLat = event.result.geometry.coordinates;
                            pickupCoords = { lng: lngLat[0], lat: lngLat[1] };
                            if (pickupMarker) pickupMarker.remove();
                            pickupMarker = new mapboxgl.Marker({ color: '#0073aa' }).setLngLat(lngLat).addTo(map);
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
                            placeholder: 'Buscar Lugar de Destino',
                            bbox: [-75.6, -55.9, -66.4, -17.5],
                            country: 'cl',
                            limit: 5
                        });
                        dropoffGeocoder.addTo(dropoffGeocoderContainer);
                        dropoffGeocoderInput = dropoffGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
                        if (dropoffGeocoderInput) {
                            dropoffGeocoderInput.id = 'dropoff-location-input';
                            dropoffGeocoderInput.classList.add('form-control');
                            dropoffGeocoderInput.addEventListener('input', () => validateForm(true));
                            dropoffGeocoderInput.addEventListener('blur', () => validateForm(true));
                        }

                        dropoffGeocoder.on('result', (event) => {
                            const lngLat = event.result.geometry.coordinates;
                            dropoffCoords = { lng: lngLat[0], lat: lngLat[1] };
                            if (dropoffMarker) dropoffMarker.remove();
                            dropoffMarker = new mapboxgl.Marker({ color: '#d32f2f' }).setLngLat(lngLat).addTo(map);
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

                const showError = (fieldElement, message) => {
                    let formGroup = $(fieldElement).closest('.form-group');
                    if (!formGroup.length) formGroup = $(fieldElement).parent();
                    let targetElement = fieldElement;
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0) || fieldElement;
                    }
                    let existingError = formGroup.find('.error-message');
                    if (!existingError.length) {
                        const errorSpan = $('<span>').addClass('error-message').text(message);
                        $(targetElement).after(errorSpan);
                    } else {
                        existingError.text(message);
                    }
                    $(targetElement).addClass('input-error');
                    formGroup.find('label').addClass('label-error');
                };

                const hideError = (fieldElement) => {
                    let formGroup = $(fieldElement).closest('.form-group');
                    if (!formGroup.length) formGroup = $(fieldElement).parent();
                    let targetElement = fieldElement;
                    if ($(fieldElement).hasClass('geocoder-container')) {
                        targetElement = $(fieldElement).find('.mapboxgl-ctrl-geocoder--input').get(0) || fieldElement;
                    }
                    formGroup.find('.error-message').remove();
                    $(targetElement).removeClass('input-error');
                    formGroup.find('label').removeClass('label-error');
                };

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
                    serviceTimeInput.addEventListener('blur', function() { 
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
                        validateForm(true);
                    });
                }
                if (petTypeSelect) petTypeSelect.addEventListener('change', () => validateForm(true));
                if (notesTextArea) notesTextArea.addEventListener('input', () => validateForm(true));
                if (petInstructionsTextArea) petInstructionsTextArea.addEventListener('input', () => validateForm(true)); 

                if (submitButton) {
                    submitButton.addEventListener('click', async (event) => { 
                        event.preventDefault();
                        if (!validateForm(true)) return;

                        submitButton.disabled = true;
                        submitButton.textContent = 'Enviando Solicitud...';
                        submitButton.classList.add('cachilupi-button--loading');
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
                                        const errorData = JSON.parse(errorText);
                                        errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
                                    } catch (e) {
                                        errorMsg = `Server error: ${fetchResponse.statusText}. Check console for raw response.`;
                                    }
                                } catch (e) {  }
                                throw new Error(errorMsg);
                            }

                            const responseData = await fetchResponse.json(); 

                            if (responseData.success) {
                                showFeedbackMessage('Solicitud enviada con éxito.', 'success');
                                if (pickupGeocoderInput) pickupGeocoderInput.value = '';
                                if (dropoffGeocoderInput) dropoffGeocoderInput.value = '';
                                if (serviceDateInput) serviceDateInput.value = '';
                                if (serviceTimeInput) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = '';
                                if (notesTextArea) notesTextArea.value = '';
                                if (petInstructionsTextArea) petInstructionsTextArea.value = '';
                                if (pickupMarker) pickupMarker.remove();
                                if (dropoffMarker) dropoffMarker.remove();
                                if (map && map.getLayer('route')) map.removeLayer('route');
                                if (map && map.getSource('route')) map.removeSource('route');
                                pickupCoords = null; dropoffCoords = null;
                                if (distanceElement) distanceElement.textContent = '';
                                validateForm(true);
                            } else {
                                const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al guardar la solicitud.';
                                showFeedbackMessage(errorMessage, 'error');
                            }
                        } catch (error) {
                            console.error('Fetch Error (Map Context):', error); 

                            if (error instanceof SyntaxError && fetchResponse) { 
                                fetchResponse.text().then(text => {
                                    console.error("Raw non-JSON response from server (Map Context):", text);
                                    showFeedbackMessage(`Error del servidor: Formato de respuesta inesperado. Revise la consola para más detalles.`, 'error');
                                }).catch(textError => {
                                    console.error("Error trying to read raw response text (Map Context):", textError);
                                    showFeedbackMessage(`Error de comunicación: ${error.message}. Además, la respuesta del servidor no pudo ser leída como texto.`, 'error');
                                });
                            } else if (!fetchResponse) {
                                showFeedbackMessage(`Error de red o comunicación: ${error.message}. No se recibió respuesta del servidor.`, 'error');
                            } else {
                                showFeedbackMessage(`Error de comunicación: ${error.message}`, 'error');
                            }
                        } finally {
                            if (submitButton) {
                                submitButton.classList.remove('cachilupi-button--loading');
                                submitButton.disabled = false;
                                submitButton.textContent = 'Solicitar Servicio';
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
            if (serviceDateInput) {
                serviceDateInput.addEventListener('input', validateFormNoMap);
                const today = new Date();
                const dd = String(today.getDate()).padStart(2, '0');
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const year = today.getFullYear();
                serviceDateInput.setAttribute('min', `${year}-${mm}-${dd}`);
            }
            if (serviceTimeInput) {
                serviceTimeInput.addEventListener('input', validateFormNoMap);
                serviceTimeInput.addEventListener('blur', function() { 
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
            if (petTypeSelect) petTypeSelect.addEventListener('change', validateFormNoMap);
            if (notesTextArea) notesTextArea.addEventListener('input', validateFormNoMap);
            if (petInstructionsTextArea) petInstructionsTextArea.addEventListener('input', validateFormNoMap); 


            if (submitButton) {
                submitButton.addEventListener('click', async (event) => { 
                    event.preventDefault();
                    if (validateForm(false)) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Enviando Solicitud...';
                        submitButton.classList.add('cachilupi-button--loading');
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
                                showFeedbackMessage('Solicitud enviada con éxito.', 'success');
                                if (pickupGeocoderInput) pickupGeocoderInput.value = '';
                                if (dropoffGeocoderInput) dropoffGeocoderInput.value = '';
                                if (serviceDateInput) serviceDateInput.value = '';
                                if (serviceTimeInput) serviceTimeInput.value = '';
                                if (petTypeSelect) petTypeSelect.value = '';
                                if (notesTextArea) notesTextArea.value = '';
                                if (petInstructionsTextArea) petInstructionsTextArea.value = '';
                                if (distanceElement) distanceElement.textContent = '';
                                validateForm(false);
                            } else {
                                const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al guardar la solicitud.';
                                showFeedbackMessage(errorMessage, 'error');
                            }
                        } catch (error) {
                            console.error('Fetch Error (Non-Map Context):', error); 

                            if (error instanceof SyntaxError && fetchResponse) {
                                fetchResponse.text().then(text => {
                                    console.error("Raw non-JSON response from server (Non-Map Context):", text);
                                    showFeedbackMessage(`Error del servidor: Formato de respuesta inesperado. Revise la consola para más detalles.`, 'error');
                                }).catch(textError => {
                                    console.error("Error trying to read raw response text (Non-Map Context):", textError);
                                    showFeedbackMessage(`Error de comunicación: ${error.message}. Además, la respuesta del servidor no pudo ser leída como texto.`, 'error');
                                });
                            } else if (!fetchResponse) {
                                showFeedbackMessage(`Error de red o comunicación: ${error.message}. No se recibió respuesta del servidor.`, 'error');
                            } else {
                                showFeedbackMessage(`Error de comunicación: ${error.message}`, 'error');
                            }
                        } finally {
                            if (submitButton) {
                                submitButton.classList.remove('cachilupi-button--loading');
                                submitButton.disabled = false;
                                submitButton.textContent = 'Solicitar Servicio';
                            }
                        }
                    }
                });
            }
            validateForm(false);
        };
        // --- End handleFormWithoutMap ---

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
                    } catch (e) {  }
                    throw new Error(errorMsg);
                }

                const responseData = await response.json();

                if (responseData.success && responseData.data.latitude && responseData.data.longitude) {
                    const driverPosition = [parseFloat(responseData.data.longitude), parseFloat(responseData.data.latitude)];
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
            toast.width();
            toast.addClass('show');
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

            $('.cachilupi-client-requests-panel table.widefat tbody tr').each(function() {
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
                    const newStatusSlugFromServer = requestUpdate.status_slug; 
                    const newStatusDisplay = requestUpdate.status_display;

                    if (oldStatusDisplay !== newStatusDisplay) {
                        currentStatusCell.find('span').text(newStatusDisplay); 
                        currentStatusCell.removeClass (function (index, className) {
                            return (className.match (/(^|\s)request-status-\S+/g) || []).join(' ');
                        }).addClass('request-status-' + newStatusSlugFromServer);

                        if (oldStatusDisplay && oldStatusDisplay !== '--' && oldStatusDisplay !== newStatusDisplay) {
                            showGlobalToast(`Tu solicitud #${requestId} ahora está: ${newStatusDisplay}`, 'info');
                        }
                    }
                    
                    let followCellHTML = '--'; 
                    const statusSlugForSwitch = newStatusSlugFromServer; 

                    switch (statusSlugForSwitch) {
                        case 'on_the_way':
                            if (requestUpdate.driver_id) {
                                followCellHTML = `<button class="button cachilupi-follow-driver-btn" data-request-id="${requestId}">${followButtonText}</button>`;
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
                        case 'picked_up':
                            followCellHTML = 'Mascota recogida, viaje en curso'; 
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

                } else {
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
            let response; 

            try {
                response = await fetch(url); 
                if (!response.ok) {
                    let errorMsg = `Error HTTP: ${response.status}`;
                    try {
                        const errorText = await response.text(); 
                        console.error('fetchClientRequestsStatus: Non-OK HTTP response text:', errorText); // Keep this error log
                        try {
                            const errorData = JSON.parse(errorText);
                            errorMsg = errorData.data && errorData.data.message ? errorData.data.message : errorMsg;
                        } catch (e_json) {
                             errorMsg = `Server error (${response.status}). Check console for raw response.`;
                        }
                    } catch (e_text) { 
                    }
                    throw new Error(errorMsg);
                }
                const responseData = await response.json();
                
                if (responseData.success && responseData.data) {
                    updateClientRequestsTable(responseData.data);
                } else {
                    console.warn('fetchClientRequestsStatus: Response not successful or data missing. Full responseData:', responseData); // Keep this warn
                }
            } catch (error) {
                console.error('fetchClientRequestsStatus: Error during fetch:', error); // Keep this error log
            }
        };

        if ($('.cachilupi-client-requests-panel').length > 0) {
            fetchClientRequestsStatus(); 
            clientRequestsStatusInterval = setInterval(fetchClientRequestsStatus, 20000); 
        } else {
        }
    });
})(jQuery);
