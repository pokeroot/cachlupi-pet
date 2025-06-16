<?php

namespace CachilupiPet\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages database operations for Cachilupi Pet requests.
 *
 * Handles table creation and provides methods for CRUD operations on requests.
 *
 * @package CachilupiPet\Data
 */
class Cachilupi_Pet_Request_Manager {

	/**
	 * Creates the database table for requests.
	 *
	 * @return void
	 */
	public static function create_table() {
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
			KEY client_user_id (client_user_id),
			KEY client_time_created_sort (client_user_id, time, created_at),
			KEY status_driver_id (status, driver_id),
			KEY client_created_at_sort (client_user_id, created_at)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Example method to retrieve a request by its ID.
	 *
	 * @param int $request_id The ID of the request.
	 * @return object|null Database row object or null if not found.
	 */
	public static function get_request_by_id( int $request_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $request_id )
		);
	}

	/**
	 * Retrieves the driver_id and status for a specific request.
	 *
	 * @param int $request_id The ID of the request.
	 * @return object|null An object with driver_id and status, or null if not found.
	 */
	public static function get_request_driver_and_status( int $request_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT driver_id, status FROM {$table_name} WHERE id = %d", $request_id )
		);
	}

	/**
	 * Retrieves the status for a specific request.
	 *
	 * @param int $request_id The ID of the request.
	 * @return string|null The status of the request, or null if not found.
	 */
	public static function get_request_status( int $request_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';
		return $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$table_name} WHERE id = %d", $request_id )
		);
	}

	/**
	 * Updates the status and optionally the driver_id of a request based on given conditions.
	 *
	 * @param int    $request_id      The ID of the request to update.
	 * @param string $new_status      The new status slug.
	 * @param int|null $new_driver_id   The new driver_id (can be null to clear it or if not changing).
	 * @param array  $where_conditions Associative array of WHERE conditions (e.g., ['status' => 'pending', 'driver_id' => null]).
	 *                                 The 'id' => $request_id condition is added automatically.
	 * @param array  $where_formats   Array of formats for the WHERE condition values.
	 *                                 The format for 'id' (%d) is added automatically.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function update_request_status_and_driver( int $request_id, string $new_status, $new_driver_id, array $where_conditions, array $where_formats ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$data_to_update = array( 'status' => $new_status );
		$data_formats   = array( '%s' );

		if ( ! is_null( $new_driver_id ) ) {
			$data_to_update['driver_id'] = $new_driver_id;
			$data_formats[]              = '%d';
		} elseif ( array_key_exists('driver_id', $where_conditions) && is_null($where_conditions['driver_id']) && $new_status === 'rejected') {
			// This case is specifically for 'reject' where we might set driver_id to NULL explicitly
			// if the original condition was that driver_id IS NULL.
			// However, the current logic in handle_driver_action for reject doesn't change driver_id.
			// This part might need refinement based on exact logic for 'reject' if it should clear a driver_id.
			// For now, we only add driver_id to $data_to_update if $new_driver_id is not null.
		}


		// Always include the request ID in the WHERE clause
		$where_conditions['id'] = $request_id;
		$where_formats[]        = '%d';

		return $wpdb->update(
			$table_name,
			$data_to_update,
			$where_conditions,
			$data_formats,
			$where_formats
		);
	}

	/**
	 * Updates the status of a request based on given conditions.
	 * This is a more specific version of update_request_status_and_driver if driver_id is not changed.
	 *
	 * @param int    $request_id      The ID of the request to update.
	 * @param string $new_status      The new status slug.
	 * @param array  $where_conditions Associative array of WHERE conditions (e.g., ['status' => 'accepted', 'driver_id' => 123]).
	 *                                 The 'id' => $request_id condition is added automatically.
	 * @param array  $where_formats   Array of formats for the WHERE condition values.
	 *                                 The format for 'id' (%d) is added automatically.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function update_request_status( int $request_id, string $new_status, array $where_conditions, array $where_formats ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$data_to_update = array( 'status' => $new_status );
		$data_formats   = array( '%s' );

		// Always include the request ID in the WHERE clause
		$where_conditions['id'] = $request_id;
		$where_formats[]        = '%d';

		return $wpdb->update(
			$table_name,
			$data_to_update,
			$where_conditions,
			$data_formats,
			$where_formats
		);
	}

	/**
	 * Updates the driver's current location for a specific request.
	 *
	 * @param int   $request_id The ID of the request.
	 * @param int   $driver_id  The ID of the driver.
	 * @param float $latitude   The new latitude.
	 * @param float $longitude  The new longitude.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function update_request_driver_location( int $request_id, int $driver_id, float $latitude, float $longitude ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		return $wpdb->update(
			$table_name,
			array(
				'driver_current_lat'           => $latitude,
				'driver_current_lon'           => $longitude,
				'driver_location_updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'        => $request_id,
				'driver_id' => $driver_id,
				'status'    => 'on_the_way', // Only update if the trip is active
			),
			array( '%f', '%f', '%s' ), // Formats for data
			array( '%d', '%d', '%s' )  // Formats for WHERE conditions
		);
	}

	/**
	 * Retrieves the driver's current location for a specific request if the client is authorized.
	 *
	 * @param int $request_id The ID of the request.
	 * @param int $client_id  The ID of the client making the request.
	 * @return object|null An object with latitude, longitude, and updated_at, or null if not found/authorized.
	 */
	public static function get_request_driver_location( int $request_id, int $client_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT driver_current_lat, driver_current_lon, driver_location_updated_at
				 FROM {$table_name}
				 WHERE id = %d AND client_user_id = %d AND status = 'on_the_way'",
				$request_id,
				$client_id
			)
		);
	}

	/**
	 * Creates a new service request in the database.
	 *
	 * @param array $data   Associative array of data to insert.
	 * @param array $formats Array of formats for the data values.
	 * @return int|false The ID of the newly inserted row, or false on error.
	 */
	public static function create_service_request( array $data, array $formats ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$result = $wpdb->insert( $table_name, $data, $formats );
		if ( $result === false ) {
			return false;
		}
		return $wpdb->insert_id;
	}

	/**
	 * Counts the number of pending requests that are not yet assigned to any driver.
	 *
	 * @return int The count of pending unassigned requests.
	 */
	public static function get_pending_unassigned_requests_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = %s AND (driver_id IS NULL OR driver_id = 0)",
				'pending'
			)
		);
	}

	/**
	 * Retrieves all requests for a specific client, ordered by creation date.
	 *
	 * @param int $client_id The ID of the client.
	 * @return array An array of request objects, or an empty array if none found or on error.
	 */
	public static function get_client_requests( int $client_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cachilupi_requests';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, status, driver_id FROM {$table_name} WHERE client_user_id = %d ORDER BY created_at DESC",
				$client_id
			)
		);
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Retrieves requests for the driver panel.
	 * Fetches requests assigned to the given driver OR pending requests that are unassigned.
	 * Includes client's display name.
	 *
	 * @param int    $driver_id      The ID of the current driver.
	 * @param string $pending_status The slug for 'pending' status.
	 * @return array An array of request objects.
	 */
	public static function get_requests_for_driver_panel( int $driver_id, string $pending_status = 'pending' ): array {
		global $wpdb;
		$requests_table = $wpdb->prefix . 'cachilupi_requests';
		$users_table    = $wpdb->users;

		$sql = $wpdb->prepare(
			"SELECT r.id, r.time, r.pickup_address, r.dropoff_address, r.status, r.pet_type, r.pet_instructions, r.notes, r.driver_id, r.client_user_id, u.display_name as client_name
			 FROM {$requests_table} r
			 LEFT JOIN {$users_table} u ON r.client_user_id = u.ID
			 WHERE (r.driver_id = %d OR (r.status = %s AND r.driver_id IS NULL))
			 ORDER BY r.time DESC, r.created_at DESC",
			$driver_id,
			$pending_status
		);

		$results = $wpdb->get_results( $sql );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Retrieves all requests for a specific client, including detailed information and driver's name.
	 *
	 * @param int $client_id The ID of the client.
	 * @return array An array of request objects with details.
	 */
	public static function get_client_requests_with_details( int $client_id ): array {
		global $wpdb;
		$requests_table = $wpdb->prefix . 'cachilupi_requests';
		$users_table    = $wpdb->users;

		$sql = $wpdb->prepare(
			"SELECT r.id, r.time, r.pickup_address, r.dropoff_address, r.pet_type, r.status, r.driver_id, u.display_name as driver_name
			 FROM {$requests_table} r
			 LEFT JOIN {$users_table} u ON r.driver_id = u.ID
			 WHERE r.client_user_id = %d ORDER BY r.time DESC, r.created_at DESC",
			$client_id
		);
		$results = $wpdb->get_results( $sql );
		return is_array( $results ) ? $results : array();
	}

	// More methods will be added later.
}
