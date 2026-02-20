<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetDataPreserver;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test that Hiive connection data is preserved and restored across a factory reset.
 *
 * These tests cover the non-destructive prepare() method and the private
 * restore_nfd_data() method (invoked via reflection). We cannot call execute()
 * in automated tests because it drops all database tables.
 *
 * The most important tests here are the round-trips: they simulate the actual
 * bug scenario by capturing data with prepare(), deleting the options (as the
 * DB reset would), restoring via restore_nfd_data(), and asserting the values
 * survived. If the preservation keys are removed or restore logic breaks, these
 * tests fail.
 */
class HiivePreservationWPUnitTest extends WPTestCase {

	/**
	 * Admin user ID.
	 *
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

		// Create a minimal theme so prepare()'s ensure_theme_installed()
		// short-circuits without hitting the WordPress.org API.
		$theme_slug           = BrandConfig::get_default_theme_slug();
		$this->fake_theme_dir = get_theme_root() . '/' . $theme_slug;

		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
			file_put_contents(
				$this->fake_theme_dir . '/style.css',
				"/*\nTheme Name: Test Stub\n*/"
			);
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
	// prepare() — contract tests for data capture
	// ------------------------------------------------------------------

	public function test_prepare_captures_all_hiive_connection_keys() {
		update_option( 'nfd_data_token', 'tok_abc' );
		update_option( 'nfd_data_module_version', '2.9.3' );
		update_option( 'nfd_data_connection_attempts', 1 );

		$result = ResetService::prepare();

		$this->assertTrue( $result['success'], 'prepare() should succeed with admin user and existing theme.' );

		$required_keys = array(
			'nfd_data_token',
			'nfd_data_module_version',
			'nfd_data_connection_attempts',
			'nfd_data_connection_throttle',
			'nfd_data_connection_throttle_timeout',
		);

		foreach ( $required_keys as $key ) {
			$this->assertArrayHasKey( $key, $result['data'], "prepare() data is missing key: $key" );
		}
	}

	public function test_prepare_captures_brand_plugin_version() {
		$brand_id    = BrandConfig::get_brand_id();
		$version_key = $brand_id . '_plugin_version';
		update_option( $version_key, '4.13.1' );

		$result = ResetService::prepare();

		$this->assertSame( $version_key, $result['data']['brand_plugin_version_option'] );
		$this->assertSame( '4.13.1', $result['data']['brand_plugin_version'] );
	}

	// ------------------------------------------------------------------
	// Round-trip: prepare() → wipe → restore_nfd_data() → verify
	//
	// These simulate the actual failure scenario. If preservation or
	// restoration breaks, these are the tests that catch it.
	// ------------------------------------------------------------------

	public function test_hiive_data_round_trip() {
		// 1. Seed the DB with Hiive connection data.
		update_option( 'nfd_data_token', 'round-trip-token-xyz' );
		update_option( 'nfd_data_module_version', '2.9.3' );
		update_option( 'nfd_data_connection_attempts', 3 );

		// 2. Capture via prepare().
		$result = ResetService::prepare();
		$this->assertTrue( $result['success'] );
		$data = $result['data'];

		// 3. Simulate the DB wipe (delete all the options).
		delete_option( 'nfd_data_token' );
		delete_option( 'nfd_data_module_version' );
		delete_option( 'nfd_data_connection_attempts' );

		$this->assertFalse( get_option( 'nfd_data_token' ), 'Token should be gone after simulated wipe.' );

		// 4. Restore from the captured data.
		$restore_result = $this->call_restore_nfd_data( $data );
		$this->assertTrue( $restore_result['success'] );

		// 5. Verify values survived.
		$this->assertSame( 'round-trip-token-xyz', get_option( 'nfd_data_token' ) );
		$this->assertSame( '2.9.3', get_option( 'nfd_data_module_version' ) );
		$this->assertEquals( 3, get_option( 'nfd_data_connection_attempts' ) );
	}

	public function test_brand_version_round_trip() {
		$brand_id    = BrandConfig::get_brand_id();
		$version_key = $brand_id . '_plugin_version';
		update_option( $version_key, '4.13.1' );

		$data = ResetService::prepare()['data'];

		delete_option( $version_key );
		$this->assertFalse( get_option( $version_key ) );

		$this->call_restore_nfd_data( $data );

		$this->assertSame( '4.13.1', get_option( $version_key ) );
	}

	public function test_restore_nfd_data_uses_data_preserver() {
		$data = array(
			'nfd_data_token'                       => 'tok_123',
			'nfd_data_module_version'              => '1.2.3',
			'nfd_data_connection_attempts'         => 5,
			'nfd_data_connection_throttle'         => 'throttle',
			'nfd_data_connection_throttle_timeout' => time() + 60,
			'brand_plugin_version_option'          => 'brand_plugin_version',
			'brand_plugin_version'                 => '9.9.9',
		);

		$result = ResetDataPreserver::restore_nfd_data( $data );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'tok_123', get_option( 'nfd_data_token' ) );
	}

	// ------------------------------------------------------------------
	// Edge cases in restore_nfd_data()
	// ------------------------------------------------------------------

	public function test_restore_does_not_write_empty_token() {
		delete_option( 'nfd_data_token' );

		$this->call_restore_nfd_data( array( 'nfd_data_token' => '' ) );

		$this->assertFalse( get_option( 'nfd_data_token' ), 'An empty token should not be written to the DB.' );
	}

	public function test_restore_enables_coming_soon_mode() {
		delete_option( 'nfd_coming_soon' );

		$this->call_restore_nfd_data( array() );

		$this->assertTrue( (bool) get_option( 'nfd_coming_soon' ) );
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	/**
	 * Invoke the private restore_nfd_data() method via reflection.
	 *
	 * We test this private method directly because the only public method
	 * that calls it (execute()) drops all database tables.
	 *
	 * @param array $data Preservation data.
	 * @return array Step result.
	 */
	private function call_restore_nfd_data( array $data ): array {
		$method = new \ReflectionMethod( ResetService::class, 'restore_nfd_data' );
		$method->setAccessible( true );

		return $method->invoke( null, $data );
	}
}
