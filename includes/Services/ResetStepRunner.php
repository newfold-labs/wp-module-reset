<?php

namespace NewfoldLabs\WP\Module\Reset\Services;

/**
 * Shared helper for executing reset steps in a consistent, guarded way.
 *
 * This centralizes the try/catch behavior so individual services can focus on
 * their own logic while callers always receive a predictable step structure.
 */
class ResetStepRunner {

	/**
	 * Execute a reset step and normalize its result.
	 *
	 * @param callable $callback Step callback to invoke.
	 * @param mixed    ...$args  Arguments to pass to the callback.
	 * @return array {
	 *     Step result.
	 *
	 *     @type bool   $success Whether the step succeeded.
	 *     @type string $message Human-readable status or error message.
	 * }
	 */
	public static function run( $callback, ...$args ) {
		try {
			$result = \call_user_func_array( $callback, $args );

			// Allow existing step implementations that already return the
			// normalized structure to pass straight through.
			if ( \is_array( $result ) && array_key_exists( 'success', $result ) ) {
				return $result;
			}

			// Fallback: wrap truthy/falsy responses in a standard structure.
			$success = (bool) $result;

			return array(
				'success' => $success,
				'message' => $success ? '' : 'Step failed.',
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => 'Error: ' . $e->getMessage(),
			);
		}
	}
}

