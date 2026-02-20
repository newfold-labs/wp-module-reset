<?php

namespace NewfoldLabs\WP\Module\Reset\Admin;

use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;
use NewfoldLabs\WP\Module\Reset\Permissions;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;

/**
 * Standalone admin page under Tools > Factory Reset Website.
 *
 * PHP-rendered (not React SPA) so the destructive operation works even if JS assets fail.
 */
class ToolsPage {

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'nfd_factory_reset_action';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'nfd_factory_reset_nonce';

	/**
	 * Get the page slug (computed at runtime from the brand ID).
	 *
	 * @return string
	 */
	public static function get_slug() {
		return BrandConfig::get_page_slug();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'handle_submission' ) );
	}

	/**
	 * Register the admin page under Tools.
	 */
	public function register_page() {
		add_submenu_page(
			'tools.php',
			__( 'Factory Reset Website', 'wp-module-reset' ),
			__( 'Factory Reset Website', 'wp-module-reset' ),
			'manage_options',
			self::get_slug(),
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle form submission and phase routing on admin_init.
	 *
	 * The reset runs in two HTTP requests:
	 * - Phase 1 (POST): Prepare environment, deactivate plugins, redirect.
	 * - Phase 2 (GET):  Execute destructive reset in a clean environment.
	 */
	public function handle_submission() {
		if ( ! $this->is_reset_page_request() ) {
			return;
		}

		// Phase 2: clean GET request after plugins have been deactivated.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['nfd_reset_phase'] ) && '2' === $_GET['nfd_reset_phase'] ) {
			$this->handle_phase2();
			return;
		}

		// Phase 1: POST form submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$this->handle_phase1();
	}

	/**
	 * Phase 1: Validate form, prepare environment, redirect to Phase 2.
	 *
	 * Runs while all plugins are still loaded. Uses raw header() redirect
	 * because wp_safe_redirect() fires the allowed_redirect_hosts filter
	 * which third-party plugins hook into — and those hooks can fatal.
	 */
	private function handle_phase1() {
		if ( ! $this->verify_nonce() ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'wp-module-reset' ),
				esc_html__( 'Error', 'wp-module-reset' ),
				array( 'response' => 403 )
			);
		}

		if ( ! $this->current_user_can_reset() ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wp-module-reset' ),
				esc_html__( 'Error', 'wp-module-reset' ),
				array( 'response' => 403 )
			);
		}

		$confirmation_valid = $this->is_confirmation_url_valid(
			isset( $_POST['confirmation_url'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_url'] ) ) : ''
		);

		if ( ! $confirmation_valid ) {
			set_transient( 'nfd_reset_error', __( 'The URL you entered does not match your website URL. Please try again.', 'wp-module-reset' ), 60 );
			return;
		}

		// Buffer output so stray warnings don't break the redirect.
		ob_start();

		// Run Phase 1: preserve values, install theme, deactivate plugins,
		// remove MU plugins and drop-ins.
		$preparation = ResetService::prepare();

		ob_end_clean();

		if ( ! $preparation['success'] ) {
			$error_msg = ! empty( $preparation['errors'] )
				? implode( ' ', $preparation['errors'] )
				: __( 'Failed to prepare for reset.', 'wp-module-reset' );
			set_transient( 'nfd_reset_error', $error_msg, 60 );
			return;
		}

		// Store Phase 1 data for Phase 2 to pick up on the next request.
		set_transient(
			'nfd_reset_phase2',
			array(
				'data'  => $preparation['data'],
				'steps' => $preparation['steps'],
			),
			300
		);

		// Raw header redirect — NOT wp_safe_redirect() — because third-party
		// plugins may still have hooks on allowed_redirect_hosts that would
		// fatal now that their files might be partially cleaned up.
		$phase2_url = admin_url( 'admin.php?page=' . self::get_slug() . '&nfd_reset_phase=2' );
		header( 'Location: ' . $phase2_url, true, 302 );
		exit;
	}

	/**
	 * Phase 2: Execute the destructive reset in a clean environment.
	 *
	 * Runs on a fresh GET request where only the brand plugin is active.
	 * No third-party hooks, autoloaders, or shutdown handlers can interfere.
	 */
	private function handle_phase2() {
		if ( ! $this->current_user_can_reset() ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wp-module-reset' ),
				esc_html__( 'Error', 'wp-module-reset' ),
				array( 'response' => 403 )
			);
		}

		// Retrieve Phase 1 data.
		$phase2_data = get_transient( 'nfd_reset_phase2' );
		delete_transient( 'nfd_reset_phase2' );

		if ( ! $phase2_data || empty( $phase2_data['data'] ) ) {
			set_transient( 'nfd_reset_error', __( 'Reset session expired or was not found. Please try again.', 'wp-module-reset' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::get_slug() ) );
			exit;
		}

		// Buffer output during the destructive phase.
		ob_start();

		// Execute Phase 2: delete files, reset database, restore values.
		$result = ResetService::execute( $phase2_data['data'], $phase2_data['steps'] );

		ob_end_clean();

		// Store result for the results page.
		set_transient( 'nfd_reset_result', $result, 300 );

		// Safe to use wp_safe_redirect here — no third-party plugins are loaded.
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::get_slug() . '&reset_complete=1' ) );
		exit;
	}

	/**
	 * Determine whether the current request targets the reset tools page.
	 *
	 * @return bool
	 */
	private function is_reset_page_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && self::get_slug() === $_GET['page'];
	}

	/**
	 * Verify the reset nonce from the request.
	 *
	 * @return bool
	 */
	private function verify_nonce() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_nonce = wp_unslash( $_POST[ self::NONCE_NAME ] );

		return wp_verify_nonce( sanitize_text_field( $raw_nonce ), self::NONCE_ACTION );
	}

	/**
	 * Check whether the current user is allowed to run a factory reset.
	 *
	 * @return bool
	 */
	private function current_user_can_reset() {
		return Permissions::is_admin();
	}

	/**
	 * Validate the user-provided confirmation URL against the site URL.
	 *
	 * @param string $confirmation_url Raw URL provided by the user.
	 * @return bool
	 */
	private function is_confirmation_url_valid( $confirmation_url ) {
		$expected_url  = untrailingslashit( home_url() );
		$submitted_url = untrailingslashit( $confirmation_url );

		return $expected_url === $submitted_url;
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! Permissions::is_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-module-reset' ) );
		}

		// Check if we're showing results.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show_results = isset( $_GET['reset_complete'] ) && '1' === $_GET['reset_complete'];
		$result       = $show_results ? get_transient( 'nfd_reset_result' ) : false;

		if ( $show_results && $result ) {
			delete_transient( 'nfd_reset_result' );
			$this->render_results( $result );
			return;
		}

		// Check for errors.
		$error = get_transient( 'nfd_reset_error' );
		if ( $error ) {
			delete_transient( 'nfd_reset_error' );
		}

		$this->render_confirmation( $error );
	}

	/**
	 * Render the confirmation screen.
	 *
	 * @param string|false $error Error message to display, or false.
	 */
	private function render_confirmation( $error = false ) {
		$home_url = untrailingslashit( home_url() );
		?>
		<div class="wrap">
			<style>
				.nfd-reset-page {
					max-width: 900px;
					margin: 0 auto;
					padding: 2rem;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				}
				.nfd-reset-page h1 {
					font-size: 1.5rem;
					font-weight: 700;
					margin: 0 0 0.5rem;
				}
				.nfd-reset-page .nfd-reset-subtitle {
					color: #646970;
					font-size: 14px;
					margin: 0 0 2rem;
				}
				.nfd-reset-warning {
					background: #fff;
					border: 1px solid #dc3232;
					border-left-width: 4px;
					border-radius: 4px;
					padding: 24px;
					margin: 0 0 24px;
				}
				.nfd-reset-warning h2 {
					color: #dc3232;
					font-size: 1rem;
					font-weight: 600;
					margin: 0 0 12px;
				}
				.nfd-reset-warning p {
					font-size: 14px;
					color: #1e1e1e;
					line-height: 1.6;
					margin: 0 0 8px;
				}
				.nfd-reset-warning ul {
					list-style: disc;
					padding-left: 20px;
					margin: 8px 0 12px;
				}
				.nfd-reset-warning ul li {
					font-size: 14px;
					color: #1e1e1e;
					margin-bottom: 6px;
					line-height: 1.5;
				}
				.nfd-reset-warning .nfd-reset-preserved {
					color: #646970;
					font-size: 13px;
					margin-top: 12px;
				}
				.nfd-reset-confirm-field {
					margin: 0 0 24px;
				}
				.nfd-reset-confirm-field label {
					display: block;
					font-weight: 600;
					font-size: 14px;
					margin-bottom: 8px;
				}
				.nfd-reset-confirm-field input[type="text"] {
					width: 100%;
					max-width: 420px;
					padding: 10px 12px;
					font-size: 14px;
					border: 1px solid #8c8f94;
					border-radius: 4px;
					box-shadow: none;
				}
.nfd-reset-confirm-field code {
					background: #f0f0f1;
					padding: 2px 6px;
					font-size: 13px;
					border-radius: 2px;
				}
				.nfd-reset-submit {
					background: #d63638 !important;
					border-color: #d63638 !important;
					color: #fff !important;
					padding: 8px 24px !important;
					font-size: 14px !important;
					font-weight: 500 !important;
					border-radius: 4px !important;
					cursor: pointer;
					line-height: 1.5 !important;
					min-height: 40px !important;
				}
				.nfd-reset-submit:disabled {
					background: #e6e6e6 !important;
					border-color: #babac3 !important;
					color: #7d7d7d !important;
					cursor: not-allowed;
				}
				.nfd-reset-submit.nfd-resetting:disabled {
					color: #000000 !important;
				}
				.nfd-reset-submit .nfd-spinner {
					display: inline-block;
					width: 14px;
					height: 14px;
					border: 2px solid rgba(0, 0, 0, 0.2);
					border-top-color: #000;
					border-radius: 50%;
					animation: nfd-spin 0.6s linear infinite;
					vertical-align: middle;
					margin-right: 6px;
				}
				@keyframes nfd-spin {
					to { transform: rotate(360deg); }
				}
				.nfd-reset-submit:hover:not(:disabled) {
					background: #b32d2e !important;
					border-color: #b32d2e !important;
				}
				.nfd-reset-error {
					background: #fcf0f1;
					border: 1px solid #dc3232;
					border-left-width: 4px;
					border-radius: 4px;
					padding: 12px 16px;
					margin: 0 0 24px;
					font-size: 14px;
				}
			</style>

			<div class="nfd-reset-page">
				<h1><?php esc_html_e( 'Factory Reset Website', 'wp-module-reset' ); ?></h1>
				<p class="nfd-reset-subtitle"><?php esc_html_e( 'Restore your website to a fresh WordPress installation.', 'wp-module-reset' ); ?></p>

				<?php if ( $error ) : ?>
					<div class="nfd-reset-error">
						<strong><?php echo esc_html( $error ); ?></strong>
					</div>
				<?php endif; ?>

				<div class="nfd-reset-warning">
					<h2><?php esc_html_e( 'This action is permanent and cannot be undone.', 'wp-module-reset' ); ?></h2>
					<p><?php esc_html_e( 'Performing a factory reset will:', 'wp-module-reset' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Delete all database content (posts, pages, comments, settings, custom tables)', 'wp-module-reset' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: brand plugin name, e.g. "The Bluehost Plugin" */
								esc_html__( 'Remove all plugins and themes (except %s and default theme)', 'wp-module-reset' ),
								esc_html( BrandConfig::get_brand_name() )
							);
							?>
						</li>
						<li><?php esc_html_e( 'Delete all uploaded media files', 'wp-module-reset' ); ?></li>
						<li><?php esc_html_e( 'Destroy any staging sites', 'wp-module-reset' ); ?></li>
					</ul>
					<p class="nfd-reset-preserved"><?php esc_html_e( 'Your admin account and site URL will be preserved.', 'wp-module-reset' ); ?></p>
				</div>

				<form method="post" id="nfd-reset-form">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<div class="nfd-reset-confirm-field">
						<label for="confirmation_url">
							<?php
							printf(
								/* translators: %s: The site URL the user must type to confirm */
								esc_html__( 'To confirm, type your website URL: %s', 'wp-module-reset' ),
								'<code>' . esc_html( $home_url ) . '</code>'
							);
							?>
						</label>
						<input
							type="text"
							id="confirmation_url"
							name="confirmation_url"
							autocomplete="off"
							spellcheck="false"
							placeholder=""
						/>
					</div>

					<button type="submit" class="button nfd-reset-submit" id="nfd-reset-button" disabled>
						<?php esc_html_e( 'Reset Website', 'wp-module-reset' ); ?>
					</button>
				</form>
			</div>

			<script>
			(function() {
				var input = document.getElementById('confirmation_url');
				var button = document.getElementById('nfd-reset-button');
				var form = document.getElementById('nfd-reset-form');
				var expected = <?php echo wp_json_encode( $home_url ); ?>;

				if (input && button) {
					input.addEventListener('input', function() {
						var value = this.value.replace(/\/+$/, '');
						button.disabled = (value !== expected);
					});
				}

				if (form && button) {
					form.addEventListener('submit', function() {
						button.disabled = true;
						button.classList.add('nfd-resetting');
						button.innerHTML = '<span class="nfd-spinner"></span> <?php echo esc_js( __( 'Resetting...', 'wp-module-reset' ) ); ?>';
						input.readOnly = true;
						input.style.opacity = '0.5';
					});
				}
			})();
			</script>
		</div>
		<?php
	}

	/**
	 * Render the results screen after reset.
	 *
	 * @param array $result Reset result from ResetService::execute().
	 */
	private function render_results( $result ) {
		$success      = ! empty( $result['success'] );
		$redirect_url = admin_url();
		$steps        = ! empty( $result['steps'] ) ? $result['steps'] : array();
		$errors       = ! empty( $result['errors'] ) ? $result['errors'] : array();
		$has_errors   = ! empty( $errors );
		$hide_chrome  = $success && ! $has_errors;
		?>
		<?php if ( $hide_chrome ) : ?>
		<style type="text/css">
			/* Hide admin bar, sidebar, and footer so user focuses on the two CTAs. */
			#wpadminbar,
			#adminmenumain,
			#adminmenuwrap,
			#wpfooter {
				display: none !important;
			}
			#wpcontent {
				margin-left: 0 !important;
			}
		</style>
		<?php endif; ?>
		<div class="wrap">
			<style>
				.nfd-reset-page {
					max-width: 900px;
					margin: 0 auto;
					padding: 2rem;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				}
				.nfd-reset-page h1 {
					font-size: 1.5rem;
					font-weight: 700;
					margin: 0 0 0.5rem;
				}
				.nfd-reset-page .nfd-reset-subtitle {
					color: #646970;
					font-size: 14px;
					margin: 0 0 2rem;
				}
				.nfd-reset-result {
					background: #fff;
					border: 1px solid <?php echo $has_errors ? '#dc3232' : '#00a32a'; ?>;
					border-left-width: 4px;
					border-radius: 4px;
					padding: 24px;
					margin: 0 0 24px;
				}
				.nfd-reset-result h2 {
					color: <?php echo $has_errors ? '#dc3232' : '#00a32a'; ?>;
					font-size: 1rem;
					font-weight: 600;
					margin: 0 0 12px;
				}
				.nfd-reset-result p {
					font-size: 14px;
					color: #1e1e1e;
					line-height: 1.6;
					margin: 0 0 8px;
				}
				.nfd-reset-result ul {
					list-style: disc;
					padding-left: 20px;
					margin: 8px 0 12px;
				}
				.nfd-reset-result ul li {
					font-size: 14px;
					color: #1e1e1e;
					margin-bottom: 6px;
					line-height: 1.5;
				}
				.nfd-reset-result .nfd-reset-preserved {
					color: #646970;
					font-size: 13px;
					margin-top: 12px;
				}
				.nfd-reset-steps {
					margin: 0;
				}
				.nfd-reset-steps table {
					border-collapse: collapse;
					width: 100%;
				}
				.nfd-reset-steps td {
					padding: 8px 12px;
					border-bottom: 1px solid #f0f0f1;
					font-size: 14px;
					line-height: 1.5;
				}
				.nfd-reset-step-success {
					color: #00a32a;
					font-weight: 600;
				}
				.nfd-reset-step-fail {
					color: #dc3232;
					font-weight: 600;
				}
				.nfd-reset-errors {
					margin-top: 16px;
					padding: 12px 16px;
					background: #fcf0f1;
					border-radius: 4px;
					font-size: 14px;
				}
				.nfd-reset-errors ul {
					margin: 6px 0 0 16px;
					list-style: disc;
				}
				.nfd-reset-cta-group {
					display: flex;
					gap: 12px;
					align-items: center;
					flex-wrap: wrap;
				}
				.nfd-reset-cta-primary {
					display: inline-block;
					padding: 10px 24px;
					background: #2271b1;
					color: #fff;
					text-decoration: none;
					border-radius: 4px;
					font-size: 14px;
					font-weight: 500;
					line-height: 1.5;
				}
				.nfd-reset-cta-primary:hover {
					background: #135e96;
					color: #fff;
				}
				.nfd-reset-cta-secondary {
					display: inline-block;
					padding: 10px 24px;
					background: transparent;
					color: #2271b1;
					text-decoration: none;
					border: 1px solid #2271b1;
					border-radius: 4px;
					font-size: 14px;
					font-weight: 500;
					line-height: 1.5;
				}
				.nfd-reset-cta-secondary:hover {
					background: #f0f0f1;
					color: #135e96;
					border-color: #135e96;
				}
			</style>

			<div class="nfd-reset-page">
				<h1><?php esc_html_e( 'Factory Reset Complete', 'wp-module-reset' ); ?></h1>
				<p class="nfd-reset-subtitle"><?php esc_html_e( 'Your website has been restored to a fresh WordPress installation.', 'wp-module-reset' ); ?></p>

				<?php if ( $has_errors ) : ?>
					<?php // Detailed technical view — shown only when errors occurred. ?>
					<div class="nfd-reset-result">
						<h2><?php esc_html_e( 'Reset completed with errors.', 'wp-module-reset' ); ?></h2>

						<?php if ( ! empty( $steps ) ) : ?>
							<div class="nfd-reset-steps">
								<table>
									<?php foreach ( $steps as $step_name => $step_data ) : ?>
										<tr>
											<td>
												<?php if ( ! empty( $step_data['success'] ) ) : ?>
													<span class="nfd-reset-step-success">&#10003;</span>
												<?php else : ?>
													<span class="nfd-reset-step-fail">&#10007;</span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( self::format_step_name( $step_name ) ); ?></td>
											<td><?php echo esc_html( ! empty( $step_data['message'] ) ? $step_data['message'] : '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</table>
							</div>
						<?php endif; ?>

						<div class="nfd-reset-errors">
							<strong><?php esc_html_e( 'Errors:', 'wp-module-reset' ); ?></strong>
							<ul>
								<?php foreach ( $errors as $err ) : ?>
									<li><?php echo esc_html( $err ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>

					<div class="nfd-reset-cta-group">
						<a href="<?php echo esc_url( $redirect_url ); ?>" class="nfd-reset-cta-primary">
							<?php esc_html_e( 'Continue to Dashboard', 'wp-module-reset' ); ?>
						</a>
					</div>

				<?php else : ?>
					<?php // Simple success view — mirrors the confirmation screen style but green/positive. ?>
					<div class="nfd-reset-result">
						<h2><?php esc_html_e( 'Your website has been reset successfully.', 'wp-module-reset' ); ?></h2>
						<p><?php esc_html_e( 'The factory reset is complete. Your site has been restored to a clean state:', 'wp-module-reset' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'All database content has been cleared (posts, pages, comments, settings)', 'wp-module-reset' ); ?></li>
							<li><?php esc_html_e( 'Third-party plugins and themes have been removed', 'wp-module-reset' ); ?></li>
							<li><?php esc_html_e( 'Uploaded media files have been deleted', 'wp-module-reset' ); ?></li>
							<li><?php esc_html_e( 'WordPress core and default theme have been freshly reinstalled', 'wp-module-reset' ); ?></li>
							<li>
								<?php
								printf(
									/* translators: %s: brand plugin name, e.g. "The Bluehost Plugin" */
									esc_html__( '%s is active and connected', 'wp-module-reset' ),
									esc_html( BrandConfig::get_brand_name() )
								);
								?>
							</li>
						</ul>
						<p class="nfd-reset-preserved"><?php esc_html_e( 'Your admin account and site URL have been preserved.', 'wp-module-reset' ); ?></p>
					</div>

					<div class="nfd-reset-cta-group">
						<a href="<?php echo esc_url( admin_url( 'index.php?page=nfd-onboarding' ) ); ?>" class="nfd-reset-cta-primary">
							<?php esc_html_e( 'Set up my site', 'wp-module-reset' ); ?>
						</a>
						<a href="<?php echo esc_url( $redirect_url ); ?>" class="nfd-reset-cta-secondary">
							<?php esc_html_e( 'Exit to dashboard', 'wp-module-reset' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Format a step name for display.
	 *
	 * @param string $step_name The step key name.
	 * @return string Formatted name.
	 */
	private static function format_step_name( $step_name ) {
		$map = array(
			'install_theme'        => 'Install default theme',
			'deactivate_plugins'   => 'Deactivate third-party plugins',
			'harden_environment'   => 'Prepare environment',
			'staging_cleanup'      => 'Clean up staging sites',
			'remove_plugins'       => 'Remove plugins',
			'remove_themes'        => 'Remove themes',
			'remove_mu_plugins'    => 'Remove MU plugins',
			'remove_dropins'       => 'Remove drop-in files',
			'clean_wp_content'     => 'Clean wp-content directory',
			'clean_uploads'        => 'Clean uploads directory',
			'reset_database'       => 'Reset database',
			'restore_values'       => 'Restore settings',
			'reinstall_core'       => 'Reinstall WordPress core',
			'reinstall_theme'      => 'Reinstall default theme',
			'restore_nfd_data'     => 'Restore hosting connection',
			'verify_fresh_install' => 'Verify fresh install state',
			'restore_session'      => 'Restore session',
		);

		return isset( $map[ $step_name ] ) ? $map[ $step_name ] : ucwords( str_replace( '_', ' ', $step_name ) );
	}
}
