<?php
/**
 * Autoloader for RatNotes plugin.
 *
 * @package RatNotes
 */

namespace RatNotes;

/**
 * Class Autoloader.
 */
class Autoloader {

    /**
     * Register the autoloader.
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes.
     *
     * @param string $class_name The class name to autoload.
     */
    public static function autoload( $class_name ) {
        // Only autoload classes in our namespace.
        if ( 0 !== strpos( $class_name, __NAMESPACE__ . '\\' ) ) {
            return;
        }

        // Remove namespace prefix.
        $relative_class = substr( $class_name, strlen( __NAMESPACE__ ) + 1 );

        // Convert namespace separators to directory separators.
        $file_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );

        // Build the file path.
        $file = RATNOTES_PLUGIN_DIR . 'includes/' . strtolower( str_replace( '_', '-', $file_path ) ) . '.php';

        // Load the file if it exists.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
