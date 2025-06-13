<?php

namespace CachilupiPet\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cachilupi_Pet_Request_Manager {

	/**
	 * Creates the database table for requests.
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
			KEY client_user_id (client_user_id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
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

	// More methods will be added later.
}
