# Cachilupi Pet Plugin

## Description

Cachilupi Pet is a WordPress plugin designed to manage pet transportation services. It allows clients to book transportation for their pets and provides drivers with a panel to manage these requests, including real-time location tracking during active trips.

## Features

*   **Pet Transportation Booking:** Clients can request pet transportation services, specifying pickup and dropoff locations, service time, and pet details.
*   **Driver Panel:** Registered drivers have access to a dedicated panel where they can:
    *   View pending service requests.
    *   Accept or reject requests.
    *   Manage the status of active trips (e.g., "On the way," "Arrived," "Pet Picked Up," "Completed").
    *   Share their location in real-time during active trips.
    *   View a history of their completed and rejected trips.
*   **Client Panel:** Registered clients can:
    *   Submit new service requests.
    *   View a history of their past and current service requests with their statuses.
    *   Track the driver's location in real-time for "on the way" trips.
*   **User Roles:**
    *   `client`: For customers booking services.
    *   `driver`: For personnel managing and executing transportation services.
*   **Map Integration:** Uses Mapbox for location searching (geocoding) and displaying maps for tracking.
*   **Email Notifications:** Clients receive email updates as the status of their service request changes.

## Shortcodes

*   **`[cachilupi_maps]`**: Displays the client-facing booking form and their list of service requests. Typically placed on a page like "/reserva".
*   **`[cachilupi_driver_panel]`**: Displays the driver's panel for managing requests. Typically placed on a page like "/driver".

## Setup and Configuration

1.  **Installation:**
    *   Upload the `cachilupi-pet` folder to the `/wp-content/plugins/` directory.
    *   Activate the plugin through the 'Plugins' menu in WordPress.
2.  **Mapbox Access Token:**
    *   Go to `Settings > Cachilupi Pet` in your WordPress admin area.
    *   Enter your Mapbox Access Token in the designated field. This is required for map and geocoding functionalities.
3.  **Create Pages for Shortcodes:**
    *   Create a page (e.g., "Reserva", "Booking") and add the `[cachilupi_maps]` shortcode to it. This will be the client's booking page.
    *   Create another page (e.g., "Driver Panel", "Area Conductor") and add the `[cachilupi_driver_panel]` shortcode to it. This will be the driver's interface.
4.  **Configure Redirect Slugs (Optional):**
    *   In `Settings > Cachilupi Pet`, you can customize the URL slugs that users are redirected to after logging in, based on their role (client or driver). By default, these are 'reserva' and 'driver'. Ensure these slugs match the pages where you've placed the shortcodes.

## Roles

*   **Client:** Users with this role can submit and manage their pet transportation requests.
*   **Driver:** Users with this role can view and manage assigned or available transportation requests.

---

This plugin requires a valid Mapbox Access Token for its mapping and geocoding features to work correctly.
