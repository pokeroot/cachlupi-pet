<?php

namespace CachilupiPet\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cachilupi_Pet_User_Roles {

	/**
	 * Adds custom user roles ('driver' and 'client').
	 */
	public static function add_roles() {
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
}
