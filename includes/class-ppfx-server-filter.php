<?php
/**
 * Server-side product filtering for WooCommerce main query (SSR).
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WooCommerce's main product query and modifies it based on URL params.
 */
class PPFX_Server_Filter {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'request', array( $this, 'normalize_shop_request_query_vars' ), 1 );
		add_filter( 'pre_handle_404', array( $this, 'prevent_filtered_shop_404' ), 10, 2 );
		add_action( 'woocommerce_product_query', array( $this, 'modify_main_query' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_canonical_redirect' ), 10, 1 );
	}

	/**
	 * Register PPFX filter params as public query vars (NOT WC endpoints).
	 *
	 * WC endpoints are reserved for account/checkout sub-routes (order-pay, view-order, etc).
	 * Filter params are just query string data that needs to survive WP_Query sanitization.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( $vars ) {
		$ppfx_vars = array(
			'ppfx_category',
			'ppfx_product_tag',
			'ppfx_on_sale',
			'ppfx_min_price',
			'ppfx_max_price',
			'ppfx_rating',
			'ppfx_page',
			'ppfx_orderby',
			'ppfx_order',
		);

		// Merge with existing vars.
		$vars = array_merge( $vars, $ppfx_vars );

		// Register dynamic attribute filter vars (ppfx_filter_*).
		$attribute_taxonomies = function_exists( 'wc_get_attribute_taxonomies' )
			? wc_get_attribute_taxonomies()
			: array();
		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $taxonomy ) {
				$key    = 'ppfx_filter_' . $taxonomy->attribute_name;
				$vars[] = $key;
			}
		}

		// Register other public product taxonomy filter vars (ppfx_*).
		// Mirrors get_request_filter_vars() and REST endpoint args.
		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if (
				$taxonomy->public &&
				! in_array( $taxonomy->name, array( 'product_cat', 'product_tag' ), true )
			) {
				$vars[] = 'ppfx_' . $taxonomy->name;
			}
		}

		return $vars;
	}

	/**
	 * Prevent canonical redirect from stripping PPFX filter params.
	 *
	 * WordPress's redirect_canonical() may redirect filtered URLs like /shop/?ppfx_category=t-shirt
	 * back to /shop/, losing the filter params. This method disables the redirect when
	 * PPFX params are present in the URL.
	 *
	 * @param string $redirect_url  The computed redirect URL.
	 * @param string $requested_url The original requested URL.
	 * @return string|false The redirect URL, or false to prevent redirect.
	 */
	public function prevent_canonical_redirect( $redirect_url, $requested_url = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL params.
		if ( empty( $_GET ) ) {
			return $redirect_url;
		}

		$ppfx_params = $this->get_request_filter_vars();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL params.
		foreach ( $_GET as $key => $value ) {
			if (
				in_array( $key, $ppfx_params, true ) ||
				str_starts_with( $key, 'ppfx_filter_' ) ||
				str_starts_with( $key, 'ppfx_' )
			) {
				return false;
			}
		}

		return $redirect_url;
	}

	/**
	 * Apply PPFX filters to the WooCommerce main product query.
	 *
	 * @param WP_Query $query WooCommerce product query.
	 * @return void
	 */
	public function modify_main_query( $query ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! ( is_shop() || is_product_category() || is_product_tag() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL filter params.
		$raw_params = PPFX_Utility::normalize_public_filter_params( wp_unslash( $_GET ) );

		$excluded = array( 'post_type', 'paged', 'page', 's', 'orderby', 'order' );
		foreach ( $excluded as $key ) {
			unset( $raw_params[ $key ] );
		}

		$ppfx_page = isset( $_GET['ppfx_page'] ) ? absint( $_GET['ppfx_page'] ) : 1;
		if ( $ppfx_page > 1 ) {
			$query->set( 'paged', $ppfx_page );
		}

		if ( empty( $raw_params ) ) {
			return;
		}

		if ( is_product_category() && empty( $raw_params['category'] ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$raw_params['category'] = $term->slug;
			}
		} elseif ( is_product_tag() && empty( $raw_params['product_tag'] ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$raw_params['product_tag'] = $term->slug;
			}
		}

		$raw_params['per_page'] = -1;
		$raw_params['page']     = 1;

		$builder = new PPFX_Product_Query_Builder( $raw_params );
		$result  = $builder->get_query( true );
		$ids     = ! empty( $result->posts ) ? array_map( 'intval', $result->posts ) : array();

		if ( empty( $ids ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 'post__in', $ids );

		if ( empty( $raw_params['orderby'] ) ) {
			$query->set( 'orderby', 'post__in' );
		}
	}

	/**
	 * Remove PPFX taxonomy filters from WordPress's shop page routing query.
	 *
	 * Query vars such as product_tag are real taxonomy query vars in WordPress.
	 * On /shop/?product_tag=term, WP can try to resolve the shop page with a
	 * taxonomy constraint and mark the page as 404 before WooCommerce renders.
	 * Keep $_GET intact for PPFX filtering, but do not let those vars affect page
	 * resolution for the configured shop page.
	 *
	 * @param array $query_vars Parsed public query vars.
	 * @return array Modified query vars.
	 */
	public function normalize_shop_request_query_vars( $query_vars ) {
		if ( is_admin() ) {
			return $query_vars;
		}

		// Check if this is a shop page request first.
		$is_shop_page = $this->is_shop_page_request_query( $query_vars );

		// Check for category filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL param presence check.
		$has_category = isset( $_GET['category'] ) || isset( $query_vars['category'] ) || isset( $query_vars['category_name'] ) || isset( $query_vars['cat'] );

		// Check for other filter params.
		$has_other_filters = false;

		// Build a set of keys to check: ppfx_ prefixed names + direct taxonomy names.
		$check_keys = array();
		foreach ( $this->get_request_filter_vars() as $var ) {
			if ( 'category' !== $var && 'product_tag' !== $var ) {
				$check_keys[] = $var;
				// Also check the non-prefixed name so direct ?product_brand= URLs are detected.
				if ( str_starts_with( $var, 'ppfx_' ) ) {
					$check_keys[] = substr( $var, 5 );
				}
			}
		}
		$check_keys = array_unique( $check_keys );

		foreach ( $check_keys as $var ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL param presence check.
			if ( isset( $_GET[ $var ] ) || isset( $query_vars[ $var ] ) ) {
				$has_other_filters = true;
				break;
			}
		}

		// Only normalize if this is a shop page request with PPFX filters.
		if ( ! $is_shop_page || ( ! $has_category && ! $has_other_filters ) ) {
			return $query_vars;
		}

		// Remove taxonomy-related query vars that can interfere with shop page resolution.
		$tax_vars_to_remove = array(
			'category_name',
			'cat',
			'tag',
			'tag_id',
			'taxonomy',
			'term',
			'product_cat',
			'product_tag',
		);

		// Dynamically include ALL product public taxonomy names (including custom ones like product_brand).
		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $tax ) {
			if ( $tax->public ) {
				$tax_vars_to_remove[] = $tax->name;
			}
		}

		$tax_vars_to_remove = array_unique( $tax_vars_to_remove );

		foreach ( $tax_vars_to_remove as $tax_var ) {
			if ( isset( $query_vars[ $tax_var ] ) ) {
				unset( $query_vars[ $tax_var ] );
			}
		}

		return $query_vars;
	}

	/**
	 * Check if the query vars indicate a shop page request.
	 *
	 * @param array $query_vars Query vars to check.
	 * @return bool
	 */
	private function is_shop_page_request_query( $query_vars ) {
		// Check for product post type (shop page indicator).
		if ( isset( $query_vars['post_type'] ) && 'product' === $query_vars['post_type'] ) {
			return true;
		}

		// Check if this resolves to the WooCommerce shop page.
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_page_id = wc_get_page_id( 'shop' );
			if ( $shop_page_id > 0 ) {
				if ( isset( $query_vars['page_id'] ) && (int) $query_vars['page_id'] === $shop_page_id ) {
					return true;
				}
				if ( isset( $query_vars['pagename'] ) ) {
					$shop_page = get_post( $shop_page_id );
					if ( $shop_page && $shop_page->post_name === $query_vars['pagename'] ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Prevent 404 status for filtered shop page URLs.
	 *
	 * When filter query vars are present on the shop page, WordPress can
	 * misinterpret the request and prepare a 404 response. This hook intercepts
	 * the 404 decision and forces it back to a valid shop response when PPFX
	 * filters are active.
	 *
	 * @param bool     $preempt  Whether to short-circuit default 404 handling.
	 * @param WP_Query $wp_query WordPress query object.
	 * @return bool
	 */
	public function prevent_filtered_shop_404( $preempt, $wp_query ) {
		if ( ! is_shop() || ! $this->has_request_filter_params() ) {
			return $preempt;
		}

		$wp_query->is_404 = false;
		status_header( 200 );

		return true;
	}

	/**
	 * Check whether the current request contains any PPFX filter params.
	 *
	 * @return bool
	 */
	private function has_request_filter_params(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL filter params.
		foreach ( $_GET as $key => $value ) {
			$key = (string) $key;
			if (
				in_array( $key, $this->get_request_filter_vars(), true ) ||
				0 === strpos( $key, 'ppfx_filter_' )
			) {
				return true;
			}
		}

		return false;
	}
	/**
	 * Get filter vars that must not participate in shop page routing.
	 *
	 * @return array<string>
	 */
	private function get_request_filter_vars(): array {
		$vars = array(
			'ppfx_category',
			'ppfx_product_tag',
			'ppfx_min_price',
			'ppfx_max_price',
			'ppfx_rating',
			'ppfx_on_sale',
			'ppfx_page',
			'ppfx_orderby',
			'ppfx_order',
		);

		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if (
				$taxonomy->public &&
				! in_array( $taxonomy->name, array( 'product_cat', 'product_tag' ), true )
			) {
				$vars[] = 'ppfx_' . $taxonomy->name;
			}
		}

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			foreach ( $attribute_taxonomies as $taxonomy ) {
				$vars[] = 'ppfx_filter_' . $taxonomy->attribute_name;
			}
		}

		return array_values( array_unique( $vars ) );
	}
}
