<?php

namespace CachilupiPet\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages user roles and capabilities for the Cachilupi Pet plugin.
 *
 * Handles creation of custom roles and role-specific behaviors like login redirection.
 *
 * @package CachilupiPet\Users
 */
class Cachilupi_Pet_User_Roles {

	/**
	 * Adds custom user roles ('driver' and 'client').
	 *
	 * @return void
	 */
	public static function add_roles() {
		// Add 'driver' role on plugin activation
		// Ensure the role is added if it doesn't exist
		if ( null === get_role( 'driver' ) ) {
			add_role(
				'driver',
				__( 'Driver', 'cachilupi-pet' ),
				array(
					'read'                    => true,
					'edit_posts'              => false,
					'delete_posts'            => false,
					'manage_trip_status'      => true,
					'update_trip_location'    => true,
					'view_pending_requests'   => true,
					'access_driver_panel'     => true,
					'access_booking_form'     => true, // Assuming drivers can also create requests
					'submit_pet_request'      => true, // Assuming drivers can also create requests
				)
			);
		}
		// Add 'client' role on plugin activation
		// Ensure the role is added if it doesn't exist
		if ( null === get_role( 'client' ) ) {
			add_role(
				'client',
				__( 'Cliente', 'cachilupi-pet' ),
				array(
					'read'                      => true,
					'view_own_trip_location'    => true,
					'submit_pet_request'        => true,
					'view_own_request_status'   => true,
					'access_booking_form'       => true,
					'view_own_requests'         => true,
				)
			);
		}
	}

	/**
	 * Handles custom login redirection based on user role.
	 *
	 * @param string $redirect_to           The default redirect destination.
	 * @param string $requested_redirect_to The user-requested redirect destination.
	 * @param \WP_User|\WP_Error $user      WP_User object if login is successful, WP_Error otherwise.
	 * @return string The final redirect URL.
	 */
	public function custom_login_redirect_handler( $redirect_to, $requested_redirect_to, $user ) {
		// Ensure we have a valid WP_User object
		if ( ! is_wp_error( $user ) && $user instanceof \WP_User ) {

			// Get redirect slugs from options
			$client_slug = get_option( 'cachilupi_pet_client_redirect_slug', 'reserva' );
			$driver_slug = get_option( 'cachilupi_pet_driver_redirect_slug', 'driver' );

			$client_redirect_url = home_url( '/' . trim( $client_slug, '/' ) . '/' );
			$driver_panel_url    = home_url( '/' . trim( $driver_slug, '/' ) . '/' );

			// Administrator: default redirect or requested redirect
			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return $requested_redirect_to ?: $redirect_to;
			}

			// Driver: redirect to driver panel
			if ( in_array( 'driver', (array) $user->roles, true ) ) {
				return $driver_panel_url;
			}

			// Client: redirect to client page (booking form)
			if ( in_array( 'client', (array) $user->roles, true ) ) {
				return $client_redirect_url;
			}

			// Default redirect for other roles (e.g., subscriber) can be client page or home
			return $client_redirect_url;
		}

		// If user object is invalid or there's an error, return default redirect
		return $redirect_to;
	}

	/**
	 * Modifies the default WordPress login error messages to be more generic.
	 * This helps to prevent user enumeration.
	 *
	 * @param string $errors The default WordPress error message.
	 * @return string The modified error message.
	 */
	public function filter_generic_login_error( $errors ) {
		// Check if there is any login error
		if ( ! empty( $errors ) ) {
			// Replace any login error message with a generic one.
			// This covers incorrect username/email and incorrect password errors.
			$error_title   = esc_html__( 'Error', 'cachilupi-pet' );
			$error_message = esc_html__( 'Nombre de usuario o contraseña incorrectos.', 'cachilupi-pet' );
			$errors        = sprintf( '<p class="login-error-message"><strong>%s:</strong> %s</p>', $error_title, $error_message );
		}
		return $errors;
	}
}
