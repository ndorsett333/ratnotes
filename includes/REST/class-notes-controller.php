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
use WP_Post;

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
		$user_id = get_current_user_id();

		$args = array(
			'post_type'      => 'ratnote',
			'post_status'    => 'any',
			'author'         => $user_id,
			'posts_per_page' => $request->get_param( 'per_page' ),
			'paged'          => $request->get_param( 'page' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(),
		);

		// Filter by status.
		$status = $request->get_param( 'status' );
		if ( 'archived' === $status ) {
			$args['meta_query'][] = array(
				'key'   => 'ratnotes_is_archived',
				'value' => '1',
			);
			$args['meta_query'][] = array(
				'key'   => 'ratnotes_is_trashed',
				'value' => '0',
			);
		} elseif ( 'trash' === $status ) {
			$args['post_status'] = 'trash';
		} else {
			// Active notes: not archived, not trashed.
			$args['meta_query'][] = array(
				'key'   => 'ratnotes_is_archived',
				'value' => '0',
			);
			$args['meta_query'][] = array(
				'key'   => 'ratnotes_is_trashed',
				'value' => '0',
			);
		}

		// Filter by pinned.
		$pinned = $request->get_param( 'pinned' );
		if ( null !== $pinned ) {
			$args['meta_query'][] = array(
				'key'   => 'ratnotes_is_pinned',
				'value' => $pinned ? '1' : '0',
			);
		}

		// Search.
		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Orderby.
		$orderby = $request->get_param( 'orderby' );
		$order   = strtoupper( $request->get_param( 'order' ) );
		$allowed_orderby = array( 'date', 'modified', 'title' );
		if ( in_array( $orderby, $allowed_orderby, true ) ) {
			$args['orderby'] = $orderby;
		}
		if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = $order;
		}

		$query = new \WP_Query( $args );
		$notes = array();

		foreach ( $query->posts as $post ) {
			$notes[] = $this->prepare_item_for_response( $post, $request );
		}

		return new WP_REST_Response( $notes );
	}

	/**
	 * Get one note.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$note_id = $request->get_param( 'id' );
		$post    = get_post( $note_id );

		if ( ! $post || 'ratnote' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		// Check ownership.
		if ( get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->prepare_item_for_response( $post, $request ) );
	}

	/**
	 * Create a note.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user_id = get_current_user_id();

		$post_data = array(
			'post_type'    => 'ratnote',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_title'   => sanitize_text_field( $request->get_param( 'title' ) ),
			'post_content' => wp_kses_post( $request->get_param( 'content' ) ),
		);

		$note_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $note_id ) ) {
			return new WP_Error( 'db_error', __( 'Could not create note.', 'ratnotes' ), array( 'status' => 500 ) );
		}

		// Update meta fields.
		update_post_meta( $note_id, 'ratnotes_color', sanitize_hex_color( $request->get_param( 'color' ) ) );
		update_post_meta( $note_id, 'ratnotes_is_pinned', $request->get_param( 'is_pinned' ) ? 1 : 0 );
		update_post_meta( $note_id, 'ratnotes_is_archived', $request->get_param( 'is_archived' ) ? 1 : 0 );
		update_post_meta( $note_id, 'ratnotes_is_trashed', 0 );
		update_post_meta( $note_id, 'ratnotes_labels', array_map( 'sanitize_text_field', (array) $request->get_param( 'labels' ) ) );

		$post = get_post( $note_id );

		return new WP_REST_Response( $this->prepare_item_for_response( $post, $request ) );
	}

	/**
	 * Update a note.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$note_id = $request->get_param( 'id' );
		$post    = get_post( $note_id );

		if ( ! $post || 'ratnote' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		// Verify ownership.
		if ( get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		$post_data = array(
			'ID' => $note_id,
		);

		if ( $request->has_param( 'title' ) ) {
			$post_data['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}
		if ( $request->has_param( 'content' ) ) {
			$post_data['post_content'] = wp_kses_post( $request->get_param( 'content' ) );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'db_error', __( 'Could not update note.', 'ratnotes' ), array( 'status' => 500 ) );
		}

		// Update meta fields.
		if ( $request->has_param( 'color' ) ) {
			update_post_meta( $note_id, 'ratnotes_color', sanitize_hex_color( $request->get_param( 'color' ) ) );
		}
		if ( $request->has_param( 'is_pinned' ) ) {
			update_post_meta( $note_id, 'ratnotes_is_pinned', $request->get_param( 'is_pinned' ) ? 1 : 0 );
		}
		if ( $request->has_param( 'is_archived' ) ) {
			update_post_meta( $note_id, 'ratnotes_is_archived', $request->get_param( 'is_archived' ) ? 1 : 0 );
		}
		if ( $request->has_param( 'labels' ) ) {
			update_post_meta( $note_id, 'ratnotes_labels', array_map( 'sanitize_text_field', (array) $request->get_param( 'labels' ) ) );
		}

		$post = get_post( $note_id );

		return new WP_REST_Response( $this->prepare_item_for_response( $post, $request ) );
	}

	/**
	 * Delete a note.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$note_id = $request->get_param( 'id' );
		$force   = $request->get_param( 'force' );
		$post    = get_post( $note_id );

		if ( ! $post || 'ratnote' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		// Verify ownership.
		if ( get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		if ( $force ) {
			$result = wp_delete_post( $note_id, true );
		} else {
			// Move to trash and set trashed meta.
			update_post_meta( $note_id, 'ratnotes_is_trashed', 1 );
			update_post_meta( $note_id, 'ratnotes_trashed_at', current_time( 'mysql' ) );
			$result = wp_delete_post( $note_id, false );
		}

		if ( ! $result ) {
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
		$note_id = $request->get_param( 'id' );
		$post    = get_post( $note_id );

		if ( ! $post || 'ratnote' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		// Verify ownership and trashed status.
		if ( get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		$is_trashed = get_post_meta( $note_id, 'ratnotes_is_trashed', true );
		if ( ! $is_trashed && 'trash' !== $post->post_status ) {
			return new WP_Error( 'not_found', __( 'Note not found or not in trash.', 'ratnotes' ), array( 'status' => 404 ) );
		}

		// Restore from trash.
		$result = wp_untrash_post( $note_id );

		if ( ! $result ) {
			return new WP_Error( 'db_error', __( 'Could not restore note.', 'ratnotes' ), array( 'status' => 500 ) );
		}

		// Update meta.
		update_post_meta( $note_id, 'ratnotes_is_trashed', 0 );
		delete_post_meta( $note_id, 'ratnotes_trashed_at' );

		$post = get_post( $note_id );

		return new WP_REST_Response( $this->prepare_item_for_response( $post, $request ) );
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
	 * @param WP_Post         $post    The note post object.
	 * @param WP_REST_Request $request The request object.
	 * @return array
	 */
	public function prepare_item_for_response( $post, $request ) {
		$color       = get_post_meta( $post->ID, 'ratnotes_color', true );
		$is_pinned   = get_post_meta( $post->ID, 'ratnotes_is_pinned', true );
		$is_archived = get_post_meta( $post->ID, 'ratnotes_is_archived', true );
		$is_trashed  = get_post_meta( $post->ID, 'ratnotes_is_trashed', true );
		$labels      = get_post_meta( $post->ID, 'ratnotes_labels', true );

		return array(
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'color'       => $color ? $color : '#ffffff',
			'is_pinned'   => (bool) $is_pinned,
			'is_archived' => (bool) $is_archived,
			'is_trashed'  => (bool) $is_trashed,
			'labels'      => is_array( $labels ) ? $labels : array(),
			'created_at'  => $post->post_date,
			'updated_at'  => $post->post_modified,
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
				'enum'        => array( 'date', 'modified', 'title' ),
				'default'     => 'date',
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
