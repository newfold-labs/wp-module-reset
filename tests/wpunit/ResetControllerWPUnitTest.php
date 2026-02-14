<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Api\Controllers\ResetController;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test the REST API controller registration and permissions.
 */
class ResetControllerWPUnitTest extends WPTestCase {

	/**
	 * @var ResetController
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new ResetController();

		// Register routes through the proper hook so WP doesn't flag _doing_it_wrong.
		add_action(
			'rest_api_init',
			function () {
				$this->controller->register_routes();
			}
		);

		// Reset the REST server so it picks up our freshly-registered routes.
		global $wp_rest_server;
		$wp_rest_server = null;
		do_action( 'rest_api_init', rest_get_server() );
	}

	public function test_route_is_registered() {
		$routes    = rest_get_server()->get_routes();
		$namespace = BrandConfig::get_rest_namespace();
		$route     = '/' . $namespace . '/factory-reset';

		$this->assertArrayHasKey( $route, $routes );
	}

	public function test_route_namespace_is_dynamic() {
		$namespace = BrandConfig::get_rest_namespace();
		$brand_id  = BrandConfig::get_brand_id();

		$this->assertStringContainsString( $brand_id, $namespace );
		$this->assertStringContainsString( '/v1', $namespace );
	}

	public function test_route_requires_post_method() {
		$routes    = rest_get_server()->get_routes();
		$namespace = BrandConfig::get_rest_namespace();
		$route     = '/' . $namespace . '/factory-reset';

		$this->assertArrayHasKey( $route, $routes );

		$methods = $routes[ $route ][0]['methods'];
		$this->assertArrayHasKey( 'POST', $methods );
	}

	public function test_permission_callback_rejects_unauthenticated() {
		wp_set_current_user( 0 );

		$result = $this->controller->check_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_permission_callback_rejects_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = $this->controller->check_permission();

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_permission_callback_accepts_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = $this->controller->check_permission();

		$this->assertTrue( $result );
	}

	public function test_url_validation_mismatched_url_returns_error() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'POST', '/' . BrandConfig::get_rest_namespace() . '/factory-reset' );
		$request->set_param( 'confirmation_url', 'https://wrong-url.example.com' );

		$result = $this->controller->execute_reset( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_confirmation', $result->get_error_code() );
	}
}
