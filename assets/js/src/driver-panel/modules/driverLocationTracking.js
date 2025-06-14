import { showDriverPanelFeedback } from './driverPanelUI.js';
// Assumes cachilupi_driver_vars is available globally

let locationWatchId = null;
let currentTrackingRequestId = null;

const sendLocationUpdate = async (requestId, latitude, longitude) => {
    if (!requestId) {
        console.warn('sendLocationUpdate: requestId is missing.');
        return;
    }
    if (typeof cachilupi_driver_vars === 'undefined' || !cachilupi_driver_vars.ajaxurl || !cachilupi_driver_vars.update_location_nonce) {
        console.error('Location update: Missing required JS variables (ajaxurl or nonce).');
        // Optionally show feedback to user, but this is more of a config error
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
            let errorData = null;
            try { errorData = await response.json(); } catch (e) { /* ignore */ }
            const serverMessage = errorData?.data?.message || response.statusText;
            throw new Error(`Network response was not ok: ${serverMessage}`);
        }

        const responseData = await response.json();
        if (responseData.success) {
            console.log('Ubicación del conductor actualizada en el servidor.');
            if (responseData.data?.status_code === 'no_change') {
                console.log('Ubicación sin cambios significativos en el servidor.');
            }
        } else {
            const message = responseData.data?.message || 'Error desconocido del servidor.';
            console.warn(`Fallo al actualizar ubicación del conductor en el servidor: ${message}`);
        }
    } catch (error) {
        console.error('Error Fetch al enviar actualización de ubicación:', error.message);
    }
};

export const startLocationTracking = (requestId) => {
    if (navigator.geolocation) {
        stopLocationTracking(); // Stop any previous tracking
        currentTrackingRequestId = requestId;
        console.log(`Iniciando seguimiento de ubicación para Request ID: ${currentTrackingRequestId}`);

        locationWatchId = navigator.geolocation.watchPosition(
            (position) => {
                const { latitude: lat, longitude: lon } = position.coords;
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

export const stopLocationTracking = () => {
    if (locationWatchId !== null) {
        navigator.geolocation.clearWatch(locationWatchId);
        locationWatchId = null;
        console.log(`Seguimiento de ubicación detenido para Request ID: ${currentTrackingRequestId}`);
        if (currentTrackingRequestId) {
            showDriverPanelFeedback(`Se ha detenido el compartir ubicación para la solicitud #${currentTrackingRequestId}.`, 'info');
        }
        currentTrackingRequestId = null;
    }
};

// To be called by other modules if they need to know the current tracking ID
export const getCurrentTrackingRequestId = () => currentTrackingRequestId;
