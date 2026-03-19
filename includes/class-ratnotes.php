<?php
/**
 * Main plugin class.
 *
 * @package RatNotes
 */

namespace RatNotes;

/**
 * Main class.
 */
class Main {

    /**
     * Single instance of the class.
     *
     * @var Main
     */
    private static $instance = null;

    /**
     * Get instance of the class.
     *
     * @return Main
     */
    public static function instance(): Main {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Initialize plugin components.
     */
    public function init() {
        // Load text domain for translations.
        load_plugin_textdomain( 'ratnotes', false, dirname( RATNOTES_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'RatNotes', 'ratnotes' ),
            __( 'RatNotes', 'ratnotes' ),
            'manage_options',
            'ratnotes',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-notes',
            30
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( 'toplevel_page_ratnotes' !== $hook_suffix ) {
            return;
        }

        // Enqueue styles.
        wp_enqueue_style(
            'ratnotes-admin',
            RATNOTES_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            RATNOTES_VERSION
        );

        // Enqueue scripts.
        wp_enqueue_script(
            'ratnotes-admin',
            RATNOTES_PLUGIN_URL . 'admin/js/admin.js',
            array( 'wp-api', 'wp-i18n' ),
            RATNOTES_VERSION,
            true
        );

        // Localize script with data.
        wp_localize_script(
            'ratnotes-admin',
            'ratnotesData',
            array(
                'root'   => esc_url_raw( rest_url() ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
                'strings' => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this note?', 'ratnotes' ),
                    'createNote'    => __( 'Create Note', 'ratnotes' ),
                    'editNote'      => __( 'Edit Note', 'ratnotes' ),
                ),
            )
        );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        include_once RATNOTES_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        $controller = new REST\Notes_Controller();
        $controller->register_routes();
    }

    /**
     * Activation hook.
     */
    public static function activate() {
        // Create database tables.
        Installer::install();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();
    }
}
