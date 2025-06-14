// Assumes mapboxgl and MapboxGeocoder are available globally (e.g., via CDN)
// If using npm packages with Parcel, you'd import them:
// import mapboxgl from 'mapbox-gl';
// import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';

let map = null;
let pickupGeocoder = null;
let dropoffGeocoder = null;
let pickupGeocoderInput = null;
let dropoffGeocoderInput = null;

let pickupCoords = null;
let dropoffCoords = null;
let pickupMarker = null;
let dropoffMarker = null;

// DOM elements needed by this module
let mapElement = null;
let distanceElement = null;
let pickupGeocoderContainer = null;
let dropoffGeocoderContainer = null;


const decodePolyline = (encoded) => {
    const len = encoded.length;
    let index = 0;
    const array = [];
    let lat = 0;
    let lng = 0;

    while (index < len) {
        let b, shift = 0, result = 0;
        do {
            b = encoded.charCodeAt(index++) - 63;
            result |= (b & 0x1f) << shift;
            shift += 5;
        } while (b >= 0x20);
        const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
        lat += dlat;

        shift = 0; result = 0;
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
                const routeGeometry = data.routes[0].geometry;
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
                    geometry: { type: 'LineString', coordinates: decodedRoute }
                };

                if (map.getSource('route')) {
                    map.getSource('route').setData(geojson);
                } else {
                    map.addSource('route', { type: 'geojson', data: geojson });
                    map.addLayer({
                        id: 'route', type: 'line', source: 'route',
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
            // Consider calling a feedback function imported from uiUtils if this module needs to show user messages
            // For now, just logging, or assuming another module handles UI feedback for this.
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

// This function will be called from bookingForm.js or the main entry point
// to pass the validateForm function, as mapService shouldn't know about it directly.
let externalValidateFormCallback = () => {};

export const setValidateFormCallback = (callback) => {
    externalValidateFormCallback = callback;
};


const initializeGeocoders = () => {
    const pickupPlaceholder = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_pickup_placeholder_detailed) ? cachilupi_pet_vars.text_pickup_placeholder_detailed : 'Lugar de Recogida...';
    const dropoffPlaceholder = (typeof cachilupi_pet_vars !== 'undefined' && cachilupi_pet_vars.text_dropoff_placeholder_detailed) ? cachilupi_pet_vars.text_dropoff_placeholder_detailed : 'Lugar de Destino...';

    if (pickupGeocoderContainer) {
        pickupGeocoder = new MapboxGeocoder({
            accessToken: mapboxgl.accessToken, placeholder: pickupPlaceholder, mapboxgl: mapboxgl,
            bbox: [-75.6, -55.9, -66.4, -17.5], country: 'cl', limit: 5
        });
        pickupGeocoder.addTo(pickupGeocoderContainer);
        pickupGeocoderInput = pickupGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
        if (pickupGeocoderInput) {
            pickupGeocoderInput.classList.add('form-control');
            pickupGeocoderInput.addEventListener('input', () => externalValidateFormCallback(true));
            pickupGeocoderInput.addEventListener('blur', () => externalValidateFormCallback(true));
            if (document.getElementById('pickup-location-label')) {
                pickupGeocoderInput.setAttribute('aria-labelledby', 'pickup-location-label');
            }
        }
        pickupGeocoder.on('result', (event) => {
            const [lng, lat] = event.result.geometry.coordinates;
            pickupCoords = { lng, lat };
            if (pickupMarker) pickupMarker.remove();
            const elPickup = document.createElement('div');
            elPickup.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" fill="#0073aa"><path d="M12 2L2 7v13h20V7L12 2zm0 2.09L19.22 7H4.78L12 4.09zM4 9h16v10H4V9zm2 1v2h2v-2H6zm4 0v2h2v-2h-2zm4 0v2h2v-2h-2z"/></svg>`;
            elPickup.style.cssText = 'width:30px; height:30px; cursor:pointer;';
            pickupMarker = new mapboxgl.Marker(elPickup).setLngLat([lng, lat]).addTo(map);
            getRouteAndDistance();
            externalValidateFormCallback(true);
        });
        pickupGeocoder.on('clear', () => {
            pickupCoords = null;
            if (pickupMarker) { pickupMarker.remove(); pickupMarker = null; }
            getRouteAndDistance();
            externalValidateFormCallback(true);
        });
    }

    if (dropoffGeocoderContainer) {
        dropoffGeocoder = new MapboxGeocoder({
            accessToken: mapboxgl.accessToken, placeholder: dropoffPlaceholder, mapboxgl: mapboxgl,
            bbox: [-75.6, -55.9, -66.4, -17.5], country: 'cl', limit: 5
        });
        dropoffGeocoder.addTo(dropoffGeocoderContainer);
        dropoffGeocoderInput = dropoffGeocoderContainer.querySelector('.mapboxgl-ctrl-geocoder--input');
        if (dropoffGeocoderInput) {
            dropoffGeocoderInput.classList.add('form-control');
            dropoffGeocoderInput.addEventListener('input', () => externalValidateFormCallback(true));
            dropoffGeocoderInput.addEventListener('blur', () => externalValidateFormCallback(true));
            if (document.getElementById('dropoff-location-label')) {
                dropoffGeocoderInput.setAttribute('aria-labelledby', 'dropoff-location-label');
            }
        }
        dropoffGeocoder.on('result', (event) => {
            const [lng, lat] = event.result.geometry.coordinates;
            dropoffCoords = { lng, lat };
            if (dropoffMarker) dropoffMarker.remove();
            const elDropoff = document.createElement('div');
            elDropoff.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" fill="#d32f2f"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"/></svg>`;
            elDropoff.style.cssText = 'width:30px; height:30px; cursor:pointer;';
            dropoffMarker = new mapboxgl.Marker(elDropoff).setLngLat([lng, lat]).addTo(map);
            getRouteAndDistance();
            externalValidateFormCallback(true);
        });
        dropoffGeocoder.on('clear', () => {
            dropoffCoords = null;
            if (dropoffMarker) { dropoffMarker.remove(); dropoffMarker = null; }
            getRouteAndDistance();
            externalValidateFormCallback(true);
        });
    }
};

export const initMap = () => {
    mapElement = document.getElementById('cachilupi-pet-map'); // Ensure it's defined here
    distanceElement = document.getElementById('cachilupi-pet-distance');
    pickupGeocoderContainer = document.getElementById('pickup-geocoder-container');
    dropoffGeocoderContainer = document.getElementById('dropoff-geocoder-container');

    if (!mapElement || typeof mapboxgl === 'undefined' || !mapboxgl.accessToken) {
        console.warn('Mapbox map element not found or Mapbox GL not loaded/configured.');
        return null; // Return null or handle error appropriately
    }

    const defaultCenter = [-70.6693, -33.4489];
    const defaultZoom = 10;

    map = new mapboxgl.Map({
        container: mapElement.id, // Use the ID from the element
        style: 'mapbox://styles/mapbox/streets-v11',
        center: defaultCenter,
        zoom: defaultZoom
    });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (map) {
                    const { longitude, latitude } = position.coords;
                    map.setCenter([longitude, latitude]);
                    map.setZoom(12);
                }
            },
            (error) => console.warn(`Error getting user location: ${error.message}. Using default center.`),
            { timeout: 5000 }
        );
    } else {
        console.warn('Geolocation is not supported. Using default center.');
    }

    window.addEventListener('resize', () => {
        if (map) map.resize();
    });

    loadMapboxGeocoder(initializeGeocoders);

    return map; // Return the map instance
};

