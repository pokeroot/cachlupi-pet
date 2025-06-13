<?php
/**
 * Plugin Name: Cachilupi Pet
 * Description: Plugin para gestionar servicios de transporte de mascotas con seguimiento.
 * Version: 2.0
 * Author: Jhon Narvaez
 *
 * === Cachilupi Pet ===
 * Contributors: Jhon Narvaez
 * Donate Link: #
 * Tags: pet, transportation, booking, driver panel, maps, geocoding, ajax, wordpress plugin, login redirect, UI improvements, roles, accessibility, security
 * Requires at least: 5.0
 * Tested up to: 6.x
 * Stable tag: 2.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Define plugin directory path
if ( ! defined( 'CACHILUPI_PET_DIR' ) ) {
    define( 'CACHILUPI_PET_DIR', plugin_dir_path( __FILE__ ) );
}

// Include the main plugin class.
require_once CACHILUPI_PET_DIR . 'includes/class-cachilupi-pet-plugin.php';

// Register activation hook to call the static activate method.
register_activation_hook( __FILE__, array( 'Cachilupi_Pet_Plugin', 'activate' ) );

// Instantiate the main plugin class and run it.
function cachilupi_pet_run_plugin() {
	$plugin = new Cachilupi_Pet_Plugin();
	$plugin->init();
}
cachilupi_pet_run_plugin();

// =============================================================================
// Funcionalidad de Redirección Post-Login Personalizada (Manejada por Plugin)
// =============================================================================

// This functionality is now handled by CachilupiPet\Users\Cachilupi_Pet_User_Roles class.

// =============================================================================
// Fin Funcionalidad de Redirección Post-Login Personalizada
// =============================================================================


// =============================================================================
// Modificar Mensajes de Error de Login por Defecto - MOVED
// =============================================================================
// This functionality is now handled by CachilupiPet\Users\Cachilupi_Pet_User_Roles class.

// =============================================================================
// Fin Modificar Mensajes de Error de Login por Defecto
// =============================================================================


// =============================================================================
// Función para traducir los estados de las solicitudes - MOVED
// =============================================================================
// This function has been moved to CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status()

// Shortcode rendering is now handled by CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes

// AJAX Handlers are now managed by CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers

// Enqueue scripts and styles conditionally
// Note: The shortcode functions themselves have been moved.
// The enqueue logic might also be moved to the Shortcodes class or a dedicated Assets class later if it's tied to shortcode presence.
// For now, it remains here as it's hooked to 'wp_enqueue_scripts' which is a general hook.
function cachilupi_pet_enqueue_scripts() {
    global $post;

    // Check if it's a single post/page and has the shortcode [cachilupi_maps]
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cachilupi_maps' ) ) { 
        wp_enqueue_style(
            'cachilupi-maps',
            plugin_dir_url( __FILE__ ) . 'assets/css/maps.css',
            array(),
            '1.0' 
        );

        wp_enqueue_style(
            'mapbox-gl-css',
            'https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css',
            array(),
            '2.14.1'
        );

        wp_enqueue_style(
            'mapbox-gl-geocoder-css',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css',
            array( 'mapbox-gl-css' ),
            '5.0.0'
        );
        
        wp_enqueue_style(
            'flatpickr-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13' 
        );

        wp_enqueue_script(
            'mapbox-gl',
            'https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js',
            array(),
            '2.14.1',
            true
        );

        wp_enqueue_script(
            'mapbox-gl-geocoder',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js',
            array( 'mapbox-gl' ),
            '5.0.0',
            true
        );

        wp_enqueue_script(
            'flatpickr-js',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array(), 
            '4.6.13', 
            true 
        );

        wp_enqueue_script(
            'flatpickr-l10n-es',
            'https://npmcdn.com/flatpickr/dist/l10n/es.js',
            array('flatpickr-js'), 
            '4.6.13',
            true
        );

        wp_enqueue_script(
            'cachilupi-maps',
            plugin_dir_url( __FILE__ ) . 'assets/js/maps.js',
            array( 'mapbox-gl', 'jquery', 'flatpickr-js', 'flatpickr-l10n-es' ), 
            '1.5', 
            true
        );

        $mapbox_token = get_option('cachilupi_pet_mapbox_token', '');
        wp_localize_script( 'cachilupi-maps', 'cachilupi_pet_vars', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'submit_request_nonce' => wp_create_nonce( 'cachilupi_pet_submit_request' ),
            'get_location_nonce' => wp_create_nonce( 'cachilupi_pet_get_location_nonce'),
            'home_url' => home_url( '/' ), 
            'get_requests_status_nonce' => wp_create_nonce( 'cachilupi_pet_get_requests_status_nonce' ),
            'mapbox_access_token' => $mapbox_token,
            'text_follow_driver' => __('Seguir Conductor', 'cachilupi-pet'),
            'text_driver_location_not_available' => __('Ubicación del conductor no disponible en este momento.', 'cachilupi-pet'),
            'text_pickup_placeholder' => __('Dirección de recogida...', 'cachilupi-pet'), // Original simple placeholder
            'text_dropoff_placeholder' => __('Dirección de destino...', 'cachilupi-pet'), // Original simple placeholder
            'text_pickup_placeholder_detailed' => __('Lugar de Recogida: Ingresa la dirección completa...', 'cachilupi-pet'),
            'text_dropoff_placeholder_detailed' => __('Lugar de Destino: ¿A dónde irá tu mascota?', 'cachilupi-pet'),
        ) );
    }

    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cachilupi_driver_panel' ) ) {
        wp_enqueue_style(
            'cachilupi-driver-panel-css', 
            plugin_dir_url( __FILE__ ) . 'assets/css/driver-panel.css', 
            array(), 
            '1.0' 
        );
        wp_enqueue_script(
            'cachilupi-driver-panel',
            plugin_dir_url( __FILE__ ) . 'assets/js/driver-panel.js',
            array( 'jquery' ),
            '1.0', 
            true
        );

        wp_localize_script( 'cachilupi-driver-panel', 'cachilupi_driver_vars', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'driver_action_nonce' => wp_create_nonce( 'cachilupi_pet_driver_action' ),
            'update_location_nonce' => wp_create_nonce( 'cachilupi_pet_update_location_nonce' ),
            'check_new_requests_nonce' => wp_create_nonce('cachilupi_check_new_requests_nonce'),
        ) );
    }
}
add_action( 'wp', 'cachilupi_pet_enqueue_scripts' );

// Shortcode for displaying the map and location inputs (Client Form)
function cachilupi_pet_shortcode() {
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator', 'driver' ), (array) $user->roles ) ) {
        return '<p>' . esc_html__( 'Debes iniciar sesión como cliente o conductor para solicitar un servicio.', 'cachilupi-pet' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="cachilupi-booking-container">
        <div class="cachilupi-booking-form">
            <h1><?php esc_html_e('Solicitar Servicio', 'cachilupi-pet'); ?></h1>

            <fieldset id="cachilupi-trip-info-section">
                <legend><?php esc_html_e('Información del Viaje', 'cachilupi-pet'); ?></legend>

                <div class="form-group">
                    <label id="pickup-location-label" for="pickup-location-input" class="required-field-label"><?php esc_html_e('Lugar de Recogida:', 'cachilupi-pet'); ?></label>
                    <div id="pickup-geocoder-container" class="geocoder-container"></div>
                </div>

                <div class="form-group">
                    <label id="dropoff-location-label" for="dropoff-location-input" class="required-field-label"><?php esc_html_e('Lugar de Destino:', 'cachilupi-pet'); ?></label>
                    <div id="dropoff-geocoder-container" class="geocoder-container"></div>
                </div>

                <div class="form-group">
                    <label for="service-date" class="required-field-label"><?php esc_html_e('Fecha de Servicio:', 'cachilupi-pet'); ?></label>
                    <input type="text" id="service-date" class="required-field form-control cachilupi-datetime-picker" placeholder="<?php esc_attr_e('Selecciona fecha', 'cachilupi-pet'); ?>">
                </div>

                <div class="form-group">
                    <label for="service-time" class="required-field-label"><?php esc_html_e('Hora de Servicio:', 'cachilupi-pet'); ?></label>
                    <input type="text" id="service-time" class="required-field form-control cachilupi-datetime-picker" placeholder="<?php esc_attr_e('Selecciona hora', 'cachilupi-pet'); ?>">
                </div>

                 <div id="cachilupi-pet-distance" class="distance-display"></div>
            </fieldset>

            <fieldset id="cachilupi-pet-details-section">
                <legend><?php esc_html_e('Detalles de la Mascota', 'cachilupi-pet'); ?></legend>

                <div class="form-group">
                    <label for="cachilupi-pet-pet-type" class="required-field-label"><?php esc_html_e('Tipo de Mascota:', 'cachilupi-pet'); ?></label>
                    <select id="cachilupi-pet-pet-type" class="required-field form-control">
                    <option value=""><?php esc_html_e('-- Selecciona una opción --', 'cachilupi-pet'); ?></option>
                    <option value="perro"><?php esc_html_e('Perro', 'cachilupi-pet'); ?></option>
                    <option value="gato"><?php esc_html_e('Gato', 'cachilupi-pet'); ?></option>
                    <option value="otro"><?php esc_html_e('Otro', 'cachilupi-pet'); ?></option>
                </select>
                </div>

                <div class="form-group">
                    <label for="cachilupi-pet-instructions"><?php esc_html_e('Instrucciones Específicas para la Mascota:', 'cachilupi-pet'); ?></label>
                    <textarea id="cachilupi-pet-instructions" class="form-control" placeholder="<?php esc_attr_e('Ej: Comportamiento con extraños, si necesita bozal, si es amigable con otros animales, medicación, etc.', 'cachilupi-pet'); ?>"></textarea>
                </div>

                <div class="form-group">
                    <label for="cachilupi-pet-notes"><?php esc_html_e('Notas Adicionales:', 'cachilupi-pet'); ?></label>
                    <textarea id="cachilupi-pet-notes" class="form-control" placeholder="<?php esc_attr_e('Ej: Referencias de la dirección (casa esquina, portón rojo), consideraciones para el transporte (ej. necesita jaula grande), contacto alternativo, etc.', 'cachilupi-pet'); ?>"></textarea>
                </div>
            </fieldset>

            <button id="submit-service-request" type="button" class="button-primary"><?php esc_html_e('Solicitar Servicio', 'cachilupi-pet'); ?></button>

        </div>
        <div id="cachilupi-pet-map" class="booking-map"></div>
    </div>
    <?php

    if ( is_user_logged_in() && ( in_array( 'client', (array) $user->roles ) || current_user_can( 'manage_options' ) ) ) {
        global $wpdb;
        $requests_table_name = $wpdb->prefix . 'cachilupi_requests';
        $client_id = $user->ID;

        $all_client_requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.display_name as driver_name 
                 FROM {$requests_table_name} r 
                 LEFT JOIN {$wpdb->users} u ON r.driver_id = u.ID 
                 WHERE r.client_user_id = %d ORDER BY r.time DESC, r.created_at DESC",
                $client_id
            )
        );

        $active_client_requests = [];
        $historical_client_requests = [];
        $client_active_statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'picked_up']; 
        $client_historical_statuses = ['completed', 'rejected'];

        if ($all_client_requests) {
            foreach ($all_client_requests as $request_item) {
                if (in_array(strtolower($request_item->status), $client_historical_statuses)) {
                    $historical_client_requests[] = $request_item;
                } else { 
                    $active_client_requests[] = $request_item;
                }
            }
        }
        
        echo '<div class="cachilupi-client-requests-panel">';
        echo '<h2>' . esc_html__('Mis Solicitudes de Servicio', 'cachilupi-pet') . '</h2>';

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#client-active-requests" class="nav-tab nav-tab-active">' . esc_html__('Solicitudes Activas', 'cachilupi-pet') . '</a>';
        echo '<a href="#client-historical-requests" class="nav-tab">' . esc_html__('Historial de Solicitudes', 'cachilupi-pet') . '</a>';
        echo '</h2>';

        echo '<div id="client-active-requests" class="tab-content active">';
        if ( !empty($active_client_requests) ) {
            echo '<table class="widefat fixed striped" cellspacing="0">';
            echo '<thead><tr>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('ID', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Fecha Programada', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Origen', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Destino', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Mascota', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Estado', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Conductor', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Seguimiento', 'cachilupi-pet') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $active_client_requests as $request_item ) {
                echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
                echo '<td class="column-columnname" data-label="' . esc_attr__('ID:', 'cachilupi-pet') . '">' . esc_html( $request_item->id ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Fecha Programada:', 'cachilupi-pet') . '">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request_item->time ) ) ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Origen:', 'cachilupi-pet') . '">' . esc_html( $request_item->pickup_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Destino:', 'cachilupi-pet') . '">' . esc_html( $request_item->dropoff_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Mascota:', 'cachilupi-pet') . '">' . esc_html( $request_item->pet_type ) . '</td>';
                $status_slug_class = 'request-status-' . esc_attr( strtolower( $request_item->status ) );
                echo '<td class="column-columnname request-status ' . $status_slug_class . '" data-label="' . esc_attr__('Estado:', 'cachilupi-pet') . '"><span>' . esc_html( cachilupi_pet_translate_status( $request_item->status ) ) . '</span></td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Conductor:', 'cachilupi-pet') . '">' . esc_html( $request_item->driver_name ? $request_item->driver_name : __('No asignado aún', 'cachilupi-pet') ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Seguimiento:', 'cachilupi-pet') . '">';
                switch ( strtolower($request_item->status) ) {
                    case 'pending':
                        echo esc_html__('Seguimiento disponible una vez que el conductor acepte e inicie el viaje.', 'cachilupi-pet');
                        break;
                    case 'accepted':
                        echo esc_html__('Seguimiento disponible cuando el conductor inicie el viaje.', 'cachilupi-pet');
                        break;
                    case 'on_the_way':
                        if ( $request_item->driver_id ) {
                            echo '<button class="button cachilupi-follow-driver-btn" data-request-id="' . esc_attr( $request_item->id ) . '">' . esc_html__('Seguir Viaje en Tiempo Real', 'cachilupi-pet') . '</button>';
                        } else {
                            echo esc_html__('Información de seguimiento no disponible.', 'cachilupi-pet');
                        }
                        break;
                    case 'arrived':
                        echo esc_html__('El conductor ha llegado al punto de recogida.', 'cachilupi-pet');
                        break;
                    case 'picked_up':
                         if ( $request_item->driver_id ) { 
                            echo '<button class="button cachilupi-follow-driver-btn" data-request-id="' . esc_attr( $request_item->id ) . '">' . esc_html__('Seguir Viaje (Mascota a Bordo)', 'cachilupi-pet') . '</button>';
                        } else {
                            echo esc_html__('Mascota recogida, seguimiento no disponible.', 'cachilupi-pet');
                        }
                        break;
                    default: 
                        echo esc_html( cachilupi_pet_translate_status( $request_item->status ) );
                        break;
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No tienes solicitudes activas en este momento.', 'cachilupi-pet') . '</p>';
        }
        echo '</div>'; 

        echo '<div id="client-historical-requests" class="tab-content" style="display:none;">';
        if ( !empty($historical_client_requests) ) {
            echo '<table class="widefat fixed striped" cellspacing="0">';
            echo '<thead><tr>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('ID', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Fecha Programada', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Origen', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Destino', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Mascota', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Estado Final', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Conductor', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Detalles del Viaje', 'cachilupi-pet') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $historical_client_requests as $request_item ) {
                echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
                echo '<td class="column-columnname" data-label="' . esc_attr__('ID:', 'cachilupi-pet') . '">' . esc_html( $request_item->id ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Fecha Programada:', 'cachilupi-pet') . '">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request_item->time ) ) ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Origen:', 'cachilupi-pet') . '">' . esc_html( $request_item->pickup_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Destino:', 'cachilupi-pet') . '">' . esc_html( $request_item->dropoff_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Mascota:', 'cachilupi-pet') . '">' . esc_html( $request_item->pet_type ) . '</td>';
                $status_slug_class = 'request-status-' . esc_attr( strtolower( $request_item->status ) );
                echo '<td class="column-columnname request-status ' . $status_slug_class . '" data-label="' . esc_attr__('Estado Final:', 'cachilupi-pet') . '"><span>' . esc_html( cachilupi_pet_translate_status( $request_item->status ) ) . '</span></td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Conductor:', 'cachilupi-pet') . '">' . esc_html( $request_item->driver_name ? $request_item->driver_name : __('N/A', 'cachilupi-pet') ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Detalles del Viaje:', 'cachilupi-pet') . '">';
                switch ( strtolower($request_item->status) ) {
                    case 'completed':
                        echo esc_html__('Viaje finalizado con éxito.', 'cachilupi-pet');
                        break;
                    case 'rejected':
                        echo esc_html__('Solicitud rechazada.', 'cachilupi-pet');
                        break;
                    default: 
                        echo esc_html( cachilupi_pet_translate_status( $request_item->status ) );
                        break;
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No tienes solicitudes en el historial.', 'cachilupi-pet') . '</p>';
        }
        echo '</div>'; 
        echo '</div>'; 

        echo '<div id="cachilupi-follow-modal" style="display:none; position:fixed; top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:10000;"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;max-width:700px;height:70%;background-color:white;padding:20px;border-radius:8px;"><h3 id="cachilupi-follow-modal-title">' . esc_html__('Siguiendo Viaje', 'cachilupi-pet') . '</h3><div id="cachilupi-client-follow-map" style="width:100%;height:80%;"></div><button id="cachilupi-close-follow-modal" style="margin-top:10px;">' . esc_html__('Cerrar', 'cachilupi-pet') . '</button></div></div>';
    }
    return ob_get_clean();
}
// add_shortcode( 'cachilupi_maps', 'cachilupi_pet_shortcode' ); // Moved to Cachilupi_Pet_Plugin init

// Handle AJAX request for submitting service requests
function cachilupi_pet_submit_service_request() {
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator' ), (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => 'No tienes permisos para enviar solicitudes.'
        ));
        wp_die();
    }

    check_ajax_referer('cachilupi_pet_submit_request', 'security');

    $pickup_address = isset($_POST['pickup_address']) ? sanitize_text_field($_POST['pickup_address']) : '';
    $dropoff_address = isset($_POST['dropoff_address']) ? sanitize_text_field($_POST['dropoff_address']) : '';
    $pet_type = isset($_POST['pet_type']) ? sanitize_text_field($_POST['pet_type']) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''; 
    $scheduled_date_time = isset($_POST['scheduled_date_time']) ? sanitize_text_field($_POST['scheduled_date_time']) : '';

    $pickup_lat_str = isset($_POST['pickup_lat']) ? $_POST['pickup_lat'] : null;
    $pickup_lon_str = isset($_POST['pickup_lon']) ? $_POST['pickup_lon'] : null;
    $dropoff_lat_str = isset($_POST['dropoff_lat']) ? $_POST['dropoff_lat'] : null;
    $dropoff_lon_str = isset($_POST['dropoff_lon']) ? $_POST['dropoff_lon'] : null;
    $pet_instructions = isset($_POST['pet_instructions']) ? sanitize_textarea_field($_POST['pet_instructions']) : '';

    if ( empty($pickup_address) || is_null($pickup_lat_str) || is_null($pickup_lon_str) ||
         empty($dropoff_address) || is_null($dropoff_lat_str) || is_null($dropoff_lon_str) ||
         empty($pet_type) || empty($scheduled_date_time) ) {
         wp_send_json_error(array(
             'message' => 'Por favor, completa todos los campos requeridos.'
         ));
         wp_die();
    }

    if (!is_numeric($pickup_lat_str) || !is_numeric($pickup_lon_str) ||
        !is_numeric($dropoff_lat_str) || !is_numeric($dropoff_lon_str)) {
        wp_send_json_error(array('message' => 'Las coordenadas deben ser numéricas.'));
        wp_die();
    }

    $pickup_lat = floatval($pickup_lat_str);
    $pickup_lon = floatval($pickup_lon_str);
    $dropoff_lat = floatval($dropoff_lat_str);
    $dropoff_lon = floatval($dropoff_lon_str);

    if ($pickup_lat < -90.0 || $pickup_lat > 90.0 || $pickup_lon < -180.0 || $pickup_lon > 180.0 ||
        $dropoff_lat < -90.0 || $dropoff_lat > 90.0 || $dropoff_lon < -180.0 || $dropoff_lon > 180.0) {
        wp_send_json_error(array('message' => 'Coordenadas geográficas fuera de rango.'));
        wp_die();
    }

    $scheduledDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_date_time);
    if ($scheduledDateTime === false) {
        $scheduledDateTime = DateTime::createFromFormat('Y-m-d H:i', $scheduled_date_time);
         if ($scheduledDateTime === false) {
            $scheduledDateTime = DateTime::createFromFormat('Y-m-d', $scheduled_date_time);
            if ($scheduledDateTime) { 
                 $scheduledDateTime->setTime(0,0,0);
            } else {
                 wp_send_json_error(array(
                    'message' => 'Formato de fecha y hora incorrecto. Asegúrese de que la fecha y la hora estén seleccionadas.',
                    'debug_sent_value' => $scheduled_date_time 
                ));
                wp_die();
            }
        }
    }
    
    if ($scheduled_date_time && strpos($scheduled_date_time, ' ') === false && $scheduledDateTime && $scheduledDateTime->format('H:i:s') === '00:00:00') {
        wp_send_json_error(array(
            'message' => 'Por favor, selecciona tanto la fecha como la hora para el servicio.',
            'debug_parsed_dt' => $scheduledDateTime ? $scheduledDateTime->format('Y-m-d H:i:s') : 'null'
        ));
        wp_die();
    }


    $now = new DateTime();
    $currentTimeWithBuffer = (clone $now)->modify('-1 minute'); 
    $minScheduledTime = (clone $now)->modify('+89 minutes'); 

    if ($scheduledDateTime < $minScheduledTime) {
        wp_send_json_error(array(
            'message' => 'El servicio debe ser agendado con al menos 90 minutos de anticipación desde la hora actual.',
            'debug_current_time' => $now->format('Y-m-d H:i:s'),
            'debug_scheduled_time' => $scheduledDateTime->format('Y-m-d H:i:s'),
            'debug_min_allowed' => $minScheduledTime->format('Y-m-d H:i:s')
        ));
        wp_die();
    }

    $scheduledHour = (int) $scheduledDateTime->format('H');
    $scheduledMinute = (int) $scheduledDateTime->format('i');

    if ($scheduledHour < 8 || ($scheduledHour === 21 && $scheduledMinute > 0) || $scheduledHour > 21 ) { 
        wp_send_json_error(array('message' => 'El servicio debe ser agendado entre las 8:00 y las 21:00.'));
        wp_die();
    }

    $data = array(
        'time' => $scheduledDateTime->format('Y-m-d H:i:s'), 
        'pickup_address' => $pickup_address,
        'pickup_lat' => $pickup_lat,
        'pickup_lon' => $pickup_lon,
        'dropoff_address' => $dropoff_address,
        'dropoff_lat' => $dropoff_lat,
        'dropoff_lon' => $dropoff_lon,
        'pet_type' => $pet_type,
        'notes' => $notes,
        'status' => 'pending', 
        'created_at' => current_time('mysql'), 
        'client_user_id' => get_current_user_id(), 
        'pet_instructions' => $pet_instructions,
    );

    $format = array(
        '%s', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s'
    );

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $result = $wpdb->insert($table_name, $data, $format); 

    if ($result === false) {
        wp_send_json_error(array(
            'message' => 'Error al guardar la solicitud. Por favor, intenta de nuevo.'
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Solicitud guardada correctamente.',
            'request_id' => $wpdb->insert_id 
        ));
    }
    wp_die(); 
}
add_action('wp_ajax_cachilupi_pet_submit_request', 'cachilupi_pet_submit_service_request');


// Settings Page logic is now handled by CachilupiPet\Admin\Cachilupi_Pet_Settings

/**
 * Carga el text domain del plugin para la traducción.
 */
