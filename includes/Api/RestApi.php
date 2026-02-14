<?php

namespace NewfoldLabs\WP\Module\Reset\Api;

use NewfoldLabs\WP\Module\Reset\Api\Controllers\ResetController;

/**
 * Register REST API routes.
 */
final class RestApi {

	/**
	 * Constructor.
	 */
	public function __construct() {
		\add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$controller = new ResetController();
		$controller->register_routes();
	}
}
