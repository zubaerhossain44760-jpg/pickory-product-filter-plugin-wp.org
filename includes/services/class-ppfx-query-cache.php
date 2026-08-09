<?php
/**
 * Query result caching for Pickory Product Filter REST endpoint.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Builds stable cache keys and manages the query cache version.
 *
 * Separated from PPFX_Product_Query_Builder so that cache concerns
 * (key normalisation, version bumping) can evolve independently of the
 * SQL query construction logic.
 */
class PPFX_Query_Cache {

	/**
	 * Retrieves the current query cache version.
	 *
	 * This is used to invalidate both object cache and client-side cache
	 * when product data changes. The version is bumped by PPFX_Index whenever
	 * a product is synced or deleted.
	 *
	 * @return string The current cache version.
	 */
	public static function get_query_cache_version(): string {
		return get_option( 'ppfx_query_cache_version', '1' );
	}

	/**
	 * Build a stable object-cache key for a REST product query.
	 *
	 * Normalises all known filter parameters (page, price, rating, attributes,
	 * public taxonomies) and encodes them as a sorted JSON string before
	 * hashing, ensuring identical filter sets always produce the same key
	 * regardless of param ordering in the original request.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return string Cache key string prefixed with 'ppfx_query_'.
	 */
	public static function build_query_cache_key( WP_REST_Request $request ): string {
		$params     = $request->get_params();
		$normalized = array();

		$known_keys = array(
			'page',
			'per_page',
			'category',
			'product_tag',
			'orderby',
			'order',
			'min_price',
			'max_price',
			'rating',
			'on_sale',
			'instance_id',
		);

		foreach ( $known_keys as $key ) {
			if (
				isset( $params[ $key ] ) &&
				'' !== $params[ $key ]
			) {
				$value = wp_unslash( $params[ $key ] );
				if ( in_array( $key, array( 'page', 'per_page', 'on_sale' ), true ) ) {
					$normalized[ $key ] = (int) $value;
				} elseif ( in_array( $key, array( 'min_price', 'max_price', 'rating' ), true ) ) {
					$normalized[ $key ] = (float) $value;
				} else {
					$normalized[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		foreach ( $params as $key => $value ) {
			if (
				str_starts_with( $key, 'filter_' ) &&
				'' !== $value &&
				null !== $value
			) {
				$attr_name = sanitize_title( substr( $key, 7 ) );
				$taxonomy  = wc_attribute_taxonomy_name( $attr_name );

				if ( taxonomy_exists( $taxonomy ) ) {
					$normalized[ $key ] = sanitize_text_field( wp_unslash( $value ) );
				}
			}
		}

		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $tax ) {
			if (
				$tax->public &&
				isset( $params[ $tax->name ] ) &&
				'' !== $params[ $tax->name ]
			) {
				$normalized[ $tax->name ] = sanitize_text_field(
					wp_unslash( $params[ $tax->name ] )
				);
			}
		}

		ksort( $normalized );

		return 'ppfx_query_' . self::get_query_cache_version() . '_' . md5(
			(string) wp_json_encode( $normalized )
		);
	}
}
