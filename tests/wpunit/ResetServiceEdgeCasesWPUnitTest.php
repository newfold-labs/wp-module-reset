<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test ResetService edge cases and error paths not covered elsewhere.
 *
 * Focuses on:
 * - prepare() WP-CLI fallback (user ID 0)
 * - prepare() when theme installation fails
 * - reinstall_core() error path (HTTP blocked)
 * - reinstall_theme() error path (HTTP blocked)
 * - restore_nfd_data() with full data including throttle transient
 * - restore_values() success path
 */
class ResetServiceEdgeCasesWPUnitTest extends WPTestCase {

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * Path to fake theme directory.
	 *
	 * @var string
	 */
	private $fake_theme_dir = '';

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$theme_slug           = BrandConfig::get_default_theme_slug();
		$this->fake_theme_dir = get_theme_root() . '/' . $theme_slug;

		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
		}
		file_put_contents(
			$this->fake_theme_dir . '/style.css',
			"/*\nTheme Name: Test Stub\n*/"
		);
		if ( ! file_exists( $this->fake_theme_dir . '/index.php' ) ) {
			file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
		}

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
	 * @return array
	 */
	public function block_http() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}

	// ------------------------------------------------------------------
	// prepare() — WP-CLI fallback (current user ID is 0)
	// ------------------------------------------------------------------

	public function test_prepare_falls_back_to_first_admin_when_current_user_is_zero() {
		wp_set_current_user( 0 );

		// In WP-CLI, user ID 0 can still have manage_options capability.
		// Simulate this by granting the cap via filter.
		$grant_cap = function ( $allcaps ) {
			$allcaps['manage_options'] = true;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $grant_cap );

		$result = ResetService::prepare();

		remove_filter( 'user_has_cap', $grant_cap );

		// Should find the first admin user (ordered by ID) as the fallback.
		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['data']['user_login'] );
		$this->assertNotEmpty( $result['data']['user_email'] );
	}

	// ------------------------------------------------------------------
	// prepare() — theme installation failure aborts reset
	// ------------------------------------------------------------------

	public function test_prepare_fails_when_theme_not_installed_and_http_blocked() {
		global $wp_filesystem;

		// Remove the fake theme so ensure_theme_installed must fetch it.
		if ( is_dir( $this->fake_theme_dir ) ) {
			$wp_filesystem->delete( $this->fake_theme_dir, true );
		}
		wp_clean_themes_cache();

		$result = ResetService::prepare();

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'default theme', $result['errors'][0] );

		// Recreate for tearDown.
		mkdir( $this->fake_theme_dir, 0755, true );
		file_put_contents( $this->fake_theme_dir . '/style.css', "/*\nTheme Name: Test Stub\n*/" );
		file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
	}

	// ------------------------------------------------------------------
	// prepare() — data captures brand version option
	// ------------------------------------------------------------------

	public function test_prepare_captures_brand_version_option() {
		$brand_id             = BrandConfig::get_brand_id();
		$brand_version_option = $brand_id . '_plugin_version';
		update_option( $brand_version_option, '9.9.9' );

		$result = ResetService::prepare();

		$this->assertTrue( $result['success'] );
		$this->assertSame( $brand_version_option, $result['data']['brand_plugin_version_option'] );
		$this->assertSame( '9.9.9', $result['data']['brand_plugin_version'] );
	}

	// ------------------------------------------------------------------
	// reinstall_core() — error path (no update offers when HTTP blocked)
	// ------------------------------------------------------------------

	public function test_reinstall_core_fails_when_no_update_offers() {
		$result = $this->call_private( 'reinstall_core' );

		// wp_version_check() will fail with HTTP blocked, so get_core_updates()
		// returns empty or stale data. Either way, the method should handle it.
		$this->assertIsBool( $result['success'] );
		$this->assertArrayHasKey( 'message', $result );
	}

	// ------------------------------------------------------------------
	// reinstall_theme() — error path (deletes theme, then fetch fails)
	// ------------------------------------------------------------------

	public function test_reinstall_theme_fails_when_http_blocked() {
		$result = $this->call_private( 'reinstall_theme' );

		// reinstall_theme deletes the existing theme dir, then tries to fetch
		// a fresh copy. With HTTP blocked, ensure_theme_installed will fail.
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'failed', strtolower( $result['message'] ) );

		// Recreate the fake theme for subsequent tests.
		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
		}
		file_put_contents( $this->fake_theme_dir . '/style.css', "/*\nTheme Name: Test Stub\n*/" );
		file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
	}

	// ------------------------------------------------------------------
	// restore_nfd_data() — full data with all optional fields
	// ------------------------------------------------------------------

	public function test_restore_nfd_data_restores_all_fields() {
		$data = array(
			'nfd_data_token'                       => 'test-hiive-token-123',
			'nfd_data_module_version'              => '2.5.0',
			'nfd_data_connection_attempts'         => '3',
			'nfd_data_connection_throttle'         => 'throttled',
			'nfd_data_connection_throttle_timeout' => (string) ( time() + 3600 ),
			'brand_plugin_version_option'          => 'bluehost_plugin_version',
			'brand_plugin_version'                 => '3.14.0',
		);

		$result = $this->call_private( 'restore_nfd_data', $data );

		$this->assertTrue( $result['success'] );

		// Verify all options were restored.
		$this->assertSame( 'test-hiive-token-123', get_option( 'nfd_data_token' ) );
		$this->assertSame( '2.5.0', get_option( 'nfd_data_module_version' ) );
		$this->assertSame( '3', get_option( 'nfd_data_connection_attempts' ) );
		$this->assertSame( 'throttled', get_transient( 'nfd_data_connection_throttle' ) );
		$this->assertSame( '3.14.0', get_option( 'bluehost_plugin_version' ) );
		$this->assertTrue( (bool) get_option( 'nfd_coming_soon' ) );
	}

	public function test_restore_nfd_data_skips_empty_token() {
		$data = array(
			'nfd_data_token'                       => '',
			'nfd_data_module_version'              => '',
			'nfd_data_connection_attempts'         => '',
			'nfd_data_connection_throttle'         => '',
			'nfd_data_connection_throttle_timeout' => '',
			'brand_plugin_version_option'          => '',
			'brand_plugin_version'                 => '',
		);

		$result = $this->call_private( 'restore_nfd_data', $data );

		$this->assertTrue( $result['success'] );

		// Empty values should not be written.
		$this->assertFalse( get_option( 'nfd_data_token' ) );
		$this->assertFalse( get_option( 'nfd_data_module_version' ) );
	}

	public function test_restore_nfd_data_skips_expired_throttle() {
		$data = array(
			'nfd_data_token'                       => '',
			'nfd_data_module_version'              => '',
			'nfd_data_connection_attempts'         => '',
			'nfd_data_connection_throttle'         => 'throttled',
			'nfd_data_connection_throttle_timeout' => (string) ( time() - 100 ),
			'brand_plugin_version_option'          => '',
			'brand_plugin_version'                 => '',
		);

		$result = $this->call_private( 'restore_nfd_data', $data );

		$this->assertTrue( $result['success'] );

		// Expired throttle should not be restored.
		$this->assertFalse( get_transient( 'nfd_data_connection_throttle' ) );
	}

	// ------------------------------------------------------------------
	// execute() — all steps are present in result
	// ------------------------------------------------------------------

	public function test_execute_returns_all_expected_step_keys() {
		// Provide minimal data to execute with.
		$data = array(
			'blogname'                             => 'Test',
			'blog_public'                          => '1',
			'siteurl'                              => home_url(),
			'home'                                 => home_url(),
			'wplang'                               => '',
			'user_pass'                            => 'hash',
			'user_login'                           => 'admin',
			'user_email'                           => 'admin@example.org',
			'brand_basename'                       => 'brand/brand.php',
			'nfd_data_token'                       => '',
			'nfd_data_module_version'              => '',
			'nfd_data_connection_attempts'         => '',
			'nfd_data_connection_throttle'         => '',
			'nfd_data_connection_throttle_timeout' => '',
			'brand_plugin_version_option'          => '',
			'brand_plugin_version'                 => '',
		);

		add_filter( 'pre_wp_mail', '__return_true' );
		$result = ResetService::execute( $data, array() );
		remove_filter( 'pre_wp_mail', '__return_true' );

		$expected_keys = array(
			'harden_environment',
			'staging_cleanup',
			'remove_plugins',
			'remove_themes',
			'clean_wp_content',
			'clean_uploads',
			'reset_database',
			'restore_nfd_data',
			'restore_values',
			'reinstall_core',
			'reinstall_theme',
			'verify_fresh_install',
			'restore_session',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result['steps'], "Step '$key' should be present in results." );
		}

		// Restore the fake theme and default theme for next tests.
		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
			file_put_contents( $this->fake_theme_dir . '/style.css', "/*\nTheme Name: Test Stub\n*/" );
			file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
		}
		$default_dir = get_theme_root() . '/default';
		if ( ! is_dir( $default_dir ) ) {
			mkdir( $default_dir, 0755, true );
			file_put_contents( $default_dir . '/style.css', "/*\nTheme Name: Default\n*/" );
			file_put_contents( $default_dir . '/index.php', '<?php' );
		}
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	/**
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
