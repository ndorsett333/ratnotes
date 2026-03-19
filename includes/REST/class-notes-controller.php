<?php
/**
 * REST API Notes Controller.
 *
 * @package RatNotes
 */

namespace RatNotes\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use global $wpdb;

/**
 * Notes Controller class.
 */
class Notes_Controller extends WP_REST_Controller {

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace = 'ratnotes/v1';

    /**
     * Rest base.
     *
     * @var string
     */
    protected $rest_base = 'notes';

    /**
     * Register routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_item_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => __( 'Unique identifier of the note.', 'ratnotes' ),
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'update_item_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                    'args'                => array(
                        'id'    => array(
                            'required'    => true,
                            'type'        => 'integer',
                            'description' => __( 'Unique identifier of the note.', 'ratnotes' ),
                        ),
                        'force' => array(
                            'default'     => false,
                            'type'        => 'boolean',
                            'description' => __( 'Whether to bypass trash and force deletion.', 'ratnotes' ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/restore',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'restore_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                        'description' => __( 'Unique identifier of the note.', 'ratnotes' ),
                    ),
                ),
            )
        );
    }

    /**
     * Get all notes.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $user_id    = get_current_user_id();

        $where = array( 'user_id = %d' );
        $args  = array( $user_id );

        // Filter by status.
        $status = $request->get_param( 'status' );
        if ( 'archived' === $status ) {
            $where[] = 'is_archived = 1';
        } elseif ( 'trash' === $status ) {
            $where[] = 'is_trashed = 1';
        } else {
            $where[] = 'is_archived = 0';
            $where[] = 'is_trashed = 0';
        }

        // Filter by pinned.
        $pinned = $request->get_param( 'pinned' );
        if ( null !== $pinned ) {
            $where[] = 'is_pinned = %d';
            $args[]  = $pinned ? 1 : 0;
        }

        // Search.
        $search = $request->get_param( 'search' );
        if ( ! empty( $search ) ) {
            $where[] = '(title LIKE %s OR content LIKE %s)';
            $args[]  = '%' . $wpdb->esc_like( $search ) . '%';
            $args[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_clause = implode( ' AND ', $where );

        // Order.
        $orderby = $request->get_param( 'orderby' );
        $order   = strtoupper( $request->get_param( 'order' ) );
        $allowed_orderby = array( 'created_at', 'updated_at', 'title' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'created_at';
        }
        if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
            $order = 'DESC';
        }

        // Pagination.
        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $offset   = ( $page - 1 ) * $per_page;

        $args[] = $per_page;
        $args[] = $offset;

        $sql = "SELECT * FROM $table_name WHERE " . $where_clause . " ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare( $sql, $args );

        $notes = $wpdb->get_results( $sql, ARRAY_A );

        $response = array();
        foreach ( $notes as $note ) {
            $response[] = $this->prepare_item_for_response( $note, $request );
        }

        return new WP_REST_Response( $response );
    }

    /**
     * Get one note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_item( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $note_id    = $request->get_param( 'id' );
        $user_id    = get_current_user_id();

        $note = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
                $note_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $note ) {
            return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
        }

        return new WP_REST_Response( $this->prepare_item_for_response( $note, $request ) );
    }

    /**
     * Create a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function create_item( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $user_id    = get_current_user_id();

        $data = array(
            'user_id'     => $user_id,
            'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
            'content'     => wp_kses_post( $request->get_param( 'content' ) ),
            'color'       => sanitize_hex_color( $request->get_param( 'color' ) ),
            'is_pinned'   => $request->get_param( 'is_pinned' ) ? 1 : 0,
            'is_archived' => $request->get_param( 'is_archived' ) ? 1 : 0,
            'labels'      => implode( ',', array_map( 'sanitize_text_field', (array) $request->get_param( 'labels' ) ) ),
        );

        $result = $wpdb->insert( $table_name, $data );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Could not create note.', 'ratnotes' ), array( 'status' => 500 ) );
        }

        $note_id = $wpdb->insert_id;
        $note    = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $note_id
            ),
            ARRAY_A
        );

        return new WP_REST_Response( $this->prepare_item_for_response( $note, $request ) );
    }

    /**
     * Update a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $note_id    = $request->get_param( 'id' );
        $user_id    = get_current_user_id();

        // Verify ownership.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
                $note_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
        }

        $data = array();

        if ( $request->has_param( 'title' ) ) {
            $data['title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }
        if ( $request->has_param( 'content' ) ) {
            $data['content'] = wp_kses_post( $request->get_param( 'content' ) );
        }
        if ( $request->has_param( 'color' ) ) {
            $data['color'] = sanitize_hex_color( $request->get_param( 'color' ) );
        }
        if ( $request->has_param( 'is_pinned' ) ) {
            $data['is_pinned'] = $request->get_param( 'is_pinned' ) ? 1 : 0;
        }
        if ( $request->has_param( 'is_archived' ) ) {
            $data['is_archived'] = $request->get_param( 'is_archived' ) ? 1 : 0;
        }
        if ( $request->has_param( 'labels' ) ) {
            $data['labels'] = implode( ',', array_map( 'sanitize_text_field', (array) $request->get_param( 'labels' ) ) );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_changes', __( 'No changes to save.', 'ratnotes' ), array( 'status' => 400 ) );
        }

        $result = $wpdb->update( $table_name, $data, array( 'id' => $note_id ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Could not update note.', 'ratnotes' ), array( 'status' => 500 ) );
        }

        $note = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $note_id
            ),
            ARRAY_A
        );

        return new WP_REST_Response( $this->prepare_item_for_response( $note, $request ) );
    }

    /**
     * Delete a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $note_id    = $request->get_param( 'id' );
        $user_id    = get_current_user_id();
        $force      = $request->get_param( 'force' );

        // Verify ownership.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
                $note_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
        }

        if ( $force ) {
            $result = $wpdb->delete( $table_name, array( 'id' => $note_id ) );
        } else {
            // Move to trash.
            $result = $wpdb->update(
                $table_name,
                array(
                    'is_trashed' => 1,
                    'trashed_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $note_id )
            );
        }

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Could not delete note.', 'ratnotes' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'deleted' => true ) );
    }

    /**
     * Restore a trashed note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function restore_item( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ratnotes';
        $note_id    = $request->get_param( 'id' );
        $user_id    = get_current_user_id();

        // Verify ownership and trashed status.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND is_trashed = 1",
                $note_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'Note not found or not in trash.', 'ratnotes' ), array( 'status' => 404 ) );
        }

        $result = $wpdb->update(
            $table_name,
            array(
                'is_trashed' => 0,
                'trashed_at' => null,
            ),
            array( 'id' => $note_id )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Could not restore note.', 'ratnotes' ), array( 'status' => 500 ) );
        }

        $note = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $note_id
            ),
            ARRAY_A
        );

        return new WP_REST_Response( $this->prepare_item_for_response( $note, $request ) );
    }

    /**
     * Check permissions for getting notes.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function get_items_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to view notes.', 'ratnotes' ), array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * Check permissions for getting a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function get_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to view notes.', 'ratnotes' ), array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * Check permissions for creating a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function create_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to create notes.', 'ratnotes' ), array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * Check permissions for updating a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function update_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to edit notes.', 'ratnotes' ), array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * Check permissions for deleting a note.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'You must be logged in to delete notes.', 'ratnotes' ), array( 'status' => 401 ) );
        }
        return true;
    }

    /**
     * Prepare item for response.
     *
     * @param array           $note    The note data.
     * @param WP_REST_Request $request The request object.
     * @return array
     */
    public function prepare_item_for_response( $note, $request ) {
        return array(
            'id'          => (int) $note['id'],
            'title'       => $note['title'],
            'content'     => $note['content'],
            'color'       => $note['color'],
            'is_pinned'   => (bool) $note['is_pinned'],
            'is_archived' => (bool) $note['is_archived'],
            'is_trashed'  => (bool) $note['is_trashed'],
            'labels'      => empty( $note['labels'] ) ? array() : explode( ',', $note['labels'] ),
            'created_at'  => $note['created_at'],
            'updated_at'  => $note['updated_at'],
        );
    }

