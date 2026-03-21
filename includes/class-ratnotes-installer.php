<?php
/**
 * Plugin installer/database setup.
 *
 * @package RatNotes
 */

namespace RatNotes;

/**
 * Installer class.
 */
class Installer {

    /**
     * Install plugin database tables.
     */
    public static function install() {
        // Notes are stored as custom post types, no custom tables needed.
        // Add default options.
        add_option( 'ratnotes_version', RATNOTES_VERSION );
    }
}