// Export other things that might be needed by other modules
export {
    map, // Export the map instance itself (though it's let, its properties are mutated)
    pickupCoords,
    dropoffCoords,
    pickupGeocoder, // To potentially clear it from outside, etc.
    dropoffGeocoder,
    pickupGeocoderInput, // Potentially for bookingForm to get values if not using pickupCoords
    dropoffGeocoderInput,
    pickupMarker, // To clear them if needed
    dropoffMarker,
    getRouteAndDistance // If bookingForm needs to trigger this independently
};

export const getPickupCoords = () => pickupCoords;
export const getDropoffCoords = () => dropoffCoords;
export const getPickupGeocoderValue = () => pickupGeocoderInput ? pickupGeocoderInput.value : '';
export const getDropoffGeocoderValue = () => dropoffGeocoderInput ? dropoffGeocoderInput.value : '';

export const clearMapFeatures = () => {
    if (pickupMarker) pickupMarker.remove();
    if (dropoffMarker) dropoffMarker.remove();
    pickupCoords = null;
    dropoffCoords = null;
    if (map && map.getSource('route')) {
        map.removeLayer('route');
        map.removeSource('route');
    }
    if (distanceElement) distanceElement.textContent = '';
    if (pickupGeocoder) pickupGeocoder.clear();
    if (dropoffGeocoder) dropoffGeocoder.clear();
};
