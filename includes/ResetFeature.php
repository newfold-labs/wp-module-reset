<?php

namespace NewfoldLabs\WP\Module\Reset;

use function NewfoldLabs\WP\ModuleLoader\container as getContainer;

/**
 * Factory Reset feature registration.
 */
class ResetFeature extends \NewfoldLabs\WP\Module\Features\Feature {
	/**
	 * The feature name.
	 *
	 * @var string
	 */
	protected $name = 'reset';

	/**
	 * The feature value. Defaults to on.
	 *
	 * @var boolean
	 */
	protected $value = true;

	/**
	 * Initialize the reset feature.
	 */
	public function initialize() {
		if ( function_exists( 'add_action' ) ) {
			add_action(
				'plugins_loaded',
				function () {
					new Reset( getContainer() );
				}
			);
		}
	}
}
