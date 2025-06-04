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

// Activation function
function cachilupi_pet_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests'; // Prefix with wp_ or your custom prefix
 $charset_collate = $wpdb->get_charset_collate();

    // SQL statement to create the table
    // Define the table structure and data types
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        pickup_address text NOT NULL,
        pickup_lat decimal(10,7) NOT NULL,
        pickup_lon decimal(10,7) NOT NULL,
        dropoff_address text NOT NULL,
        dropoff_lat decimal(10,7) NOT NULL,
        dropoff_lon decimal(10,7) NOT NULL,
        pet_type varchar(50) DEFAULT '' NOT NULL,
        notes text,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        driver_id bigint(20) unsigned NULL DEFAULT NULL,
        driver_current_lat decimal(10,7) NULL DEFAULT NULL,
        driver_current_lon decimal(10,7) NULL DEFAULT NULL,
        driver_location_updated_at datetime NULL DEFAULT NULL,
        client_user_id bigint(20) unsigned NULL DEFAULT NULL,
        pet_instructions TEXT NULL,
        PRIMARY KEY  (id),
        KEY driver_id (driver_id),
        KEY client_user_id (client_user_id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Add 'driver' role on plugin activation
    // Ensure the role is added if it doesn't exist
     if ( null === get_role( 'driver' ) ) {
        add_role(
            'driver',
            __( 'Driver', 'cachilupi-pet' ), // Updated text domain
            array(
                'read'         => true, // Can read posts/pages
                'edit_posts'   => false, // Cannot edit others' posts
                'delete_posts' => false
                // Puedes añadir capacidades específicas para ver solicitudes, aceptar, rechazar, etc.
                // Por ejemplo: 'view_requests' => true, 'manage_requests' => true
            )
        );
    }
     // Add 'client' role on plugin activation
    // Ensure the role is added if it doesn't exist
    if ( null === get_role( 'client' ) ) {
        add_role(
            'client',
            __( 'Cliente', 'cachilupi-pet' ), // Updated text domain
            array(
                'read' => true, // Basic read access for clients
                // Puedes añadir capacidades específicas para enviar solicitudes, ver historial, etc.
                // Por ejemplo: 'submit_request' => true, 'view_history' => true
            )
        );
    }
    // Default options
    add_option('cachilupi_pet_mapbox_token', '');
    add_option('cachilupi_pet_client_redirect_slug', 'reserva');
    add_option('cachilupi_pet_driver_redirect_slug', 'driver');
}

// =============================================================================
// Funcionalidad de Redirección Post-Login Personalizada (Manejada por Plugin)
// =============================================================================

/**
 * Redirige a los usuarios a una página personalizada después de iniciar sesión,
 * excluyendo a los administradores y redirigiendo según roles 'driver' o 'client'.
 *
 * Esta función se engancha al filtro 'login_redirect'.
 *
 * @param string $redirect_to El destino de redirección predeterminado (normalmente wp-admin).
 * @param string $requested_redirect_to El destino de redirección solicitado por el usuario (si intentó acceder a una página protegida).
 * @param object $user El objeto WP_User del usuario que ha iniciado sesión.
 * @return string La URL de redirección final.
 */
function cachilupi_pet_custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {

    // Asegurarse de que tenemos un objeto de usuario válido
    if ( ! is_wp_error( $user ) && $user instanceof WP_User ) {

        // --- Define las URLs de tus páginas personalizadas ---
        // IMPORTANTE: Reemplaza los slugs de ejemplo ('/reserva/', '/driver/')
        // Ahora se obtienen de las opciones del plugin.
        $client_slug = get_option('cachilupi_pet_client_redirect_slug', 'reserva');
        $driver_slug = get_option('cachilupi_pet_driver_redirect_slug', 'driver');

        $client_redirect_url = home_url( '/' . trim($client_slug, '/') . '/' );
        $driver_panel_url = home_url( '/' . trim($driver_slug, '/') . '/' );

        // --- Lógica de Redirección por Rol ---

        // 1. Si el usuario es administrador, permite la redirección normal a wp-admin
        if ( in_array( 'administrator', (array) $user->roles ) ) {
            // Si hay una URL solicitada (ej: intentó acceder a wp-admin/edit.php), redirige allí.
            // De lo contrario, usa el destino por defecto de WordPress.
            return $requested_redirect_to ? $requested_redirect_to : $redirect_to;
        }

        // 2. Si el usuario tiene el rol 'driver', redirige al panel del conductor
        if ( in_array( 'driver', (array) $user->roles ) ) {
            return $driver_panel_url;
        }

        // 3. Si el usuario tiene el rol 'client', redirige a la página del cliente (formulario de reserva)
         if ( in_array( 'client', (array) $user->roles ) ) {
            return $client_redirect_url;
        }

        // 4. Para cualquier otro rol (suscriptor, editor, etc.) que no sea admin, driver o client,
        // puedes redirigirlos a una página por defecto, por ejemplo, la página del cliente.
        // Opcional: podrías redirigirlos a la página de inicio home_url('/') si no tienen un rol específico.
        return $client_redirect_url; // Redirección por defecto para otros roles

    }

    // Si el objeto de usuario no es válido o hay un error, devuelve el destino predeterminado
    return $redirect_to;
}

// Engancha la función al filtro 'login_redirect'
// La prioridad 10 es estándar. 3 indica que acepta 3 argumentos ($redirect_to, $requested_redirect_to, $user).
add_filter( 'login_redirect', 'cachilupi_pet_custom_login_redirect', 10, 3 );

// =============================================================================
// Fin Funcionalidad de Redirección Post-Login Personalizada
// =============================================================================


// =============================================================================
// Modificar Mensajes de Error de Login por Defecto
// =============================================================================

/**
 * Modifica los mensajes de error de inicio de sesión por defecto de WordPress para ser más genéricos.
 * Esto ayuda a prevenir la enumeración de usuarios.
 *
 * @param string $errors El mensaje de error por defecto de WordPress.
 * @return string El mensaje de error modificado.
 */
function cachilupi_pet_generic_login_error( $errors ) {
    // Comprueba si hay algún error de login
    if ( ! empty( $errors ) ) {
        // Reemplaza cualquier mensaje de error de login por un mensaje genérico.
        // Esto cubre errores de usuario/email incorrecto y contraseña incorrecta.
        $error_title = esc_html__( 'Error', 'cachilupi-pet' );
        $error_message = esc_html__( 'Nombre de usuario o contraseña incorrectos.', 'cachilupi-pet' );
        $errors = sprintf( '<p class="login-error-message"><strong>%s:</strong> %s</p>', $error_title, $error_message );
    }
    return $errors;
}

// Engancha la función al filtro 'login_errors'
add_filter( 'login_errors', 'cachilupi_pet_generic_login_error' );


// =============================================================================
// Fin Modificar Mensajes de Error de Login por Defecto
// =============================================================================


// =============================================================================
// Función para traducir los estados de las solicitudes
// =============================================================================
/**
 * Traduce los slugs de estado de las solicitudes a un formato legible en español.
 *
 * @param string $status_slug El slug del estado (ej. 'pending', 'accepted').
 * @return string La cadena traducida del estado.
 */
