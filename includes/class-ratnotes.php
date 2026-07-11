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
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'serve_service_worker' ), 0 );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_menu_redirect' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'template_include', array( $this, 'load_archive_template' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 100 );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'manage_ratnote_posts_columns', array( $this, 'add_status_column' ) );
        add_action( 'manage_ratnote_posts_custom_column', array( $this, 'render_status_column' ), 10, 2 );
        add_filter( 'views_edit-ratnote', array( $this, 'add_status_views' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_ratnote_admin_list' ) );
        
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
     * Register REST API routes.
     */
    public function register_rest_routes() {
        $controller = new REST\Notes_Controller();
        $controller->register_routes();
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
        register_meta( 'post', 'ratnotes_is_pinned', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_boolean_string' ),
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => '0',
        ) );

        register_meta( 'post', 'ratnotes_is_archived', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_boolean_string' ),
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => '0',
        ) );

        register_meta( 'post', 'ratnotes_is_trashed', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_boolean_string' ),
            'show_in_rest'      => true,
            'single'            => true,
            'default'           => '0',
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
     * Register category taxonomy for notes.
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x( 'Categories', 'taxonomy general name', 'ratnotes' ),
            'singular_name'     => _x( 'Category', 'taxonomy singular name', 'ratnotes' ),
            'search_items'      => __( 'Search Categories', 'ratnotes' ),
            'all_items'         => __( 'All Categories', 'ratnotes' ),
            'parent_item'       => __( 'Parent Category', 'ratnotes' ),
            'parent_item_colon' => __( 'Parent Category:', 'ratnotes' ),
            'edit_item'         => __( 'Edit Category', 'ratnotes' ),
            'update_item'       => __( 'Update Category', 'ratnotes' ),
            'add_new_item'      => __( 'Add New Category', 'ratnotes' ),
            'new_item_name'     => __( 'New Category Name', 'ratnotes' ),
            'menu_name'         => __( 'Categories', 'ratnotes' ),
        );

        register_taxonomy(
            'ratnote_category',
            array( 'ratnote' ),
            array(
                'hierarchical'      => true,
                'labels'            => $labels,
                'show_ui'           => true,
                'show_in_menu'      => false,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'query_var'         => true,
                'rewrite'           => false,
            )
        );
    }

    /**
     * Sanitize boolean to string '0' or '1'.
     *
     * @param mixed $value The value to sanitize.
     * @return string '0' or '1'
     */
    public function sanitize_boolean_string( $value ) {
        return rest_sanitize_boolean( $value ) ? '1' : '0';
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
     * Serve the service worker JavaScript from a stable root URL.
     */
    public function serve_service_worker() {
        if ( ! isset( $_GET['ratnotes_sw'] ) ) {
            return;
        }

        $sw_file = RATNOTES_PLUGIN_DIR . 'frontend/sw.js';
        if ( ! file_exists( $sw_file ) ) {
            status_header( 404 );
            exit;
        }

        $sw_content = file_get_contents( $sw_file );
        if ( false === $sw_content ) {
            status_header( 500 );
            exit;
        }

        $plugin_base_path = parse_url( RATNOTES_PLUGIN_URL, PHP_URL_PATH );
        if ( empty( $plugin_base_path ) ) {
            $plugin_base_path = '/wp-content/plugins/ratnotes/';
        }

        if ( '/' !== substr( $plugin_base_path, -1 ) ) {
            $plugin_base_path .= '/';
        }

        $offline_html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RatNotes Offline</title><style>body{margin:0;padding:24px;background:#12121f;color:#cdd6f4;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif}.card{max-width:520px;margin:10vh auto;background:#1e1e2e;border:1px solid #45475a;border-radius:10px;padding:20px}h1{margin:0 0 8px;font-size:22px}p{margin:0;color:#a6adc8;line-height:1.5}</style></head><body><div class="card"><h1>Offline</h1><p>RatNotes cannot reach the network right now. Reconnect to load fresh notes.</p></div></body></html>';

        $sw_content = str_replace(
            array( '__RATNOTES_CACHE_NAME__', '__RATNOTES_ARCHIVE_PATH__', '__RATNOTES_PLUGIN_BASE_PATH__', '__RATNOTES_OFFLINE_HTML__' ),
            array(
                'ratnotes-cache-v' . RATNOTES_VERSION,
                '/ratnotes-archive/',
                $plugin_base_path,
                wp_json_encode( $offline_html )
            ),
            $sw_content
        );

        header( 'Content-Type: application/javascript; charset=UTF-8' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'Service-Worker-Allowed: /ratnotes-archive/' );
        echo $sw_content;
        exit;
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

            // Add submenu page for "Categories".
            add_submenu_page(
                'ratnotes',
                __( 'Categories', 'ratnotes' ),
                __( 'Categories', 'ratnotes' ),
                'manage_categories',
                'edit-tags.php?taxonomy=ratnote_category&post_type=ratnote'
            );

        // Add submenu page for "Archive" - redirect handled by admin_init.
        add_submenu_page(
            'ratnotes',
            __( 'Archive', 'ratnotes' ),
            __( 'Archive', 'ratnotes' ),
            'manage_options',
            'ratnotes-archive',
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

        // Redirect ratnotes-archive page to frontend archive URL.
        if ( isset( $_GET['page'] ) && 'ratnotes-archive' === $_GET['page'] ) {
            wp_redirect( home_url( '/ratnotes-archive' ) );
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
                    'confirmDeleteForever' => __( 'Are you sure you want to delete? This will delete this note forever.', 'ratnotes' ),
                    'createNote'    => __( 'Create Note', 'ratnotes' ),
                    'editNote'      => __( 'Edit Note', 'ratnotes' ),
                ),
            )
        );
    }

    /**
     * Add custom Status column to RatNotes admin list.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_status_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            if ( 'title' === $key ) {
                $new_columns['ratnotes_status'] = __( 'Status', 'ratnotes' );
            }
        }

        if ( ! isset( $new_columns['ratnotes_status'] ) ) {
            $new_columns['ratnotes_status'] = __( 'Status', 'ratnotes' );
        }

        return $new_columns;
    }

    /**
     * Render custom Status column content.
     *
     * @param string $column  Column key.
     * @param int    $post_id Current post ID.
     */
    public function render_status_column( $column, $post_id ) {
        if ( 'ratnotes_status' !== $column ) {
            return;
        }

        $is_archived = get_post_meta( $post_id, 'ratnotes_is_archived', true );
        echo esc_html( $is_archived ? __( 'Archived', 'ratnotes' ) : __( 'Active', 'ratnotes' ) );
    }

    /**
     * Add custom Active/Archived views to RatNotes admin list.
     *
     * @param array $views Existing view links.
     * @return array
     */
    public function add_status_views( $views ) {
        $selected = isset( $_GET['ratnotes_status'] ) ? sanitize_key( wp_unslash( $_GET['ratnotes_status'] ) ) : 'active';
        $base_url = admin_url( 'edit.php?post_type=ratnote' );

        $active_count   = $this->count_ratnotes_by_archived( false );
        $archived_count = $this->count_ratnotes_by_archived( true );

        $views['ratnotes_active'] = sprintf(
            '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
            esc_url( add_query_arg( 'ratnotes_status', 'active', $base_url ) ),
            'active' === $selected ? ' class="current" aria-current="page"' : '',
            esc_html__( 'Active', 'ratnotes' ),
            (int) $active_count
        );

        $views['ratnotes_archived'] = sprintf(
            '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
            esc_url( add_query_arg( 'ratnotes_status', 'archived', $base_url ) ),
            'archived' === $selected ? ' class="current" aria-current="page"' : '',
            esc_html__( 'Archived', 'ratnotes' ),
            (int) $archived_count
        );

        return $views;
    }

    /**
     * Filter RatNotes admin list by custom status.
     *
     * Defaults to Active notes unless Archived is selected.
     *
     * @param \WP_Query $query Main query instance.
     */
    public function filter_ratnote_admin_list( $query ) {
        global $pagenow;

        if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow ) {
            return;
        }

        if ( 'ratnote' !== $query->get( 'post_type' ) ) {
            return;
        }

        // Do not override native trash view.
        if ( 'trash' === $query->get( 'post_status' ) ) {
            return;
        }

        $selected = isset( $_GET['ratnotes_status'] ) ? sanitize_key( wp_unslash( $_GET['ratnotes_status'] ) ) : 'active';

        $status_query = array();
        if ( 'archived' === $selected ) {
            $status_query[] = array(
                'key'   => 'ratnotes_is_archived',
                'value' => '1',
            );
        } else {
            $status_query[] = array(
                'relation' => 'OR',
                array(
                    'key'   => 'ratnotes_is_archived',
                    'value' => '0',
                ),
                array(
                    'key'     => 'ratnotes_is_archived',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $existing_meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $existing_meta_query ) ) {
            $existing_meta_query = array();
        }

        $meta_query = array( 'relation' => 'AND' );
        if ( ! empty( $existing_meta_query ) ) {
            $meta_query[] = $existing_meta_query;
        }
        $meta_query[] = $status_query[0];

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Count RatNotes by archived state for admin view links.
     *
     * @param bool $is_archived Whether to count archived notes.
     * @return int
     */
    private function count_ratnotes_by_archived( $is_archived ) {
        $args = array(
            'post_type'      => 'ratnote',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(),
        );

        if ( $is_archived ) {
            $args['meta_query'][] = array(
                'key'   => 'ratnotes_is_archived',
                'value' => '1',
            );
        } else {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'   => 'ratnotes_is_archived',
                    'value' => '0',
                ),
                array(
                    'key'     => 'ratnotes_is_archived',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $query = new \WP_Query( $args );
        return (int) $query->found_posts;
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