// function cachilupi_pet_load_textdomain() {
//    load_plugin_textdomain( 'cachilupi-pet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
// }
// add_action( 'plugins_loaded', 'cachilupi_pet_load_textdomain' ); // This is now handled by the class's init method.

// register_activation_hook( __FILE__, 'cachilupi_pet_activate' ); // This is now handled above.

// AJAX Handler for checking new requests (for driver panel polling)
function cachilupi_pet_check_new_requests_ajax_handler() {
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( 'driver', (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => 'No tienes permisos para realizar esta acción.',
            'new_requests_count' => 0
        ));
        wp_die();
    }

    check_ajax_referer('cachilupi_check_new_requests_nonce', 'security');

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $current_driver_id = get_current_user_id(); 

    $pending_unassigned_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = %s AND (driver_id IS NULL OR driver_id = 0)",
            'pending'
        )
    );

    if ( is_numeric($pending_unassigned_count) && $pending_unassigned_count > 0 ) {
        wp_send_json_success(array(
            'new_requests_count' => (int)$pending_unassigned_count,
            'message' => __('Nuevas solicitudes pendientes encontradas.', 'cachilupi-pet')
        ));
    } else {
        wp_send_json_success(array(
            'new_requests_count' => 0,
            'message' => __('No hay nuevas solicitudes pendientes.', 'cachilupi-pet')
        ));
    }

    wp_die();
}
add_action('wp_ajax_cachilupi_check_new_requests', 'cachilupi_pet_check_new_requests_ajax_handler');