function cachilupi_pet_translate_status( $status_slug ) {
    // Asegurarse de que el slug es un string
    if ( ! is_string( $status_slug ) ) {
        // Si no es un string, intenta convertirlo o devuelve un valor por defecto
        return is_scalar( $status_slug ) ? ucfirst( esc_html( (string) $status_slug ) ) : __( 'Desconocido', 'cachilupi-pet' );
    }

    $translations = array(
        'pending'   => __( 'Pendiente', 'cachilupi-pet' ),
        'accepted'  => __( 'Aceptado', 'cachilupi-pet' ),
        'rejected'  => __( 'Rechazado', 'cachilupi-pet' ),
        'on_the_way' => __( 'En Camino', 'cachilupi-pet' ),
        'arrived'   => __( 'En Origen', 'cachilupi-pet' ), // O 'Ha llegado al origen'
        'picked_up' => __( 'Mascota Recogida', 'cachilupi-pet' ),
        'completed' => __( 'Completado', 'cachilupi-pet' ), // Para futuras implementaciones
        // Puedes añadir más estados y sus traducciones aquí
    );

    $status_slug_lower = strtolower( $status_slug );

    return isset( $translations[ $status_slug_lower ] ) ? $translations[ $status_slug_lower ] : ucfirst( esc_html( $status_slug ) );
}

