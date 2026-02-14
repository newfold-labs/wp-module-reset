<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;

/**
 * Test non-destructive ResetService methods.
 *
 * We explicitly do NOT test execute() in automated tests because it
 * drops all database tables. That path is covered by the manual UAT plan.
 */
class ResetServiceWPUnitTest extends WPTestCase {

	public function test_prepare_returns_error_when_user_lacks_manage_options() {
		wp_set_current_user( 0 );

		$result = ResetService::prepare();

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Unauthorized', $result['errors'][0] );
	}

	public function test_prepare_returns_error_for_non_admin_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = ResetService::prepare();

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Unauthorized', $result['errors'][0] );
	}

	public function test_prepare_result_structure_has_expected_keys() {
		wp_set_current_user( 0 );

		$result = ResetService::prepare();

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'steps', $result );
		$this->assertArrayHasKey( 'errors', $result );
	}

	public function test_prepare_errors_is_array() {
		wp_set_current_user( 0 );

		$result = ResetService::prepare();

		$this->assertIsArray( $result['errors'] );
	}

	public function test_prepare_data_is_array() {
		wp_set_current_user( 0 );

		$result = ResetService::prepare();

		$this->assertIsArray( $result['data'] );
	}

	public function test_prepare_steps_is_array() {
		wp_set_current_user( 0 );

		$result = ResetService::prepare();

		$this->assertIsArray( $result['steps'] );
	}
}
