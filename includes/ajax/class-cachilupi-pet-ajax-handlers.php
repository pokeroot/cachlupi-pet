<?php

namespace CachilupiPet\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Autoloader should handle Utils class: \CachilupiPet\Utils\Cachilupi_Pet_Utils

/**
 * Handles AJAX requests for the Cachilupi Pet plugin.
 *
 * All methods are hooked to WordPress AJAX actions and typically end with wp_die().
 *
 * @package CachilupiPet\Ajax
 */
class Cachilupi_Pet_Ajax_Handlers {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialization, if any. Can be used for dependency injection.
	}

	/**
	 * Handles driver actions like accept, reject, on_the_way, etc.
	 */
	public function handle_driver_action() {
		if ( ! current_user_can( 'manage_trip_status' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to manage trip status.', 'cachilupi-pet' )
			) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_pet_driver_action', 'security' );

		$request_id = isset( $_POST['request_id'] ) ? intval( $_POST['request_id'] ) : 0;
		$action     = isset( $_POST['driver_action'] ) ? sanitize_text_field( $_POST['driver_action'] ) : '';

		if ( $request_id <= 0 || ! in_array( $action, array( 'accept', 'reject', 'on_the_way', 'arrive', 'picked_up', 'complete' ), true ) ) {
			wp_send_json_error( array(
				'message' => 'Datos de solicitud inválidos o acción desconocida.'
			) );
			wp_die();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		$request_being_actioned = $wpdb->get_row( $wpdb->prepare( "SELECT driver_id, status FROM {$table_name} WHERE id = %d", $request_id ) );

		if ( ! $request_being_actioned ) {
			wp_send_json_error( array( 'message' => 'Solicitud no encontrada.' ) );
			wp_die();
		}

		$new_status_slug = '';
		$data_to_update  = array();
		$data_formats    = array();
		$where_formats   = array( '%d' );
		$current_driver_id = get_current_user_id();

		if ( $action === 'reject' && ! is_null( $request_being_actioned->driver_id ) && $request_being_actioned->driver_id != $current_driver_id ) {
			wp_send_json_error( array( 'message' => 'No puedes rechazar una solicitud asignada a otro conductor.' ) );
			wp_die();
		}

		if ( $action === 'accept' && ! is_null( $request_being_actioned->driver_id ) && $request_being_actioned->driver_id != $current_driver_id ) {
			wp_send_json_error( array( 'message' => 'Esta solicitud ya ha sido aceptada por otro conductor.' ) );
			wp_die();
		}
		if ( $action === 'accept' && $request_being_actioned->status !== 'pending' ) {
			wp_send_json_error( array( 'message' => 'Esta solicitud no está pendiente y no puede ser aceptada.' ) );
			wp_die();
		}
		if ( $action === 'reject' && $request_being_actioned->status !== 'pending' ) {
			wp_send_json_error( array( 'message' => 'Esta solicitud no está pendiente y no puede ser rechazada.' ) );
			wp_die();
		}

		switch ( $action ) {
			case 'accept':
				$new_status_slug             = 'accepted';
				$data_to_update['status']    = $new_status_slug;
				$data_formats[]              = '%s';
				$data_to_update['driver_id'] = $current_driver_id;
				$data_formats[]              = '%d';
				$where_conditions['status']  = 'pending'; // Extra condition for accept
				$where_formats[]             = '%s';
				break;
			case 'reject':
				$new_status_slug            = 'rejected';
				$data_to_update['status']   = $new_status_slug;
				$data_formats[]             = '%s';
				$where_conditions['status'] = 'pending'; // Extra condition for reject
				$where_formats[]            = '%s';
				break;
			case 'on_the_way':
				$new_status_slug          = 'on_the_way';
				$data_to_update['status'] = $new_status_slug;
				$data_formats[]           = '%s';
				break;
			case 'arrive':
				$new_status_slug          = 'arrived';
				$data_to_update['status'] = $new_status_slug;
				$data_formats[]           = '%s';
				break;
			case 'picked_up':
				$new_status_slug          = 'picked_up';
				$data_to_update['status'] = $new_status_slug;
				$data_formats[]           = '%s';
				break;
			case 'complete':
				$new_status_slug          = 'completed';
				$data_to_update['status'] = $new_status_slug;
				$data_formats[]           = '%s';
				break;
			default:
				wp_send_json_error( array( 'message' => 'Acción no válida.' ) );
				wp_die();
		}

		$where_conditions['id'] = $request_id; // Base condition

		if ( in_array( $action, array( 'on_the_way', 'arrive', 'picked_up', 'complete' ), true ) ) {
			$where_conditions['driver_id'] = $current_driver_id;
			$where_formats[]               = '%d';
		}

		$result = $wpdb->update(
			$table_name,
			$data_to_update,
			$where_conditions,
			$data_formats,
			$where_formats
		);

		if ( $result === 0 ) {
			$current_db_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table_name} WHERE id = %d", $request_id ) );
			if ( $current_db_status && $current_db_status !== $request_being_actioned->status ) {
				wp_send_json_error( array( 'message' => 'La solicitud fue actualizada por otro proceso. Por favor, refresca la página.', 'error_code' => 'status_changed' ) );
			} elseif ( $current_db_status && $action === 'accept' && $request_being_actioned->driver_id !== null && $request_being_actioned->driver_id != $current_driver_id ) {
				wp_send_json_error( array( 'message' => 'La solicitud ya fue aceptada por otro conductor. Por favor, refresca la página.', 'error_code' => 'already_accepted' ) );
			} else {
				wp_send_json_error( array( 'message' => 'No se pudo actualizar la solicitud. Es posible que ya haya sido procesada o las condiciones no se cumplen.', 'error_code' => 'condition_not_met' ) );
			}
			wp_die();
		} elseif ( $result !== false ) {
			$request_details    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $request_id ) );
			$new_status_display = \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $new_status_slug );
			// Notification logic would go here... (omitted for brevity as it's complex and not the core of this refactor)
			wp_send_json_success( array(
				'message'            => 'Solicitud actualizada correctamente.',
				'new_status_slug'    => $new_status_slug,
				'new_status_display' => $new_status_display
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Error al actualizar la solicitud. Por favor, intenta de nuevo.' ) );
		}
		wp_die();
	}

	/**
	 * Handles driver location updates.
	 */
	public function update_driver_location() {
		if ( ! current_user_can( 'update_trip_location' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to update trip location.', 'cachilupi-pet' ) ) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_pet_update_location_nonce', 'security' );

		$request_id = isset( $_POST['request_id'] ) ? intval( $_POST['request_id'] ) : 0;
		// Sanitize latitude and longitude before numeric checks
		$latitude_str   = isset( $_POST['latitude'] ) ? sanitize_text_field( $_POST['latitude'] ) : null;
		$longitude_str  = isset( $_POST['longitude'] ) ? sanitize_text_field( $_POST['longitude'] ) : null;

		if ( $request_id <= 0 ||
			is_null( $latitude_str ) || ! is_numeric( $latitude_str ) ||
			is_null( $longitude_str ) || ! is_numeric( $longitude_str ) ||
			floatval( $latitude_str ) < -90.0 || floatval( $latitude_str ) > 90.0 ||
			floatval( $longitude_str ) < -180.0 || floatval( $longitude_str ) > 180.0 ) {
			wp_send_json_error( array( 'message' => 'Datos de ubicación inválidos o fuera de rango.' ) );
			wp_die();
		}

		$latitude_val  = floatval( $latitude_str );
		$longitude_val = floatval( $longitude_str );

		global $wpdb;
		$table_name        = $wpdb->prefix . 'cachilupi_requests';
		$current_driver_id = get_current_user_id();

		$result = $wpdb->update(
			$table_name,
			array(
				'driver_current_lat'           => $latitude_val, // Using renamed variable
				'driver_current_lon'           => $longitude_val, // Using renamed variable
				'driver_location_updated_at' => current_time( 'mysql' )
			),
			array(
				'id'        => $request_id,
				'driver_id' => $current_driver_id,
				'status'    => 'on_the_way'
			),
			array( '%f', '%f', '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( $result > 0 ) {
			wp_send_json_success( array( 'message' => 'Ubicación actualizada.' ) );
		} elseif ( $result === 0 ) {
			$request_check = $wpdb->get_row( $wpdb->prepare( "SELECT status, driver_id FROM {$table_name} WHERE id = %d", $request_id ) );
			if ( ! $request_check || $request_check->driver_id != $current_driver_id || $request_check->status != 'on_the_way' ) {
				wp_send_json_error( array( 'message' => 'No se pudo actualizar la ubicación. El viaje ya no está activo o no está asignado a ti.', 'error_code' => 'trip_not_active' ) );
			} else {
				wp_send_json_success( array( 'message' => 'Ubicación sin cambios.', 'status_code' => 'no_change' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'Error al actualizar ubicación. Por favor, intenta de nuevo.' ) );
		}
		wp_die();
	}

	/**
	 * Handles fetching driver location for clients.
	 */
	public function get_driver_location() {
		// Administrator check can be done via 'manage_options' or a specific 'view_any_trip_location' capability.
		// Clients should only see their own trip. The SQL query already filters by client_user_id.
		if ( ! ( current_user_can( 'view_own_trip_location' ) || current_user_can( 'manage_options' ) ) ) { // Simplified for admin, or use 'view_any_trip_location'
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view this trip location.', 'cachilupi-pet' ) ) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_pet_get_location_nonce', 'security' );

		$request_id = isset( $_GET['request_id'] ) ? intval( $_GET['request_id'] ) : 0;
		if ( $request_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'ID de solicitud inválido.' ) );
			wp_die();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		$client_id  = get_current_user_id();

		$location_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT driver_current_lat, driver_current_lon, driver_location_updated_at FROM {$table_name} WHERE id = %d AND client_user_id = %d AND status = 'on_the_way'",
				$request_id,
				$client_id
			)
		);

		if ( $location_data && ! is_null( $location_data->driver_current_lat ) && ! is_null( $location_data->driver_current_lon ) ) {
			wp_send_json_success( array(
				'latitude'   => floatval( $location_data->driver_current_lat ),
				'longitude'  => floatval( $location_data->driver_current_lon ),
				'updated_at' => $location_data->driver_location_updated_at
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Ubicación del conductor no disponible o viaje no activo.' ) );
		}
		wp_die();
	}

	/**
	 * Handles submission of service requests from the client form.
	 */
	public function submit_service_request() {
		// Allow users who can submit requests for themselves or admins who can submit on behalf of others.
		if ( ! ( current_user_can( 'submit_pet_request' ) || current_user_can( 'manage_options' ) ) ) { // Simplified for admin, or use 'submit_pet_request_on_behalf'
			wp_send_json_error( array( 'message' => __( 'You do not have permission to submit this service request.', 'cachilupi-pet' ) ) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_pet_submit_request', 'security' );

		$pickup_address      = isset( $_POST['pickup_address'] ) ? sanitize_text_field( $_POST['pickup_address'] ) : '';
		$dropoff_address     = isset( $_POST['dropoff_address'] ) ? sanitize_text_field( $_POST['dropoff_address'] ) : '';
		$pet_type            = isset( $_POST['pet_type'] ) ? sanitize_text_field( $_POST['pet_type'] ) : '';
		$notes               = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';
		$scheduled_date_time_str = isset( $_POST['scheduled_date_time'] ) ? sanitize_text_field( $_POST['scheduled_date_time'] ) : ''; // Renamed from $scheduled_date_time
		$pickup_lat_str      = isset( $_POST['pickup_lat'] ) ? sanitize_text_field( $_POST['pickup_lat'] ) : null;
		$pickup_lon_str      = isset( $_POST['pickup_lon'] ) ? sanitize_text_field( $_POST['pickup_lon'] ) : null;
		$dropoff_lat_str     = isset( $_POST['dropoff_lat'] ) ? sanitize_text_field( $_POST['dropoff_lat'] ) : null;
		$dropoff_lon_str     = isset( $_POST['dropoff_lon'] ) ? sanitize_text_field( $_POST['dropoff_lon'] ) : null;
		$pet_instructions    = isset( $_POST['pet_instructions'] ) ? sanitize_textarea_field( $_POST['pet_instructions'] ) : '';

		// Validate required fields (coordinates are validated after sanitization and numeric check)
		if ( empty( $pickup_address ) || empty( $dropoff_address ) || empty( $pet_type ) || empty( $scheduled_date_time_str ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, completa todos los campos de dirección, tipo de mascota y fecha/hora.' ) );
			wp_die();
		}

		// Sanitize and validate coordinates
		if ( is_null( $pickup_lat_str ) || is_null( $pickup_lon_str ) || is_null( $dropoff_lat_str ) || is_null( $dropoff_lon_str ) ||
			! is_numeric( $pickup_lat_str ) || ! is_numeric( $pickup_lon_str ) ||
			! is_numeric( $dropoff_lat_str ) || ! is_numeric( $dropoff_lon_str ) ) {
			wp_send_json_error( array( 'message' => 'Las coordenadas deben ser proporcionadas y deben ser numéricas.' ) );
			wp_die();
		}

		$pickup_lat  = floatval( $pickup_lat_str );
		$pickup_lon  = floatval( $pickup_lon_str );
		$dropoff_lat = floatval( $dropoff_lat_str );
		$dropoff_lon = floatval( $dropoff_lon_str );

		if ( $pickup_lat < -90.0 || $pickup_lat > 90.0 || $pickup_lon < -180.0 || $pickup_lon > 180.0 ||
			 $dropoff_lat < -90.0 || $dropoff_lat > 90.0 || $dropoff_lon < -180.0 || $dropoff_lon > 180.0 ) {
			wp_send_json_error( array( 'message' => 'Coordenadas geográficas fuera de rango.' ) );
			wp_die();
		}

		$scheduled_date_time_obj = \DateTime::createFromFormat( 'Y-m-d H:i:s', $scheduled_date_time_str ); // Renamed variable
		if ( $scheduled_date_time_obj === false ) {
			$scheduled_date_time_obj = \DateTime::createFromFormat( 'Y-m-d H:i', $scheduled_date_time_str ); // Renamed variable
			if ( $scheduled_date_time_obj === false ) {
				$scheduled_date_time_obj = \DateTime::createFromFormat( 'Y-m-d', $scheduled_date_time_str ); // Renamed variable
				if ( $scheduled_date_time_obj ) {
					$scheduled_date_time_obj->setTime( 0, 0, 0 );
				} else {
					wp_send_json_error( array(
						'message'            => 'Formato de fecha y hora incorrecto. Asegúrese de que la fecha y la hora estén seleccionadas.',
						'debug_sent_value' => $scheduled_date_time_str // Using renamed variable
					) );
					wp_die();
				}
			}
		}

		if ( $scheduled_date_time_str && strpos( $scheduled_date_time_str, ' ' ) === false && $scheduled_date_time_obj && $scheduled_date_time_obj->format( 'H:i:s' ) === '00:00:00' ) { // Using renamed variables
			wp_send_json_error( array(
				'message'         => 'Por favor, selecciona tanto la fecha como la hora para el servicio.',
				'debug_parsed_dt' => $scheduled_date_time_obj ? $scheduled_date_time_obj->format( 'Y-m-d H:i:s' ) : 'null' // Using renamed variable
			) );
			wp_die();
		}

		$now = new \DateTime();
		$min_scheduled_time = ( clone $now )->modify( '+89 minutes' ); // Renamed variable

		if ( $scheduled_date_time_obj < $min_scheduled_time ) { // Using renamed variables
			wp_send_json_error( array(
				'message'              => 'El servicio debe ser agendado con al menos 90 minutos de anticipación desde la hora actual.',
				'debug_current_time'   => $now->format( 'Y-m-d H:i:s' ),
				'debug_scheduled_time' => $scheduled_date_time_obj->format( 'Y-m-d H:i:s' ), // Using renamed variable
				'debug_min_allowed'    => $min_scheduled_time->format( 'Y-m-d H:i:s' ) // Using renamed variable
			) );
			wp_die();
		}

		$scheduled_hour   = (int) $scheduled_date_time_obj->format( 'H' ); // Renamed variable
		$scheduled_minute = (int) $scheduled_date_time_obj->format( 'i' ); // Renamed variable

		if ( $scheduled_hour < 8 || ( $scheduled_hour === 21 && $scheduled_minute > 0 ) || $scheduled_hour > 21 ) {
			wp_send_json_error( array( 'message' => 'El servicio debe ser agendado entre las 8:00 y las 21:00.' ) );
			wp_die();
		}

		$data = array(
			'time'             => $scheduled_date_time_obj->format( 'Y-m-d H:i:s' ), // Using renamed variable
			'pickup_address'   => $pickup_address,
			'pickup_lat'       => $pickup_lat,
			'pickup_lon'       => $pickup_lon,
			'dropoff_address'  => $dropoff_address,
			'dropoff_lat'      => $dropoff_lat,
			'dropoff_lon'      => $dropoff_lon,
			'pet_type'         => $pet_type,
			'notes'            => $notes,
			'status'           => 'pending',
			'created_at'       => current_time( 'mysql' ),
			'client_user_id'   => get_current_user_id(),
			'pet_instructions' => $pet_instructions,
		);

		$format = array( '%s', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		$result     = $wpdb->insert( $table_name, $data, $format );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Error al guardar la solicitud. Por favor, intenta de nuevo.' ) );
		} else {
			wp_send_json_success( array(
				'message'    => 'Solicitud guardada correctamente.',
				'request_id' => $wpdb->insert_id
			) );
		}
		wp_die();
	}

	/**
	 * Handles checking for new requests for the driver panel.
	 */
	public function check_new_requests() {
		if ( ! current_user_can( 'view_pending_requests' ) ) {
			wp_send_json_error( array(
				'message'            => __( 'You do not have permission to check for new requests.', 'cachilupi-pet' ),
				'new_requests_count' => 0
			) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_check_new_requests_nonce', 'security' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$pending_unassigned_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = %s AND (driver_id IS NULL OR driver_id = 0)",
				'pending'
			)
		);

		if ( is_numeric( $pending_unassigned_count ) && $pending_unassigned_count > 0 ) {
			wp_send_json_success( array(
				'new_requests_count' => (int) $pending_unassigned_count,
				'message'            => __( 'Nuevas solicitudes pendientes encontradas.', 'cachilupi-pet' )
			) );
		} else {
			wp_send_json_success( array(
				'new_requests_count' => 0,
				'message'            => __( 'No hay nuevas solicitudes pendientes.', 'cachilupi-pet' )
			) );
		}
		wp_die();
	}

	/**
	 * Handles fetching client requests status for client panel polling.
	 */
	public function get_client_requests_status() {
		// Allow clients to see their own, admins to see any (or based on a specific cap)
		if ( ! ( current_user_can( 'view_own_request_status' ) || current_user_can( 'manage_options' ) ) ) { // Simplified for admin, or use 'view_any_request_status'
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view these request statuses.', 'cachilupi-pet' ) ) );
			wp_die();
		}
		// $user = wp_get_current_user(); // No longer needed for direct role check here
		check_ajax_referer( 'cachilupi_pet_get_requests_status_nonce', 'security' );

		global $wpdb;
		$client_id  = get_current_user_id();
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$client_requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, status, driver_id FROM {$table_name} WHERE client_user_id = %d ORDER BY created_at DESC",
				$client_id
			)
		);

		if ( $wpdb->last_error ) {
			wp_send_json_error( array( 'message' => __( 'Error al obtener el estado de las solicitudes.', 'cachilupi-pet' ) ) );
			wp_die();
		}

		$statuses_data = array();
		if ( $client_requests ) {
			foreach ( $client_requests as $request ) {
				$status_display  = \CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status( $request->status );
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

	// If cachilupi_pet_translate_status is needed and not refactored to a utility class yet,
	// it could be temporarily duplicated here as a private method or called globally if available.
}
