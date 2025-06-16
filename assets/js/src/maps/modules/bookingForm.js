import {
    getPickupCoords, getDropoffCoords,
    getPickupGeocoderValue, getDropoffGeocoderValue,
    clearMapFeatures, setValidateFormCallback as setMapServiceValidateCallback
} from './mapService.js';
import { showToast, showError, hideError } from './uiUtils.js'; // showFeedbackMessage removed as showToast is preferred

// DOM Elements
let serviceDateInput, serviceTimeInput, submitButton, petTypeSelect, notesTextArea, petInstructionsTextArea;
let pickupGeocoderContainer, dropoffGeocoderContainer; // For showError/hideError context

// Store flatpickr instances to clear them
let flatpickrDateInstance = null;
let flatpickrTimeInstance = null;

const validateForm = (isMapContext = true) => {
    let isValid = true;
    const pickupValue = getPickupGeocoderValue();
    const dropoffValue = getDropoffGeocoderValue();
    const pCoords = getPickupCoords();
    const dCoords = getDropoffCoords();

    if (!pickupValue.trim() || (isMapContext && !pCoords)) {
        if (pickupGeocoderContainer) showError(pickupGeocoderContainer, 'Este campo es obligatorio.');
        isValid = false;
    } else {
        if (pickupGeocoderContainer) hideError(pickupGeocoderContainer);
    }

    if (!dropoffValue.trim() || (isMapContext && !dCoords)) {
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


export const initBookingForm = (mapAvailable) => {
    // Initialize DOM elements
    pickupGeocoderContainer = document.getElementById('pickup-geocoder-container');
    dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container');
    serviceDateInput = document.getElementById('service-date');
    serviceTimeInput = document.getElementById('service-time');
    submitButton = document.getElementById('submit-service-request');
    petTypeSelect = document.getElementById('cachilupi-pet-pet-type');
    notesTextArea = document.getElementById('cachilupi-pet-notes');
    petInstructionsTextArea = document.getElementById('cachilupi-pet-instructions');

    if (mapAvailable) {
        setMapServiceValidateCallback(validateForm);
    }

    if (serviceDateInput) {
        flatpickrDateInstance = flatpickr(serviceDateInput, {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "F j, Y",
            minDate: "today",
            locale: 'es',
            onChange: () => validateForm(mapAvailable)
        });
        const today = new Date();
        const dd = String(today.getDate()).padStart(2, '0');
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const yyyy = today.getFullYear();
        serviceDateInput.setAttribute('min', `${yyyy}-${mm}-${dd}`);
        serviceDateInput.addEventListener('input', () => validateForm(mapAvailable));
        serviceDateInput.addEventListener('blur', () => validateForm(mapAvailable));
    }

    if (serviceTimeInput) {
        flatpickrTimeInstance = flatpickr(serviceTimeInput, {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            altInput: true,
            altFormat: "h:i K",
            time_24hr: false,
            minuteIncrement: 15,
            locale: 'es',
            onChange: () => validateForm(mapAvailable)
        });
        serviceTimeInput.addEventListener('input', () => validateForm(mapAvailable));
        serviceTimeInput.addEventListener('blur', function() {
            const timeValue = this.value;
            if (timeValue) {
                let [hours, minutes] = timeValue.split(':');
                minutes = parseInt(minutes, 10);
                if (!isNaN(minutes)) {
                    let roundedMinutes = Math.round(minutes / 15) * 15;
                    if (roundedMinutes === 60) {
                        hours = (parseInt(hours, 10) + 1) % 24;
                        roundedMinutes = 0;
                    }
                    this.value = `${String(hours).padStart(2, '0')}:${String(roundedMinutes).padStart(2, '0')}`;
                }
            }
            validateForm(mapAvailable);
        });
    }

    if (petTypeSelect) petTypeSelect.addEventListener('change', () => validateForm(mapAvailable));
    if (notesTextArea) notesTextArea.addEventListener('input', () => validateForm(mapAvailable));
    if (petInstructionsTextArea) petInstructionsTextArea.addEventListener('input', () => validateForm(mapAvailable));

    if (submitButton) {
        submitButton.addEventListener('click', async (event) => {
            event.preventDefault();
            if (!validateForm(mapAvailable)) {
                showToast('Por favor, corrige los errores en el formulario.', 'error');
                return;
            }

            const originalButtonText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Enviando Solicitud...';
            submitButton.classList.add('loading');

            document.querySelectorAll('.cachilupi-feedback').forEach(el => el.remove()); // Clear old feedback

            const pickupAddress = getPickupGeocoderValue();
            const dropoffAddress = getDropoffGeocoderValue();
            const pCoords = getPickupCoords();
            const dCoords = getDropoffCoords();

            const serviceRequestData = {
                scheduled_date_time: `${serviceDateInput.value} ${serviceTimeInput.value}:00`,
                pickup_address: pickupAddress,
                pickup_lat: mapAvailable && pCoords ? pCoords.lat : 0.0,
                pickup_lon: mapAvailable && pCoords ? pCoords.lng : 0.0,
                dropoff_address: dropoffAddress,
                dropoff_lat: mapAvailable && dCoords ? dCoords.lat : 0.0,
                dropoff_lon: mapAvailable && dCoords ? dCoords.lng : 0.0,
                pet_type: petTypeSelect.value,
                notes: notesTextArea.value,
                pet_instructions: petInstructionsTextArea.value,
            };

            const formData = new FormData();
            formData.append('action', 'cachilupi_pet_submit_request');
            formData.append('security', cachilupi_pet_vars.submit_request_nonce);
            for (const key in serviceRequestData) {
                formData.append(key, serviceRequestData[key]);
            }

            try {
                const fetchResponse = await fetch(cachilupi_pet_vars.ajaxurl, {
                    method: 'POST',
                    body: formData
                });

                let responseData;
                const responseText = await fetchResponse.text();
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    console.error("Failed to parse JSON response:", responseText);
                    throw new Error("Respuesta inesperada del servidor.");
                }

                if (!fetchResponse.ok) {
                    const errorMsg = responseData.data && responseData.data.message ? responseData.data.message : `Error HTTP: ${fetchResponse.status}`;
                    throw new Error(errorMsg);
                }

                if (responseData.success) {
                    showToast(responseData.data.message || 'Solicitud enviada con éxito.', 'success');
                    if (mapAvailable) {
                        clearMapFeatures();
                    } else {
                        const pickupInput = document.getElementById('pickup-location-input');
                        if (pickupInput) pickupInput.value = '';
                        const dropoffInput = document.getElementById('dropoff-location-input');
                        if (dropoffInput) dropoffInput.value = '';
                    }
                    if (flatpickrDateInstance) flatpickrDateInstance.clear(); else if(serviceDateInput) serviceDateInput.value = '';
                    if (flatpickrTimeInstance) flatpickrTimeInstance.clear(); else if(serviceTimeInput) serviceTimeInput.value = '';
                    if (petTypeSelect) petTypeSelect.value = '';
                    if (notesTextArea) notesTextArea.value = '';
                    if (petInstructionsTextArea) petInstructionsTextArea.value = '';

                    validateForm(mapAvailable);
                } else {
                    const errorMessage = responseData.data && responseData.data.message ? responseData.data.message : 'Ocurrió un error al guardar la solicitud.';
                    showToast(errorMessage, 'error');
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showToast(`Error de comunicación: ${error.message}`, 'error');
            } finally {
                submitButton.classList.remove('loading');
                submitButton.textContent = originalButtonText;
                submitButton.disabled = false; // Explicitly re-enable before validation
                validateForm(mapAvailable); // Ensure button state is correct based on form validity
            }
        });
    }
    validateForm(mapAvailable);
};
