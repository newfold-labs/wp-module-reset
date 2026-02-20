<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Api\RestApi;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test RestApi route registration.
 */
class RestApiWPUnitTest extends WPTestCase {

	public function test_constructor_adds_rest_api_init_action() {
		$api = new RestApi();

		$this->assertGreaterThan( 0, has_action( 'rest_api_init', array( $api, 'register_routes' ) ) );
	}

	public function test_register_routes_registers_factory_reset_endpoint() {
		$api = new RestApi();
		do_action( 'rest_api_init', rest_get_server() );

		$routes = rest_get_server()->get_routes();
		$route  = '/' . BrandConfig::get_rest_namespace() . '/factory-reset';

		$this->assertArrayHasKey( $route, $routes );
	}
}
