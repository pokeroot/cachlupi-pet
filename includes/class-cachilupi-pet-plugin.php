<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Ensure the User Roles class is loaded.
		if ( ! class_exists( '\CachilupiPet\Users\Cachilupi_Pet_User_Roles' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-cachilupi-pet-user-roles.php';
		}

		// Initialize User Roles functionality (like login redirect).
		// This was duplicated, ensuring it's present only once.
		if ( ! isset( $this->user_roles_manager ) ) { // Check if already instantiated to avoid re-doing it if init is called multiple times.
			$this->user_roles_manager = new \CachilupiPet\Users\Cachilupi_Pet_User_Roles();
			add_filter( 'login_redirect', array( $this->user_roles_manager, 'custom_login_redirect_handler' ), 10, 3 );
			add_filter( 'login_errors', array( $this->user_roles_manager, 'filter_generic_login_error' ) );
		}

		// Initialize Admin Settings page if in admin area.
		if ( is_admin() ) {
			// Ensure the Settings class is loaded.
			$admin_settings_file = dirname( __FILE__ ) . '/admin/class-cachilupi-pet-settings.php';
			if ( file_exists( $admin_settings_file ) && ! class_exists( '\CachilupiPet\Admin\Cachilupi_Pet_Settings' ) ) {
				require_once $admin_settings_file;
			}
			if ( class_exists( '\CachilupiPet\Admin\Cachilupi_Pet_Settings' ) ) {
				new \CachilupiPet\Admin\Cachilupi_Pet_Settings();
			}
		}

		// Initialize Shortcodes.
		// The public directory is one level up from 'includes', then into 'public'.
		$shortcodes_file = dirname( __DIR__ ) . '/public/class-cachilupi-pet-shortcodes.php';
		if ( file_exists( $shortcodes_file ) && ! class_exists( '\CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes' ) ) {
			require_once $shortcodes_file;
		}
		if ( class_exists( '\CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes' ) ) {
			$shortcode_manager = new \CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes();
			add_shortcode( 'cachilupi_driver_panel', array( $shortcode_manager, 'render_driver_panel_shortcode' ) );
			add_shortcode( 'cachilupi_maps', array( $shortcode_manager, 'render_client_booking_form_shortcode' ) );
		}

		// Initialize AJAX Handlers.
		$ajax_handlers_file = plugin_dir_path( __FILE__ ) . 'classes/class-cachilupi-pet-ajax-handlers.php';
		if ( file_exists( $ajax_handlers_file ) && ! class_exists( '\CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers' ) ) {
			require_once $ajax_handlers_file;
		}
		if ( class_exists( '\CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers' ) ) {
			$ajax_manager = new \CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers();
			add_action( 'wp_ajax_cachilupi_pet_driver_action', array( $ajax_manager, 'handle_driver_action' ) );
			add_action( 'wp_ajax_cachilupi_update_driver_location', array( $ajax_manager, 'update_driver_location' ) );
			add_action( 'wp_ajax_cachilupi_get_driver_location', array( $ajax_manager, 'get_driver_location' ) );
			add_action( 'wp_ajax_cachilupi_pet_submit_request', array( $ajax_manager, 'submit_service_request' ) );
			// For nopriv users (if any AJAX actions need to be public)
			// add_action( 'wp_ajax_nopriv_cachilupi_pet_submit_request', array( $ajax_manager, 'submit_service_request' ) );
			add_action( 'wp_ajax_cachilupi_check_new_requests', array( $ajax_manager, 'check_new_requests' ) );
			add_action( 'wp_ajax_cachilupi_get_client_requests_status', array( $ajax_manager, 'get_client_requests_status' ) );
		}

		// Initialize Assets Manager.
		// CACHILUPI_PET_DIR is defined in the main plugin file: cachilupi-pet.php
		// plugin_dir_path( __FILE__ ) here is 'wp-content/plugins/cachilupi-pet/includes/'
		// CACHILUPI_PET_DIR should be 'wp-content/plugins/cachilupi-pet/'
		// So, CACHILUPI_PET_DIR . 'cachilupi-pet.php' is the path to the main plugin file.
		$assets_manager_file = plugin_dir_path( __FILE__ ) . 'class-cachilupi-pet-assets-manager.php';
		if ( file_exists( $assets_manager_file ) && ! class_exists( '\CachilupiPet\Core\Cachilupi_Pet_Assets_Manager' ) ) {
			require_once $assets_manager_file;
		}
		if ( class_exists( '\CachilupiPet\Core\Cachilupi_Pet_Assets_Manager' ) ) {
			// Ensure CACHILUPI_PET_DIR is available or find a robust way to get plugin root url/path
			// For now, we assume CACHILUPI_PET_DIR is defined and accessible.
			// The main plugin file is assumed to be 'cachilupi-pet.php' in the CACHILUPI_PET_DIR.
			$plugin_main_file = CACHILUPI_PET_DIR . 'cachilupi-pet.php';
			$assets_manager = new \CachilupiPet\Core\Cachilupi_Pet_Assets_Manager( plugin_dir_url( $plugin_main_file ), CACHILUPI_PET_DIR );
			add_action( 'wp', array( $assets_manager, 'enqueue_assets' ) );
		}
		// Other hooks will be registered here.
	}

	/**
	 * Plugin activation hook.
	 *
	 * Creates database tables and sets default options.
	 */
	public static function activate() {
		// Ensure the Request Manager class is loaded.
		// Note: CACHILUPI_PET_DIR should be defined in the main plugin file.
		// If not available here directly, consider defining a constant in the main plugin file for the plugin's root path.
		// For now, assuming includes are relative to the current file or handled by autoloader if implemented later.
		if ( ! class_exists( '\CachilupiPet\Data\Cachilupi_Pet_Request_Manager' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-cachilupi-pet-request-manager.php';
		}

		// Create database table(s).
		\CachilupiPet\Data\Cachilupi_Pet_Request_Manager::create_table();

		// Ensure the User Roles class is loaded.
		if ( ! class_exists( '\CachilupiPet\Users\Cachilupi_Pet_User_Roles' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-cachilupi-pet-user-roles.php';
		}

		// Add custom user roles.
		\CachilupiPet\Users\Cachilupi_Pet_User_Roles::add_roles();

		// Default options
		add_option('cachilupi_pet_mapbox_token', '');
		add_option('cachilupi_pet_client_redirect_slug', 'reserva');
		add_option('cachilupi_pet_driver_redirect_slug', 'driver');
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cachilupi-pet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

}