// Shortcode for displaying the Driver panel
function cachilupi_pet_driver_panel_shortcode() {
    // Check if user is logged in and has the 'driver' role
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( 'driver', (array) $user->roles ) ) {
        return '<p>' . esc_html__( 'Debes iniciar sesión como conductor para acceder a este panel.', 'cachilupi-pet' ) . '</p>';
    }

    ob_start(); // Start output buffering
    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $current_driver_id = $user->ID;

    // Fetch all requests for the current driver (any status) AND all 'pending' unassigned requests.
    // Sorted by time DESC, then created_at DESC.
    $all_requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, u.display_name as client_name
             FROM {$table_name} r
             LEFT JOIN {$wpdb->users} u ON r.client_user_id = u.ID
             WHERE (r.driver_id = %d OR (r.status = %s AND r.driver_id IS NULL))
             ORDER BY r.time DESC, r.created_at DESC",
            $current_driver_id,
            'pending'
        )
    );

    $active_requests = [];
    $historical_requests = [];

    $active_statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'picked_up'];
    $historical_statuses = ['completed', 'rejected'];

    if ($all_requests) {
        foreach ($all_requests as $request) {
            if (in_array($request->status, $active_statuses)) {
                // Include unassigned pending requests for all drivers to see
                if ($request->status === 'pending' && is_null($request->driver_id)) {
                    $active_requests[] = $request;
                } elseif (!is_null($request->driver_id) && $request->driver_id == $current_driver_id) {
                    // Include requests assigned to the current driver
                    $active_requests[] = $request;
                }
            } elseif (in_array($request->status, $historical_statuses) && !is_null($request->driver_id) && $request->driver_id == $current_driver_id) {
                $historical_requests[] = $request;
            }
        }
    }
    ?>
    <div class="wrap cachilupi-driver-panel">
        <h2><?php esc_html_e('Panel del Conductor', 'cachilupi-pet'); ?></h2>
        <div id="driver-panel-feedback" class="feedback-messages-container" style="margin-bottom: 15px;"></div>

        <h2 class="nav-tab-wrapper">
            <a href="#active-requests" class="nav-tab nav-tab-active"><?php esc_html_e('Solicitudes Activas', 'cachilupi-pet'); ?></a>
            <a href="#historical-requests" class="nav-tab"><?php esc_html_e('Historial de Solicitudes', 'cachilupi-pet'); ?></a>
        </h2>

        <div id="active-requests" class="tab-content">
            <?php if ( !empty($active_requests) ) : ?>
                <table class="widefat fixed striped" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('ID', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Fecha y Hora', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Origen', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Cliente', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Destino', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Estado', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Mascota', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Instrucciones Mascota', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Notas', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Acciones', 'cachilupi-pet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $active_requests as $request ) : ?>
                            <tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
                                <td class="column-columnname" data-label="<?php esc_attr_e('ID:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->id ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Fecha y Hora:', 'cachilupi-pet'); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->time ) ) ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Origen:', 'cachilupi-pet'); ?>">
                                    <?php echo esc_html( $request->pickup_address ); ?>
                                    <br>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->pickup_address); ?>" target="_blank" class="map-link"><?php esc_html_e('Ver en Google Maps', 'cachilupi-pet'); ?></a>
                                </td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Cliente:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->client_name ? $request->client_name : __('N/A', 'cachilupi-pet') ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Destino:', 'cachilupi-pet'); ?>">
                                    <?php echo esc_html( $request->dropoff_address ); ?>
                                    <br>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->dropoff_address); ?>" target="_blank" class="map-link"><?php esc_html_e('Ver en Google Maps', 'cachilupi-pet'); ?></a>
                                </td>
                                <td class="column-columnname request-status" data-label="<?php esc_attr_e('Estado:', 'cachilupi-pet'); ?>"><?php echo esc_html( cachilupi_pet_translate_status( $request->status ) ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_type ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Instrucciones Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_instructions ? $request->pet_instructions : '--' ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Notas:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->notes ? $request->notes : '--'); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Acciones:', 'cachilupi-pet'); ?>">
                                    <?php
                                    $current_status_slug = strtolower( $request->status );
                                    $accept_class   = 'accept-request';
                                    $reject_class   = 'reject-request';
                                    $arrive_class   = 'arrive-request';
                                    $on_the_way_class = 'on-the-way-request';
                                    $picked_up_class = 'picked-up-request';
                                    $complete_class = 'complete-request';
                                    $action_button_shown = false;

                                    if ( $current_status_slug === 'pending' && (is_null($request->driver_id) || $request->driver_id == $current_driver_id) ) : ?>
                                        <button class="button <?php echo esc_attr($accept_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="accept"><?php esc_html_e('Aceptar', 'cachilupi-pet'); ?></button>
                                        <button class="button <?php echo esc_attr($reject_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="reject"><?php esc_html_e('Rechazar', 'cachilupi-pet'); ?></button>
                                        <?php $action_button_shown = true; ?>
                                    <?php elseif ( $current_status_slug === 'accepted' && $request->driver_id == $current_driver_id ) : ?>
                                        <button class="button <?php echo esc_attr($on_the_way_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="on_the_way"><?php esc_html_e('Iniciar Viaje', 'cachilupi-pet'); ?></button>
                                        <?php $action_button_shown = true; ?>
                                    <?php elseif ( $current_status_slug === 'on_the_way' && $request->driver_id == $current_driver_id ) : ?>
                                        <button class="button <?php echo esc_attr($arrive_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="arrive"><?php esc_html_e('He Llegado al Origen', 'cachilupi-pet'); ?></button>
                                        <?php $action_button_shown = true; ?>
                                    <?php elseif ( $current_status_slug === 'arrived' && $request->driver_id == $current_driver_id ) : ?>
                                        <button class="button <?php echo esc_attr($picked_up_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="picked_up"><?php esc_html_e('Mascota Recogida', 'cachilupi-pet'); ?></button>
                                        <?php $action_button_shown = true; ?>
                                    <?php elseif ( $current_status_slug === 'picked_up' && $request->driver_id == $current_driver_id ) : ?>
                                        <button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete"><?php esc_html_e('Completar Viaje', 'cachilupi-pet'); ?></button>
                                        <?php $action_button_shown = true; ?>
                                    <?php endif; ?>

                                    <?php if ( !$action_button_shown ) : ?>
                                        <span><?php esc_html_e('--', 'cachilupi-pet'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No hay solicitudes activas en este momento.', 'cachilupi-pet'); ?></p>
            <?php endif; ?>
        </div>

        <div id="historical-requests" class="tab-content" style="display:none;">
            <?php if ( !empty($historical_requests) ) : ?>
                <table class="widefat fixed striped" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('ID', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Fecha y Hora', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Origen', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Cliente', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Destino', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Mascota', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Instrucciones Mascota', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Notas', 'cachilupi-pet'); ?></th>
                            <th class="manage-column column-columnname" scope="col"><?php esc_html_e('Estado Final', 'cachilupi-pet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $historical_requests as $request ) : ?>
                            <tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
                                <td class="column-columnname" data-label="<?php esc_attr_e('ID:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->id ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Fecha y Hora:', 'cachilupi-pet'); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->time ) ) ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Origen:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pickup_address ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Cliente:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->client_name ? $request->client_name : __('N/A', 'cachilupi-pet') ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Destino:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->dropoff_address ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_type ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Instrucciones Mascota:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->pet_instructions ? $request->pet_instructions : '--' ); ?></td>
                                <td class="column-columnname" data-label="<?php esc_attr_e('Notas:', 'cachilupi-pet'); ?>"><?php echo esc_html( $request->notes ? $request->notes : '--'); ?></td>
                                <td class="column-columnname request-status" data-label="<?php esc_attr_e('Estado Final:', 'cachilupi-pet'); ?>"><?php echo esc_html( cachilupi_pet_translate_status( $request->status ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No hay solicitudes en el historial.', 'cachilupi-pet'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab-wrapper .nav-tab').click(function(e) {
                e.preventDefault();
                var tab_id = $(this).attr('href');

                $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                $('.tab-content').hide();

                $(this).addClass('nav-tab-active');
                $(tab_id).show();
            });
        });
    </script>
    <?php
    return ob_get_clean(); // Return the buffered content
}
add_shortcode('cachilupi_driver_panel', 'cachilupi_pet_driver_panel_shortcode');

// Handle AJAX request for driver actions (accept/reject/arrive/complete)
function cachilupi_pet_handle_driver_action() {

     // Check if user is logged in and has the 'driver' role
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( 'driver', (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => 'No tienes permisos para realizar esta acción.'
        ));
        wp_die(); // Detiene la ejecución si el usuario no tiene el rol correcto
    }
     // Check AJAX nonce
    // Asegúrate de que el nonce que se verifica aquí coincide con el que se genera en wp_localize_script para el panel del conductor.
    check_ajax_referer('cachilupi_pet_driver_action', 'security');

    // Retrieve data from $_POST
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0; // Ensure it's an integer
    $action = isset($_POST['driver_action']) ? sanitize_text_field($_POST['driver_action']) : ''; // 'driver_action' is sent by JS

    // Basic validation
    if ($request_id <= 0 || !in_array($action, array('accept', 'reject', 'on_the_way', 'arrive', 'picked_up', 'complete'))) {
        wp_send_json_error(array(
            'message' => 'Datos de solicitud inválidos o acción desconocida.'
        ));
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';

    // Fetch the request to check current driver_id and status
    $request_being_actioned = $wpdb->get_row($wpdb->prepare("SELECT driver_id, status FROM {$table_name} WHERE id = %d", $request_id));

    if (!$request_being_actioned) {
        wp_send_json_error(array('message' => 'Solicitud no encontrada.'));
        wp_die();
    }

    $new_status_slug = ''; // Slug for the new status
    $data_to_update = array();
    $data_formats = array();
    $where_formats = array('%d'); // For 'id' => $request_id

    // Get the current driver's user ID
    $current_driver_id = get_current_user_id();

    // Additional check for 'reject' action: only assigned driver or admin (not implemented here) can reject an already assigned request.
    // Unassigned requests can be rejected by any driver.
    if ($action === 'reject' && !is_null($request_being_actioned->driver_id) && $request_being_actioned->driver_id != $current_driver_id) {
        wp_send_json_error(array('message' => 'No puedes rechazar una solicitud asignada a otro conductor.'));
        wp_die();
    }

    // Prevent re-accepting/re-rejecting already processed pending requests by another driver
    if ($action === 'accept' && !is_null($request_being_actioned->driver_id) && $request_being_actioned->driver_id != $current_driver_id) {
        wp_send_json_error(array('message' => 'Esta solicitud ya ha sido aceptada por otro conductor.'));
        wp_die();
    }
    if ($action === 'accept' && $request_being_actioned->status !== 'pending') {
        wp_send_json_error(array('message' => 'Esta solicitud no está pendiente y no puede ser aceptada.'));
        wp_die();
    }
    if ($action === 'reject' && $request_being_actioned->status !== 'pending') {
        wp_send_json_error(array('message' => 'Esta solicitud no está pendiente y no puede ser rechazada.'));
        wp_die();
    }


    // Determine the new status and data based on the action
    switch ($action) {
        case 'accept':
            $new_status_slug = 'accepted';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            $data_to_update['driver_id'] = $current_driver_id; // Assign driver
            $data_formats[] = '%d';
            // For 'accept', we also need to ensure the request is still pending and not assigned to another driver
            // This is handled by an additional check before the switch for driver_id, and for status:
            // $where_conditions['driver_id'] IS NULL implicitly handled by UI logic, but good to be safe.
            // We ensure status is 'pending'. If a driver accepts, they are assigned.
            // No explicit $where_conditions['driver_id'] = NULL, as another driver might have just accepted it.
            // Instead, we check $request_being_actioned->driver_id earlier.
            // We also add a condition that the current status must be 'pending'.
            $where_conditions['status'] = 'pending';
            $where_formats[] = '%s';
            break;
        case 'reject':
            $new_status_slug = 'rejected';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            // If the request was assigned to the current driver and they reject it, driver_id could be nulled
            // or kept. Current logic keeps it. If unassigned, it stays unassigned.
            // The check for $request_being_actioned->driver_id != $current_driver_id (if set) is done above.
            // We also add a condition that the current status must be 'pending'.
            $where_conditions['status'] = 'pending';
            $where_formats[] = '%s';
            break;
        case 'on_the_way':
            $new_status_slug = 'on_the_way';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            break;
        case 'arrive':
            $new_status_slug = 'arrived';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            break;
        case 'picked_up':
            $new_status_slug = 'picked_up';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            break;
        case 'complete':
            $new_status_slug = 'completed';
            $data_to_update['status'] = $new_status_slug;
            $data_formats[] = '%s';
            break;
        default:
            // This case should not be reached due to the in_array check above
            wp_send_json_error(array('message' => 'Acción no válida.'));
            wp_die();
    }

    $where_conditions = array('id' => $request_id);

    // For actions that imply the request is already assigned to the current driver
    // and are state transitions from an accepted state by that driver.
    if (in_array($action, array('on_the_way', 'arrive', 'picked_up', 'complete'))) {
        $where_conditions['driver_id'] = $current_driver_id;
        $where_formats[] = '%d';
    }

    // Update the database
    $result = $wpdb->update(
        $table_name,
        $data_to_update,
        $where_conditions,
        $data_formats, // Format of data values
        $where_formats   // Format of where_conditions values
    );

    // If the update affected 0 rows because the conditions (e.g. status, current driver) were not met,
    // $result will be 0. We should treat this as a potential issue or stale state.
    if ($result === 0) {
        // Check if the request still exists with the original status to differentiate
        // "condition not met" from "request gone".
        $current_db_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table_name} WHERE id = %d", $request_id));
        if ($current_db_status && $current_db_status !== $request_being_actioned->status) {
             wp_send_json_error(array('message' => 'La solicitud fue actualizada por otro proceso. Por favor, refresca la página.', 'error_code' => 'status_changed'));
        } else if ($current_db_status && $action === 'accept' && $request_being_actioned->driver_id !== null && $request_being_actioned->driver_id != $current_driver_id){
            wp_send_json_error(array('message' => 'La solicitud ya fue aceptada por otro conductor. Por favor, refresca la página.', 'error_code' => 'already_accepted'));
        }
        else {
            wp_send_json_error(array('message' => 'No se pudo actualizar la solicitud. Es posible que ya haya sido procesada o las condiciones no se cumplen.', 'error_code' => 'condition_not_met'));
        }
        wp_die();
    } elseif ($result !== false) {
        // Obtener detalles de la solicitud para las notificaciones
        $request_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $request_id));
        $new_status_display = cachilupi_pet_translate_status($new_status_slug);
        $admin_email = get_option('admin_email');

        if ($request_details && $request_details->client_user_id) {
            $client_user_data = get_userdata($request_details->client_user_id);
            $client_email = $client_user_data->user_email;
            $client_name = $client_user_data->display_name;
            $driver_user_data = get_userdata($current_driver_id);
            $driver_name = $driver_user_data->display_name;

            $notification_subject = '';
            $notification_message_to_client = '';

            switch ($action) {
                case 'accept':
                    $notification_subject = sprintf(__('Tu solicitud #%d ha sido aceptada', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\n¡Buenas noticias! Tu solicitud de transporte #%d ha sido aceptada por el conductor %s.\n\nEstado actual: %s\n\nGracias,\nEl equipo de Cachilupi Pet", 'cachilupi-pet'),
                        $client_name, $request_id, $driver_name, $new_status_display
                    );
                    break;
                case 'reject':
                    $notification_subject = sprintf(__('Actualización sobre tu solicitud #%d', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nLamentamos informarte que tu solicitud de transporte #%d ha sido actualizada al estado: %s.\nSi fue rechazada, por favor, contacta con nosotros o intenta una nueva solicitud.\n\nGracias,\nEl equipo de Cachilupi Pet", 'cachilupi-pet'),
                        $client_name, $request_id, $new_status_display
                    );
                    // Opcional: Notificar al admin si un conductor rechaza
                    // wp_mail($admin_email, "Solicitud #{$request_id} rechazada por {$driver_name}", "La solicitud #{$request_id} para {$client_name} fue rechazada por el conductor {$driver_name}.");
                    break;
                case 'on_the_way':
                    $notification_subject = sprintf(__('¡Tu conductor está en camino! (Solicitud #%d)', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nEl conductor %s está en camino para tu servicio #%d.\nEstado actual: %s\n\nPuedes seguir el viaje desde tu panel si la opción está disponible.\n\nGracias,\nEl equipo de Cachilupi Pet", 'cachilupi-pet'),
                        $client_name, $driver_name, $request_id, $new_status_display
                    );
                    break;
                case 'arrive':
                    $notification_subject = sprintf(__('¡Tu conductor ha llegado! (Solicitud #%d)', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nEl conductor %s ha llegado al punto de recogida para tu servicio #%d.\nEstado actual: %s\n\nGracias,\nEl equipo de Cachilupi Pet", 'cachilupi-pet'),
                        $client_name, $driver_name, $request_id, $new_status_display
                    );
                    break;
                case 'picked_up':
                    $notification_subject = sprintf(__('¡Tu mascota ha sido recogida! (Solicitud #%d)', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nEl conductor %s ha recogido a tu mascota para el servicio #%d.\nEstado actual: %s.\n\nGracias,\nEl equipo de Cachilupi Pet", 'cachilupi-pet'),
                        $client_name, $driver_name, $request_id, $new_status_display
                    );
                    break;
                case 'complete':
                    $notification_subject = sprintf(__('¡Tu mascota ha llegado a su destino! (Solicitud #%d)', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nTu mascota ha llegado a su destino y el servicio #%d ha sido completado por el conductor %s.\n\n¡Gracias por usar Cachilupi Pet!", 'cachilupi-pet'),
                        $client_name, $request_id, $driver_name
                    );
                    break;
            }

            if ($client_email && $notification_subject && $notification_message_to_client) {
                wp_mail($client_email, $notification_subject, $notification_message_to_client);
            }
        }

        wp_send_json_success(array(
            'message' => 'Solicitud actualizada correctamente.',
            'new_status_slug' => $new_status_slug,
            'new_status_display' => $new_status_display
        ));
    } else {
        // Send error response without leaking potential DB info
        // Consider logging $wpdb->last_error for debugging on the server-side
        // error_log("Cachilupi Pet DB Error (handle_driver_action): " . $wpdb->last_error);
        wp_send_json_error(array('message' => 'Error al actualizar la solicitud. Por favor, intenta de nuevo.'));
    }
    wp_die();
}
add_action('wp_ajax_cachilupi_pet_driver_action', 'cachilupi_pet_handle_driver_action');
// No necesitas wp_ajax_nopriv para esta acción, ya que solo los conductores logueados deben poder realizarla.
// add_action('wp_ajax_nopriv_cachilupi_pet_driver_action', 'cachilupi_pet_handle_driver_action');

// AJAX handler for driver updating their location
function cachilupi_pet_update_driver_location() {
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( 'driver', (array) $user->roles ) ) {
        wp_send_json_error(array('message' => 'Acceso no autorizado.'));
        wp_die();
    }
    check_ajax_referer('cachilupi_pet_update_location_nonce', 'security');

    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null; // Keep as string for validation
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null; // Keep as string for validation

    // Validate latitude and longitude more carefully
    // Ensure they are numeric and within valid ranges. floatval will cast non-numeric to 0.0.
    if (
        $request_id <= 0 ||
        !is_numeric($latitude) || !is_numeric($longitude) ||
        floatval($latitude) < -90.0 || floatval($latitude) > 90.0 ||
        floatval($longitude) < -180.0 || floatval($longitude) > 180.0
    ) {
        wp_send_json_error(array('message' => 'Datos de ubicación inválidos o fuera de rango.'));
        wp_die();
    }

    // Convert to float after validation
    $latitude_float = floatval($latitude);
    $longitude_float = floatval($longitude);

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $current_driver_id = get_current_user_id();

    $result = $wpdb->update(
        $table_name,
        array('driver_current_lat' => $latitude_float, 'driver_current_lon' => $longitude_float, 'driver_location_updated_at' => current_time('mysql')),
        array('id' => $request_id, 'driver_id' => $current_driver_id, 'status' => 'on_the_way'), // Only update if status is 'on_the_way'
        array('%f', '%f', '%s'), // Formats for data
        array('%d', '%d', '%s')  // Formats for WHERE clause
    );

    // Check if the update actually changed a row. $result will be 0 if no row matched the WHERE clause or data was the same.
    // For location updates, it's common to send the same location if stationary, so $result === 0 might not always be an "error"
    // in the sense that the location wasn't recorded, but rather that it didn't change or conditions weren't met.
    // However, if the intention is to confirm the driver is still on this active trip, a $result === 0 could mean the trip is no longer 'on_the_way'
    // or assigned to this driver.
    if ($result > 0) {
        wp_send_json_success(array('message' => 'Ubicación actualizada.'));
    } elseif ($result === 0) {
        // Optionally, verify if the request still exists and matches conditions to give a more specific message.
        $request_check = $wpdb->get_row($wpdb->prepare(
            "SELECT status, driver_id FROM {$table_name} WHERE id = %d", $request_id
        ));
        if (!$request_check || $request_check->driver_id != $current_driver_id || $request_check->status != 'on_the_way') {
            wp_send_json_error(array('message' => 'No se pudo actualizar la ubicación. El viaje ya no está activo o no está asignado a ti.', 'error_code' => 'trip_not_active'));
        } else {
            // This means the location data sent was identical to what's already in the DB.
            wp_send_json_success(array('message' => 'Ubicación sin cambios.', 'status_code' => 'no_change'));
        }
    } else { // This else handles the case where $result is false (WPDB error)
        // error_log("Cachilupi Pet DB Error (update_driver_location): " . $wpdb->last_error);
        wp_send_json_error(array('message' => 'Error al actualizar ubicación. Por favor, intenta de nuevo.'));
    }
    wp_die();
}
add_action('wp_ajax_cachilupi_update_driver_location', 'cachilupi_pet_update_driver_location');

// AJAX handler for client fetching driver's location
function cachilupi_pet_get_driver_location() {
    $user = wp_get_current_user();
     if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator' ), (array) $user->roles ) ) {
        wp_send_json_error(array('message' => 'Acceso no autorizado.'));
        wp_die();
    }
    check_ajax_referer('cachilupi_pet_get_location_nonce', 'security');

    $request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
    if ($request_id <= 0) {
        wp_send_json_error(array('message' => 'ID de solicitud inválido.'));
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $client_id = get_current_user_id();

    $location_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT driver_current_lat, driver_current_lon, driver_location_updated_at FROM {$table_name} WHERE id = %d AND client_user_id = %d AND status = 'on_the_way'",
            $request_id,
            $client_id
        )
    );

    // Check if data was found and lat/lon are not null (0.0 is a valid coordinate)
    if ($location_data && !is_null($location_data->driver_current_lat) && !is_null($location_data->driver_current_lon)) {
        wp_send_json_success(array(
            'latitude' => floatval($location_data->driver_current_lat), // Ensure float type in response
            'longitude' => floatval($location_data->driver_current_lon), // Ensure float type in response
            'updated_at' => $location_data->driver_location_updated_at
        ));
    } else {
        wp_send_json_error(array('message' => 'Ubicación del conductor no disponible o viaje no activo.'));
    }
    wp_die();
}
add_action('wp_ajax_cachilupi_get_driver_location', 'cachilupi_pet_get_driver_location');

// Enqueue scripts and styles conditionally
function cachilupi_pet_enqueue_scripts() {
    global $post;

    // Check if it's a single post/page and has the shortcode [cachilupi_maps]
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cachilupi_maps' ) ) { // Shortcode name remains for compatibility
        // Enqueue custom CSS
        wp_enqueue_style(
            'cachilupi-maps',
            plugin_dir_url( __FILE__ ) . 'assets/css/maps.css', // Consider moving form styles here
            array(),
            '1.0'
        );

        // Enqueue Mapbox GL JS CSS
        wp_enqueue_style(
            'mapbox-gl-css',
            'https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css',
            array(),
            '2.14.1'
        );

        // Enqueue Mapbox Geocoding CSS
        wp_enqueue_style(
            'mapbox-gl-geocoder-css',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css',
            array( 'mapbox-gl-css' ),
            '5.0.0'
        );

        // Enqueue Mapbox GL JS
        wp_enqueue_script(
            'mapbox-gl',
            'https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js',
            array(),
            '2.14.1',
            true
        );

        // Enqueue Mapbox Geocoding control JS
        wp_enqueue_script(
            'mapbox-gl-geocoder',
            'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js',
            array( 'mapbox-gl' ),
            '5.0.0',
            true
        );

        // Enqueue custom JS
        wp_enqueue_script(
            'cachilupi-maps',
            plugin_dir_url( __FILE__ ) . 'assets/js/maps.js',
            array( 'mapbox-gl', 'jquery' ),
            '1.3', // Updated version for JS changes (removed geolocation button logic and variable scope fix)
            true
        );

        $mapbox_token = get_option('cachilupi_pet_mapbox_token', '');
        // Pass PHP variables to JavaScript
        wp_localize_script( 'cachilupi-maps', 'cachilupi_pet_vars', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'submit_request_nonce' => wp_create_nonce( 'cachilupi_pet_submit_request' ),
            'get_location_nonce' => wp_create_nonce( 'cachilupi_pet_get_location_nonce'), // Nonce for client getting location
            'home_url' => home_url( '/' ), // Puede que no necesites home_url aquí si la redirección post-envío la manejas en JS
            'get_requests_status_nonce' => wp_create_nonce( 'cachilupi_pet_get_requests_status_nonce' ),
            'mapbox_access_token' => $mapbox_token,
            'text_follow_driver' => __('Seguir Conductor', 'cachilupi-pet'),
            'text_driver_location_not_available' => __('Ubicación del conductor no disponible en este momento.', 'cachilupi-pet'),
        ) );
    }

    // Check if it's a single post/page and has the shortcode [cachilupi_driver_panel]
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cachilupi_driver_panel' ) ) {
         // Enqueue custom CSS for driver panel
        wp_enqueue_style(
            'cachilupi-driver-panel-css', // Handle único para el CSS
            plugin_dir_url( __FILE__ ) . 'assets/css/driver-panel.css', // Ruta al archivo CSS
            array(), // Dependencias (ninguna en este caso)
            '1.0' // Versión
        );
        wp_enqueue_script(
            'cachilupi-driver-panel',
            plugin_dir_url( __FILE__ ) . 'assets/js/driver-panel.js',
            array( 'jquery' ),
            '1.0', // Consider versioning based on file changes
            true
        );

        // Pass PHP variables to JavaScript for driver panel actions
        wp_localize_script( 'cachilupi-driver-panel', 'cachilupi_driver_vars', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            // Asegúrate de que este nonce coincide con el que se verifica en cachilupi_pet_handle_driver_action
            'driver_action_nonce' => wp_create_nonce( 'cachilupi_pet_driver_action' ),
            'update_location_nonce' => wp_create_nonce( 'cachilupi_pet_update_location_nonce' ),
            'check_new_requests_nonce' => wp_create_nonce('cachilupi_check_new_requests_nonce'),
        ) );
    }
}
// Enganchamos a 'wp' para asegurar que $post está disponible y has_shortcode funciona correctamente
add_action( 'wp', 'cachilupi_pet_enqueue_scripts' );

