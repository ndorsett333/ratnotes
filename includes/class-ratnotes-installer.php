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
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'ratnotes';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) DEFAULT '',
            content text DEFAULT '',
            color varchar(50) DEFAULT '#ffffff',
            is_pinned tinyint(1) DEFAULT 0,
            is_archived tinyint(1) DEFAULT 0,
            is_trashed tinyint(1) DEFAULT 0,
            labels varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            trashed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_pinned (is_pinned),
            KEY is_archived (is_archived),
            KEY is_trashed (is_trashed),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Add default options.
        add_option( 'ratnotes_version', RATNOTES_VERSION );
    }
}
