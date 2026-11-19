<?php

namespace NewfoldLabs\WP\Module\Reset;

/**
 * Permissions and authorization utilities.
 */
final class Permissions {

	/**
	 * WordPress Admin capability.
	 */
	const ADMIN = 'manage_options';

	/**
	 * Confirm user is logged in and has admin capabilities.
	 *
	 * @return boolean
	 */
	public static function is_admin() {
		return \is_user_logged_in() && \current_user_can( self::ADMIN );
	}

	/**
	 * REST API permission callback for admin-only endpoints.
	 *
	 * @return boolean
	 */
	public static function rest_is_authorized_admin() {
		return \is_user_logged_in() && \current_user_can( self::ADMIN );
	}

	/**
	 * Whether the current request authenticated with a WordPress Application
	 * Password rather than an interactive (cookie) session.
	 *
	 * Core fires `application_password_did_authenticate` during
	 * `determine_current_user` when an Application Password authenticates a
	 * request, which runs before any REST route permission callback.
	 *
	 * @return boolean
	 */
	public static function is_application_password_auth() {
		return \did_action( 'application_password_did_authenticate' ) > 0;
	}
}
