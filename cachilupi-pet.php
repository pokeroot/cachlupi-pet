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
        $errors = '<p class="login-error-message"><strong>Error:</strong> Nombre de usuario o contraseña incorrectos.</p>';
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
        // Si no está logueado o no es conductor, puedes redirigir o mostrar un mensaje
        // Redirigir es a menudo una mejor UX para áreas restringidas
        // wp_redirect( home_url('/pagina-de-acceso-restringido/') ); exit;
        return '<p>Debes iniciar sesión como conductor para acceder a este panel.</p>';
    }

    ob_start(); // Start output buffering
    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';

    // Get pending and accepted requests assigned to this driver
    $current_driver_id = $user->ID; // Get the current driver's user ID

    $driver_requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, u.display_name as client_name FROM {$table_name} r LEFT JOIN {$wpdb->users} u ON r.client_user_id = u.ID WHERE r.status IN (%s, %s, %s, %s) AND (r.driver_id = %d OR r.driver_id IS NULL AND r.status = %s) ORDER BY r.created_at DESC",
            'pending', // Mostrar pendientes (para que el conductor los acepte si no están asignados)
            'accepted', // Mostrar los que el conductor ha aceptado
            'on_the_way', // Mostrar los que están en camino
            'arrived', // Mostrar los que el conductor ha marcado como llegados
            $current_driver_id, // Filtrar por el ID del conductor actual
            'pending' // Para la condición r.driver_id IS NULL AND r.status = %s
        )
    );

    ?>
    <div class="wrap">
        <h2>Panel del Conductor</h2>
        <div id="driver-panel-feedback" class="feedback-messages-container" style="margin-bottom: 15px;"></div>

        <?php if ( $driver_requests ) : ?>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th class="manage-column column-columnname" scope="col">ID</th>
                        <th class="manage-column column-columnname" scope="col">Fecha y Hora</th>
                        <th class="manage-column column-columnname" scope="col">Origen</th>
                        <th class="manage-column column-columnname" scope="col">Cliente</th>
                        <th class="manage-column column-columnname" scope="col">Destino</th>
                        <th class="manage-column column-columnname" scope="col">Estado</th>
                        <th class="manage-column column-columnname" scope="col">Mascota</th>
                        <th class="manage-column column-columnname" scope="col">Notas</th>
                        <th class="manage-column column-columnname" scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $driver_requests as $request ) : ?>
                        <tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
                            <td class="column-columnname" data-label="ID:"><?php echo esc_html( $request->id ); ?></td>
                            <td class="column-columnname" data-label="Fecha y Hora:"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $request->time ) ) ); ?></td>
                             <td class="column-columnname" data-label="Origen:">
                                <?php echo esc_html( $request->pickup_address ); ?>
                                <br>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->pickup_address); ?>" target="_blank" class="map-link">Ver en Google Maps</a>
                                <?php // Puedes añadir un enlace a Waze si lo prefieres ?>
                                <?php /* <a href="https://waze.com/ul?q=<?php echo urlencode($request->pickup_address); ?>&navigate=yes" target="_blank" class="map-link">Ver en Waze</a> */ ?>
                            </td>
                            <td class="column-columnname" data-label="Cliente:"><?php echo esc_html( $request->client_name ? $request->client_name : 'N/A' ); ?></td>
                             <td class="column-columnname" data-label="Destino:">
                                <?php echo esc_html( $request->dropoff_address ); ?>
                                <br>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($request->dropoff_address); ?>" target="_blank" class="map-link">Ver en Google Maps</a>
                                <?php // Puedes añadir un enlace a Waze si lo prefieres ?>
                                <?php /* <a href="https://waze.com/ul?q=<?php echo urlencode($request->dropoff_address); ?>&navigate=yes" target="_blank" class="map-link">Ver en Waze</a> */ ?>
                            </td>
                            <td class="column-columnname request-status" data-label="Estado:"><?php echo esc_html( cachilupi_pet_translate_status( $request->status ) ); ?></td>
                            <td class="column-columnname" data-label="Mascota:"><?php echo esc_html( $request->pet_type ); ?></td>
                            <td class="column-columnname" data-label="Notas:"><?php echo esc_html( $request->notes ); ?></td>
                            <td class="column-columnname" data-label="Acciones:">
                                <?php
                                // Usamos el slug original del estado para la lógica interna
                                $current_status_slug = strtolower( $request->status );

                                // --- IMPORTANTE: Configura estas clases CSS ---
                                // Asegúrate de que estos nombres de clase coincidan EXACTAMENTE
                                // con los que tienes definidos en tu archivo assets/css/driver-panel.css
                                // Si tradujiste '.accept-request' a '.aceptar-solicitud' en tu CSS,
                                // entonces cambia '$accept_class' a 'aceptar-solicitud'.
                                $accept_class   = 'accept-request';   // Ejemplo: 'accept-request' o 'aceptar-solicitud'
                                $reject_class   = 'reject-request';   // Ejemplo: 'reject-request' o 'rechazar-solicitud'
                                $arrive_class   = 'arrive-request';   // Ejemplo: 'arrive-request' o 'llegada-solicitud'
                                $on_the_way_class = 'on-the-way-request'; // Nueva clase
                                $complete_class = 'complete-request'; // Ejemplo: 'complete-request' o 'completar-solicitud'
                                
                                $action_button_shown = false;

                                // Botones para el estado 'pending'
                                if ( $current_status_slug === 'pending' && is_null($request->driver_id) ) : // Solo mostrar si no está asignado ?>
                                    <button class="button <?php echo esc_attr($accept_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="accept">Aceptar</button>
                                    <button class="button <?php echo esc_attr($reject_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="reject">Rechazar</button>
                                    <?php // Botones para acciones futuras, ocultos inicialmente ?>
                                    <button class="button <?php echo esc_attr($on_the_way_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="on_the_way" style="display:none;">Iniciar Viaje</button>
                                    <button class="button <?php echo esc_attr($arrive_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="arrive" style="display:none;">He Llegado al Origen</button>
                                    <button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete" style="display:none;">Completar Viaje</button>
                                    <?php $action_button_shown = true; ?>
                                <?php elseif ( $current_status_slug === 'accepted' ) : ?>
                                    <?php // Botón visible para 'accepted' ?>
                                    <button class="button <?php echo esc_attr($on_the_way_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="on_the_way">Iniciar Viaje</button>
                                    <?php // Botones para acciones futuras, ocultos inicialmente ?>
                                    <button class="button <?php echo esc_attr($arrive_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="arrive">He Llegado al Origen</button>
                                    <button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete" style="display:none;">Completar Viaje</button>
                                    <?php $action_button_shown = true; ?>
                                <?php elseif ( $current_status_slug === 'on_the_way' ) : ?>
                                    <?php // Botón visible para 'on_the_way' ?>
                                    <button class="button <?php echo esc_attr($arrive_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="arrive">He Llegado al Origen</button>
                                    <?php // Botón para acción futura, oculto inicialmente ?>
                                    <button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete" style="display:none;">Completar Viaje</button>
                                    <?php $action_button_shown = true; ?>
                                <?php elseif ( $current_status_slug === 'arrived' ) : ?>
                                    <?php // Botón visible para 'arrived' ?>
                                    <button class="button <?php echo esc_attr($complete_class); ?>" data-request-id="<?php echo esc_attr( $request->id ); ?>" data-action="complete">Completar Viaje</button>
                                    <?php $action_button_shown = true; ?>
                                <?php endif; ?>

                                <?php // Si no se mostró ningún botón de acción específico (ej. para 'rejected', 'completed') ?>
                                <?php if ( !$action_button_shown ) : ?>
                                    <span>--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No hay solicitudes pendientes en este momento.</p>
        <?php endif; ?>
    </div>
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
    if ($request_id <= 0 || !in_array($action, array('accept', 'reject', 'on_the_way', 'arrive', 'complete'))) {
        wp_send_json_error(array(
            'message' => 'Datos de solicitud inválidos o acción desconocida.'
        ));
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $new_status_slug = ''; // Slug for the new status
    $data_to_update = array();

    // Get the current driver's user ID
    $current_driver_id = get_current_user_id();

    // Determine the new status and data based on the action
    switch ($action) {
        case 'accept':
            $new_status_slug = 'accepted';
            $data_to_update['status'] = $new_status_slug;
            $data_to_update['driver_id'] = $current_driver_id; // Assign driver
            break;
        case 'reject':
            $new_status_slug = 'rejected';
            $data_to_update['status'] = $new_status_slug;
            // Consider if driver_id should be nulled if the request was previously assigned to this driver
            // For now, we only update status. If it was unassigned, it remains unassigned (driver_id NULL).
            // If it was assigned, it remains assigned but rejected. This might need refinement based on business logic.
            break;
        case 'on_the_way':
            $new_status_slug = 'on_the_way';
            $data_to_update['status'] = $new_status_slug;
            break;
        case 'arrive':
            $new_status_slug = 'arrived';
            $data_to_update['status'] = $new_status_slug;
            // Action 'arrive' should only be possible if the request is already assigned to this driver.
            // The $wpdb->update below will target by 'id' only. Add 'driver_id' to WHERE if strictness is needed.
            break;
        case 'complete':
            $new_status_slug = 'completed';
            $data_to_update['status'] = $new_status_slug;
            break;
        default:
            // This case should not be reached due to the in_array check above
            wp_send_json_error(array('message' => 'Acción no válida.'));
            wp_die();
    }

    $where_conditions = array('id' => $request_id);

    // For actions that imply the request is already assigned to the current driver
    if (in_array($action, array('on_the_way', 'arrive', 'complete'))) {
        $where_conditions['driver_id'] = $current_driver_id;
    }

    // Update the database
    $result = $wpdb->update(
        $table_name,
        $data_to_update,
        $where_conditions
    );


    if ($result !== false) {
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
                case 'complete':
                    $notification_subject = sprintf(__('¡Tu viaje #%d ha sido completado!', 'cachilupi-pet'), $request_id);
                    $notification_message_to_client = sprintf(
                        __("Hola %s,\n\nEl servicio de transporte #%d para tu mascota ha sido completado por el conductor %s.\n\n¡Gracias por usar Cachilupi Pet!", 'cachilupi-pet'),
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
        // Send error response
        wp_send_json_error(array('message' => 'Error al actualizar la solicitud.', 'error' => $wpdb->last_error));
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
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;

    if ($request_id <= 0 || $latitude == 0.0 || $longitude == 0.0) {
        wp_send_json_error(array('message' => 'Datos de ubicación inválidos.'));
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $current_driver_id = get_current_user_id();

    $result = $wpdb->update(
        $table_name,
        array('driver_current_lat' => $latitude, 'driver_current_lon' => $longitude, 'driver_location_updated_at' => current_time('mysql')),
        array('id' => $request_id, 'driver_id' => $current_driver_id, 'status' => 'on_the_way'), // Only update if status is 'on_the_way'
        array('%f', '%f', '%s'),
        array('%d', '%d', '%s')
    );

    if ($result !== false) {
        wp_send_json_success(array('message' => 'Ubicación actualizada.'));
    } else {
        wp_send_json_error(array('message' => 'Error al actualizar ubicación.', 'db_error' => $wpdb->last_error));
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

    if ($location_data && $location_data->driver_current_lat && $location_data->driver_current_lon) {
        wp_send_json_success(array(
            'latitude' => $location_data->driver_current_lat,
            'longitude' => $location_data->driver_current_lon,
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
        // Si no está logueado o no tiene el rol correcto, puedes redirigir o mostrar un mensaje.
        // Redirigir a la página de login personalizada o mostrar un mensaje.
        // wp_redirect( home_url('/log-in/') ); exit; // Ejemplo de redirección
        return '<p>Debes iniciar sesión como cliente o conductor para solicitar un servicio.</p>';
    }

    ob_start(); // Start output buffering
    // --- INICIO: Formulario de Reserva (tu código existente) ---
    ?>
    <div class="cachilupi-booking-container">
        <div class="cachilupi-booking-form">
            <h1>Solicitar Servicio</h1>

            <div class="form-group">
                <?php
                // MODIFICADO: El atributo 'for' ahora apunta al ID del input del geocodificador
                // definido en maps.js ('pickup-location-input').
                ?>
                <label for="pickup-location-input" class="required-field-label">Lugar de Recogida:</label>
                <div id="pickup-geocoder-container" class="geocoder-container"></div>
                <?php
                // ELIMINADO: Botón "Usar mi ubicación actual"
                // <button id="use-current-location" type="button" class="button-secondary">Usar mi ubicación actual</button>
                ?>
            </div>

            <div class="form-group">
                 <?php
                // MODIFICADO: El atributo 'for' ahora apunta al ID del input del geocodificador
                // definido en maps.js ('dropoff-location-input').
                ?>
                <label for="dropoff-location-input" class="required-field-label">Lugar de Destino:</label>
                <div id="dropoff-geocoder-container" class="geocoder-container"></div>
            </div>

            <div class="form-group">
                <label for="service-date" class="required-field-label">Fecha de Servicio:</label>
                <input type="date" id="service-date" class="required-field form-control">
            </div>

            <div class="form-group">
                <label for="service-time" class="required-field-label">Hora de Servicio:</label>
                <input type="time" id="service-time" class="required-field form-control">
            </div>

             <div id="cachilupi-pet-distance" class="distance-display"></div>

            <div class="form-group">
                <label for="cachilupi-pet-pet-type" class="required-field-label">Tipo de Mascota:</label>
                <select id="cachilupi-pet-pet-type" class="required-field form-control">
                    <option value="">-- Selecciona una opción --</option>
                    <option value="perro">Perro</option>
                    <option value="gato">Gato</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label for="cachilupi-pet-notes">Notas Adicionales:</label>
                <textarea id="cachilupi-pet-notes" class="form-control"></textarea>
            </div>

            <button id="submit-service-request" type="button" class="button-primary">Solicitar Servicio</button>

        </div>
        <div id="cachilupi-pet-map" class="booking-map"></div>
    </div>
    <?php // --- FIN: Formulario de Reserva ---

    // --- INICIO: Sección para Mostrar las Solicitudes del Cliente ---
    // (Asegúrate de que $user ya está definido arriba en esta función)
    if ( is_user_logged_in() && ( in_array( 'client', (array) $user->roles ) || current_user_can( 'manage_options' ) ) ) {
        global $wpdb;
        $requests_table_name = $wpdb->prefix . 'cachilupi_requests';
        $client_id = $user->ID;

        $client_requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.display_name as driver_name 
                 FROM {$requests_table_name} r 
                 LEFT JOIN {$wpdb->users} u ON r.driver_id = u.ID 
                 WHERE r.client_user_id = %d ORDER BY r.created_at DESC",
                $client_id
            )
        );

        echo '<div class="cachilupi-client-requests-panel">'; // Styles moved to maps.css
        echo '<h2>Mis Solicitudes de Servicio</h2>'; // Styles moved to maps.css

        if ( $client_requests ) {
            // Se utilizan las clases 'widefat fixed' para que los estilos responsivos de driver-panel.css se apliquen.
            echo '<table class="widefat fixed" cellspacing="0">'; // Styles moved to maps.css or driver-panel.css
            echo '<thead><tr>';
            echo '<th class="manage-column column-columnname" scope="col">ID</th>';
            echo '<th class="manage-column column-columnname" scope="col">Fecha Programada</th>';
            echo '<th class="manage-column column-columnname" scope="col">Origen</th>';
            echo '<th class="manage-column column-columnname" scope="col">Destino</th>';
            echo '<th class="manage-column column-columnname" scope="col">Mascota</th>';
            echo '<th class="manage-column column-columnname" scope="col">Estado</th>';
            echo '<th class="manage-column column-columnname" scope="col">Conductor</th>';
            echo '<th class="manage-column column-columnname" scope="col">Seguimiento</th>';
            echo '</tr></thead><tbody>';

            foreach ( $client_requests as $request_idx => $request_item ) { // Renombrada la variable del bucle para evitar conflicto con $request
                echo '<tr data-request-id="' . esc_attr( $request_item->id ) . '">';
                echo '<td class="column-columnname" data-label="ID:">' . esc_html( $request_item->id ) . '</td>';
                echo '<td class="column-columnname" data-label="Fecha Programada:">' . esc_html( date( 'd/m/Y H:i', strtotime( $request_item->time ) ) ) . '</td>';
                echo '<td class="column-columnname" data-label="Origen:">' . esc_html( $request_item->pickup_address ) . '</td>';
                echo '<td class="column-columnname" data-label="Destino:">' . esc_html( $request_item->dropoff_address ) . '</td>';
                echo '<td class="column-columnname" data-label="Mascota:">' . esc_html( $request_item->pet_type ) . '</td>';
                echo '<td class="column-columnname request-status" data-label="Estado:">' . esc_html( cachilupi_pet_translate_status( $request_item->status ) ) . '</td>';
                echo '<td class="column-columnname" data-label="Conductor:">' . esc_html( $request_item->driver_name ? $request_item->driver_name : 'No asignado' ) . '</td>';
                echo '<td class="column-columnname" data-label="Seguimiento:">';
                if ($request_item->status === 'on_the_way' && $request_item->driver_id) {
                    echo '<button class="button cachilupi-follow-driver-btn" data-request-id="' . esc_attr( $request_item->id ) . '">' . __('Seguir Viaje', 'cachilupi-pet') . '</button>';
                } else { echo '--'; }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No has realizado ninguna solicitud de servicio todavía.</p>';
        }
        echo '</div>'; // Fin de cachilupi-client-requests-panel
        // Modal para el mapa de seguimiento del cliente
        echo '<div id="cachilupi-follow-modal" style="display:none; position:fixed; top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:10000;"><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80%;max-width:700px;height:70%;background-color:white;padding:20px;border-radius:8px;"><h3 id="cachilupi-follow-modal-title">Siguiendo Viaje</h3><div id="cachilupi-client-follow-map" style="width:100%;height:80%;"></div><button id="cachilupi-close-follow-modal" style="margin-top:10px;">Cerrar</button></div></div>';
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
    $pickup_lat = isset($_POST['pickup_lat']) ? floatval($_POST['pickup_lat']) : 0.0; // Use floatval for coordinates
    $pickup_lon = isset($_POST['pickup_lon']) ? floatval($_POST['pickup_lon']) : 0.0; // Use floatval for coordinates
    $dropoff_address = isset($_POST['dropoff_address']) ? sanitize_text_field($_POST['dropoff_address']) : '';
    $dropoff_lat = isset($_POST['dropoff_lat']) ? floatval($_POST['dropoff_lat']) : 0.0; // Use floatval for coordinates
    $dropoff_lon = isset($_POST['dropoff_lon']) ? floatval($_POST['dropoff_lon']) : 0.0; // Use floatval for coordinates
    $pet_type = isset($_POST['pet_type']) ? sanitize_text_field($_POST['pet_type']) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''; // Use sanitize_textarea_field for textareas
    $scheduled_date_time = isset($_POST['scheduled_date_time']) ? sanitize_text_field($_POST['scheduled_date_time']) : '';

    // Server-side validation
    if ( empty($pickup_address) || $pickup_lat == 0.0 || $pickup_lon == 0.0 ||
         empty($dropoff_address) || $dropoff_lat == 0.0 || $dropoff_lon == 0.0 ||
         empty($pet_type) || empty($scheduled_date_time) ) {
         wp_send_json_error(array(
             'message' => 'Por favor, completa todos los campos requeridos.'
         ));
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
    );


    // Insert data into the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'cachilupi_requests';
    $result = $wpdb->insert($table_name, $data, $format); // Pass format array

    if ($result === false) {
        // Send error response if insertion fails
        wp_send_json_error(array(
            'message' => 'Error al guardar la solicitud.',
            'error' => $wpdb->last_error
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
            submit_button('Guardar Ajustes');
            ?>
        </form>
    </div>
    <?php
}

function cachilupi_pet_register_settings() {
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_mapbox_token', 'sanitize_text_field');
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_client_redirect_slug', 'sanitize_text_field');
    register_setting('cachilupi_pet_options_group', 'cachilupi_pet_driver_redirect_slug', 'sanitize_text_field');

    add_settings_section('cachilupi_pet_general_section', 'Ajustes Generales', null, 'cachilupi_pet_settings_page');

    add_settings_field('cachilupi_pet_mapbox_token_field', 'Mapbox Access Token', 'cachilupi_pet_mapbox_token_field_cb', 'cachilupi_pet_settings_page', 'cachilupi_pet_general_section');
    add_settings_field('cachilupi_pet_client_redirect_slug_field', 'Slug Página Cliente (Reserva)', 'cachilupi_pet_client_redirect_slug_field_cb', 'cachilupi_pet_settings_page', 'cachilupi_pet_general_section');
    add_settings_field('cachilupi_pet_driver_redirect_slug_field', 'Slug Página Conductor (Panel)', 'cachilupi_pet_driver_redirect_slug_field_cb', 'cachilupi_pet_settings_page', 'cachilupi_pet_general_section');
}
add_action('admin_init', 'cachilupi_pet_register_settings');

function cachilupi_pet_mapbox_token_field_cb() {
    $option = get_option('cachilupi_pet_mapbox_token');
    echo '<input type="text" id="cachilupi_pet_mapbox_token" name="cachilupi_pet_mapbox_token" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">Ingresa tu token de acceso de Mapbox.</p>';
}

function cachilupi_pet_client_redirect_slug_field_cb() {
    $option = get_option('cachilupi_pet_client_redirect_slug', 'reserva');
    echo '<input type="text" id="cachilupi_pet_client_redirect_slug" name="cachilupi_pet_client_redirect_slug" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">Slug de la página donde los clientes realizan reservas (ej: <code>reserva</code> para <code>'.home_url('/reserva/').'</code>).</p>';
}

function cachilupi_pet_driver_redirect_slug_field_cb() {
    $option = get_option('cachilupi_pet_driver_redirect_slug', 'driver');
    echo '<input type="text" id="cachilupi_pet_driver_redirect_slug" name="cachilupi_pet_driver_redirect_slug" value="' . esc_attr($option) . '" class="regular-text" />';
    echo '<p class="description">Slug de la página del panel de conductores (ej: <code>driver</code> para <code>'.home_url('/driver/').'</code>).</p>';
}

function cachilupi_pet_add_settings_page() {
    add_options_page(
        'Cachilupi Pet Ajustes',
        'Cachilupi Pet',
        'manage_options',
        'cachilupi_pet_settings_page',
        'cachilupi_pet_settings_page_html'
    );
}
add_action('admin_menu', 'cachilupi_pet_add_settings_page');


register_activation_hook( __FILE__, 'cachilupi_pet_activate' );
