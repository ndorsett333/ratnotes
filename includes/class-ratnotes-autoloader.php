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

        // Split into directory and class name.
        $parts = explode( DIRECTORY_SEPARATOR, $file_path );
        $class_file = array_pop( $parts );

        // Build the file path with WordPress naming convention.
        $prefix = empty( $parts ) ? 'class-ratnotes-' : 'class-';
        $file = RATNOTES_PLUGIN_DIR . 'includes/' . implode( DIRECTORY_SEPARATOR, $parts ) . '/' . $prefix . strtolower( str_replace( '_', '-', $class_file ) ) . '.php';

        // Load the file if it exists.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
