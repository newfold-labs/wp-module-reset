<?php

namespace NewfoldLabs\WP\Module\Reset;

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Reset\Admin\ToolsPage;
use NewfoldLabs\WP\Module\Reset\Api\RestApi;

/**
 * Factory Reset module main class.
 */
class Reset {

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Dependency injection container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		add_action( 'init', array( $this, 'load_textdomain' ), 100 );

		if ( is_admin() ) {
			new ToolsPage();
		}

		new RestApi();
	}

	/**
	 * Load the module text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-module-reset',
			false,
			dirname( plugin_basename( NFD_RESET_DIR ) ) . '/languages'
		);
	}
}
