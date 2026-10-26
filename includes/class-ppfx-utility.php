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
			'ppfx_product_cat' => 'category',
			'product_cat'      => 'category',
			'ppfx_product_tag' => 'product_tag',
			'ppfx_tag'         => 'product_tag',
			'tag'              => 'product_tag',
			'ppfx_page'        => 'page',
			'paged'            => 'page',
			'ppfx_orderby'     => 'orderby',
			'ppfx_order'       => 'order',
			'ppfx_min_price'   => 'min_price',
			'ppfx_max_price'   => 'max_price',
			'ppfx_rating'      => 'rating',
			'ppfx_on_sale'     => 'on_sale',
		);

		// Convert known public/synonym params to internal keys.
		foreach ( $map as $public_key => $internal_key ) {
			if ( isset( $params[ $public_key ] ) && ( ! isset( $params[ $internal_key ] ) || '' === $params[ $internal_key ] ) ) {
				$params[ $internal_key ] = $params[ $public_key ];
			}
			unset( $params[ $public_key ] );
		}

		// Handle ppfx_filter_* and any remaining ppfx_* params (e.g. custom taxonomies) in a single pass.
		foreach ( $params as $key => $value ) {
			if ( str_starts_with( $key, 'ppfx_' ) ) {
				$internal_key = str_starts_with( $key, 'ppfx_filter_' )
					? 'filter_' . substr( $key, 12 )
					: substr( $key, 5 );

				if ( '' !== $internal_key && ( ! isset( $params[ $internal_key ] ) || '' === $params[ $internal_key ] ) ) {
					$params[ $internal_key ] = $value;
				}
				unset( $params[ $key ] );
			}
		}

		// Standardize parameters (arrays, comma-separated lists, and single scalars).
		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				$clean_values = array_filter( array_map( 'sanitize_text_field', $value ) );
				sort( $clean_values );
				$params[ $key ] = implode( ',', array_unique( $clean_values ) );
			} elseif ( is_string( $value ) && str_contains( $value, ',' ) ) {
				$clean_values = array_filter( array_map( 'trim', explode( ',', $value ) ) );
				$clean_values = array_filter( array_map( 'sanitize_text_field', $clean_values ) );
				sort( $clean_values );
				$params[ $key ] = implode( ',', array_unique( $clean_values ) );
			} elseif ( is_string( $value ) ) {
				$params[ $key ] = sanitize_text_field( trim( $value ) );
			}
		}

		return $params;
	}

	/**
	 * Return the canonical list of base PPFX filter parameter names.
	 * All other filter key sources (assets, cache, server-filter) must derive from this.
	 *
	 * @return string[]
	 */
	public static function get_base_filter_keys(): array {
		return array(
			'category',
			'product_tag',
			'min_price',
			'max_price',
			'rating',
			'on_sale',
		);
	}

	/**
	 * Get the public URL keys supported by the product filter.
	 *
	 * @return string[]
	 */
	public static function get_filter_keys(): array {
		static $keys = null;

		if ( null !== $keys ) {
			return $keys;
		}

		$keys = array();
		foreach ( self::get_base_filter_keys() as $base_key ) {
			$keys[] = 'ppfx_' . $base_key;
		}

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $taxonomy ) {
					$keys[] = 'ppfx_filter_' . $taxonomy->attribute_name;
				}
			}
		}

		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->public && ! in_array( 'ppfx_' . $tax->name, $keys, true ) ) {
				$keys[] = 'ppfx_' . $tax->name;
			}
		}

		return $keys;
	}

	/**
	 * Helper to determine input name based on section key.
	 *
	 * @param string $key The section key.
	 * @return string
	 */
	public static function get_input_name( string $key ): string {
		if ( 'category' === $key ) {
			return 'category';
		}
		if ( 'product_tag' === $key ) {
			return 'product_tag';
		}
		if ( 'price' === $key ) {
			return 'price';
		}
		if ( 'rating' === $key ) {
			return 'rating';
		}
		if ( 'sale' === $key ) {
			return 'on_sale';
		}
		if ( str_starts_with( $key, 'pa_' ) ) {
			return 'filter_' . substr( $key, 3 );
		}
		return $key;
	}
}
