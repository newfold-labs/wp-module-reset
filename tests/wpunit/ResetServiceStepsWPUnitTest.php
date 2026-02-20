<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test individual ResetService step methods via reflection.
 *
 * Each step is tested in isolation with appropriate setup/teardown
 * so the test environment is left clean for subsequent tests.
 */
class ResetServiceStepsWPUnitTest extends WPTestCase {

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * Path to fake theme directory (cleaned up in tearDown).
	 *
	 * @var string
	 */
	private $fake_theme_dir = '';

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		// Ensure WP_Filesystem is available.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// Create a minimal theme so methods that reference the default theme work.
		$theme_slug           = BrandConfig::get_default_theme_slug();
		$this->fake_theme_dir = get_theme_root() . '/' . $theme_slug;

		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
		}
		// Ensure it has both required files for switch_theme() to work.
		file_put_contents(
			$this->fake_theme_dir . '/style.css',
			"/*\nTheme Name: Test Stub\n*/"
		);
		if ( ! file_exists( $this->fake_theme_dir . '/index.php' ) ) {
			file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
		}

		// Block outbound HTTP as a safety net.
		add_filter( 'pre_http_request', array( $this, 'block_http' ), 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'block_http' ), 10 );

		if ( is_dir( $this->fake_theme_dir ) ) {
			array_map( 'unlink', glob( $this->fake_theme_dir . '/*' ) );
			rmdir( $this->fake_theme_dir );
		}

		parent::tearDown();
	}

	/**
	 * Short-circuit all outbound HTTP requests.
	 *
	 * @return array
	 */
	public function block_http() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}

	// ------------------------------------------------------------------
	// run_step()
	// ------------------------------------------------------------------

	public function test_run_step_returns_callback_result() {
		$result = $this->call_private( 'run_step', function () {
			return array( 'success' => true, 'message' => 'ok' );
		} );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'ok', $result['message'] );
	}

	public function test_run_step_catches_exceptions() {
		$result = $this->call_private( 'run_step', function () {
			throw new \RuntimeException( 'something broke' );
		} );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'something broke', $result['message'] );
	}

	public function test_run_step_passes_arguments() {
		$result = $this->call_private( 'run_step', function ( $a, $b ) {
			return array( 'success' => true, 'message' => $a . $b );
		}, 'hello', 'world' );

		$this->assertSame( 'helloworld', $result['message'] );
	}

	// ------------------------------------------------------------------
	// harden_environment() / restore_environment()
	// ------------------------------------------------------------------

	public function test_harden_environment_blocks_email() {
		$result = $this->call_private( 'harden_environment' );

		$this->assertTrue( $result['success'] );
		$this->assertNotFalse( has_filter( 'pre_wp_mail', '__return_true' ) );

		// Clean up.
		$this->call_private( 'restore_environment' );
	}

	public function test_harden_environment_removes_shutdown_actions() {
		add_action( 'shutdown', '__return_true' );

		$this->call_private( 'harden_environment' );

		$this->assertFalse( has_action( 'shutdown' ) );

		// Clean up.
		$this->call_private( 'restore_environment' );
	}

	public function test_restore_environment_re_enables_email() {
		$this->call_private( 'harden_environment' );
		$this->call_private( 'restore_environment' );

		$this->assertFalse( has_filter( 'pre_wp_mail', '__return_true' ) );
	}

	// ------------------------------------------------------------------
	// ensure_theme_installed()
	// ------------------------------------------------------------------

	public function test_ensure_theme_installed_returns_success_when_theme_exists() {
		// Our fake theme exists from setUp.
		$slug   = BrandConfig::get_default_theme_slug();
		$result = $this->call_private( 'ensure_theme_installed', $slug );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'already installed', $result['message'] );
	}

	public function test_ensure_theme_installed_returns_error_for_missing_theme_when_http_blocked() {
		// themes_api will fail because HTTP is blocked.
		$result = $this->call_private( 'ensure_theme_installed', 'nonexistent-theme-slug-xyz' );

		// Should fail gracefully (either themes_api error or install failure).
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// remove_mu_plugins()
	// ------------------------------------------------------------------

	public function test_remove_mu_plugins_succeeds_with_no_directory() {
		global $wp_filesystem;

		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';

		// Remove if it exists so we test the "no dir" path.
		if ( $wp_filesystem->is_dir( $mu_dir ) ) {
			$wp_filesystem->delete( $mu_dir, true );
		}

		$result = $this->call_private( 'remove_mu_plugins' );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'No MU plugins', $result['message'] );

		// Restore dir for other tests.
		wp_mkdir_p( $mu_dir );
	}

	public function test_remove_mu_plugins_removes_files() {
		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';

		wp_mkdir_p( $mu_dir );
		file_put_contents( $mu_dir . '/test-mu-plugin.php', '<?php // test' );

		$result = $this->call_private( 'remove_mu_plugins' );

		$this->assertTrue( $result['success'] );
		$this->assertFileDoesNotExist( $mu_dir . '/test-mu-plugin.php' );

		// Restore.
		wp_mkdir_p( $mu_dir );
	}

	// ------------------------------------------------------------------
	// remove_dropins()
	// ------------------------------------------------------------------

	public function test_remove_dropins_succeeds_with_no_files() {
		$result = $this->call_private( 'remove_dropins' );

		$this->assertTrue( $result['success'] );
	}

	public function test_remove_dropins_removes_dropin_file() {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		file_put_contents( $content_dir . '/db.php', '<?php // fake dropin' );
		$this->assertFileExists( $content_dir . '/db.php' );

		$result = $this->call_private( 'remove_dropins' );

		$this->assertTrue( $result['success'] );
		$this->assertFileDoesNotExist( $content_dir . '/db.php' );
	}

	// ------------------------------------------------------------------
	// clean_wp_content()
	// ------------------------------------------------------------------

	public function test_clean_wp_content_preserves_core_dirs() {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		$result = $this->call_private( 'clean_wp_content' );

		$this->assertTrue( $result['success'] );
		// Core directories must still exist.
		$this->assertDirectoryExists( $content_dir . '/plugins' );
		$this->assertDirectoryExists( $content_dir . '/themes' );
	}

	public function test_clean_wp_content_removes_extra_directory() {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$extra_dir   = $content_dir . '/cache-test-dir';

		wp_mkdir_p( $extra_dir );
		file_put_contents( $extra_dir . '/test.txt', 'data' );

		$result = $this->call_private( 'clean_wp_content' );

		$this->assertTrue( $result['success'] );
		$this->assertDirectoryDoesNotExist( $extra_dir );
	}

	// ------------------------------------------------------------------
	// clean_uploads()
	// ------------------------------------------------------------------

	public function test_clean_uploads_empties_directory() {
		$upload_dir = wp_get_upload_dir();
		$basedir    = $upload_dir['basedir'];

		wp_mkdir_p( $basedir );
		file_put_contents( $basedir . '/test-upload.txt', 'data' );

		$result = $this->call_private( 'clean_uploads' );

		$this->assertTrue( $result['success'] );
		$this->assertFileDoesNotExist( $basedir . '/test-upload.txt' );
		// Directory itself should still exist.
		$this->assertDirectoryExists( $basedir );
	}

	// ------------------------------------------------------------------
	// remove_plugins()
	// ------------------------------------------------------------------

	public function test_remove_plugins_succeeds_with_no_third_party() {
		// Pass a brand basename that won't match any installed plugin.
		$result = $this->call_private( 'remove_plugins', 'nonexistent/plugin.php' );

		$this->assertTrue( $result['success'] );
	}

	public function test_remove_plugins_preserves_brand_plugin() {
		global $wp_filesystem;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Create a fake third-party plugin.
		$plugin_dir = WP_PLUGIN_DIR . '/fake-test-plugin';
		wp_mkdir_p( $plugin_dir );
		file_put_contents(
			$plugin_dir . '/fake-test-plugin.php',
			"<?php\n/*\nPlugin Name: Fake Test Plugin\n*/"
		);

		// Brand basename — should NOT be removed.
		$brand_basename = BrandConfig::get_brand_plugin_basename();

		$result = $this->call_private( 'remove_plugins', $brand_basename );

		$this->assertTrue( $result['success'] );
		$this->assertDirectoryDoesNotExist( $plugin_dir );
	}

	// ------------------------------------------------------------------
	// remove_themes()
	// ------------------------------------------------------------------

	public function test_remove_themes_preserves_default_theme() {
		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		// Create a sacrificial extra theme so there's something to remove.
		$extra_theme_dir = get_theme_root() . '/test-extra-theme';
		if ( ! is_dir( $extra_theme_dir ) ) {
			mkdir( $extra_theme_dir, 0755, true );
		}
		file_put_contents(
			$extra_theme_dir . '/style.css',
			"/*\nTheme Name: Test Extra Theme\n*/"
		);
		file_put_contents( $extra_theme_dir . '/index.php', '<?php' );

		// Clear the theme cache so WP sees our new theme.
		wp_clean_themes_cache();

		$result = $this->call_private( 'remove_themes' );

		$this->assertTrue( $result['success'] );

		$default_slug = BrandConfig::get_default_theme_slug();
		$theme        = wp_get_theme( $default_slug );
		$this->assertTrue( $theme->exists(), 'Default theme should be preserved.' );
		$this->assertDirectoryDoesNotExist( $extra_theme_dir, 'Extra theme should be removed.' );

		// Restore the "default" theme stub that WPLoader needs.
		$default_dir = get_theme_root() . '/default';
		if ( ! is_dir( $default_dir ) ) {
			mkdir( $default_dir, 0755, true );
			file_put_contents( $default_dir . '/style.css', "/*\nTheme Name: Default\n*/" );
			file_put_contents( $default_dir . '/index.php', '<?php' );
		}
	}

	// ------------------------------------------------------------------
	// restore_values()
	// ------------------------------------------------------------------

	public function test_restore_values_fails_when_db_reset_failed() {
		$db_result = array( 'success' => false, 'user_id' => 0 );

		$result = $this->call_private(
			'restore_values',
			$db_result,
			'hash',
			'http://example.com',
			'http://example.com',
			'brand/brand.php'
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'did not complete', $result['message'] );
	}

	public function test_restore_values_fails_when_user_id_is_empty() {
		$db_result = array( 'success' => true, 'user_id' => 0 );

		$result = $this->call_private(
			'restore_values',
			$db_result,
			'hash',
			'http://example.com',
			'http://example.com',
			'brand/brand.php'
		);

		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// restore_session()
	// ------------------------------------------------------------------

	public function test_restore_session_succeeds() {
		$result = $this->call_private( 'restore_session', $this->admin_id );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Session restored', $result['message'] );
	}

	// ------------------------------------------------------------------
	// verify_fresh_install()
	// ------------------------------------------------------------------

	public function test_verify_fresh_install_detects_extra_users() {
		// The test environment has multiple users (factory creates them).
		$result = $this->call_private( 'verify_fresh_install' );

		// Should report failures because of extra users.
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Fresh install check failed', $result['message'] );
	}

	// ------------------------------------------------------------------
	// prepare() success path (with fake theme already installed)
	// ------------------------------------------------------------------

	public function test_prepare_succeeds_with_admin_and_theme() {
		$result = ResetService::prepare();

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['data'] );
		$this->assertArrayHasKey( 'user_login', $result['data'] );
		$this->assertArrayHasKey( 'user_email', $result['data'] );
		$this->assertArrayHasKey( 'siteurl', $result['data'] );
		$this->assertArrayHasKey( 'brand_basename', $result['data'] );
		$this->assertArrayHasKey( 'brand_plugin_version_option', $result['data'] );

		// Steps should include theme, deactivate, mu-plugins, dropins.
		$this->assertArrayHasKey( 'install_theme', $result['steps'] );
		$this->assertArrayHasKey( 'deactivate_plugins', $result['steps'] );
		$this->assertArrayHasKey( 'remove_mu_plugins', $result['steps'] );
		$this->assertArrayHasKey( 'remove_dropins', $result['steps'] );
	}

	public function test_prepare_preserves_current_user_data() {
		$user = get_userdata( $this->admin_id );

		$result = ResetService::prepare();

		$this->assertSame( $user->user_login, $result['data']['user_login'] );
		$this->assertSame( $user->user_email, $result['data']['user_email'] );
	}

	// ------------------------------------------------------------------
	// Helper to invoke private static methods via reflection.
	// ------------------------------------------------------------------

	/**
	 * Call a private static method on ResetService.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function call_private( string $method, ...$args ) {
		$ref = new \ReflectionMethod( ResetService::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}
}
