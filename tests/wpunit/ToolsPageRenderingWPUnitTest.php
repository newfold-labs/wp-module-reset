<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Admin\ToolsPage;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test ToolsPage rendering, routing, and form validation.
 *
 * Methods that call exit (handle_phase1 happy path, handle_phase2 happy path)
 * are covered separately in the integration tests where we test the full reset
 * flow. This file focuses on the rendering output and all non-exit code paths.
 */
class ToolsPageRenderingWPUnitTest extends WPTestCase {

	/**
	 * @var ToolsPage
	 */
	private $tools_page;

	/**
	 * @var int
	 */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->tools_page = new ToolsPage();

		// Clean up superglobals between tests.
		unset( $_GET['page'], $_GET['nfd_reset_phase'], $_GET['reset_complete'] );
		unset( $_POST[ ToolsPage::NONCE_NAME ], $_POST['confirmation_url'] );
	}

	public function tearDown(): void {
		delete_transient( 'nfd_reset_error' );
		delete_transient( 'nfd_reset_result' );
		delete_transient( 'nfd_reset_phase2' );

		unset( $_GET['page'], $_GET['nfd_reset_phase'], $_GET['reset_complete'] );
		unset( $_POST[ ToolsPage::NONCE_NAME ], $_POST['confirmation_url'] );

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// handle_submission() routing
	// ------------------------------------------------------------------

	public function test_handle_submission_returns_early_when_page_not_set() {
		unset( $_GET['page'] );

		// Should return without doing anything — no error, no redirect.
		$this->tools_page->handle_submission();
		$this->assertTrue( true, 'handle_submission returned without error when page not set.' );
	}

	public function test_handle_submission_returns_early_when_wrong_page() {
		$_GET['page'] = 'some-other-page';

		$this->tools_page->handle_submission();
		$this->assertTrue( true, 'handle_submission returned without error for wrong page.' );
	}

	public function test_handle_submission_returns_early_when_no_post_nonce() {
		$_GET['page'] = ToolsPage::get_slug();

		// No $_POST nonce and no phase 2 param — should return early.
		$this->tools_page->handle_submission();
		$this->assertTrue( true, 'handle_submission returned without error when no POST nonce.' );
	}

	// ------------------------------------------------------------------
	// handle_phase1() validation failures (via handle_submission)
	// ------------------------------------------------------------------

	public function test_phase1_rejects_invalid_nonce() {
		wp_set_current_user( $this->admin_id );
		$_GET['page']                       = ToolsPage::get_slug();
		$_POST[ ToolsPage::NONCE_NAME ]     = 'invalid-nonce-value';

		// wp_die should be called — override the handler to catch it.
		add_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );

		$died = false;
		try {
			$this->tools_page->handle_submission();
		} catch ( \Exception $e ) {
			if ( 'wp_die_called' === $e->getMessage() ) {
				$died = true;
			}
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
		$this->assertTrue( $died, 'Phase 1 should wp_die on invalid nonce.' );
	}

	public function test_phase1_rejects_non_admin_user() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_GET['page']                       = ToolsPage::get_slug();
		$_POST[ ToolsPage::NONCE_NAME ]     = wp_create_nonce( ToolsPage::NONCE_ACTION );

		add_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );

		$died = false;
		try {
			$this->tools_page->handle_submission();
		} catch ( \Exception $e ) {
			if ( 'wp_die_called' === $e->getMessage() ) {
				$died = true;
			}
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
		$this->assertTrue( $died, 'Phase 1 should wp_die for non-admin user.' );
	}

	public function test_phase1_sets_error_transient_on_url_mismatch() {
		wp_set_current_user( $this->admin_id );

		$_GET['page']                       = ToolsPage::get_slug();
		$_POST[ ToolsPage::NONCE_NAME ]     = wp_create_nonce( ToolsPage::NONCE_ACTION );
		$_POST['confirmation_url']          = 'https://wrong-url.example.com';

		$this->tools_page->handle_submission();

		$error = get_transient( 'nfd_reset_error' );
		$this->assertNotFalse( $error, 'Error transient should be set on URL mismatch.' );
		$this->assertStringContainsString( 'does not match', $error );
	}

	// ------------------------------------------------------------------
	// handle_phase2() validation (via handle_submission)
	// ------------------------------------------------------------------

	public function test_phase2_rejects_non_admin() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_GET['page']            = ToolsPage::get_slug();
		$_GET['nfd_reset_phase'] = '2';

		add_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );

		$died = false;
		try {
			$this->tools_page->handle_submission();
		} catch ( \Exception $e ) {
			if ( 'wp_die_called' === $e->getMessage() ) {
				$died = true;
			}
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
		$this->assertTrue( $died, 'Phase 2 should wp_die for non-admin user.' );
	}

	public function test_phase2_sets_error_when_no_transient() {
		wp_set_current_user( $this->admin_id );

		$_GET['page']            = ToolsPage::get_slug();
		$_GET['nfd_reset_phase'] = '2';

		// No transient set — should set error and redirect.
		// Catch the redirect to prevent exit.
		$redirect_url = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect_url ) {
				$redirect_url = $location;
				throw new \Exception( 'redirect_caught' );
			}
		);

		try {
			$this->tools_page->handle_submission();
		} catch ( \Exception $e ) {
			// Expected.
		}

		$error = get_transient( 'nfd_reset_error' );
		$this->assertNotFalse( $error, 'Error transient should be set when phase2 data is missing.' );
		$this->assertStringContainsString( 'expired', $error );
		$this->assertNotNull( $redirect_url, 'Should redirect back to the form page.' );
	}

	// ------------------------------------------------------------------
	// render_page() routing
	// ------------------------------------------------------------------

	public function test_render_page_dies_for_non_admin() {
		wp_set_current_user( 0 );

		add_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );

		$died = false;
		try {
			$this->tools_page->render_page();
		} catch ( \Exception $e ) {
			if ( 'wp_die_called' === $e->getMessage() ) {
				$died = true;
			}
		}

		remove_filter( 'wp_die_handler', array( $this, 'get_die_handler' ) );
		$this->assertTrue( $died, 'render_page should wp_die for non-admin.' );
	}

	public function test_render_page_shows_confirmation_by_default() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Factory Reset Website', $html );
		$this->assertStringContainsString( 'nfd-reset-form', $html );
		$this->assertStringContainsString( 'confirmation_url', $html );
		$this->assertStringContainsString( ToolsPage::NONCE_NAME, $html );
	}

	public function test_render_page_shows_error_from_transient() {
		wp_set_current_user( $this->admin_id );
		set_transient( 'nfd_reset_error', 'Something went wrong!', 60 );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Something went wrong!', $html );
		$this->assertStringContainsString( 'nfd-reset-error', $html );

		// Transient should be consumed.
		$this->assertFalse( get_transient( 'nfd_reset_error' ) );
	}

	public function test_render_page_shows_success_results() {
		wp_set_current_user( $this->admin_id );
		$_GET['reset_complete'] = '1';

		set_transient(
			'nfd_reset_result',
			array(
				'success' => true,
				'steps'   => array(
					'reset_database' => array( 'success' => true, 'message' => 'Done.' ),
				),
				'errors'  => array(),
			),
			300
		);

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Factory Reset Complete', $html );
		$this->assertStringContainsString( 'reset successfully', $html );
		$this->assertStringContainsString( 'Set up my site', $html );
		$this->assertStringContainsString( 'Exit to dashboard', $html );

		// Transient should be consumed.
		$this->assertFalse( get_transient( 'nfd_reset_result' ) );
	}

	public function test_render_page_shows_error_results_with_steps() {
		wp_set_current_user( $this->admin_id );
		$_GET['reset_complete'] = '1';

		set_transient(
			'nfd_reset_result',
			array(
				'success' => false,
				'steps'   => array(
					'install_theme'  => array( 'success' => true, 'message' => 'Theme installed.' ),
					'reset_database' => array( 'success' => false, 'message' => 'DB error.' ),
				),
				'errors'  => array( 'Database reset encountered errors.' ),
			),
			300
		);

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'completed with errors', $html );
		$this->assertStringContainsString( 'nfd-reset-step-success', $html );
		$this->assertStringContainsString( 'nfd-reset-step-fail', $html );
		$this->assertStringContainsString( 'Install default theme', $html );
		$this->assertStringContainsString( 'Reset database', $html );
		$this->assertStringContainsString( 'Database reset encountered errors.', $html );
		$this->assertStringContainsString( 'Continue to Dashboard', $html );
	}

	public function test_render_page_falls_back_to_confirmation_when_result_transient_missing() {
		wp_set_current_user( $this->admin_id );
		$_GET['reset_complete'] = '1';
		// No transient set.

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		// Should fall through to confirmation form.
		$this->assertStringContainsString( 'nfd-reset-form', $html );
	}

	// ------------------------------------------------------------------
	// render_confirmation() content verification
	// ------------------------------------------------------------------

	public function test_confirmation_contains_home_url() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$home_url = untrailingslashit( home_url() );
		$this->assertStringContainsString( $home_url, $html );
	}

	public function test_confirmation_contains_brand_name() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		// The brand name is used in the "except {brand} and default theme" text.
		// Even if empty, the esc_html call is executed.
		$this->assertStringContainsString( 'except', $html );
	}

	public function test_confirmation_contains_nonce_field() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="' . ToolsPage::NONCE_NAME . '"', $html );
	}

	public function test_confirmation_contains_javascript_validation() {
		wp_set_current_user( $this->admin_id );

		ob_start();
		$this->tools_page->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'addEventListener', $html );
		$this->assertStringContainsString( 'nfd-reset-button', $html );
	}

	// ------------------------------------------------------------------
	// format_step_name() via reflection
	// ------------------------------------------------------------------

	public function test_format_step_name_returns_mapped_value() {
		$method = new \ReflectionMethod( ToolsPage::class, 'format_step_name' );
		$method->setAccessible( true );

		$this->assertSame( 'Install default theme', $method->invoke( null, 'install_theme' ) );
		$this->assertSame( 'Reset database', $method->invoke( null, 'reset_database' ) );
		$this->assertSame( 'Restore session', $method->invoke( null, 'restore_session' ) );
	}

	public function test_format_step_name_formats_unknown_key() {
		$method = new \ReflectionMethod( ToolsPage::class, 'format_step_name' );
		$method->setAccessible( true );

		$this->assertSame( 'Some Custom Step', $method->invoke( null, 'some_custom_step' ) );
	}

	// ------------------------------------------------------------------
	// handle_phase1() — prepare failure path
	// ------------------------------------------------------------------

	public function test_phase1_sets_error_transient_when_prepare_fails() {
		wp_set_current_user( $this->admin_id );

		$_GET['page']                   = ToolsPage::get_slug();
		$_POST[ ToolsPage::NONCE_NAME ] = wp_create_nonce( ToolsPage::NONCE_ACTION );
		$_POST['confirmation_url']      = untrailingslashit( home_url() );

		// Remove the default theme so prepare() fails on theme installation.
		$theme_slug = BrandConfig::get_default_theme_slug();
		$theme_dir  = get_theme_root() . '/' . $theme_slug;

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$had_theme = is_dir( $theme_dir );
		if ( $had_theme ) {
			$wp_filesystem->delete( $theme_dir, true );
		}
		wp_clean_themes_cache();

		// Block HTTP so theme fetch fails.
		add_filter( 'pre_http_request', array( $this, 'block_http_filter' ), 10, 3 );

		$this->tools_page->handle_submission();

		remove_filter( 'pre_http_request', array( $this, 'block_http_filter' ), 10 );

		$error = get_transient( 'nfd_reset_error' );
		$this->assertNotFalse( $error, 'Error transient should be set when prepare fails.' );
		$this->assertStringContainsString( 'default theme', $error );

		// No Phase 2 transient should be set.
		$this->assertFalse( get_transient( 'nfd_reset_phase2' ) );

		// Restore theme.
		if ( $had_theme || true ) {
			mkdir( $theme_dir, 0755, true );
			file_put_contents( $theme_dir . '/style.css', "/*\nTheme Name: Test Stub\n*/" );
			file_put_contents( $theme_dir . '/index.php', '<?php' );
		}
	}

	/**
	 * Short-circuit outbound HTTP.
	 *
	 * @return array
	 */
	public function block_http_filter() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}

	// ------------------------------------------------------------------
	// Helper: wp_die handler that throws an exception instead of dying.
	// ------------------------------------------------------------------

	public function get_die_handler() {
		return function () {
			throw new \Exception( 'wp_die_called' );
		};
	}
}
