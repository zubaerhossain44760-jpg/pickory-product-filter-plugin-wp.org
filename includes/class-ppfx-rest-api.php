<?php
/**
 * REST API registration for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require necessary service and rest classes.
require_once PPFX_PLUGIN_DIR . 'includes/services/class-ppfx-rate-limiter.php';
require_once PPFX_PLUGIN_DIR . 'includes/services/class-ppfx-product-query-builder.php';
require_once PPFX_PLUGIN_DIR . 'includes/rest/class-ppfx-products-controller.php';

/**
 * Registers REST API routes for Pickory Product Filter.
 */
class PPFX_Rest_Api {


	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'pickory-filter/v1',
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'PPFX_Products_Controller', 'get_products' ),
				'permission_callback' => '__return_true',
				'args'                => PPFX_Products_Controller::get_endpoint_args(),
			)
		);
	}
}