// Shortcode for displaying the map and location inputs (Client Form)
function cachilupi_pet_shortcode() {
    // Check if user is logged in and has the 'client', 'administrator', or 'driver' role
    // MODIFICADO: Se añadió 'driver' a la lista de roles permitidos
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator', 'driver' ), (array) $user->roles ) ) {
        return '<p>' . esc_html__( 'Debes iniciar sesión como cliente o conductor para solicitar un servicio.', 'cachilupi-pet' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="cachilupi-booking-container">
        <div class="cachilupi-booking-form">
            <h1><?php esc_html_e('Solicitar Servicio', 'cachilupi-pet'); ?></h1>

            <div class="form-group">
                <label for="pickup-location-input" class="required-field-label"><?php esc_html_e('Lugar de Recogida:', 'cachilupi-pet'); ?></label>
                <div id="pickup-geocoder-container" class="geocoder-container"></div>
            </div>

            <div class="form-group">
                <label for="dropoff-location-input" class="required-field-label"><?php esc_html_e('Lugar de Destino:', 'cachilupi-pet'); ?></label>
                <div id="dropoff-geocoder-container" class="geocoder-container"></div>
            </div>

            <div class="form-group">
                <label for="service-date" class="required-field-label"><?php esc_html_e('Fecha de Servicio:', 'cachilupi-pet'); ?></label>
                <input type="date" id="service-date" class="required-field form-control">
            </div>

            <div class="form-group">
                <label for="service-time" class="required-field-label"><?php esc_html_e('Hora de Servicio:', 'cachilupi-pet'); ?></label>
                <input type="time" id="service-time" class="required-field form-control">
            </div>

             <div id="cachilupi-pet-distance" class="distance-display"></div>

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
                <textarea id="cachilupi-pet-instructions" class="form-control"></textarea>
            </div>

            <div class="form-group">
                <label for="cachilupi-pet-notes"><?php esc_html_e('Notas Adicionales:', 'cachilupi-pet'); ?></label>
                <textarea id="cachilupi-pet-notes" class="form-control"></textarea>
            </div>

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
                 WHERE r.client_user_id = %d ORDER BY r.time DESC, r.created_at DESC", // Order by time primarily
                $client_id
            )
        );

        $active_client_requests = [];
        $historical_client_requests = [];
        $active_statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'picked_up'];
        $historical_statuses = ['completed', 'rejected'];

        if ($all_client_requests) {
            foreach ($all_client_requests as $request_item) {
                if (in_array(strtolower($request_item->status), $historical_statuses)) {
                    $historical_client_requests[] = $request_item;
                } else {
                    $active_client_requests[] = $request_item;
                }
            }
        }

        echo '<div class="cachilupi-client-requests-panel">';
        echo '<h2>' . esc_html__('Mis Solicitudes de Servicio', 'cachilupi-pet') . '</h2>';

        // Tab Navigation
        echo '<h2 class="nav-tab-wrapper">'; // WordPress uses h2 for nav-tab-wrapper
        echo '<a href="#client-active-requests" class="nav-tab nav-tab-active">' . esc_html__('Solicitudes Activas', 'cachilupi-pet') . '</a>';
        echo '<a href="#client-historical-requests" class="nav-tab">' . esc_html__('Historial de Solicitudes', 'cachilupi-pet') . '</a>';
        echo '</h2>';

        // Tab Content for Active Requests
        echo '<div id="client-active-requests" class="tab-content">';
        if ( !empty($active_client_requests) ) {
            echo '<table class="widefat fixed striped" cellspacing="0">'; // Added striped for consistency
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

            foreach ( $active_client_requests as $request_item ) { // Iterate over active requests
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
                // Logic for active requests' tracking column
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
                            echo esc_html__('Información de seguimiento no disponible en este momento.', 'cachilupi-pet');
                        }
                        break;
                    case 'arrived':
                        echo esc_html__('El conductor ha llegado al punto de recogida. Seguimiento no activo.', 'cachilupi-pet');
                        break;
                    case 'picked_up':
                        echo esc_html__('Mascota recogida. Viaje en progreso. Seguimiento en tiempo real si está activo.', 'cachilupi-pet');
                        // Optionally, re-add button if tracking is intended for 'picked_up' as well
                        break;
                    default: // Should not happen for active statuses based on filtering
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
        echo '</div>'; // End #client-active-requests

        // Tab Content for Historical Requests
        echo '<div id="client-historical-requests" class="tab-content" style="display:none;">';
        if ( !empty($historical_client_requests) ) {
            echo '<table class="widefat fixed striped" cellspacing="0">'; // Added striped for consistency
            echo '<thead><tr>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('ID', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Fecha Programada', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Origen', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Destino', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Mascota', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Estado Final', 'cachilupi-pet') . '</th>'; // Changed from 'Estado'
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Conductor', 'cachilupi-pet') . '</th>';
            echo '<th class="manage-column column-columnname" scope="col">' . esc_html__('Detalles del Viaje', 'cachilupi-pet') . '</th>'; // Changed from 'Seguimiento'
            echo '</tr></thead><tbody>';

            foreach ( $historical_client_requests as $request_item ) { // Iterate over historical requests
                echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
                echo '<td class="column-columnname" data-label="' . esc_attr__('ID:', 'cachilupi-pet') . '">' . esc_html( $request_item->id ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Fecha Programada:', 'cachilupi-pet') . '">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request_item->time ) ) ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Origen:', 'cachilupi-pet') . '">' . esc_html( $request_item->pickup_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Destino:', 'cachilupi-pet') . '">' . esc_html( $request_item->dropoff_address ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Mascota:', 'cachilupi-pet') . '">' . esc_html( $request_item->pet_type ) . '</td>';
                $status_slug_class = 'request-status-' . esc_attr( strtolower( $request_item->status ) );
                echo '<td class="column-columnname request-status ' . $status_slug_class . '" data-label="' . esc_attr__('Estado Final:', 'cachilupi-pet') . '"><span>' . esc_html( cachilupi_pet_translate_status( $request_item->status ) ) . '</span></td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Conductor:', 'cachilupi-pet') . '">' . esc_html( $request_item->driver_name ? $request_item->driver_name : __('No asignado', 'cachilupi-pet') ) . '</td>';
                echo '<td class="column-columnname" data-label="' . esc_attr__('Detalles del Viaje:', 'cachilupi-pet') . '">';
                // Logic for historical requests' tracking/details column
                switch ( strtolower($request_item->status) ) {
                    case 'completed':
                        echo esc_html__('Viaje finalizado con éxito.', 'cachilupi-pet');
                        break;
                    case 'rejected':
                        echo esc_html__('Solicitud rechazada por el conductor o sistema.', 'cachilupi-pet');
                        break;
                    default: // Should not happen for historical statuses based on filtering
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
        echo '</div>'; // End #client-historical-requests

        echo '</div>'; // End .cachilupi-client-requests-panel (this was the main wrapper)

        // Modal (remains unchanged, outside the tab content)
        echo '<div id="cachilupi-follow-modal" style="display:none; position:fixed; top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:10000;"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;max-width:700px;height:70%;background-color:white;padding:20px;border-radius:8px;"><h3 id="cachilupi-follow-modal-title">' . esc_html__('Siguiendo Viaje', 'cachilupi-pet') . '</h3><div id="cachilupi-client-follow-map" style="width:100%;height:80%;"></div><button id="cachilupi-close-follow-modal" style="margin-top:10px;">' . esc_html__('Cerrar', 'cachilupi-pet') . '</button></div></div>';
    }
    // --- FIN: Sección para Mostrar las Solicitudes del Cliente ---


    return ob_get_clean();
}
add_shortcode( 'cachilupi_maps', 'cachilupi_pet_shortcode' ); // Shortcode name remains the same for compatibility

// Handle AJAX request for submitting service requests
function cachilupi_pet_submit_service_request() {
    // Check if user is logged in and has the 'client' or 'administrator' role before processing
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator' ), (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => 'No tienes permisos para enviar solicitudes.'
        ));
        wp_die();
    }

    // Check AJAX nonce
    check_ajax_referer('cachilupi_pet_submit_request', 'security');

    // Retrieve and sanitize data from $_POST
    $pickup_address = isset($_POST['pickup_address']) ? sanitize_text_field($_POST['pickup_address']) : '';
    $dropoff_address = isset($_POST['dropoff_address']) ? sanitize_text_field($_POST['dropoff_address']) : '';
    $pet_type = isset($_POST['pet_type']) ? sanitize_text_field($_POST['pet_type']) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''; // Use sanitize_textarea_field for textareas
    $scheduled_date_time = isset($_POST['scheduled_date_time']) ? sanitize_text_field($_POST['scheduled_date_time']) : '';

    // Retrieve coordinates as strings for validation
    $pickup_lat_str = isset($_POST['pickup_lat']) ? $_POST['pickup_lat'] : null;
    $pickup_lon_str = isset($_POST['pickup_lon']) ? $_POST['pickup_lon'] : null;
    $dropoff_lat_str = isset($_POST['dropoff_lat']) ? $_POST['dropoff_lat'] : null;
    $dropoff_lon_str = isset($_POST['dropoff_lon']) ? $_POST['dropoff_lon'] : null;
    $pet_instructions = isset($_POST['pet_instructions']) ? sanitize_textarea_field($_POST['pet_instructions']) : '';

    // Server-side validation for presence of all required fields
    if ( empty($pickup_address) || is_null($pickup_lat_str) || is_null($pickup_lon_str) ||
         empty($dropoff_address) || is_null($dropoff_lat_str) || is_null($dropoff_lon_str) ||
         empty($pet_type) || empty($scheduled_date_time) ) {
         wp_send_json_error(array(
             'message' => 'Por favor, completa todos los campos requeridos.'
         ));
         wp_die();
    }

    // Validate numeric nature of coordinates
    if (!is_numeric($pickup_lat_str) || !is_numeric($pickup_lon_str) ||
        !is_numeric($dropoff_lat_str) || !is_numeric($dropoff_lon_str)) {
        wp_send_json_error(array('message' => 'Las coordenadas deben ser numéricas.'));
        wp_die();
    }

    // Convert to float after numeric validation
    $pickup_lat = floatval($pickup_lat_str);
    $pickup_lon = floatval($pickup_lon_str);
    $dropoff_lat = floatval($dropoff_lat_str);
    $dropoff_lon = floatval($dropoff_lon_str);

    // Validate coordinate ranges
    if ($pickup_lat < -90.0 || $pickup_lat > 90.0 || $pickup_lon < -180.0 || $pickup_lon > 180.0 ||
        $dropoff_lat < -90.0 || $dropoff_lat > 90.0 || $dropoff_lon < -180.0 || $dropoff_lon > 180.0) {
        wp_send_json_error(array('message' => 'Coordenadas geográficas fuera de rango.'));
        wp_die();
    }

    // Validate scheduled date and time format and future time
    $scheduledDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_date_time);
    if ($scheduledDateTime === false) {
        wp_send_json_error(array(
            'message' => 'Formato de fecha y hora incorrecto.'
        ));
        wp_die();
    }

    $now = new DateTime();
    $minScheduledTime = (clone $now)->modify('+90 minutes'); // Add 90 minutes to current time

    if ($scheduledDateTime < $minScheduledTime) {
        wp_send_json_error(array(
            'message' => 'El servicio debe ser agendado con al menos 90 minutos de anticipación.'
        ));
        wp_die();
    }

    // Validate service hours (8:00 to 21:59)
    $scheduledHour = (int) $scheduledDateTime->format('H');
    $scheduledMinute = (int) $scheduledDateTime->format('i');

    if ($scheduledHour < 8 || $scheduledHour > 21 || ($scheduledHour === 21 && $scheduledMinute > 59)) {
        wp_send_json_error(array('message' => 'El servicio debe ser agendado entre las 8:00 y las 22:00.')); // Message can still say 22:00 for clarity
        wp_die();
    }


    // Prepare the data for database insertion
    $data = array(
        'time' => $scheduled_date_time, // Use the validated datetime string directly
        'pickup_address' => $pickup_address,
        'pickup_lat' => $pickup_lat,
        'pickup_lon' => $pickup_lon,
        'dropoff_address' => $dropoff_address,
        'dropoff_lat' => $dropoff_lat,
        'dropoff_lon' => $dropoff_lon,
        'pet_type' => $pet_type,
        'notes' => $notes,
        'status' => 'pending', // Ensure status is set to pending on new request
        'created_at' => current_time('mysql'), // Use WordPress function for current time
        'client_user_id' => get_current_user_id(), // Guardar el ID del cliente que hace la solicitud
        'pet_instructions' => $pet_instructions,
    );

    // Specify data types for insertion for better security and correctness
    $format = array(
        '%s', // time (datetime)
        '%s', // pickup_address (text)
        '%f', // pickup_lat (decimal)
        '%f', // pickup_lon (decimal)
        '%s', // dropoff_address (text)
        '%f', // dropoff_lat (decimal)
        '%f', // dropoff_lon (decimal)
        '%s', // pet_type (varchar)
        '%s', // notes (text)
        '%s', // status (varchar)
        '%s', // created_at (datetime)
        '%d', // client_user_id (bigint)
        '%s'  // pet_instructions (text)
    );


    // Insert data into the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $result = $wpdb->insert($table_name, $data, $format); // Pass format array

    if ($result === false) {
        // Send error response if insertion fails without leaking DB info
        // error_log("Cachilupi Pet DB Error (submit_service_request): " . $wpdb->last_error);
        wp_send_json_error(array(
            'message' => 'Error al guardar la solicitud. Por favor, intenta de nuevo.'
        ));
    } else {
        // Send success response if insertion is successful
        wp_send_json_success(array(
            'message' => 'Solicitud guardada correctamente.',
            'request_id' => $wpdb->insert_id // Return the new request ID
        ));
    }
    wp_die(); // This is required to terminate immediately and return a proper response
}