    /**
     * Get collection params.
     *
     * @return array
     */
    public function get_collection_params() {
        return array(
            'status'   => array(
                'description' => __( 'Filter by status (active, archived, trash).', 'ratnotes' ),
                'type'        => 'string',
                'enum'        => array( 'active', 'archived', 'trash' ),
                'default'     => 'active',
            ),
            'pinned'   => array(
                'description' => __( 'Filter by pinned status.', 'ratnotes' ),
                'type'        => 'boolean',
                'default'     => null,
            ),
            'search'   => array(
                'description' => __( 'Search notes by title or content.', 'ratnotes' ),
                'type'        => 'string',
                'default'     => '',
            ),
            'orderby'  => array(
                'description' => __( 'Sort field.', 'ratnotes' ),
                'type'        => 'string',
                'enum'        => array( 'created_at', 'updated_at', 'title' ),
                'default'     => 'created_at',
            ),
            'order'    => array(
                'description' => __( 'Sort order.', 'ratnotes' ),
                'type'        => 'string',
                'enum'        => array( 'ASC', 'DESC' ),
                'default'     => 'DESC',
            ),
            'page'     => array(
                'description' => __( 'Page number.', 'ratnotes' ),
                'type'        => 'integer',
                'default'     => 1,
                'minimum'     => 1,
            ),
            'per_page' => array(
                'description' => __( 'Items per page.', 'ratnotes' ),
                'type'        => 'integer',
                'default'     => 50,
                'minimum'     => 1,
                'maximum'     => 100,
            ),
        );
    }
}
