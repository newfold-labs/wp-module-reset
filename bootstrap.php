<?php

namespace NewfoldLabs\WP\Module\Reset;

// Set Global Constants
if ( ! defined( 'NFD_RESET_VERSION' ) ) {
	define( 'NFD_RESET_VERSION', '1.0.0' );
}
if ( ! defined( 'NFD_RESET_DIR' ) ) {
	define( 'NFD_RESET_DIR', __DIR__ );
}

// Register the ResetFeature class in the features filter
if ( function_exists( 'add_filter' ) ) {
	add_filter(
		'newfold/features/filter/register',
		function ( $features ) {
			return array_merge( $features, array( ResetFeature::class ) );
		}
	);
}