add_action('wp_ajax_cachilupi_pet_submit_request', 'cachilupi_pet_submit_service_request');
// No necesitas wp_ajax_nopriv para esta acción, ya que solo los usuarios logueados con rol 'client' o 'administrator' deben poder enviar solicitudes.
// add_action('wp_ajax_nopriv_cachilupi_pet_submit_service_request', 'cachilupi_pet_submit_service_request');


// Settings Page
function cachilupi_pet_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('cachilupi_pet_options_group');
            do_settings_sections('cachilupi_pet_settings_page');
            submit_button( __('Guardar Ajustes', 'cachilupi-pet') );
            ?>
        </form>
    </div>
    <?php
}

function cachilupi_pet_register_settings() {
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_mapbox_token', 'sanitize_text_field');
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_client_redirect_slug', 'sanitize_text_field');
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_driver_redirect_slug', 'sanitize_text_field');

    add_settings_section(
        'cachilupi_pet_general_section',
        __('Ajustes Generales', 'cachilupi-pet'),
        null,
        'cachilupi_pet_settings_page'
    );

    add_settings_field(
        'cachilupi_pet_mapbox_token_field',
        __('Mapbox Access Token', 'cachilupi-pet'),
        'cachilupi_pet_mapbox_token_field_cb',
        'cachilupi_pet_settings_page',
        'cachilupi_pet_general_section'
    );
    add_settings_field(
        'cachilupi_pet_client_redirect_slug_field',
        __('Slug Página Cliente (Reserva)', 'cachilupi-pet'),
        'cachilupi_pet_client_redirect_slug_field_cb',
        'cachilupi_pet_settings_page',
        'cachilupi_pet_general_section'
    );
    add_settings_field(
        'cachilupi_pet_driver_redirect_slug_field',
        __('Slug Página Conductor (Panel)', 'cachilupi-pet'),
        'cachilupi_pet_driver_redirect_slug_field_cb',
        'cachilupi_pet_settings_page',
        'cachilupi_pet_general_section'
    );
}
add_action('admin_init', 'cachilupi_pet_register_settings');

