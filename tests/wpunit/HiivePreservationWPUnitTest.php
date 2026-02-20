<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test that Hiive connection data is correctly preserved and restored
 * across a factory reset.
 *
 * These tests exercise prepare() (non-destructive) and restore_nfd_data()
 * (via reflection, since it is private). We do NOT call execute() because
 * it drops all database tables.
 */
class HiivePreservationWPUnitTest extends WPTestCase {

	/**
	 * Admin user ID created for tests.
	 *
	 * @var int
	 */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	// ------------------------------------------------------------------
	// prepare() — verify Hiive keys are captured
	// ------------------------------------------------------------------

	public function test_prepare_data_contains_nfd_data_token() {
		update_option( 'nfd_data_token', 'test-token-abc123' );

		$result = ResetService::prepare();

		$this->assertArrayHasKey( 'nfd_data_token', $result['data'] );
		$this->assertSame( 'test-token-abc123', $result['data']['nfd_data_token'] );
	}

	public function test_prepare_data_contains_nfd_data_module_version() {
		update_option( 'nfd_data_module_version', '2.9.3' );

		$result = ResetService::prepare();

		$this->assertArrayHasKey( 'nfd_data_module_version', $result['data'] );
		$this->assertSame( '2.9.3', $result['data']['nfd_data_module_version'] );
	}

	public function test_prepare_data_contains_nfd_data_connection_attempts() {
		update_option( 'nfd_data_connection_attempts', 3 );

		$result = ResetService::prepare();

		$this->assertArrayHasKey( 'nfd_data_connection_attempts', $result['data'] );
	}

	public function test_prepare_data_contains_brand_plugin_version() {
		$brand_id     = BrandConfig::get_brand_id();
		$version_key  = $brand_id . '_plugin_version';
		update_option( $version_key, '4.13.1' );

		$result = ResetService::prepare();

		$this->assertArrayHasKey( 'brand_plugin_version_option', $result['data'] );
		$this->assertArrayHasKey( 'brand_plugin_version', $result['data'] );
		$this->assertSame( $version_key, $result['data']['brand_plugin_version_option'] );
		$this->assertSame( '4.13.1', $result['data']['brand_plugin_version'] );
	}

