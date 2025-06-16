// Assumes mapboxgl is available globally

let clientFollowMap = null;
let followInterval = null;
let currentFollowingRequestId = null;
let driverMarker = null;

const modalElement = document.getElementById('cachilupi-follow-modal');
const modalTitleElement = document.getElementById('cachilupi-follow-modal-title');
const closeButtonElement = document.getElementById('cachilupi-close-follow-modal');
const mapContainerId = 'cachilupi-client-follow-map';


const fetchDriverLocationForClient = async (requestId) => {
    if (!requestId) {
        console.warn('fetchDriverLocationForClient: No requestId provided.');
        return;
    }
    if (typeof cachilupi_pet_vars === 'undefined' || !cachilupi_pet_vars.ajaxurl || !cachilupi_pet_vars.get_location_nonce) {
        console.error('Driver tracking: Missing required JS variables (ajaxurl or nonce).');
        if (modalTitleElement) modalTitleElement.textContent = 'Error de configuración.';
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
                if (modalTitleElement) modalTitleElement.textContent = `${cachilupi_pet_vars.text_follow_driver || 'Siguiendo Viaje'} ID: ${requestId}`;
            } else {
                console.warn('Mapa de seguimiento no disponible o no cargado para actualizar marcador.');
            }
        } else {
            console.warn('Ubicación del conductor no disponible:', responseData.data ? responseData.data.message : 'Respuesta del servidor no exitosa.');
            if (modalTitleElement) modalTitleElement.textContent = `${cachilupi_pet_vars.text_driver_location_not_available || 'Ubicación no disponible'} (ID: ${requestId})`;
            if (driverMarker) { driverMarker.remove(); driverMarker = null; }
        }
    } catch (error) {
        console.error('Error al obtener ubicación del conductor:', error.message);
        if (modalTitleElement) modalTitleElement.textContent = 'Error al obtener ubicación';
    }
};

export const initDriverTrackingModal = () => {
    if (!modalElement || !closeButtonElement) {
        console.error('Modal elements not found. Driver tracking cannot be initialized.');
        return;
    }

    document.addEventListener('click', (event) => {
        const followButton = event.target.closest('.cachilupi-follow-driver-btn');
        if (!followButton) return;

        currentFollowingRequestId = followButton.dataset.requestId;

        if (!currentFollowingRequestId) {
            console.error('Error: No se pudo obtener el ID de la solicitud para seguir.');
            alert(cachilupi_pet_vars.text_driver_location_not_available || 'No se pudo obtener el ID de la solicitud.');
            return;
        }

        modalElement.style.display = 'block';

        if (typeof mapboxgl === 'undefined' || !mapboxgl.accessToken) {
            if (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.mapbox_access_token) {
                 mapboxgl.accessToken = cachilupi_pet_vars.mapbox_access_token;
            } else {
                console.error("Mapbox GL JS no está cargado o falta el token de acceso.");
                alert("Error al cargar el mapa: Mapbox no está disponible.");
                modalElement.style.display = 'none';
                return;
            }
        }

        let mapContainerElement = document.getElementById(mapContainerId);
        const modalWasHidden = getComputedStyle(modalElement).display === 'none'; // Check again in case it was hidden by other means

        if (modalWasHidden) { // Should not happen if we just set it to block, but as a safeguard
            modalElement.style.display = 'block';
        }

        if (!clientFollowMap || clientFollowMap.getContainer().id !== mapContainerId || !mapContainerElement) {
            if (clientFollowMap) { clientFollowMap.remove(); clientFollowMap = null; }

            mapContainerElement = document.getElementById(mapContainerId); // Re-fetch after ensuring modal is displayed

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
                        clientFollowMap.resize();
                        fetchDriverLocationForClient(currentFollowingRequestId);
                    });
                } catch (e) {
                    console.error("Error inicializando el mapa de seguimiento del cliente:", e);
                    alert("Error al cargar el mapa de seguimiento.");
                    modalElement.style.display = 'none';
                    return;
                }
            } else {
                 console.error("Contenedor del mapa de seguimiento no encontrado:", mapContainerId);
                 alert("Error: No se pudo encontrar el contenedor del mapa.");
                 if (modalWasHidden) modalElement.style.display = 'none';
                 return;
            }
        }

        if (clientFollowMap) { // If map already exists
             clientFollowMap.resize(); // Call resize to handle cases where modal was hidden/reshown
             fetchDriverLocationForClient(currentFollowingRequestId);
        }

        if (followInterval) clearInterval(followInterval);
        followInterval = setInterval(() => fetchDriverLocationForClient(currentFollowingRequestId), 15000);
    });

    closeButtonElement.addEventListener('click', () => {
        modalElement.style.display = 'none';
        if (followInterval) { clearInterval(followInterval); followInterval = null; }
        currentFollowingRequestId = null;
        if (driverMarker) { driverMarker.remove(); driverMarker = null; }
        // if (clientFollowMap) { clientFollowMap.remove(); clientFollowMap = null; } // Optional: destroy map
    });
};