function cachilupi_pet_mapbox_token_field_cb() {
    $option = get_option('cachilupi_pet_mapbox_token');
    echo '<input type="text" id="cachilupi_pet_mapbox_token" name="cachilupi_pet_mapbox_token" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Ingresa tu token de acceso de Mapbox.', 'cachilupi-pet') . '</p>';
}

function cachilupi_pet_client_redirect_slug_field_cb() {
    $option = get_option('cachilupi_pet_client_redirect_slug', 'reserva');
    echo '<input type="text" id="cachilupi_pet_client_redirect_slug" name="cachilupi_pet_client_redirect_slug" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">' . wp_kses_post( sprintf( __('Slug de la página donde los clientes realizan reservas (ej: %s para %s).', 'cachilupi-pet'), '<code>reserva</code>', '<code>' . home_url('/reserva/') . '</code>' ) ) . '</p>';
}

function cachilupi_pet_driver_redirect_slug_field_cb() {
    $option = get_option('cachilupi_pet_driver_redirect_slug', 'driver');
    echo '<input type="text" id="cachilupi_pet_driver_redirect_slug" name="cachilupi_pet_driver_redirect_slug" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">' . wp_kses_post( sprintf( __('Slug de la página del panel de conductores (ej: %s para %s).', 'cachilupi-pet'), '<code>driver</code>', '<code>' . home_url('/driver/') . '</code>' ) ) . '</p>';
}