	public function test_prepare_data_contains_all_required_hiive_keys() {
		$expected_keys = array(
			'nfd_data_token',
			'nfd_data_module_version',
			'nfd_data_connection_attempts',
			'nfd_data_connection_throttle',
			'nfd_data_connection_throttle_timeout',
		);

		$result = ResetService::prepare();

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result['data'], "Missing key: $key" );
		}
	}

	// ------------------------------------------------------------------
	// restore_nfd_data() — verify options are written back to the DB
	// ------------------------------------------------------------------

	public function test_restore_nfd_data_restores_token() {
		$data = array( 'nfd_data_token' => 'restored-token-xyz' );

		$result = $this->invoke_restore_nfd_data( $data );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'restored-token-xyz', get_option( 'nfd_data_token' ) );
	}

	public function test_restore_nfd_data_restores_module_version() {
		$data = array( 'nfd_data_module_version' => '2.9.3' );

		$this->invoke_restore_nfd_data( $data );

		$this->assertSame( '2.9.3', get_option( 'nfd_data_module_version' ) );
	}

	public function test_restore_nfd_data_restores_connection_attempts() {
		$data = array( 'nfd_data_connection_attempts' => 5 );

		$this->invoke_restore_nfd_data( $data );

		$this->assertEquals( 5, get_option( 'nfd_data_connection_attempts' ) );
	}

	public function test_restore_nfd_data_restores_brand_plugin_version() {
		$data = array(
			'brand_plugin_version_option' => 'bluehost_plugin_version',
			'brand_plugin_version'        => '4.13.1',
		);

		$this->invoke_restore_nfd_data( $data );

		$this->assertSame( '4.13.1', get_option( 'bluehost_plugin_version' ) );
	}

	public function test_restore_nfd_data_enables_coming_soon() {
		$this->invoke_restore_nfd_data( array() );

		$this->assertTrue( (bool) get_option( 'nfd_coming_soon' ) );
	}

	public function test_restore_nfd_data_skips_empty_token() {
		delete_option( 'nfd_data_token' );

		$this->invoke_restore_nfd_data( array( 'nfd_data_token' => '' ) );

		$this->assertFalse( get_option( 'nfd_data_token' ) );
	}

	public function test_restore_nfd_data_returns_success() {
		$result = $this->invoke_restore_nfd_data( array( 'nfd_data_token' => 'tok' ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Round-trip: prepare captures → restore_nfd_data writes back
	// ------------------------------------------------------------------

	public function test_round_trip_token_survives_prepare_then_restore() {
		update_option( 'nfd_data_token', 'round-trip-token' );
		update_option( 'nfd_data_module_version', '2.9.3' );

		$result = ResetService::prepare();
		$data   = $result['data'];

		// Simulate the DB being wiped (delete the options).
		delete_option( 'nfd_data_token' );
		delete_option( 'nfd_data_module_version' );
		$this->assertFalse( get_option( 'nfd_data_token' ) );

		// Restore from the captured data.
		$this->invoke_restore_nfd_data( $data );

		$this->assertSame( 'round-trip-token', get_option( 'nfd_data_token' ) );
		$this->assertSame( '2.9.3', get_option( 'nfd_data_module_version' ) );
	}

	public function test_round_trip_brand_version_survives_prepare_then_restore() {
		$brand_id    = BrandConfig::get_brand_id();
		$version_key = $brand_id . '_plugin_version';
		update_option( $version_key, '4.13.1' );

		$result = ResetService::prepare();
		$data   = $result['data'];

		delete_option( $version_key );
		$this->assertFalse( get_option( $version_key ) );

		$this->invoke_restore_nfd_data( $data );

		$this->assertSame( '4.13.1', get_option( $version_key ) );
	}

	// ------------------------------------------------------------------
	// execute() step ordering — verify restore_nfd_data runs before
	// restore_values (which triggers plugin activation hooks).
	// ------------------------------------------------------------------

	public function test_execute_step_ordering_nfd_data_before_values() {
		// Read the execute() method source to verify the step order.
		// This is a structural test that will fail if someone reorders
		// the steps, alerting them to the ordering requirement.
		$reflector  = new \ReflectionMethod( ResetService::class, 'execute' );
		$filename   = $reflector->getFileName();
		$start_line = $reflector->getStartLine();
		$end_line   = $reflector->getEndLine();
		$source     = implode( '', array_slice( file( $filename ), $start_line - 1, $end_line - $start_line + 1 ) );

		$nfd_pos    = strpos( $source, "steps['restore_nfd_data']" );
		$values_pos = strpos( $source, "steps['restore_values']" );

		$this->assertNotFalse( $nfd_pos, 'restore_nfd_data step must exist in execute()' );
		$this->assertNotFalse( $values_pos, 'restore_values step must exist in execute()' );
		$this->assertLessThan(
			$values_pos,
			$nfd_pos,
			'restore_nfd_data MUST run before restore_values (which calls activate_plugin). '
			. 'The Hiive token must be in the database before plugin activation hooks fire.'
		);
	}

	// ------------------------------------------------------------------
	// REST controller — verify it calls prepare() before execute().
	// ------------------------------------------------------------------

	public function test_controller_source_calls_prepare_before_execute() {
		$reflector  = new \ReflectionMethod(
			\NewfoldLabs\WP\Module\Reset\Api\Controllers\ResetController::class,
			'execute_reset'
		);
		$filename   = $reflector->getFileName();
		$start_line = $reflector->getStartLine();
		$end_line   = $reflector->getEndLine();
		$source     = implode( '', array_slice( file( $filename ), $start_line - 1, $end_line - $start_line + 1 ) );

		$prepare_pos = strpos( $source, 'ResetService::prepare()' );
		$execute_pos = strpos( $source, 'ResetService::execute(' );

		$this->assertNotFalse( $prepare_pos, 'Controller must call ResetService::prepare()' );
		$this->assertNotFalse( $execute_pos, 'Controller must call ResetService::execute()' );
		$this->assertLessThan(
			$execute_pos,
			$prepare_pos,
			'Controller must call prepare() before execute(). '
			. 'Without prepare(), no Hiive data is captured and the connection is lost.'
		);
	}

	// ------------------------------------------------------------------
	// Helper: invoke the private restore_nfd_data() via reflection.
	// ------------------------------------------------------------------

	/**
	 * @param array $data Data array to pass to restore_nfd_data().
	 * @return array Step result from the method.
	 */
	private function invoke_restore_nfd_data( array $data ): array {
		$method = new \ReflectionMethod( ResetService::class, 'restore_nfd_data' );
		$method->setAccessible( true );

		return $method->invoke( null, $data );
	}
}
