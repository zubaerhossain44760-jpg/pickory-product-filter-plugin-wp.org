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
			'per_page'        => array(
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
			'instance_id'     => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
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
					'menu_order title',
					'price',
					'price-desc',
					'date',
					'popularity',
					'rating',
				),
			),
			'ppfx_order'       => array(
				'type'              => 'string',
				'default'           => 'ASC',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
			),
			'ppfx_min_price'   => array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => function ( $v ) {
					return (float) $v;
				},
			),
			'ppfx_max_price'   => array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => function ( $v ) {
					return (float) $v;
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
	 * Registers standard WooCommerce loop properties from query.
	 *
	 * @param WP_Query $query Current query instance.
	 * @return void
	 */
	private static function setup_loop_props( WP_Query $query ): void {
		$total    = (int) $query->found_posts;
		$per_page = (int) $query->get( 'posts_per_page' );
		$current  = max( 1, (int) $query->get( 'paged' ) );

		wc_set_loop_prop( 'total', (string) $total );
		wc_set_loop_prop( 'total_pages', (string) $query->max_num_pages );
		wc_set_loop_prop( 'per_page', (string) $per_page );
		wc_set_loop_prop( 'current_page', (string) $current );
		wc_set_loop_prop( 'is_paginated', $query->max_num_pages > 1 ? '1' : '0' );
	}

	/**
	 * Render WooCommerce HTML fragments for the given WP_Query result.
	 *
	 * Temporarily overrides the global $wp_query so WooCommerce functions
	 * like woocommerce_catalog_ordering() and woocommerce_pagination() read
	 * from the REST-filtered query rather than the main page query.
	 *
	 * @param WP_Query $query The filtered products query.
	 * @param string   $instance_id The filter instance ID.
	 * @return array Keyed HTML fragments ready to send to the client.
	 */
	private static function render_fragments( WP_Query $query, string $instance_id = '' ): array {
		global $wp_query, $post;
		$original_query  = $wp_query;
		$original_post   = $post;
		$fragments       = array();
		$ob_level_before = ob_get_level();

		// Filter pagination links to prevent REST URL leakage during fragment rendering.
		add_filter( 'get_pagenum_link', array( __CLASS__, 'filter_pagination_links' ), 10, 1 );
		// Filter add-to-cart URLs to prevent REST URL leakage during fragment rendering.
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'filter_add_to_cart_url' ), 99, 2 );
		// Ensure WooCommerce thinks products will display (required for ordering template).
		add_filter( 'woocommerce_products_will_display', '__return_true' );

		try {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional: WC loop helpers read from $wp_query; we restore it in the finally block.
			$wp_query = $query;

			// Set queried object and archive flags BEFORE rendering any WooCommerce fragments.
			// This ensures woocommerce_catalog_ordering() and other templates render correctly.
			$shop_page_id = absint( wc_get_page_id( 'shop' ) );
			if ( $shop_page_id > 0 ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$post = get_post( $shop_page_id );
				if ( $post instanceof WP_Post ) {
					$query->queried_object    = $post;
					$query->queried_object_id = $shop_page_id;
				}
			}

			$query->is_archive           = true;
			$query->is_post_type_archive = true;
			$query->is_singular          = false;
			$query->is_page              = false;
			$query->is_404               = false;

			$total    = (int) $query->found_posts;
			$per_page = (int) $query->get( 'posts_per_page' );
			$current  = max( 1, (int) $query->get( 'paged' ) );
			$first    = $per_page * ( $current - 1 ) + 1;
			$last     = min( $total, $per_page * $current );

			self::setup_loop_props( $query );

			$wp_query->found_posts                  = $total;
			$wp_query->max_num_pages                = $query->max_num_pages;
			$wp_query->query_vars['posts_per_page'] = $per_page;
			$wp_query->query_vars['paged']          = $current;

			// Result count.
			if ( 0 === $total ) {
				$count_text = __( 'No products found', 'pickory-product-filter' );
			} elseif ( $total <= $per_page || -1 === $per_page ) {
				$count_text = sprintf(
					// translators: %d is the total number of products found.
					_n(
						'Showing %d result',
						'Showing all %d results',
						$total,
						'pickory-product-filter',
					),
					$total,
				);
			} else {
				$count_text = sprintf(
					// translators: 1: first item number, 2: last item number, 3: total results.
					__(
						'Showing %1$d&ndash;%2$d of %3$d results',
						'pickory-product-filter',
					),
					$first,
					$last,
					$total,
				);
			}
			$fragments['.woocommerce-result-count'] = $count_text;

			// Catalog ordering.
			ob_start();
			$original_is_paginated = wc_get_loop_prop( 'is_paginated' );

			wc_set_loop_prop( 'is_paginated', (string) true );
			wc_set_loop_prop( 'total', (string) $total );

			woocommerce_catalog_ordering();
			$html = ob_get_clean();

			// Restore original state.
			wc_set_loop_prop( 'is_paginated', $original_is_paginated );

			if ( ! empty( trim( $html ) ) ) {
				$html                               = trim( $html );
				$html                               = preg_replace( '/^<form\b[^>]*>/i', '', $html );
				$html                               = preg_replace( '/<\/form>$/i', '', $html );
				$fragments['.woocommerce-ordering'] = $html;
			} else {
				$fragments['.woocommerce-ordering'] = '';
			}

			if ( ! $query->have_posts() ) {
				ob_start();
				do_action( 'woocommerce_no_products_found' );
				$empty_html = ob_get_clean();

				if ( ! str_contains( $empty_html, 'ppfx-products-wrapper' ) ) {
					$data_attr  = $instance_id ? ' data-ppfx-instance="' . esc_attr( $instance_id ) . '"' : '';
					$empty_html = '<div class="ppfx-products-wrapper"' . $data_attr . '>' . $empty_html . '</div>';
				} elseif ( $instance_id ) {
					$processor = new WP_HTML_Tag_Processor( $empty_html );
					if ( $processor->next_tag( array( 'class_name' => 'ppfx-products-wrapper' ) ) ) {
						$processor->set_attribute( 'data-ppfx-instance', $instance_id );
						$empty_html = $processor->get_updated_html();
					}
				}

				$fragments['.ppfx-products-wrapper']     = $empty_html;
				$fragments['.woocommerce-result-count'] = $count_text;
				$fragments['.woocommerce-ordering']     = '';
				$fragments['.woocommerce-pagination']   = '<nav class="woocommerce-pagination" aria-label="Product Pagination" style="display:none;"></nav>';

				return $fragments;
			}

			// Products loop.
			ob_start();
			woocommerce_product_loop_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();
			$captured_products = ob_get_clean();
			if ( ! empty( trim( $captured_products ) ) ) {
				// If the captured HTML doesn't already have the wrapper, add it with instance ID.
				if ( ! str_contains( $captured_products, 'ppfx-products-wrapper' ) ) {
					$data_attr         = $instance_id ? ' data-ppfx-instance="' . esc_attr( $instance_id ) . '"' : '';
					$captured_products = '<div class="ppfx-products-wrapper"' . $data_attr . '>' . $captured_products . '</div>';
				} elseif ( $instance_id ) {
					// If it does have the wrapper, use WP_HTML_Tag_Processor to safely add the instance ID attribute.
					$processor = new WP_HTML_Tag_Processor( $captured_products );
					if ( $processor->next_tag( array( 'class_name' => 'ppfx-products-wrapper' ) ) ) {
						$processor->set_attribute( 'data-ppfx-instance', $instance_id );
						$captured_products = $processor->get_updated_html();
					}
				}
				$fragments['.ppfx-products-wrapper'] = $captured_products;
			}

			// Pagination — always output a stable anchor so the JS fragment
			// replacement can target it even when results fit on a single page.
			ob_start();
			woocommerce_pagination();
			$html                                 = ob_get_clean();
			$fragments['.woocommerce-pagination'] = ! empty( trim( $html ) )
				? $html
				: '<nav class="woocommerce-pagination" aria-label="Product Pagination" style="display:none;"></nav>';
		} finally {
			// Clean up any stray output buffers.
			while ( ob_get_level() > $ob_level_before ) {
				ob_end_clean();
			}
			// Restore globals.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring original value after intentional override above.
			$wp_query = $original_query;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring original value after intentional override above.
			$post = $original_post;
			remove_filter( 'woocommerce_products_will_display', '__return_true' );
			remove_filter( 'get_pagenum_link', array( __CLASS__, 'filter_pagination_links' ), 10 );
			remove_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'filter_add_to_cart_url' ), 99 );
			wp_reset_postdata();
		}

		return $fragments;
	}

	/**
	 * Replaces data-ppfx-instance attribute value in an HTML string.
	 *
	 * Instance_id is excluded from the cache key to improve cache hit rate.
	 * So we fix the cached HTML at read time by replacing the baked-in
	 * instance attribute with the current request's instance_id.
	 *
	 * @param string $html        Cached HTML fragment.
	 * @param string $instance_id Current request instance ID.
	 * @return string             HTML with corrected data-ppfx-instance attribute.
	 */
	private static function replace_instance_id_in_html(
		string $html,
		string $instance_id
	): string {
		if ( empty( $html ) || empty( $instance_id ) ) {
			return $html;
		}

		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag() ) {
			if ( null !== $processor->get_attribute( 'data-ppfx-instance' ) ) {
				$processor->set_attribute(
					'data-ppfx-instance',
					esc_attr( $instance_id )
				);
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Filters the pagination links generated during REST requests to point back to the referrer page.
	 *
	 * Prevents WooCommerce from emitting /wp-json/ URLs in pagination links
	 * when rendering fragments inside a REST request context.
	 *
	 * @param string $link The generated pagination link.
	 * @return string Filtered pagination link pointing to the referrer page.
	 */
	public static function filter_pagination_links( string $link ): string {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		}

		// Validate referrer belongs to current site.
		if ( $referer && wp_parse_url( home_url(), PHP_URL_HOST ) !== wp_parse_url( $referer, PHP_URL_HOST ) ) {
			$referer = wc_get_page_permalink( 'shop' );
		}

		if ( $referer ) {
			$referer_base = strtok( $referer, '?' );
			if ( preg_match( '/[?&]paged=(\d+)/', $link, $matches ) ) {
				return add_query_arg( 'paged', $matches[1], $referer_base );
			}
			if ( preg_match( '/\/page\/(\d+)/', $link, $matches ) ) {
				return trailingslashit( $referer_base ) . 'page/' . $matches[1] . '/';
			}
		}

		return $link;
	}

	/**
	 * Filters the add-to-cart URLs to prevent REST URL leakage during fragment rendering.
	 *
	 * @param string     $url     The generated add-to-cart URL.
	 * @param WC_Product $product The product object.
	 * @return string Filtered add-to-cart URL.
	 */
	public static function filter_add_to_cart_url( string $url, WC_Product $product ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$base_url = wp_get_referer();

		// Validate referrer belongs to current site.
		if ( ! $base_url || wp_parse_url( home_url(), PHP_URL_HOST ) !== wp_parse_url( $base_url, PHP_URL_HOST ) ) {
			$base_url = wc_get_page_permalink( 'shop' );
		}

		$parsed_url = wp_parse_url( $url );
		$query      = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';

		if ( $query ) {
			parse_str( $query, $query_args );
			unset( $query_args['orderby'] ); // Strip REST routing params.
			$url = add_query_arg( $query_args, $base_url );
		} else {
			$url = $base_url;
		}

		return $url;
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

		// Get instance ID from request.
		$instance_id = $request->get_param( 'instance_id' );
		$instance_id = $instance_id ? $instance_id : '';

		// Create a new request object with normalized params for cache key.
		$normalized_request = new WP_REST_Request();
		foreach ( $normalized_params as $key => $value ) {
			$normalized_request->set_param( $key, $value );
		}

		// Note: instance_id is intentionally excluded from cache key to improve cache hit rate.
		// The query result is identical across instances; instance-specific attributes
		// are applied during fragment rendering, not during query execution.

		// Query caching.
		$cache_key = PPFX_Query_Cache::build_query_cache_key( $normalized_request );
		if ( ! is_user_logged_in() ) {
			$cached_response = wp_cache_get( $cache_key, 'pickory-product-filter' );
			if ( is_array( $cached_response ) ) {
				// instance_id is excluded from cache key for better hit rate.
				// Replace baked-in data-ppfx-instance in cached fragments
				// with the current request's instance_id before returning.
				if ( ! empty( $instance_id ) && ! empty( $cached_response['fragments'] ) ) {
					foreach ( $cached_response['fragments'] as $selector => $html ) {
						$cached_response['fragments'][ $selector ] =
							self::replace_instance_id_in_html( $html, $instance_id );
					}
				}
				$response = rest_ensure_response( $cached_response );
				$response->header( 'Cache-Control', 'no-store' );
				return $response;
			}
		}

		// Build and run the query.
		$query = ( new PPFX_Product_Query_Builder( $normalized_request ) )->get_query();

		$fragments = self::render_fragments( $query, $instance_id );

		$response_data = array(
			'success'   => true,
			'fragments' => $fragments,
		);

		if ( ! is_user_logged_in() ) {
			wp_cache_set( $cache_key, $response_data, 'pickory-product-filter', 5 * MINUTE_IN_SECONDS );
		}

		$response = rest_ensure_response( $response_data );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
