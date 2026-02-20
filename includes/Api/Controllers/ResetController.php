<?php

namespace NewfoldLabs\WP\Module\Reset\Api\Controllers;

use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;
use NewfoldLabs\WP\Module\Reset\Permissions;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;

/**
 * REST controller for factory reset.
 */
class ResetController extends \WP_REST_Controller {

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'factory-reset';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = BrandConfig::get_rest_namespace();
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_reset' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'confirmation_url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'The site URL typed by the user for confirmation.',
					),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission() {
		if ( ! Permissions::rest_is_authorized_admin() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform a factory reset.', 'wp-module-reset' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Execute the factory reset.
	 *
	 * Unlike the ToolsPage flow (which uses two HTTP requests with a redirect),
	 * the REST API handles both phases in a single request: prepare() collects
	 * the preservation data while all plugins are still loaded, then execute()
	 * performs the destructive reset using that data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_reset( \WP_REST_Request $request ) {
		$confirmation_url = $request->get_param( 'confirmation_url' );
		$expected_url     = untrailingslashit( home_url() );
		$submitted_url    = untrailingslashit( $confirmation_url );

		if ( $expected_url !== $submitted_url ) {
			return new \WP_Error(
				'invalid_confirmation',
				__( 'The confirmation URL does not match your website URL.', 'wp-module-reset' ),
				array( 'status' => 400 )
			);
		}

		// Phase 1: Preserve critical data while all plugins are still loaded.
		$preparation = ResetService::prepare();

		if ( ! $preparation['success'] ) {
			return new \WP_Error(
				'reset_preparation_failed',
				! empty( $preparation['errors'] )
					? implode( ' ', $preparation['errors'] )
					: __( 'Failed to prepare for reset.', 'wp-module-reset' ),
				array( 'status' => 500 )
			);
		}

		// Phase 2: Execute the destructive reset with preserved data.
		$result = ResetService::execute( $preparation['data'], $preparation['steps'] );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( $result, 500 );
		}

		return new \WP_REST_Response( $result, 200 );
	}
}
