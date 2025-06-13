<?php
/**
 * Plugin Name: Cachilupi Pet
 * Description: Plugin para gestionar servicios de transporte de mascotas con seguimiento.
 * Version: 2.0
 * Author: Jhon Narvaez
 *
 * === Cachilupi Pet ===
 * Contributors: Jhon Narvaez
 * Donate Link: #
 * Tags: pet, transportation, booking, driver panel, maps, geocoding, ajax, wordpress plugin, login redirect, UI improvements, roles, accessibility, security
 * Requires at least: 5.0
 * Tested up to: 6.x
 * Stable tag: 2.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Define plugin directory path
if ( ! defined( 'CACHILUPI_PET_DIR' ) ) {
    define( 'CACHILUPI_PET_DIR', plugin_dir_path( __FILE__ ) );
}

// Autoloader
spl_autoload_register( 'cachilupi_pet_autoloader' );

/**
 * Cachilupi Pet Autoloader.
 * Follows PSR-4 like convention for namespaces mapped to directories.
 * Base Namespace: CachilupiPet -> includes/
 * Sub-namespaces map to subdirectories.
 * Class names: My_Class_Name or MyClassName -> class-my-class-name.php
 *
 * @param string $class_name The fully qualified class name.
 */
function cachilupi_pet_autoloader( $class_name ) {
    if ( strpos( $class_name, 'CachilupiPet\\' ) !== 0 ) {
        return; // Not our namespace
    }

    // Remove the base namespace prefix "CachilupiPet\"
    $relative_class_name = substr( $class_name, strlen( 'CachilupiPet\\' ) );

    $parts = explode( '\\', $relative_class_name );
    $class_leaf_name = array_pop( $parts );

    // Convert PascalCase/CamelCase with underscores to lowercase kebab-case
    // Example: Cachilupi_Pet_User_Roles -> cachilupi-pet-user-roles
    // Example: MyExampleClass -> my-example-class
    // Updated regex to handle consecutive capitals (like an acronym) better by only inserting hyphen before a capital if it's preceded by a lowercase or digit,
    // or if it's not the first char in a segment after an underscore.
    $class_file_name_kebab = strtolower( preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])|_/', '-', $class_leaf_name ) );
    // Replace any remaining underscores (e.g., if it was My__Class) with hyphens.
    $class_file_name_kebab = str_replace( '_', '-', $class_file_name_kebab );

    $file_name = 'class-' . $class_file_name_kebab . '.php';

    // Construct the path: includes/{sub_namespace_paths}/{class_file_name}
    $path = CACHILUPI_PET_DIR . 'includes/';
    if ( ! empty( $parts ) ) { // If there are sub-namespace parts
        // Convert namespace parts to lowercase directory names
        // Special mapping for PublicArea to publicarea
        $processed_parts = array_map( 'strtolower', $parts );
        if ( count( $processed_parts ) === 1 && $processed_parts[0] === 'publicarea' ) {
            // This condition is specific if PublicArea was the only sub-namespace part.
            // If PublicArea can be nested, this logic needs adjustment.
        }
        $path .= implode( '/', $processed_parts ) . '/';
    }
    $path .= $file_name;

    if ( file_exists( $path ) ) {
        require_once $path;
    } else {
        // Fallback for classes directly under includes (like Cachilupi_Pet_Plugin)
        // if the path with sub-namespace directories didn't work.
        // This handles CachilupiPet\Cachilupi_Pet_Plugin -> includes/class-cachilupi-pet-plugin.php
        if ( empty( $parts ) ) {
            $direct_path = CACHILUPI_PET_DIR . 'includes/' . $file_name;
            if ( file_exists( $direct_path ) ) {
                require_once $direct_path;
            }
        }
        // Optional: Log or display an error if file not found for debugging purposes
        // else { error_log("Autoloader: File not found for class {$class_name} at path {$path}"); }
    }
}

// The main plugin class will be autoloaded now.
// require_once CACHILUPI_PET_DIR . 'includes/class-cachilupi-pet-plugin.php';

// Register activation hook to call the static activate method.
// Ensure this uses the fully qualified class name if it's namespaced.
// Assuming Cachilupi_Pet_Plugin is now CachilupiPet\Cachilupi_Pet_Plugin
register_activation_hook( __FILE__, array( 'CachilupiPet\\Cachilupi_Pet_Plugin', 'activate' ) );

/**
 * Instantiate the main plugin class and run it.
 *
 * Ensures the main plugin logic is initialized.
 */
function cachilupi_pet_run_plugin() {
    // Ensure this uses the fully qualified class name if it's namespaced.
	$plugin = new \CachilupiPet\Cachilupi_Pet_Plugin();
	$plugin->init();
}
cachilupi_pet_run_plugin();

// =============================================================================
// Funcionalidad de Redirección Post-Login Personalizada (Manejada por Plugin)
// =============================================================================

// This functionality is now handled by CachilupiPet\Users\Cachilupi_Pet_User_Roles class.

// =============================================================================
// Fin Funcionalidad de Redirección Post-Login Personalizada
// =============================================================================


// =============================================================================
// Modificar Mensajes de Error de Login por Defecto - MOVED
// =============================================================================
// This functionality is now handled by CachilupiPet\Users\Cachilupi_Pet_User_Roles class.

// =============================================================================
// Fin Modificar Mensajes de Error de Login por Defecto
// =============================================================================


// =============================================================================
// Función para traducir los estados de las solicitudes - MOVED
// =============================================================================
// This function has been moved to CachilupiPet\Utils\Cachilupi_Pet_Utils::translate_status()

// Shortcode rendering is now handled by CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes

// AJAX Handlers are now managed by \CachilupiPet\Ajax\Cachilupi_Pet_Ajax_Handlers
// - cachilupi_pet_submit_service_request
// - cachilupi_pet_check_new_requests_ajax_handler
// - cachilupi_pet_get_client_requests_status_ajax_handler
// - and others...

// Enqueue scripts and styles are now handled by CachilupiPet\Core\Cachilupi_Pet_Assets_Manager

// Shortcode 'cachilupi_maps' (client booking form) is handled by \CachilupiPet\PublicArea\Cachilupi_Pet_Shortcodes::render_client_booking_form_shortcode()

// Settings Page logic is now handled by CachilupiPet\Admin\Cachilupi_Pet_Settings

/**
 * Carga el text domain del plugin para la traducción.
 */
// function cachilupi_pet_load_textdomain() {
//    load_plugin_textdomain( 'cachilupi-pet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
// }
// add_action( 'plugins_loaded', 'cachilupi_pet_load_textdomain' ); // This is now handled by the class's init method.

// register_activation_hook( __FILE__, 'cachilupi_pet_activate' ); // This is now handled above.

?>
