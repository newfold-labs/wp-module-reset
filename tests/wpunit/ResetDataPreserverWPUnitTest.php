<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetDataPreserver;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test ResetDataPreserver::capture() return structure and keys.
 */
class ResetDataPreserverWPUnitTest extends WPTestCase {

	public function test_capture_returns_expected_keys() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user   = get_user_by( 'id', $user_id );

		$data = ResetDataPreserver::capture( $user );

		$required = array(
			'blogname',
			'blog_public',
			'siteurl',
			'home',
			'wplang',
			'user_pass',
			'user_login',
			'user_email',
			'brand_basename',
			'nfd_data_token',
			'nfd_data_module_version',
			'nfd_data_connection_attempts',
			'nfd_data_connection_throttle',
			'nfd_data_connection_throttle_timeout',
			'brand_plugin_version_option',
			'brand_plugin_version',
		);
		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $data, "capture() must include key: $key" );
		}
	}

	public function test_capture_user_login_matches_current_user() {
		$user_id = self::factory()->user->create(
			array(
				'role'   => 'administrator',
				'user_login' => 'capture_test_user',
			)
		);
		$user = get_user_by( 'id', $user_id );

		$data = ResetDataPreserver::capture( $user );

		$this->assertSame( 'capture_test_user', $data['user_login'] );
		$this->assertSame( $user->user_email, $data['user_email'] );
	}

	public function test_capture_blogname_matches_option() {
		update_option( 'blogname', 'Test Site Name' );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user   = get_user_by( 'id', $user_id );

		$data = ResetDataPreserver::capture( $user );

		$this->assertSame( 'Test Site Name', $data['blogname'] );
	}

	public function test_restore_nfd_data_with_empty_data_enables_coming_soon() {
		delete_option( 'nfd_coming_soon' );

		ResetDataPreserver::restore_nfd_data( array() );

		$this->assertTrue( (bool) get_option( 'nfd_coming_soon' ) );
	}

	public function test_restore_nfd_data_restores_brand_version_when_provided() {
		$brand_id    = BrandConfig::get_brand_id();
		$version_key = $brand_id . '_plugin_version';
		delete_option( $version_key );

		ResetDataPreserver::restore_nfd_data(
			array(
				'brand_plugin_version_option' => $version_key,
				'brand_plugin_version'       => '5.0.0',
			)
		);

		$this->assertSame( '5.0.0', get_option( $version_key ) );
	}
}
