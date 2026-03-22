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
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_menu_redirect' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'template_include', array( $this, 'load_archive_template' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 100 );
        
        // Initialize frontend shortcode.
        add_action( 'init', array( 'RatNotes\Shortcode', 'init' ) );
    }

    /**
     * Initialize plugin components.
     */
    public function init() {
        // Load text domain for translations.
        load_plugin_textdomain( 'ratnotes', false, dirname( RATNOTES_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Register custom post type for notes.
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x( 'Notes', 'post type general name', 'ratnotes' ),
            'singular_name'      => _x( 'Note', 'post type singular name', 'ratnotes' ),
            'menu_name'          => _x( 'RatNotes', 'admin menu', 'ratnotes' ),
            'add_new'            => _x( 'Add New', 'note', 'ratnotes' ),
            'add_new_item'       => __( 'Add New Note', 'ratnotes' ),
            'edit_item'          => __( 'Edit Note', 'ratnotes' ),
            'new_item'           => __( 'New Note', 'ratnotes' ),
            'view_item'          => __( 'View Note', 'ratnotes' ),
            'search_items'       => __( 'Search Notes', 'ratnotes' ),
            'not_found'          => __( 'No notes found.', 'ratnotes' ),
            'not_found_in_trash' => __( 'No notes found in trash.', 'ratnotes' ),
        );

        $args = array(
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false, // We use our own admin menu page.
            'show_in_rest'    => true,
            'rest_base'       => 'ratnotes',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'hierarchical'    => false,
            'supports'        => array( 'title', 'editor', 'custom-fields' ),
            'has_archive'     => false,
            'rewrite'         => false,
            'query_var'       => false,
        );

        register_post_type( 'ratnote', $args );

        // Register meta fields.
        register_meta( 'post', 'ratnotes_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => '#ffffff',
        ) );

        register_meta( 'post', 'ratnotes_is_pinned', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => false,
        ) );

        register_meta( 'post', 'ratnotes_is_archived', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => false,
        ) );

        register_meta( 'post', 'ratnotes_labels', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_labels' ),
            'show_in_rest'      => array(
                'schema' => array(
                    'type'  => 'array',
                    'items' => array( 'type' => 'string' ),
                ),
            ),
            'single'            => true,
            'default'           => array(),
        ) );
    }

    /**
     * Sanitize labels array.
     *
     * @param mixed $labels The labels to sanitize.
     * @return array
     */
    public function sanitize_labels( $labels ) {
        if ( ! is_array( $labels ) ) {
            return array();
        }
        return array_map( 'sanitize_text_field', $labels );
    }

    /**
     * Add rewrite rules for RatNotes archive page.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^ratnotes-archive/?$', 'index.php?ratnotes_archive=1', 'top' );
        add_rewrite_tag( '%ratnotes_archive%', '1' );
    }

    /**
     * Load archive page template.
     *
     * @param string $template The template to load.
     * @return string
     */
    public function load_archive_template( $template ) {
        if ( get_query_var( 'ratnotes_archive' ) ) {
            // Only allow administrators to access the archive page.
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_redirect( home_url() );
                exit;
            }
            $archive_template = RATNOTES_PLUGIN_DIR . 'templates/archive-page.php';
            if ( file_exists( $archive_template ) ) {
                return $archive_template;
            }
        }
        return $template;
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        // Add menu page with empty callback - redirect handled by admin_init.
        add_menu_page(
            __( 'RatNotes', 'ratnotes' ),
            __( 'RatNotes', 'ratnotes' ),
            'manage_options',
            'ratnotes',
            '__return_empty_string',
            'dashicons-admin-notes',
            30
        );

        // Add submenu page for "All Notes".
        add_submenu_page(
            'ratnotes',
            __( 'All Notes', 'ratnotes' ),
            __( 'All Notes', 'ratnotes' ),
            'manage_options',
            'ratnotes',
            '__return_empty_string'
        );

        // Add submenu page for "Add New" - redirect handled by admin_init.
        add_submenu_page(
            'ratnotes',
            __( 'Add New', 'ratnotes' ),
            __( 'Add New', 'ratnotes' ),
            'manage_options',
            'ratnotes-new',
            '__return_empty_string'
        );
    }

    /**
     * Handle menu page redirect.
     */
    public function handle_menu_redirect() {
        // Redirect ratnotes page to notes list.
        if ( isset( $_GET['page'] ) && 'ratnotes' === $_GET['page'] ) {
            wp_redirect( admin_url( 'edit.php?post_type=ratnote' ) );
            exit;
        }

        // Redirect ratnotes-new page to new note editor.
        if ( isset( $_GET['page'] ) && 'ratnotes-new' === $_GET['page'] ) {
            wp_redirect( admin_url( 'post-new.php?post_type=ratnote' ) );
            exit;
        }
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Only load on note post type pages.
        if ( 'edit.php' !== $hook_suffix && 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
            return;
        }

        // Check if we're on the ratnote post type.
        $screen = get_current_screen();
        if ( 'ratnote' !== $screen->post_type ) {
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
                'root'    => esc_url_raw( rest_url() ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'userId'  => get_current_user_id(),
                'strings' => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this note?', 'ratnotes' ),
                    'createNote'    => __( 'Create Note', 'ratnotes' ),
                    'editNote'      => __( 'Edit Note', 'ratnotes' ),
                ),
            )
        );
    }

    /**
     * Activation hook.
     */
    public static function activate() {
        // Notes are stored as custom post types, no custom tables needed.
        // Flush rewrite rules for the CPT and archive page.
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Add archive page link to admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
     */
    public function add_admin_bar_link( $wp_admin_bar ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $wp_admin_bar->add_node(
            array(
                'id'    => 'ratnotes-archive',
                'title' => '<span class="dashicons dashicons-admin-notes"></span> ' . __( 'My Notes', 'ratnotes' ),
                'href'  => home_url( '/ratnotes-archive' ),
                'meta'  => array(
                    'class' => 'ratnotes-admin-bar-link',
                ),
            )
        );
    }
}
