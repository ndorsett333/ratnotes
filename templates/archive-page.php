<?php
/**
 * RatNotes Archive Page Template
 *
 * This is a standalone template that bypasses WordPress theme templates.
 *
 * @package RatNotes
 */

// Prevent direct access without proper routing.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current user.
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

// Enqueue assets.
wp_enqueue_style( 'dashicons' );

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
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'ratnotes_frontend' ),
		'userId'     => $current_user->ID,
		'isLoggedIn' => $is_logged_in,
		'strings'    => array(
			'confirmDelete' => __( 'Are you sure you want to delete this note?', 'ratnotes' ),
			'createNote'    => __( 'Create Note', 'ratnotes' ),
			'editNote'      => __( 'Edit Note', 'ratnotes' ),
			'loginRequired' => __( 'You must be logged in to create notes.', 'ratnotes' ),
		),
	)
);

// Get site info.
$site_name = get_bloginfo( 'name' );
$site_url  = home_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="<?php esc_attr_e( 'RatNotes', 'ratnotes' ); ?>">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( RATNOTES_PLUGIN_URL . 'frontend/icons/apple-touch-icon-180.png' ); ?>">
	<title><?php esc_html_e( 'RatNotes Archive', 'ratnotes' ); ?> - <?php echo esc_html( $site_name ); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Minimal reset and base styles for standalone page */
		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background-color: #12121f;
			min-height: 100vh;
		}

		.ratnotes-archive-wrapper {
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}

		/* Header Bar */
		.ratnotes-archive-header {
			background-color: #1e1e2e;
			border-bottom: 1px solid #45475a;
			padding: 15px 30px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.ratnotes-archive-logo {
			display: flex;
			align-items: center;
			gap: 10px;
			color: #cdd6f4;
			font-size: 20px;
			font-weight: 600;
			text-decoration: none;
		}

		.ratnotes-archive-logo .dashicons {
			font-size: 28px;
			width: 28px;
			height: 28px;
			color: #89b4fa;
		}

		.ratnotes-archive-nav {
			display: flex;
			gap: 15px;
			align-items: center;
		}

		.ratnotes-archive-nav a {
			color: #a6adc8;
			text-decoration: none;
			padding: 8px 16px;
			border-radius: 6px;
			transition: background-color 0.2s, color 0.2s;
		}

		.ratnotes-archive-nav a:hover {
			background-color: #313244;
			color: #cdd6f4;
		}

		.ratnotes-archive-login {
			background-color: #89b4fa;
			color: #1e1e2e !important;
			font-weight: 500;
		}

		.ratnotes-archive-login:hover {
			background-color: #b4befe !important;
		}

		/* Main Content */
		.ratnotes-archive-main {
			flex: 1;
			padding: 30px;
		}

		.ratnotes-archive-title {
			color: #cdd6f4;
			font-size: 28px;
			font-weight: 600;
			margin: 0 0 25px;
			padding-left: 30px;
		}

		/* Login Notice */
		.ratnotes-archive-notice {
			background-color: #313244;
			border: 1px solid #45475a;
			border-radius: 8px;
			padding: 30px;
			text-align: center;
			max-width: 500px;
			margin: 40px auto;
		}

		.ratnotes-archive-notice h3 {
			color: #cdd6f4;
			margin: 0 0 10px;
			font-size: 20px;
		}

		.ratnotes-archive-notice p {
			color: #a6adc8;
			margin: 0 0 20px;
		}

		.ratnotes-archive-notice .button {
			background-color: #89b4fa;
			color: #1e1e2e;
			border: none;
			padding: 10px 24px;
			font-size: 14px;
			text-decoration: none;
			display: inline-block;
			border-radius: 6px;
			cursor: pointer;
		}

		.ratnotes-archive-notice .button:hover {
			background-color: #b4befe;
		}

		/* Footer */
		.ratnotes-archive-footer {
			background-color: #1e1e2e;
			border-top: 1px solid #45475a;
			padding: 20px 30px;
			text-align: center;
			color: #6c7086;
			font-size: 13px;
		}

		.ratnotes-archive-footer a {
			color: #89b4fa;
			text-decoration: none;
		}

		.ratnotes-archive-footer a:hover {
			text-decoration: underline;
		}

		@media (max-width: 480px) {
			.ratnotes-archive-header {
				flex-direction: column;
				align-items: flex-start;
				gap: 12px;
				padding: 14px 16px;
			}

			.ratnotes-archive-logo {
				font-size: 18px;
			}

			.ratnotes-archive-logo .dashicons {
				font-size: 24px;
				width: 24px;
				height: 24px;
			}

			.ratnotes-archive-nav {
				width: 100%;
				justify-content: space-between;
				gap: 8px;
			}

			.ratnotes-archive-nav a {
				padding: 8px 10px;
				text-align: center;
			}

			.ratnotes-archive-main {
				padding-left: 0;
				padding-right: 0;
			}

			.ratnotes-archive-title {
				padding-left: 16px;
				padding-right: 16px;
			}
		}
	</style>
</head>
<body>
	<div class="ratnotes-archive-wrapper">
		<header class="ratnotes-archive-header">
			<a href="<?php echo esc_url( $site_url ); ?>" class="ratnotes-archive-logo">
				<span class="dashicons dashicons-admin-notes"></span>
				<?php esc_html_e( 'RatNotes', 'ratnotes' ); ?>
			</a>
			<nav class="ratnotes-archive-nav">
				<a href="<?php echo esc_url( $site_url ); ?>">
					<?php esc_html_e( 'Back to Site', 'ratnotes' ); ?>
				</a>
				<?php if ( $is_logged_in ) : ?>
					<a href="<?php echo esc_url( wp_logout_url( $site_url ) ); ?>">
						<?php esc_html_e( 'Log Out', 'ratnotes' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url( $site_url ) ); ?>" class="ratnotes-archive-login">
						<?php esc_html_e( 'Log In', 'ratnotes' ); ?>
					</a>
				<?php endif; ?>
			</nav>
		</header>

		<main class="ratnotes-archive-main">
			<h1 class="ratnotes-archive-title"><?php esc_html_e( 'My Notes', 'ratnotes' ); ?></h1>

			<?php if ( ! $is_logged_in ) : ?>
				<div class="ratnotes-archive-notice">
					<h3><?php esc_html_e( 'Please log in to view your notes', 'ratnotes' ); ?></h3>
					<p><?php esc_html_e( 'You need to be logged in to access your personal notes archive.', 'ratnotes' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( $site_url ) ); ?>" class="button">
						<?php esc_html_e( 'Log In', 'ratnotes' ); ?>
					</a>
				</div>
			<?php else : ?>
				<!-- Notes Grid Container -->
				<div class="ratnotes-frontend" data-status="active" data-columns="3">
					<div class="ratnotes-frontend-sidebar-overlay"></div>
					<aside class="ratnotes-frontend-sidebar" aria-label="<?php esc_attr_e( 'Categories', 'ratnotes' ); ?>">
						<div class="ratnotes-frontend-sidebar-header">
							<h3><?php esc_html_e( 'Categories', 'ratnotes' ); ?></h3>
							<button type="button" class="ratnotes-frontend-sidebar-close" aria-label="<?php esc_attr_e( 'Close categories', 'ratnotes' ); ?>">
								<span class="dashicons dashicons-no"></span>
							</button>
						</div>
						<div class="ratnotes-frontend-category-create-wrap">
							<form class="ratnotes-frontend-category-create-form">
								<input type="text" class="ratnotes-frontend-category-create-input" placeholder="<?php esc_attr_e( 'New category name...', 'ratnotes' ); ?>" maxlength="100" />
								<button type="submit" class="ratnotes-frontend-category-create-submit button button-primary">
									<span class="dashicons dashicons-plus"></span>
								</button>
							</form>
							<div class="ratnotes-frontend-category-create-error" style="display:none;"></div>
						</div>
						<nav class="ratnotes-frontend-category-list"></nav>
					</aside>

					<div class="ratnotes-frontend-nav">
						<button class="ratnotes-frontend-nav-item active" data-status="active">
						<span class="dashicons dashicons-admin-page"></span>
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

					<div class="ratnotes-frontend-header">
						<button type="button" class="ratnotes-frontend-menu-toggle button" aria-label="<?php esc_attr_e( 'Open categories', 'ratnotes' ); ?>">
							<span class="dashicons dashicons-menu"></span>
						</button>
						<div class="ratnotes-frontend-search">
							<input
								type="search"
								class="ratnotes-frontend-search-input"
								placeholder="<?php esc_attr_e( 'Search notes...', 'ratnotes' ); ?>"
							/>
						</div>
						<button class="ratnotes-frontend-create-btn button button-primary">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'New Note', 'ratnotes' ); ?>
						</button>
					</div>

					<div class="ratnotes-frontend-selected-category-wrap">
						<div class="ratnotes-frontend-selected-category" style="display: none;"></div>
					<button type="button" class="ratnotes-frontend-category-create-btn button button-primary" style="display: none;">
						<span class="dashicons dashicons-plus"></span>
						<span class="ratnotes-frontend-category-create-text"></span>
					</button>
				</div>

					<div class="ratnotes-frontend-grid">
						<div class="ratnotes-frontend-loading">
							<span class="spinner is-active"></span>
							<p><?php esc_html_e( 'Loading notes...', 'ratnotes' ); ?></p>
						</div>
					</div>

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
								<div class="ratnotes-frontend-actions">
									<button class="ratnotes-frontend-pin-btn button">
										<span class="dashicons dashicons-admin-post"></span>
									</button>
									<button class="ratnotes-frontend-archive-btn button">
										<span class="dashicons dashicons-archive"></span>
									</button>
									<button class="ratnotes-frontend-delete-btn button">
										<span class="dashicons dashicons-trash"></span>
									</button>
									<div class="ratnotes-frontend-category-picker">
										<button type="button" class="ratnotes-frontend-category-trigger button" aria-haspopup="true" aria-expanded="false">
											<span class="dashicons dashicons-category"></span>
											<span class="ratnotes-frontend-category-trigger-text"><?php esc_html_e( 'Categories', 'ratnotes' ); ?></span>
										</button>
										<div class="ratnotes-frontend-category-menu"></div>
									</div>
									<button class="ratnotes-frontend-save-btn button button-primary">
										<?php esc_html_e( 'Close', 'ratnotes' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</main>

		<footer class="ratnotes-archive-footer">
			<p>
				<?php
				printf(
					/* translators: %s: RatNotes link */
					esc_html__( 'Powered by %s', 'ratnotes' ),
					'<a href="https://example.com/ratnotes" target="_blank" rel="noopener">RatNotes</a>'
				);
				?>
			</p>
		</footer>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