function cachilupi_pet_add_settings_page() {
    add_options_page(
        __('Cachilupi Pet Ajustes', 'cachilupi-pet'),
        __('Cachilupi Pet', 'cachilupi-pet'),
        'manage_options',
        'cachilupi_pet_settings_page',
        'cachilupi_pet_settings_page_html'
    );
}
add_action('admin_menu', 'cachilupi_pet_add_settings_page');

/**
 * Carga el text domain del plugin para la traducción.
 */
function cachilupi_pet_load_textdomain() {
    load_plugin_textdomain( 'cachilupi-pet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'cachilupi_pet_load_textdomain' );

register_activation_hook( __FILE__, 'cachilupi_pet_activate' );

// AJAX Handler for checking new requests (for driver panel polling)
function cachilupi_pet_check_new_requests_ajax_handler() {
    // Check if user is logged in and has the 'driver' role
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! in_array( 'driver', (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => 'No tienes permisos para realizar esta acción.',
            'new_requests_count' => 0
        ));
        wp_die();
    }

    // Verify AJAX nonce
    check_ajax_referer('cachilupi_check_new_requests_nonce', 'security');

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $current_driver_id = get_current_user_id(); // Not strictly needed for this version of query, but good practice

    // Query for requests with status = 'pending' AND (driver_id IS NULL OR driver_id = 0)
    // These are requests that are not yet claimed by any driver.
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
    // Security & Permissions
    $user = wp_get_current_user();
    if ( ! is_user_logged_in() || ! array_intersect( array( 'client', 'administrator' ), (array) $user->roles ) ) {
        wp_send_json_error(array(
            'message' => __('Acceso no autorizado.', 'cachilupi-pet')
        ));
        wp_die();
    }

    // Verify AJAX nonce
    check_ajax_referer('cachilupi_pet_get_requests_status_nonce', 'security');

    // Fetch Data
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
        // error_log("Cachilupi Pet DB Error (get_client_requests_status): " . $wpdb->last_error);
        wp_send_json_error(array(
            'message' => __('Error al obtener el estado de las solicitudes.', 'cachilupi-pet')
        ));
        wp_die();
    }

    // Process Data
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

    // Send Response
    wp_send_json_success( $statuses_data );
    wp_die();
}
add_action('wp_ajax_cachilupi_get_client_requests_status', 'cachilupi_pet_get_client_requests_status_ajax_handler');
