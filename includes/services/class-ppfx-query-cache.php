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
	 * Get the price-display context that determines whether rendered fragments
	 * can be safely shared between requests.
	 *
	 * Third-party pricing integrations can add scalar values or replace the
	 * context through the ppfx_cache_key_context filter.
	 *
	 * @param WP_REST_Request|null $request Current REST request, when available.
	 * @return array<string, string>
	 */
	public static function get_cache_context( $request = null ): array {
		$context = array(
			'currency'    => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
			'locale'      => function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale(),
			'tax_display' => (string) get_option( 'woocommerce_tax_display_shop', '' ),
		);

		$context = apply_filters( 'ppfx_cache_key_context', $context, $request );

		return self::normalize_cache_context( $context );
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

		$known_keys = array_merge(
			array( 'page', 'per_page', 'orderby', 'order' ),
			PPFX_Utility::get_base_filter_keys()
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

		$normalized['context'] = self::get_cache_context( $request );

		ksort( $normalized );

		return 'ppfx_query_' . self::get_query_cache_version() . '_' . md5(
			(string) wp_json_encode( $normalized )
		);
	}

	/**
	 * Normalize extension-provided cache context to stable scalar values.
	 *
	 * @param mixed $context Raw cache context.
	 * @return array<string, string>
	 */
	private static function normalize_cache_context( $context ): array {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ( ! is_scalar( $value ) && null !== $value ) ) {
				continue;
			}

			$normalized[ $key ] = is_bool( $value )
				? ( $value ? '1' : '0' )
				: sanitize_text_field( (string) $value );
		}

		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Whether the current request has WooCommerce state that can alter rendered
	 * fragments, such as cart contents or session-specific pricing.
	 *
	 * @return bool
	 */
	public static function has_dynamic_session(): bool {
		if ( is_user_logged_in() || ! function_exists( 'WC' ) ) {
			return is_user_logged_in();
		}

		$woocommerce = WC();
		if ( ! is_object( $woocommerce ) ) {
			return false;
		}

		$session = isset( $woocommerce->session ) ? $woocommerce->session : null;
		if ( is_object( $session ) && method_exists( $session, 'has_session' ) && $session->has_session() ) {
			return true;
		}

		$cart = isset( $woocommerce->cart ) ? $woocommerce->cart : null;
		return is_object( $cart ) && method_exists( $cart, 'is_empty' ) && ! $cart->is_empty();
	}

	/**
	 * Recreate a page query from cached IDs while preserving the original
	 * result totals needed by WooCommerce's result count and pagination.
	 *
	 * @param array           $cached_data Cached page IDs and pagination metadata.
	 * @param WP_REST_Request $request Current REST request.
	 * @return WP_Query|null
	 */
	public static function hydrate_query( array $cached_data, WP_REST_Request $request ): ?WP_Query {
		if ( ! isset( $cached_data['ids'], $cached_data['found_posts'], $cached_data['max_num_pages'] ) || ! is_array( $cached_data['ids'] ) ) {
			return null;
		}

		$ids = array_values( array_filter( array_map( 'absint', $cached_data['ids'] ) ) );

		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( 0 === $per_page ) {
			$per_page = (int) apply_filters(
				'loop_shop_per_page',
				wc_get_default_products_per_row() * wc_get_default_product_rows_per_page(),
			);
		}

		$paged = max( 1, absint( $request->get_param( 'page' ) ) );
		// The cache contains only this page's IDs. Query them as a single page
		// so WP_Query does not apply the requested page offset a second time.
		$query = new WP_Query(
			array(
				'post_type'            => 'product',
				'post_status'          => 'publish',
				'post__in'             => empty( $ids ) ? array( 0 ) : $ids,
				'orderby'              => 'post__in',
				'posts_per_page'       => max( 1, count( $ids ) ),
				'paged'                => 1,
				'no_found_rows'        => true,
				'ignore_sticky_posts'  => true,
				'ppfx_is_filter_query' => false,
			)
		);

		// Restore display pagination metadata after the unpaged hydration query
		// has run, so WooCommerce renders the correct current page and totals.
		$query->set( 'posts_per_page', $per_page );
		$query->set( 'paged', $paged );
		$query->is_paged      = $paged > 1;
		$query->found_posts   = max( 0, (int) $cached_data['found_posts'] );
		$query->max_num_pages = max( 0, (int) $cached_data['max_num_pages'] );

		return $query;
	}

	/**
	 * Backwards-compatible alias for hydrate_query().
	 *
	 * @param array           $cached_data Cached page IDs and pagination metadata.
	 * @param WP_REST_Request $request Current REST request.
	 * @return WP_Query|null
	 */
	public static function hydrate_cached_query( array $cached_data, WP_REST_Request $request ): ?WP_Query {
		return self::hydrate_query( $cached_data, $request );
	}

	/**
	 * Extract the page-level query data that is safe to cache independently of
	 * rendered HTML and the request-specific WooCommerce presentation context.
	 *
	 * @param WP_Query $query Filtered product query.
	 * @return array{ids: array<int>, found_posts: int, max_num_pages: int}
	 */
	public static function get_cacheable_query_data( WP_Query $query ): array {
		return array(
			'ids'           => array_map( 'absint', wp_list_pluck( $query->posts, 'ID' ) ),
			'found_posts'   => max( 0, (int) $query->found_posts ),
			'max_num_pages' => max( 0, (int) $query->max_num_pages ),
		);
	}
}
