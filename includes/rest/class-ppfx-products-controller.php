<?php
/**
 * Products REST endpoint controller for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles the /products REST endpoint.
 */
class PPFX_Products_Controller {

	/**
	 * Get REST endpoint args.
	 *
	 * @return array
	 */
	public static function get_endpoint_args(): array {
		$args = array(
			'ppfx_page'        => array(
				'description'       => __(
					'Current page of the product loop.',
					'pickory-product-filter',
				),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			),
			'per_page'         => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => function ( $v ) {
					if ( empty( $v ) ) {
						return 0;
					}

					$max_per_page = (int) apply_filters( 'ppfx_rest_max_per_page', 100 );
					return min( max( absint( $v ), 1 ), $max_per_page );
				},
			),
			'instance_id'      => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'toolbar_missing'  => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'ppfx_category'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'ppfx_orderby'     => array(
				'type'              => 'string',
				'default'           => get_option(
					'woocommerce_default_catalog_orderby',
					'menu_order',
				),
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array(
					'menu_order',
					'popularity',
					'rating',
					'date',
					'price',
					'price-desc',
					'rand',
					'title',
					'id',
				),
			),
			'ppfx_order'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => function ( $v ) {
					return strtoupper( sanitize_text_field( (string) $v ) );
				},
				'enum'              => array( '', 'ASC', 'DESC', 'asc', 'desc' ),
			),
			'ppfx_min_price'   => array(
				'type'              => 'number',
				'sanitize_callback' => function ( $v ) {
					return max( 0.0, (float) $v );
				},
			),
			'ppfx_max_price'   => array(
				'type'              => 'number',
				'sanitize_callback' => function ( $v ) {
					return max( 0.0, (float) $v );
				},
			),
			'ppfx_product_tag' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'ppfx_rating'      => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'ppfx_on_sale'     => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		// Dynamically add attribute filters.
		$attribute_taxonomies = function_exists( 'wc_get_attribute_taxonomies' )
			? wc_get_attribute_taxonomies()
			: array();
		foreach ( $attribute_taxonomies as $tax ) {
			$args[ 'ppfx_filter_' . $tax->attribute_name ] = array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			);
		}

		// Add other public taxonomies.
		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->public && ! isset( $args[ 'ppfx_' . $tax->name ] ) ) {
				$args[ 'ppfx_' . $tax->name ] = array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				);
			}
		}

		return $args;
	}

	/**
	 * Get products endpoint callback.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_products( WP_REST_Request $request ) {
		// Rate limit (enable/disable logic lives inside is_rate_limited()).
		if ( PPFX_Rate_Limiter::is_rate_limited( 'ppfx_rate', 80 ) ) {
			return new WP_Error(
				'ppfx_rate_limited',
				__(
					'Too many requests. Please try again shortly.',
					'pickory-product-filter',
				),
				array( 'status' => 429 ),
			);
		}

		// Normalize public params to internal keys first.
		$normalized_params = PPFX_Utility::normalize_public_filter_params( $request );
		// UI transport state must never influence the query or its cache key.
		unset( $normalized_params['toolbar_missing'], $normalized_params['instance_id'] );

		// Get instance ID from request.
		$raw_instance_id = $request->get_param( 'instance_id' );
		$instance_id     = $raw_instance_id ? sanitize_key( (string) $raw_instance_id ) : '';
		$toolbar_missing = (bool) $request->get_param( 'toolbar_missing' );

		// Create a new request object with normalized params for cache key.
		$normalized_request = new WP_REST_Request();
		foreach ( $normalized_params as $key => $value ) {
			$normalized_request->set_param( $key, $value );
		}

		// instance_id only affects rendered markup and is intentionally excluded
		// from the data cache key so matching instances share cached query data.

		// Fragment caching is limited to requests without session-specific WooCommerce state.
		$has_dynamic_session = PPFX_Query_Cache::has_dynamic_session();
		$cache_key           = PPFX_Query_Cache::build_query_cache_key( $normalized_request );
		$query               = null;
		if ( ! $has_dynamic_session ) {
			$cached_data = wp_cache_get( $cache_key, 'pickory-product-filter' );
			$query       = is_array( $cached_data ) ? PPFX_Query_Cache::hydrate_query( $cached_data, $normalized_request ) : null;
		}

		if ( ! $query instanceof WP_Query ) {
			// Cache miss or dynamic session: run the full filtered query.
			$query = ( new PPFX_Product_Query_Builder( $normalized_request ) )->get_query();

			if ( ! $has_dynamic_session ) {
				wp_cache_set(
					$cache_key,
					PPFX_Query_Cache::get_cacheable_query_data( $query ),
					'pickory-product-filter',
					5 * MINUTE_IN_SECONDS
				);
			}
		}

		$fragments = PPFX_Fragment_Renderer::render( $query, $instance_id, $toolbar_missing );

		$response_data = array(
			'success'   => true,
			'loop'      => array(
				'has_results' => (int) $query->found_posts > 0,
			),
			'fragments' => $fragments,
		);

		$response = rest_ensure_response( $response_data );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
