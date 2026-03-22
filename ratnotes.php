<?php
/**
 * Plugin Name:       RatNotes
 * Plugin URI:        https://example.com/ratnotes
 * Description:       A Google Keep-like note-taking application for WordPress.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nick Dorsett
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ratnotes
 * Domain Path:       /languages
 *
 * @package RatNotes
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'RATNOTES_VERSION', '1.0.0' );
define( 'RATNOTES_PLUGIN_FILE', __FILE__ );
define( 'RATNOTES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RATNOTES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RATNOTES_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once RATNOTES_PLUGIN_DIR . 'includes/class-ratnotes-autoloader.php';
RatNotes\Autoloader::register();

// Main plugin class.
require_once RATNOTES_PLUGIN_DIR . 'includes/class-ratnotes.php';

// Shortcode class.
require_once RATNOTES_PLUGIN_DIR . 'includes/class-ratnotes-shortcode.php';

/**
 * Returns the main instance of the RatNotes plugin.
 *
 * @return RatNotes\Main
 */
function ratnotes(): RatNotes\Main {
    return RatNotes\Main::instance();
}

// Initialize the plugin.
ratnotes();

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'RatNotes\Main', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RatNotes\Main', 'deactivate' ) );
