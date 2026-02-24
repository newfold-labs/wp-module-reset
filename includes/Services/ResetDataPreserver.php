<?php

namespace NewfoldLabs\WP\Module\Reset\Services;

use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Encapsulates capture and restoration of options that must survive a reset.
 *
 * This keeps data-shape concerns out of the main ResetService orchestration and
 * makes it easier for developers to reason about what is preserved.
 */
class ResetDataPreserver {

	/**
	 * Capture all values that should be preserved before Phase 2.
	 *
	 * @param \WP_User $current_user The admin user performing the reset.
	 * @return array
	 */
	public static function capture( \WP_User $current_user ) {
		$brand_basename = BrandConfig::get_brand_plugin_basename();

		$brand_id             = BrandConfig::get_brand_id();
		$brand_version_option = $brand_id . '_plugin_version';

		return array(
			'blogname'                             => get_option( 'blogname' ),
			'blog_public'                          => get_option( 'blog_public' ),
			'siteurl'                              => get_option( 'siteurl' ),
			'home'                                 => get_option( 'home' ),
			'wplang'                               => get_option( 'WPLANG' ),
			'user_pass'                            => $current_user->user_pass,
			'user_login'                           => $current_user->user_login,
			'user_email'                           => $current_user->user_email,
			'brand_basename'                       => $brand_basename,

			// Hiive / NFD data options (preserved across reset).
			'nfd_data_token'                       => get_option( 'nfd_data_token' ),
			'nfd_data_module_version'              => get_option( 'nfd_data_module_version' ),
			'nfd_data_connection_attempts'         => get_option( 'nfd_data_connection_attempts' ),
			'nfd_data_connection_throttle'         => get_option( '_transient_nfd_data_connection_throttle' ),
			'nfd_data_connection_throttle_timeout' => get_option( '_transient_timeout_nfd_data_connection_throttle' ),

			// Brand plugin version (prevents upgrade handler from re-running
			// all upgrade routines on the first post-reset page load).
			'brand_plugin_version_option'          => $brand_version_option,
			'brand_plugin_version'                 => get_option( $brand_version_option ),
		);
	}

	/**
	 * Restore Hiive / NFD data and brand plugin version after database reset.
	 *
	 * @param array $data Preservation payload from Phase 1.
	 * @return array Step result (success/message).
	 */
	public static function restore_nfd_data( array $data ) {
		$restored = array();

		// Restore Hiive auth token.
		if ( ! empty( $data['nfd_data_token'] ) ) {
			update_option( 'nfd_data_token', $data['nfd_data_token'] );
			$restored[] = 'nfd_data_token';
		}

		// Restore module version (for upgrade handler).
		if ( ! empty( $data['nfd_data_module_version'] ) ) {
			update_option( 'nfd_data_module_version', $data['nfd_data_module_version'] );
			$restored[] = 'nfd_data_module_version';
		}

		// Restore connection attempts counter.
		if ( ! empty( $data['nfd_data_connection_attempts'] ) ) {
			update_option( 'nfd_data_connection_attempts', $data['nfd_data_connection_attempts'] );
			$restored[] = 'nfd_data_connection_attempts';
		}

		// Restore connection throttle transient.
		if ( ! empty( $data['nfd_data_connection_throttle'] ) && ! empty( $data['nfd_data_connection_throttle_timeout'] ) ) {
			$remaining = (int) $data['nfd_data_connection_throttle_timeout'] - time();
			if ( $remaining > 0 ) {
				set_transient( 'nfd_data_connection_throttle', $data['nfd_data_connection_throttle'], $remaining );
				$restored[] = 'nfd_data_connection_throttle';
			}
		}

		// Restore brand plugin version (prevents upgrade handler re-run).
		if ( array_key_exists( 'brand_plugin_version_option', $data ) && '' !== (string) $data['brand_plugin_version'] ) {
			update_option( $data['brand_plugin_version_option'], $data['brand_plugin_version'] );
			$restored[] = $data['brand_plugin_version_option'];
		}

		// Enable coming soon mode (required for onboarding to trigger).
		update_option( 'nfd_coming_soon', true );
		$restored[] = 'nfd_coming_soon';

		return array(
			'success' => true,
			'message' => 'Restored hosting connection data and token.',
		);
	}
}

