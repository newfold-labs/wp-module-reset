<?php

namespace NewfoldLabs\WP\Module\Reset\Services;

/**
 * Silent upgrader skin that captures messages and errors
 * instead of printing them to the browser.
 */
class SilentUpgraderSkin extends \WP_Upgrader_Skin {

	/**
	 * Captured feedback messages.
	 *
	 * @var string[]
	 */
	public $messages = array();

	/**
	 * Captured error messages.
	 *
	 * @var string[]
	 */
	public $errors_captured = array();

	/**
	 * Capture feedback instead of printing it.
	 *
	 * @param string $feedback Message.
	 * @param mixed  ...$args  Optional sprintf args.
	 */
	public function feedback( $feedback, ...$args ) {
		if ( is_string( $feedback ) && isset( $this->upgrader->strings[ $feedback ] ) ) {
			$feedback = $this->upgrader->strings[ $feedback ];
		}
		if ( $args ) {
			$feedback = vsprintf( $feedback, $args );
		}
		$this->messages[] = wp_strip_all_tags( (string) $feedback );
	}

	/**
	 * Capture errors instead of printing them.
	 *
	 * @param string|\WP_Error $errors Error message or WP_Error.
	 */
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			foreach ( $errors->get_error_messages() as $msg ) {
				$this->errors_captured[] = $msg;
			}
		} elseif ( is_string( $errors ) ) {
			$this->errors_captured[] = $errors;
		}
	}
}
