<?php

namespace CachilupiPet\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages plugin scripts and styles.
 *
 * Responsible for enqueueing CSS and JavaScript files at the appropriate times.
 *
 * @package CachilupiPet\Core
 */
class Cachilupi_Pet_Assets_Manager {

	/**
	 * Base URL of the plugin.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Base path of the plugin.
	 *
	 * @var string
	 */
	private $plugin_path; // If needed for file checks

	/**
	 * Constructor.
	 *
	 * @param string $plugin_url  Base URL of the plugin.
	 * @param string $plugin_path Base Path of the plugin.
	 */
	public function __construct( $plugin_url, $plugin_path ) {
		$this->plugin_url  = $plugin_url;
		$this->plugin_path = $plugin_path;
	}

	/**
	 * Enqueues scripts and styles based on shortcode presence.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		// Check if it's a single post/page and has the shortcode [cachilupi_maps]
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cachilupi_maps' ) ) {
			wp_enqueue_style(
				'cachilupi-maps',
				$this->plugin_url . 'assets/css/maps.css',
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
				$this->plugin_url . 'assets/dist/js/cachilupi-maps-entry.js', // Updated path
				array( 'mapbox-gl', 'jquery', 'flatpickr-js', 'flatpickr-l10n-es' ), // Dependencies remain
				'1.5', // Version can be updated or managed by Parcel build if integrated
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
				$this->plugin_url . 'assets/css/driver-panel.css',
				array(),
				'1.0'
			);
			wp_enqueue_script(
				'cachilupi-driver-panel',
				$this->plugin_url . 'assets/dist/js/cachilupi-driver-panel-entry.js', // Updated path
				array( 'jquery' ), // Dependencies remain
				'1.0', // Version can be updated or managed by Parcel build if integrated
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
}
