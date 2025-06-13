<?php

namespace CachilupiPet;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin class for Cachilupi Pet.
 *
 * Handles initialization of plugin components, hooks, and activation.
 *
 * @package CachilupiPet
 */
class Cachilupi_Pet_Plugin {

	/**
	 * Manages user roles and login redirection.
	 *
	 * @var \CachilupiPet\Users\Cachilupi_Pet_User_Roles
	 */
	private $user_roles_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialization code if needed in the future.
	}

	/**
	 * Initialize the plugin.
	 *
	 * Loads the plugin text domain and registers hooks.
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );

		// Autoloader will handle these class loads.
		// No need for: if ( ! class_exists(...) ) require_once ...;

		// Initialize User Roles functionality (like login redirect).
		// This was duplicated, ensuring it's present only once.
		if ( ! isset( $this->user_roles_manager ) ) { // Check if already instantiated to avoid re-doing it if init is called multiple times.
			$this->user_roles_manager = new \CachilupiPet\Users\Cachilupi_Pet_User_Roles();
			add_filter( 'login_redirect', array( $this->user_roles_manager, 'custom_login_redirect_handler' ), 10, 3 );
			add_filter( 'login_errors', array( $this->user_roles_manager, 'filter_generic_login_error' ) );
		}

		// Initialize Admin Settings page if in admin area.
		if ( is_admin() ) {
			// Autoloader handles class_exists checks and require_once for \CachilupiPet\Admin\Cachilupi_Pet_Settings
			if ( class_exists( '\CachilupiPet\Admin\Cachilupi_Pet_Settings' ) ) {
				new \CachilupiPet\Admin\Cachilupi_Pet_Settings();
			}
		}

		// Initialize Shortcodes.
		// Autoloader handles \CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes
		if ( class_exists( '\CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes' ) ) {
			$shortcode_manager_instance = new \CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes();
			add_shortcode( 'cachilupi_driver_panel', array( $shortcode_manager_instance, 'render_driver_panel_shortcode' ) );
			add_shortcode( 'cachilupi_maps', array( $shortcode_manager_instance, 'render_client_booking_form_shortcode' ) );
		}

		// Initialize AJAX Handlers.
		// Autoloader handles \CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers
		if ( class_exists( '\CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers' ) ) {
			$ajax_manager_instance = new \CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers();
			add_action( 'wp_ajax_cachilupi_pet_driver_action', array( $ajax_manager_instance, 'handle_driver_action' ) );
			add_action( 'wp_ajax_cachilupi_update_driver_location', array( $ajax_manager_instance, 'update_driver_location' ) );
			add_action( 'wp_ajax_cachilupi_get_driver_location', array( $ajax_manager_instance, 'get_driver_location' ) );
			add_action( 'wp_ajax_cachilupi_pet_submit_request', array( $ajax_manager_instance, 'submit_service_request' ) );
			add_action( 'wp_ajax_cachilupi_check_new_requests', array( $ajax_manager_instance, 'check_new_requests' ) );
			add_action( 'wp_ajax_cachilupi_get_client_requests_status', array( $ajax_manager_instance, 'get_client_requests_status' ) );
		}

		// Initialize Assets Manager.
		// Autoloader handles \CachilupiPet\Core\Cachilupi_Pet_Assets_Manager
		if ( class_exists( '\CachilupiPet\Core\Cachilupi_Pet_Assets_Manager' ) ) {
			$plugin_main_file_path = CACHILUPI_PET_DIR . 'cachilupi-pet.php'; // Assumes CACHILUPI_PET_DIR is defined
			$assets_manager_instance = new \CachilupiPet\Core\Cachilupi_Pet_Assets_Manager( plugin_dir_url( $plugin_main_file_path ), CACHILUPI_PET_DIR );
			add_action( 'wp_enqueue_scripts', array( $assets_manager_instance, 'enqueue_assets' ) );
		}
		// Other hooks will be registered here.
	}

	/**
	 * Plugin activation hook.
	 *
	 * Creates database tables and sets default options.
	 * @return void
	 */
	public static function activate() {
		// Autoloader will handle these class loads.
		// No need for: if ( ! class_exists(...) ) require_once ...; for classes within CachilupiPet namespace.

		// Create database table(s).
		\CachilupiPet\Data\Cachilupi_Pet_Request_Manager::create_table();

		// Add custom user roles.
		\CachilupiPet\Users\Cachilupi_Pet_User_Roles::add_roles();

		// Default options
		add_option('cachilupi_pet_mapbox_token', '');
		add_option('cachilupi_pet_client_redirect_slug', 'reserva');
		add_option('cachilupi_pet_driver_redirect_slug', 'driver');
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_text_domain() { // Changed method name
		load_plugin_textdomain( 'cachilupi-pet', false, dirname( plugin_basename( CACHILUPI_PET_DIR . 'cachilupi-pet.php' ) ) . '/languages/' ); // Ensure plugin_basename is used correctly
	}

}
