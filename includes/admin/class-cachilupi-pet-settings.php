<?php

namespace CachilupiPet\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles the admin settings page for Cachilupi Pet plugin.
 *
 * Registers settings, sections, fields, and renders the settings page.
 *
 * @package CachilupiPet\Admin
 */
class Cachilupi_Pet_Settings {

	/**
	 * Constructor. Hooks into WordPress actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Adds the plugin settings page to the WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu_page() {
		add_options_page(
			__( 'Cachilupi Pet Ajustes', 'cachilupi-pet' ),
			__( 'Cachilupi Pet', 'cachilupi-pet' ),
			'manage_options',
			'cachilupi_pet_settings_page',
			array( $this, 'render_settings_page' ) // Point to the method in this class
		);
	}

	/**
	 * Registers plugin settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'cachilupi_pet_options_group', 'cachilupi_pet_mapbox_token', 'sanitize_text_field' );
		register_setting( 'cachilupi_pet_options_group', 'cachilupi_pet_client_redirect_slug', 'sanitize_text_field' );
		register_setting( 'cachilupi_pet_options_group', 'cachilupi_pet_driver_redirect_slug', 'sanitize_text_field' );

		add_settings_section(
			'cachilupi_pet_general_section',
			__( 'Ajustes Generales', 'cachilupi-pet' ),
			null, // No callback needed for the section description itself
			'cachilupi_pet_settings_page' // Page slug
		);

		add_settings_field(
			'cachilupi_pet_mapbox_token_field',
			__( 'Mapbox Access Token', 'cachilupi-pet' ),
			array( $this, 'mapbox_token_field_cb' ),
			'cachilupi_pet_settings_page',
			'cachilupi_pet_general_section'
		);
		add_settings_field(
			'cachilupi_pet_client_redirect_slug_field',
			__( 'Slug Página Cliente (Reserva)', 'cachilupi-pet' ),
			array( $this, 'client_redirect_slug_field_cb' ),
			'cachilupi_pet_settings_page',
			'cachilupi_pet_general_section'
		);
		add_settings_field(
			'cachilupi_pet_driver_redirect_slug_field',
			__( 'Slug Página Conductor (Panel)', 'cachilupi-pet' ),
			array( $this, 'driver_redirect_slug_field_cb' ),
			'cachilupi_pet_settings_page',
			'cachilupi_pet_general_section'
		);
	}

	/**
	 * Renders the HTML for the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'cachilupi_pet_options_group' );
				do_settings_sections( 'cachilupi_pet_settings_page' );
				submit_button( __( 'Guardar Ajustes', 'cachilupi-pet' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Callback function to render the Mapbox token field.
	 *
	 * @return void
	 */
	public function mapbox_token_field_cb() {
		$cached_option = get_transient( 'cachilupi_pet_mapbox_token_cached' );
		if ( false === $cached_option ) {
			$option = get_option( 'cachilupi_pet_mapbox_token' );
			set_transient( 'cachilupi_pet_mapbox_token_cached', $option, HOUR_IN_SECONDS );
		} else {
			$option = $cached_option;
		}
		echo '<input type="text" id="cachilupi_pet_mapbox_token" name="cachilupi_pet_mapbox_token" value="' . esc_attr( $option ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu token de acceso de Mapbox.', 'cachilupi-pet' ) . '</p>';
	}

	/**
	 * Callback function to render the client redirect slug field.
	 *
	 * @return void
	 */
	public function client_redirect_slug_field_cb() {
		$cached_option = get_transient( 'cachilupi_pet_client_redirect_slug_cached' );
		if ( false === $cached_option ) {
			$option = get_option( 'cachilupi_pet_client_redirect_slug', 'reserva' );
			set_transient( 'cachilupi_pet_client_redirect_slug_cached', $option, HOUR_IN_SECONDS );
		} else {
			$option = $cached_option;
		}
		echo '<input type="text" id="cachilupi_pet_client_redirect_slug" name="cachilupi_pet_client_redirect_slug" value="' . esc_attr( $option ) . '" class="regular-text" />';
		echo '<p class="description">' . wp_kses_post( sprintf( __( 'Slug de la página donde los clientes realizan reservas (ej: %s para %s).', 'cachilupi-pet' ), '<code>reserva</code>', '<code>' . home_url( '/reserva/' ) . '</code>' ) ) . '</p>';
	}

	/**
	 * Callback function to render the driver redirect slug field.
	 *
	 * @return void
	 */
	public function driver_redirect_slug_field_cb() {
		$cached_option = get_transient( 'cachilupi_pet_driver_redirect_slug_cached' );
		if ( false === $cached_option ) {
			$option = get_option( 'cachilupi_pet_driver_redirect_slug', 'driver' );
			set_transient( 'cachilupi_pet_driver_redirect_slug_cached', $option, HOUR_IN_SECONDS );
		} else {
			$option = $cached_option;
		}
		echo '<input type="text" id="cachilupi_pet_driver_redirect_slug" name="cachilupi_pet_driver_redirect_slug" value="' . esc_attr( $option ) . '" class="regular-text" />';
		echo '<p class="description">' . wp_kses_post( sprintf( __( 'Slug de la página del panel de conductores (ej: %s para %s).', 'cachilupi-pet' ), '<code>driver</code>', '<code>' . home_url( '/driver/' ) . '</code>' ) ) . '</p>';
	}
}