// AJAX Handler for getting client requests status (for client panel polling)
function cachilupi_pet_get_client_requests_status_ajax_handler() {
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator' ), (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => __('Acceso no autorizado.', 'cachilupi-pet')
        ));
        wp_die();
    }

    check_ajax_referer('cachilupi_pet_get_requests_status_nonce', 'security');

    global $wpdb;
    $client_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'cachilupi_requests';

    $client_requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, status, driver_id FROM {$table_name} WHERE client_user_id = %d ORDER BY created_at DESC",
            $client_id
        )
    );

    if ( $wpdb->last_error ) {
        wp_send_json_error(array(
            'message' => __('Error al obtener el estado de las solicitudes.', 'cachilupi-pet')
        ));
        wp_die();
    }

    $statuses_data = array();
    if ( $client_requests ) {
        foreach ( $client_requests as $request ) {
            $status_display = cachilupi_pet_translate_status( $request->status );
            $statuses_data[] = array(
                'request_id'     => $request->id,
                'status_slug'    => $request->status,
                'status_display' => $status_display,
                'driver_id'      => $request->driver_id ? (int) $request->driver_id : null,
            );
        }
    }

    wp_send_json_success( $statuses_data );
    wp_die();
}
add_action('wp_ajax_cachilupi_get_client_requests_status', 'cachilupi_pet_get_client_requests_status_ajax_handler');

?>
