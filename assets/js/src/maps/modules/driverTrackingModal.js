// Assumes mapboxgl is available globally
// Assumes jQuery for modal show/hide and event binding for now

let clientFollowMap = null;
let followInterval = null;
let currentFollowingRequestId = null;
let driverMarker = null;

const fetchDriverLocationForClient = async (requestId) => {
    if (!requestId) {
        console.warn('fetchDriverLocationForClient: No requestId provided.');
        return;
    }
    if (typeof cachilupi_pet_vars === 'undefined' || !cachilupi_pet_vars.ajaxurl || !cachilupi_pet_vars.get_location_nonce) {
        console.error('Driver tracking: Missing required JS variables (ajaxurl or nonce).');
        jQuery('#cachilupi-follow-modal-title').text('Error de configuración.'); // User feedback
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
            const { longitude, latitude } = responseData.data;
            const driverPosition = [parseFloat(longitude), parseFloat(latitude)];

            if (clientFollowMap && typeof clientFollowMap.getStyle === 'function' && clientFollowMap.isStyleLoaded()) {
                if (!driverMarker) {
                    driverMarker = new mapboxgl.Marker().setLngLat(driverPosition).addTo(clientFollowMap);
                } else {
                    driverMarker.setLngLat(driverPosition);
                }
                clientFollowMap.flyTo({ center: driverPosition, zoom: 15 });
                jQuery('#cachilupi-follow-modal-title').text(`${cachilupi_pet_vars.text_follow_driver || 'Siguiendo Viaje'} ID: ${requestId}`);
            } else {
                console.warn('Mapa de seguimiento no disponible o no cargado para actualizar marcador.');
            }
        } else {
            console.warn('Ubicación del conductor no disponible:', responseData.data ? responseData.data.message : 'Respuesta del servidor no exitosa.');
            jQuery('#cachilupi-follow-modal-title').text(`${cachilupi_pet_vars.text_driver_location_not_available || 'Ubicación no disponible'} (ID: ${requestId})`);
            if (driverMarker) { driverMarker.remove(); driverMarker = null; }
        }
    } catch (error) {
        console.error('Error al obtener ubicación del conductor:', error.message);
        jQuery('#cachilupi-follow-modal-title').text('Error al obtener ubicación');
    }
};

export const initDriverTrackingModal = () => {
    jQuery(document).on('click', '.cachilupi-follow-driver-btn', function() {
        const $button = jQuery(this);
        currentFollowingRequestId = $button.data('request-id');

        if (!currentFollowingRequestId) {
            console.error('Error: No se pudo obtener el ID de la solicitud para seguir.');
            alert(cachilupi_pet_vars.text_driver_location_not_available || 'No se pudo obtener el ID de la solicitud.');
            return;
        }
        jQuery('#cachilupi-follow-modal').show();

        if (typeof mapboxgl === 'undefined' || !mapboxgl.accessToken) {
            if (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) {
                 mapboxgl.accessToken = cachilupi_pet_vars.mapbox_access_token;
            } else {
                console.error("Mapbox GL JS no está cargado o falta el token de acceso.");
                alert("Error al cargar el mapa: Mapbox no está disponible.");
                jQuery('#cachilupi-follow-modal').hide();
                return;
            }
        }

        const mapContainerId = 'cachilupi-client-follow-map';
        let mapContainerElement = document.getElementById(mapContainerId);

        // Ensure map container is visible and has dimensions before initializing map
        // This is a common issue if the modal is hidden with display:none
        const modalWasHidden = jQuery('#cachilupi-follow-modal').css('display') === 'none';
        if (modalWasHidden) {
             jQuery('#cachilupi-follow-modal').show(); // Temporarily show if it was hidden
        }


        if (!clientFollowMap || clientFollowMap.getContainer().id !== mapContainerId || !mapContainerElement) {
            if (clientFollowMap) { clientFollowMap.remove(); clientFollowMap = null; }

            if (!mapContainerElement) { // If it was null, try to get it again after modal is shown
                 mapContainerElement = document.getElementById(mapContainerId);
            }

            if (mapContainerElement) {
                try {
                    clientFollowMap = new mapboxgl.Map({
                        container: mapContainerId,
                        style: 'mapbox://styles/mapbox/streets-v11',
                        center: [-70.6693, -33.4489], // Default center
                        zoom: 9
                    });
                    clientFollowMap.addControl(new mapboxgl.NavigationControl());
                    clientFollowMap.on('load', () => {
                        clientFollowMap.resize(); // Ensure map resizes correctly after modal is shown
                        fetchDriverLocationForClient(currentFollowingRequestId); // Initial fetch after map loads
                    });
                } catch (e) {
                    console.error("Error inicializando el mapa de seguimiento del cliente:", e);
                    alert("Error al cargar el mapa de seguimiento.");
                    jQuery('#cachilupi-follow-modal').hide();
                    return;
                }
            } else {
                 console.error("Contenedor del mapa de seguimiento no encontrado:", mapContainerId);
                 alert("Error: No se pudo encontrar el contenedor del mapa.");
                 if(modalWasHidden) jQuery('#cachilupi-follow-modal').hide(); // Hide modal back if we temp showed it
                 return;
            }
        }

        // If map already exists, ensure it resizes and fetches
        if (clientFollowMap) {
             clientFollowMap.resize();
             fetchDriverLocationForClient(currentFollowingRequestId);
        }

        if (followInterval) clearInterval(followInterval);
        followInterval = setInterval(() => fetchDriverLocationForClient(currentFollowingRequestId), 15000);
    });

    jQuery('#cachilupi-close-follow-modal').on('click', () => {
        jQuery('#cachilupi-follow-modal').hide();
        if (followInterval) { clearInterval(followInterval); followInterval = null; }
        currentFollowingRequestId = null;
        if (driverMarker) { driverMarker.remove(); driverMarker = null; }
        // Optionally, if map was initialized specifically for this modal instance and not reused:
        // if (clientFollowMap) { clientFollowMap.remove(); clientFollowMap = null; }
    });
};
