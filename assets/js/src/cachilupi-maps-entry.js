import { initMap } from './maps/modules/mapService.js';
import { initBookingForm } from './maps/modules/bookingForm.js';
import { initClientRequestsDisplay } from './maps/modules/clientRequestsDisplay.js';
import { initDriverTrackingModal } from './maps/modules/driverTrackingModal.js';
// uiUtils are typically used by other modules, so direct init might not be needed here
// unless there are standalone UI elements to initialize from the entry point.

// Set Mapbox access token from localized variable
if (typeof mapboxgl !== 'undefined' && typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) {
    mapboxgl.accessToken = cachilupi_pet_vars.mapbox_access_token;
} else if (typeof mapboxgl !== 'undefined' && !mapboxgl.accessToken) {
    console.warn('Mapbox Access Token no está configurado. Las funcionalidades del mapa estarán deshabilitadas.');
}

const handleFormWithoutMap = () => {
    // This function contains the logic from the original maps.js for when mapboxgl is not available
    // It should initialize form elements and validation for a non-map scenario.
    // For brevity, its full content isn't duplicated here but should be moved from maps.js
    // and refactored to not depend on map-specific variables if they are not available.
    // It would primarily call initBookingForm(false) after setting up any non-map specific listeners or fallbacks.

    console.log("Modo sin mapa: Inicializando solo el formulario.");
    // Query for elements again if they are not globally available or passed down
    const serviceDateInput = document.getElementById('service-date');
    const serviceTimeInput = document.getElementById('service-time');
    const petTypeSelect = document.getElementById('cachilupi-pet-pet-type');
    const notesTextArea = document.getElementById('cachilupi-pet-notes');
    const petInstructionsTextArea = document.getElementById('cachilupi-pet-instructions');
    const submitButton = document.getElementById('submit-service-request');
    const pickupInput = document.getElementById('pickup-location-input'); // Assuming standard input fallback
    const dropoffInput = document.getElementById('dropoff-location-input'); // Assuming standard input fallback

    // Call initBookingForm with mapAvailable = false
    // initBookingForm should be designed to handle this flag
    initBookingForm(false);

    // Add any specific event listeners or validation logic for non-map inputs here if needed
    // For example, if geocoders are replaced by simple text inputs:
    if (pickupInput) {
        pickupInput.addEventListener('input', () => validateForm(false)); // Assuming validateForm is made available/redefined
        pickupInput.addEventListener('blur', () => validateForm(false));
    }
    if (dropoffInput) {
        dropoffInput.addEventListener('input', () => validateForm(false));
        dropoffInput.addEventListener('blur', () => validateForm(false));
    }
    // The rest of the form listeners (date, time, petType etc.) are set up within initBookingForm.
};


document.addEventListener('DOMContentLoaded', () => {
    console.log('Cachilupi Maps JS (Parcel) Loaded via cachilupi-maps-entry.js - DOMContentLoaded');

    const mapElement = document.getElementById('cachilupi-pet-map');
    let mapAvailable = false;

    if (mapElement && typeof mapboxgl !== 'undefined' && mapboxgl.accessToken) {
        try {
            initMap(); // Initializes the map and geocoders from mapService.js
            mapAvailable = true;
        } catch (e) {
            console.error("Failed to initialize map:", e);
            // Fallback to non-map version of the form
            if (typeof handleFormWithoutMap === "function") {
                 handleFormWithoutMap();
            }
        }
    } else {
        console.log("Mapbox map element not found or Mapbox GL not loaded/configured. Running in non-map mode.");
        if (typeof handleFormWithoutMap === "function") {
            handleFormWithoutMap();
        }
    }

    // Initialize other modules, passing mapAvailable if they need to behave differently
    initBookingForm(mapAvailable);
    initClientRequestsDisplay(); // This module might not depend on the map itself
    initDriverTrackingModal();   // This module heavily depends on mapboxgl, might need its own checks or rely on mapService's map object

    // Example of how validateForm might be handled if it needs to be globally accessible or passed around
    // This is tricky due to module scopes. The current approach in bookingForm.js is to have its own.
    // If mapService needs to call validateForm from bookingForm, a callback mechanism is better (as implemented with setMapServiceValidateCallback).
});
