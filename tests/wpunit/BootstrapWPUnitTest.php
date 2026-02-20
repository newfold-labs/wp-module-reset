<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Reset;
use NewfoldLabs\WP\Module\Reset\ResetFeature;
use NewfoldLabs\WP\Module\Reset\Api\RestApi;
use WP_Forge\Options\Options;

// Define a stub Container if the real one is not available (it lives in
// the brand plugin, not in this module's vendor directory).
if ( ! class_exists( 'NewfoldLabs\WP\ModuleLoader\Container' ) ) {
	// phpcs:ignore
	class Container {}
	class_alias( Container::class, 'NewfoldLabs\WP\ModuleLoader\Container' );
}

/**
 * Test the module bootstrapping classes: Reset, ResetFeature, RestApi.
 */
class BootstrapWPUnitTest extends WPTestCase {

	// ------------------------------------------------------------------
	// Reset (main module class)
	// ------------------------------------------------------------------

	public function test_reset_constructor_registers_init_hook() {
		$container = new \NewfoldLabs\WP\ModuleLoader\Container();
		$reset     = new Reset( $container );

		$this->assertNotFalse(
			has_action( 'init', array( $reset, 'load_textdomain' ) ),
			'Constructor should register load_textdomain on the init hook.'
		);
	}

	public function test_reset_constructor_creates_rest_api() {
		$container = new \NewfoldLabs\WP\ModuleLoader\Container();

		// RestApi constructor adds rest_api_init hook.
		new Reset( $container );

		$this->assertNotFalse(
			has_action( 'rest_api_init' ),
			'Constructor should register RestApi rest_api_init hook.'
		);
	}

	public function test_load_textdomain_executes_without_error() {
		$container = new \NewfoldLabs\WP\ModuleLoader\Container();
		$reset     = new Reset( $container );

		// Calling load_textdomain should not throw.
		$reset->load_textdomain();
		$this->assertTrue( true );
	}

	public function test_reset_constructor_creates_tools_page_in_admin_context() {
		// Make is_admin() return true.
		set_current_screen( 'dashboard' );

		$container = new \NewfoldLabs\WP\ModuleLoader\Container();
		new Reset( $container );

		// ToolsPage constructor adds admin_menu hook.
		$this->assertNotFalse(
			has_action( 'admin_menu' ),
			'Constructor should create ToolsPage in admin context.'
		);

		// Restore.
		set_current_screen( 'front' );
	}

	// ------------------------------------------------------------------
	// RestApi
	// ------------------------------------------------------------------

	public function test_rest_api_constructor_hooks_rest_api_init() {
		$rest_api = new RestApi();

		$this->assertNotFalse(
			has_action( 'rest_api_init', array( $rest_api, 'register_routes' ) ),
			'RestApi constructor should hook register_routes on rest_api_init.'
		);
	}

	public function test_rest_api_register_routes_registers_factory_reset_endpoint() {
		// Fire register_routes inside the rest_api_init action context.
		$rest_api = new RestApi();

		// Reset the REST server and fire the action.
		global $wp_rest_server;
		$wp_rest_server = null;

		add_action(
			'rest_api_init',
			function () use ( $rest_api ) {
				$rest_api->register_routes();
			},
			20
		);

		$server = rest_get_server();
		do_action( 'rest_api_init', $server );

		$routes    = $server->get_routes();
		$namespace = \NewfoldLabs\WP\Module\Reset\Data\BrandConfig::get_rest_namespace();
		$route_key = '/' . $namespace . '/factory-reset';

		$this->assertArrayHasKey( $route_key, $routes );
	}

	// ------------------------------------------------------------------
	// ResetFeature
	// ------------------------------------------------------------------

	public function test_reset_feature_initialize_adds_plugins_loaded_hook() {
		$options = new Options( 'nfd_features_test' );
		$feature = new ResetFeature( $options );

		// ResetFeature has $value = true, so initialize() is called by the
		// parent constructor. initialize() adds a plugins_loaded action.
		$this->assertNotFalse(
			has_action( 'plugins_loaded' ),
			'initialize() should register a plugins_loaded action.'
		);
	}
}
