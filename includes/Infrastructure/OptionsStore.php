<?php

namespace NewfoldLabs\WP\Module\Reset\Infrastructure;

/**
 * Thin wrapper around core WordPress option and transient APIs.
 *
 * This makes it easier for developers to discover where persistent state is
 * read and written during a reset, and provides a single place to adapt or
 * mock these interactions in tests.
 */
class OptionsStore {

	/**
	 * Get an option value.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value if the option does not exist.
	 * @return mixed
	 */
	public static function get( $name, $default = false ) {
		return get_option( $name, $default );
	}

	/**
	 * Update an option value.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public static function set( $name, $value ) {
		return update_option( $name, $value );
	}

	/**
	 * Get a transient value.
	 *
	 * @param string $name    Transient name.
	 * @param mixed  $default Default when missing.
	 * @return mixed
	 */
	public static function get_transient( $name, $default = false ) {
		$value = get_transient( $name );

		return ( false === $value ) ? $default : $value;
	}

	/**
	 * Set a transient value.
	 *
	 * @param string $name   Transient name.
	 * @param mixed  $value  Value to store.
	 * @param int    $expiry Expiry in seconds.
	 * @return bool
	 */
	public static function set_transient( $name, $value, $expiry ) {
		return set_transient( $name, $value, $expiry );
	}
}

