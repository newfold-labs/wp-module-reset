<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Permissions;

/**
 * Test the Permissions utility class.
 */
class PermissionsWPUnitTest extends WPTestCase {

	public function test_admin_constant_equals_manage_options() {
		$this->assertSame( 'manage_options', Permissions::ADMIN );
	}

	public function test_is_admin_returns_false_when_not_logged_in() {
		wp_set_current_user( 0 );
		$this->assertFalse( Permissions::is_admin() );
	}

	public function test_is_admin_returns_true_for_admin_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( Permissions::is_admin() );
	}

	public function test_is_admin_returns_false_for_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( Permissions::is_admin() );
	}

	public function test_rest_is_authorized_admin_returns_false_when_not_logged_in() {
		wp_set_current_user( 0 );
		$this->assertFalse( Permissions::rest_is_authorized_admin() );
	}

	public function test_rest_is_authorized_admin_returns_true_for_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( Permissions::rest_is_authorized_admin() );
	}

	public function test_rest_is_authorized_admin_returns_false_for_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( Permissions::rest_is_authorized_admin() );
	}
}
