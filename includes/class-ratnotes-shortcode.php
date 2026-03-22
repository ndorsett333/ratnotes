<?php
/**
 * Shortcode for displaying notes on the frontend.
 *
 * @package RatNotes
 */

namespace RatNotes;

/**
 * Shortcode class.
 */
class Shortcode {

	/**
	 * Initialize shortcode.
	 */
	public static function init() {
		add_shortcode( 'ratnotes', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ratnotes_get_notes', array( __CLASS__, 'ajax_get_notes' ) );
		add_action( 'wp_ajax_nopriv_ratnotes_get_notes', array( __CLASS__, 'ajax_get_notes' ) );
		add_action( 'wp_ajax_ratnotes_save_note', array( __CLASS__, 'ajax_save_note' ) );
		add_action( 'wp_ajax_nopriv_ratnotes_save_note', array( __CLASS__, 'ajax_save_note' ) );
		add_action( 'wp_ajax_ratnotes_delete_note', array( __CLASS__, 'ajax_delete_note' ) );
		add_action( 'wp_ajax_nopriv_ratnotes_delete_note', array( __CLASS__, 'ajax_delete_note' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'status'   => 'active', // active, archived, trash
				'pinned'   => '',       // true, false, or empty
				'columns'  => '3',      // number of columns
				'search'   => 'true',   // show search box
				'create'   => 'true',   // show create button
				'filter'   => 'true',   // show filter navigation
			),
			$atts,
			'ratnotes'
		);

		// Enqueue assets.
		self::enqueue_assets();

		ob_start();
		?>
		<div class="ratnotes-frontend" data-status="<?php echo esc_attr( $atts['status'] ); ?>" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php if ( 'true' === $atts['filter'] ) : ?>
			<div class="ratnotes-frontend-nav">
				<button class="ratnotes-frontend-nav-item active" data-status="active">
					<span class="dashicons dashicons-admin-notes"></span>
					<?php esc_html_e( 'Notes', 'ratnotes' ); ?>
				</button>
				<button class="ratnotes-frontend-nav-item" data-status="archived">
					<span class="dashicons dashicons-archive"></span>
					<?php esc_html_e( 'Archive', 'ratnotes' ); ?>
				</button>
				<button class="ratnotes-frontend-nav-item" data-status="trash">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Trash', 'ratnotes' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<div class="ratnotes-frontend-header">
				<?php if ( 'true' === $atts['search'] ) : ?>
				<div class="ratnotes-frontend-search">
					<input
						type="search"
						class="ratnotes-frontend-search-input"
						placeholder="<?php esc_attr_e( 'Search notes...', 'ratnotes' ); ?>"
					/>
				</div>
				<?php endif; ?>

				<?php if ( 'true' === $atts['create'] && is_user_logged_in() ) : ?>
				<button class="ratnotes-frontend-create-btn button button-primary">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'New Note', 'ratnotes' ); ?>
				</button>
				<?php endif; ?>
			</div>

			<div class="ratnotes-frontend-grid">
				<div class="ratnotes-frontend-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading notes...', 'ratnotes' ); ?></p>
				</div>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
			<!-- Note Modal -->
			<div class="ratnotes-frontend-modal" style="display: none;">
				<div class="ratnotes-frontend-modal-overlay"></div>
				<div class="ratnotes-frontend-modal-content">
					<div class="ratnotes-frontend-modal-header">
						<input
							type="text"
							class="ratnotes-frontend-note-title"
							placeholder="<?php esc_attr_e( 'Title', 'ratnotes' ); ?>"
						/>
						<button class="ratnotes-frontend-modal-close">
							<span class="dashicons dashicons-no"></span>
						</button>
					</div>
					<div class="ratnotes-frontend-modal-body">
						<textarea
							class="ratnotes-frontend-note-content"
							placeholder="<?php esc_attr_e( 'Take a note...', 'ratnotes' ); ?>"
						></textarea>
					</div>
					<div class="ratnotes-frontend-modal-footer">
						<div class="ratnotes-frontend-color-picker">
							<button class="ratnotes-frontend-color-btn active" data-color="#ffffff" style="background-color: #ffffff;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#f28b82" style="background-color: #f28b82;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#fbbc04" style="background-color: #fbbc04;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#fff475" style="background-color: #fff475;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#ccff90" style="background-color: #ccff90;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#a7ffeb" style="background-color: #a7ffeb;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#cbf0f8" style="background-color: #cbf0f8;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#d0c4ff" style="background-color: #d0c4ff;"></button>
							<button class="ratnotes-frontend-color-btn" data-color="#ffccbc" style="background-color: #ffccbc;"></button>
						</div>
						<div class="ratnotes-frontend-actions">
							<button class="ratnotes-frontend-pin-btn button">
								<span class="dashicons dashicons-pin"></span>
							</button>
							<button class="ratnotes-frontend-archive-btn button">
								<span class="dashicons dashicons-archive"></span>
							</button>
							<button class="ratnotes-frontend-delete-btn button">
								<span class="dashicons dashicons-trash"></span>
							</button>
							<button class="ratnotes-frontend-save-btn button button-primary">
								<?php esc_html_e( 'Close', 'ratnotes' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_style(
			'ratnotes-frontend',
			RATNOTES_PLUGIN_URL . 'frontend/css/frontend.css',
			array(),
			RATNOTES_VERSION
		);

		wp_enqueue_script(
			'ratnotes-frontend',
			RATNOTES_PLUGIN_URL . 'frontend/js/frontend.js',
			array( 'jquery' ),
			RATNOTES_VERSION,
			true
		);

		wp_localize_script(
			'ratnotes-frontend',
			'ratnotesFrontendData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ratnotes_frontend' ),
				'userId'  => get_current_user_id(),
				'isLoggedIn' => is_user_logged_in(),
				'strings' => array(
					'confirmDelete' => __( 'Are you sure you want to delete this note?', 'ratnotes' ),
					'createNote'    => __( 'Create Note', 'ratnotes' ),
					'editNote'      => __( 'Edit Note', 'ratnotes' ),
					'loginRequired' => __( 'You must be logged in to create notes.', 'ratnotes' ),
				),
			)
		);
	}

	/**
	 * AJAX: Get notes.
	 */
	public static function ajax_get_notes() {
		check_ajax_referer( 'ratnotes_frontend', 'nonce' );

		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		$args = array(
			'post_type'      => 'ratnote',
			'post_status'    => 'any',
			'author'         => get_current_user_id(),
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
			),
		);

		// Filter by status.
		if ( 'trash' === $status ) {
			$args['post_status'] = 'trash';
		} elseif ( 'archived' === $status ) {
			$args['meta_query'][] = array(
				'key'     => 'ratnotes_is_archived',
				'value'   => '1',
				'compare' => '=',
			);
			$args['meta_query'][] = array(
				'key'     => 'ratnotes_is_trashed',
				'value'   => '0',
				'compare' => '!=',
			);
		} else {
			// Active: not archived (or meta doesn't exist) AND not trashed (or meta doesn't exist).
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'ratnotes_is_archived',
					'value'   => '0',
					'compare' => '=',
				),
				array(
					'key'     => 'ratnotes_is_archived',
					'compare' => 'NOT EXISTS',
				),
			);
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'ratnotes_is_trashed',
					'value'   => '0',
					'compare' => '=',
				),
				array(
					'key'     => 'ratnotes_is_trashed',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		// Search.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$notes = array();

		foreach ( $query->posts as $post ) {
			$notes[] = array(
				'id'          => (int) $post->ID,
				'title'       => $post->post_title,
				'content'     => $post->post_content,
				'color'       => get_post_meta( $post->ID, 'ratnotes_color', true ) ?: '#ffffff',
				'is_pinned'   => (bool) get_post_meta( $post->ID, 'ratnotes_is_pinned', true ),
				'is_archived' => (bool) get_post_meta( $post->ID, 'ratnotes_is_archived', true ),
				'is_trashed'  => (bool) get_post_meta( $post->ID, 'ratnotes_is_trashed', true ),
				'labels'      => get_post_meta( $post->ID, 'ratnotes_labels', true ) ?: array(),
				'created_at'  => $post->post_date,
				'updated_at'  => $post->post_modified,
			);
		}

		wp_send_json_success( $notes );
	}

	/**
	 * AJAX: Save note.
	 */
	public static function ajax_save_note() {
		check_ajax_referer( 'ratnotes_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ratnotes' ) ) );
		}

		$note_id   = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$title     = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$content   = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
		$color     = isset( $_POST['color'] ) ? sanitize_hex_color( $_POST['color'] ) : '#ffffff';
		$is_pinned = isset( $_POST['is_pinned'] ) ? rest_sanitize_boolean( $_POST['is_pinned'] ) : false;
		$is_archived = isset( $_POST['is_archived'] ) ? rest_sanitize_boolean( $_POST['is_archived'] ) : false;

		if ( $note_id ) {
			// Update existing note.
			$post = get_post( $note_id );
			if ( ! $post || 'ratnote' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
				wp_send_json_error( array( 'message' => __( 'Note not found.', 'ratnotes' ) ) );
			}

			$post_data = array(
				'ID'           => $note_id,
				'post_title'   => $title,
				'post_content' => $content,
			);

			wp_update_post( $post_data );

			update_post_meta( $note_id, 'ratnotes_color', $color );
			update_post_meta( $note_id, 'ratnotes_is_pinned', $is_pinned ? 1 : 0 );
			update_post_meta( $note_id, 'ratnotes_is_archived', $is_archived ? 1 : 0 );
		} else {
			// Create new note.
			$post_data = array(
				'post_type'    => 'ratnote',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_content' => $content,
			);

			$note_id = wp_insert_post( $post_data );

			if ( is_wp_error( $note_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not create note.', 'ratnotes' ) ) );
			}

			update_post_meta( $note_id, 'ratnotes_color', $color );
			update_post_meta( $note_id, 'ratnotes_is_pinned', $is_pinned ? 1 : 0 );
			update_post_meta( $note_id, 'ratnotes_is_archived', $is_archived ? 1 : 0 );
			update_post_meta( $note_id, 'ratnotes_is_trashed', 0 );
		}

		$post = get_post( $note_id );
		wp_send_json_success(
			array(
				'id'          => (int) $post->ID,
				'title'       => $post->post_title,
				'content'     => $post->post_content,
				'color'       => get_post_meta( $post->ID, 'ratnotes_color', true ) ?: '#ffffff',
				'is_pinned'   => (bool) get_post_meta( $post->ID, 'ratnotes_is_pinned', true ),
				'is_archived' => (bool) get_post_meta( $post->ID, 'ratnotes_is_archived', true ),
				'is_trashed'  => (bool) get_post_meta( $post->ID, 'ratnotes_is_trashed', true ),
				'labels'      => get_post_meta( $post->ID, 'ratnotes_labels', true ) ?: array(),
				'created_at'  => $post->post_date,
				'updated_at'  => $post->post_modified,
			)
		);
	}

	/**
	 * AJAX: Delete note.
	 */
	public static function ajax_delete_note() {
		check_ajax_referer( 'ratnotes_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'ratnotes' ) ) );
		}

		$note_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$force   = isset( $_POST['force'] ) ? rest_sanitize_boolean( $_POST['force'] ) : false;

		if ( ! $note_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note ID.', 'ratnotes' ) ) );
		}

		$post = get_post( $note_id );
		if ( ! $post || 'ratnote' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
			wp_send_json_error( array( 'message' => __( 'Note not found.', 'ratnotes' ) ) );
		}

		if ( $force ) {
			wp_delete_post( $note_id, true );
		} else {
			update_post_meta( $note_id, 'ratnotes_is_trashed', 1 );
			update_post_meta( $note_id, 'ratnotes_trashed_at', current_time( 'mysql' ) );
			wp_delete_post( $note_id, false );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}
}
