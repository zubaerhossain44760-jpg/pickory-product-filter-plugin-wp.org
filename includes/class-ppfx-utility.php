<?php
/**
 * Shared utility functions for PPFX plugin.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for shared helper methods.
 */
class PPFX_Utility {

	/**
	 * Normalizes public filter params to internal keys.
	 *
	 * Converts public URL parameters (ppfx_*) to internal filter keys
	 * used by the plugin. This ensures consistency between PHP and
	 * JavaScript hash generation.
	 *
	 * @param array|WP_REST_Request $params Raw params from URL, request, or REST request object.
	 * @return array Normalized params with internal keys.
	 */
	public static function normalize_public_filter_params( $params ) {
		// Handle WP_REST_Request objects.
		if ( $params instanceof WP_REST_Request ) {
			$params = $params->get_params();
		}

		// Ensure we're working with an array.
		if ( ! is_array( $params ) ) {
			return array();
		}
		$map = array(
			'ppfx_category'    => 'category',
			'ppfx_product_tag' => 'product_tag',
			'ppfx_page'        => 'page',
			'ppfx_orderby'     => 'orderby',
			'ppfx_order'       => 'order',
			'ppfx_min_price'   => 'min_price',
			'ppfx_max_price'   => 'max_price',
			'ppfx_rating'      => 'rating',
			'ppfx_on_sale'     => 'on_sale',
		);

		// Convert known ppfx_* params to internal keys.
		foreach ( $map as $public_key => $internal_key ) {
			if ( isset( $params[ $public_key ] ) && ! isset( $params[ $internal_key ] ) ) {
				$params[ $internal_key ] = $params[ $public_key ];
			}
			unset( $params[ $public_key ] );
		}

		// Handle filter_* params that start with ppfx_filter_.
		foreach ( $params as $key => $value ) {
			if ( strpos( $key, 'ppfx_filter_' ) === 0 ) {
				$internal_key = str_replace( 'ppfx_filter_', 'filter_', $key );
				if ( ! isset( $params[ $internal_key ] ) ) {
					$params[ $internal_key ] = $value;
				}
				unset( $params[ $key ] );
			}
		}

		// Remove any remaining unrecognized ppfx_* params that JS will also drop.
		// This ensures PHP and JS hash the same set of keys.
		foreach ( $params as $key => $value ) {
			if ( strpos( $key, 'ppfx_' ) === 0 ) {
				unset( $params[ $key ] );
			}
		}

		return $params;
	}
}
