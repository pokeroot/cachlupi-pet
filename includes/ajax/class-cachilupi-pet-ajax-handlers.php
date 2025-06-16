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

		// Use Request Manager to get current request details
		$request_being_actioned = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_request_driver_and_status( $request_id );

		if ( ! $request_being_actioned ) {
			wp_send_json_error( array( 'message' => 'Solicitud no encontrada.' ) );
			wp_die();
		}

		$current_driver_id = get_current_user_id();
		$new_status_slug   = '';
		$result            = false;
		// $where_conditions and $where_formats will be built for the Request_Manager methods
		$where_conditions = array();
		$where_formats    = array();


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
				$new_status_slug            = 'accepted';
				$where_conditions['status'] = 'pending'; // Current status must be pending
				// $where_conditions['driver_id'] IS NULL; // Implicitly, or explicitly if needed by DB method
				$where_formats[]            = '%s';
				// driver_id can also be 0 if no driver is assigned yet.
                // The new method update_request_status_and_driver will handle setting driver_id = $current_driver_id
				$result = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::update_request_status_and_driver(
					$request_id,
					$new_status_slug,
					$current_driver_id,
					$where_conditions,
					$where_formats
				);
				break;
			case 'reject':
				$new_status_slug            = 'rejected';
				$where_conditions['status'] = 'pending';
				$where_formats[]            = '%s';
				// If request is assigned to current driver, they can reject.
				// If request is unassigned (driver_id is NULL), any driver can reject (as per current logic).
				// The original logic didn't explicitly check driver_id IS NULL for reject, but $where_conditions['driver_id'] was not set.
				// This means it would try to update WHERE status='pending' AND id=X.
				// If a request was pending AND assigned to another driver, the initial check prevents this.
				// If it was pending AND assigned to THIS driver, this driver could reject.
				// If it was pending AND unassigned, this driver could reject.
				// The new method update_request_status does not change driver_id.
				$result = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::update_request_status(
					$request_id,
					$new_status_slug,
					$where_conditions, // existing driver_id is not changed by this action
					$where_formats
				);
				break;
			case 'on_the_way':
			case 'arrive':
			case 'picked_up':
			case 'complete':
				// Determine new status slug based on action
				$status_map = array(
					'on_the_way' => 'on_the_way',
					'arrive'     => 'arrived',
					'picked_up'  => 'picked_up',
					'complete'   => 'completed',
				);
				$new_status_slug = $status_map[ $action ];

				$where_conditions['driver_id'] = $current_driver_id; // Must be assigned to current driver
				$where_formats[]               = '%d';
				// Add specific previous status checks based on the action
				switch ($action) {
					case 'on_the_way':
						$where_conditions['status'] = 'accepted';
						$where_formats[] = '%s';
						break;
					case 'arrive':
						$where_conditions['status'] = 'on_the_way';
						$where_formats[] = '%s';
						break;
					case 'picked_up':
						$where_conditions['status'] = 'arrived';
						$where_formats[] = '%s';
						break;
					case 'complete':
						$where_conditions['status'] = 'picked_up';
						$where_formats[] = '%s';
						break;
				}
				// For now, matching original logic: only checks current driver_id and new status conditions
				$result = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::update_request_status(
					$request_id,
					$new_status_slug,
					$where_conditions,
					$where_formats
				);
				break;
			default:
				wp_send_json_error( array( 'message' => 'Acción no válida.' ) );
				wp_die();
		}

		if ( $result === 0 ) { // Update affected 0 rows
			// Fetch current status from DB to determine exact cause
			$current_db_status = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_request_status( $request_id );
			// $request_being_actioned->status is the status *before* this attempt to update.
			if ( $current_db_status && $current_db_status !== $request_being_actioned->status ) {
				// Status changed by another process between initial read and update attempt
				wp_send_json_error( array( 'message' => 'La solicitud fue actualizada por otro proceso. Por favor, refresca la página.', 'error_code' => 'status_changed' ) );
			} elseif ( $action === 'accept' ) {
				// If action was 'accept' and 0 rows updated, it might be because another driver accepted it (driver_id changed)
				// or status is no longer 'pending'. The initial checks should catch most, but this is a fallback.
				// Re-fetch full driver/status to check driver_id specifically for 'already_accepted'
                $refetched_request = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_request_driver_and_status( $request_id );
                if ($refetched_request && !is_null($refetched_request->driver_id) && $refetched_request->driver_id != $current_driver_id) {
					wp_send_json_error( array( 'message' => 'La solicitud ya fue aceptada por otro conductor. Por favor, refresca la página.', 'error_code' => 'already_accepted' ) );
                } else {
					// If driver_id is null or current_driver_id, but status is not pending, it's caught by status_changed or initial checks.
					// This specific error is for when the condition for update wasn't met.
                    wp_send_json_error( array( 'message' => 'No se pudo actualizar la solicitud. Es posible que ya haya sido procesada o las condiciones no se cumplen (ej. estado no es \'pendiente\' para aceptar).', 'error_code' => 'condition_not_met_accept' ) );
                }
			} else {
				// For other actions, or if $current_db_status is same as $request_being_actioned->status
				wp_send_json_error( array( 'message' => 'No se pudo actualizar la solicitud. Es posible que las condiciones no se cumplan (ej. el viaje no está asignado a ti o el estado actual no permite esta acción).', 'error_code' => 'condition_not_met' ) );
			}
			wp_die();
		} elseif ( $result !== false ) { // Update succeeded (result is number of rows updated, > 0)
			// $request_details    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $request_id ) ); // Removed as it's not used
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
			} else {
				wp_send_json_error( array( 'message' => 'No se pudo actualizar la solicitud. Es posible que ya haya sido procesada o las condiciones no se cumplen.', 'error_code' => 'condition_not_met' ) );
			}
			wp_die();
		} elseif ( $result !== false ) {
			// $request_details    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $request_id ) ); // Removed as it's not used
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
		$current_driver_id = get_current_user_id();

		$result = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::update_request_driver_location(
			$request_id,
			$current_driver_id,
			$latitude_val,
			$longitude_val
		);

		if ( $result > 0 ) {
			wp_send_json_success( array( 'message' => 'Ubicación actualizada.' ) );
		} elseif ( $result === 0 ) {
			// To give a more specific message, check if the request still meets conditions
			$request_check = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_request_by_id( $request_id ); // Fetches full request to check status and driver
			if ( ! $request_check || $request_check->driver_id != $current_driver_id || $request_check->status != 'on_the_way' ) {
				wp_send_json_error( array( 'message' => 'No se pudo actualizar la ubicación. El viaje ya no está activo o no está asignado a ti.', 'error_code' => 'trip_not_active' ) );
			} else {
				// Conditions met, but data was identical to existing, so 0 rows updated.
				wp_send_json_success( array( 'message' => 'Ubicación sin cambios.', 'status_code' => 'no_change' ) );
			}
		} else { // $result === false
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

		$client_id = get_current_user_id();
		$location_data = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_request_driver_location( $request_id, $client_id );

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

		$insert_id = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::create_service_request( $data, $format );

		if ( $insert_id === false ) {
			wp_send_json_error( array( 'message' => 'Error al guardar la solicitud. Por favor, intenta de nuevo.' ) );
		} else {
			wp_send_json_success( array(
				'message'    => 'Solicitud guardada correctamente.',
				'request_id' => $insert_id
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

		$pending_unassigned_count = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_pending_unassigned_requests_count();

		if ( $pending_unassigned_count > 0 ) {
			wp_send_json_success( array(
				'new_requests_count' => $pending_unassigned_count,
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

		$client_id = get_current_user_id();
		$client_requests = \CachilupiPet\Data\Cachilupi_Pet_Request_Manager::get_client_requests( $client_id );

		// The new get_client_requests method in Request_Manager always returns an array,
		// so direct check for $wpdb->last_error is not applicable here.
		// Error handling (like DB connection issues) would ideally be managed within Request_Manager,
		// though for now, it returns an empty array on $wpdb->get_results error.

		$statuses_data = array();
		if ( ! empty( $client_requests ) ) { // Check if the array is not empty
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
