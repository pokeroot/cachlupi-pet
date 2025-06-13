<?php

namespace CachilupiPet\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cachilupi_Pet_Utils {

	/**
	 * Translates request status slugs to a human-readable format.
	 *
	 * @param string $status_slug The status slug (e.g., 'pending', 'accepted').
	 * @return string The translated status string.
	 */
	public static function translate_status( $status_slug ) {
		// Ensure the slug is a string
		if ( ! is_string( $status_slug ) ) {
			// If not a string, try to convert or return a default value
			return is_scalar( $status_slug ) ? ucfirst( esc_html( (string) $status_slug ) ) : __( 'Desconocido', 'cachilupi-pet' );
		}

		$translations = array(
			'pending'    => __( 'Pendiente', 'cachilupi-pet' ),
			'accepted'   => __( 'Aceptado', 'cachilupi-pet' ),
			'rejected'   => __( 'Rechazado', 'cachilupi-pet' ),
			'on_the_way' => __( 'En Camino', 'cachilupi-pet' ),
			'arrived'    => __( 'En Origen', 'cachilupi-pet' ), // O 'Ha llegado al origen'
			'picked_up'  => __( 'Mascota Recogida', 'cachilupi-pet' ),
			'completed'  => __( 'Completado', 'cachilupi-pet' ), // Para futuras implementaciones
			// Puedes añadir más estados y sus traducciones aquí
		);

		$status_slug_lower = strtolower( $status_slug );

		return isset( $translations[ $status_slug_lower ] ) ? $translations[ $status_slug_lower ] : ucfirst( esc_html( $status_slug ) );
	}
}
