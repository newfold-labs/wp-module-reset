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

		$this->load_view(
			'reset-confirmation',
			array(
				'home_url' => $home_url,
				'error'    => $error,
			)
		);
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

		$this->load_view(
			'reset-results',
			array(
				'success'      => $success,
				'redirect_url' => $redirect_url,
				'steps'        => $steps,
				'errors'       => $errors,
				'has_errors'   => $has_errors,
				'hide_chrome'  => $hide_chrome,
			)
		);
	}

	/**
	 * Load a PHP view file for the tools page.
	 *
	 * @param string $view View slug (without extension).
	 * @param array  $data Data to extract into the view scope.
	 */
	private function load_view( $view, array $data = array() ) {
		if ( ! empty( $data ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data, EXTR_SKIP );
		}

		$view_file = __DIR__ . '/views/' . $view . '.php';

		if ( file_exists( $view_file ) ) {
			require $view_file;
		}
	}

	/**
	 * Format a step name for display.
	 *
	 * @param string $step_name The step key name.
	 * @return string Formatted name.
	 */
	public static function format_step_name( $step_name ) {
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
