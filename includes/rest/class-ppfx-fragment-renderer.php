<?php
/**
 * Fragment renderer for Pickory Product Filter REST responses.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Renders WooCommerce HTML fragments for REST responses.
 */
class PPFX_Fragment_Renderer {

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
	 * @param string   $instance_id     The filter instance ID.
	 * @param bool     $include_toolbar Whether the client needs missing toolbar HTML.
	 * @return array Keyed HTML fragments ready to send to the client.
	 */
	public static function render(
		WP_Query $query,
		string $instance_id = '',
		bool $include_toolbar = false
	): array {
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

			// The client normally preserves this DOM region to retain theme and
			// plugin event bindings. Render it only when an empty SSR page needs
			// to introduce it after results return.
			if ( $include_toolbar && $total > 0 ) {
				ob_start();
				do_action( 'woocommerce_before_shop_loop' );
				$fragments['.ppfx-loop-toolbar'] = ob_get_clean();
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

				$fragments['.ppfx-products-wrapper']  = $empty_html;
				$fragments['.woocommerce-pagination'] = '<nav class="woocommerce-pagination" aria-label="Product Pagination" style="display:none;"></nav>';

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
			if ( function_exists( 'wc_reset_loop' ) ) {
				wc_reset_loop();
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
	 * @param string          $url     The generated add-to-cart URL.
	 * @param WC_Product|null $product The product object.
	 * @return string Filtered add-to-cart URL.
	 */
	public static function filter_add_to_cart_url( $url = '', $product = null ): string {
		$url = is_string( $url ) ? $url : '';

		if ( ! ( $product instanceof WC_Product ) ) {
			return $url;
		}

		if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
			$permalink = $product->get_permalink();
			return ( is_string( $permalink ) && ! empty( $permalink ) ) ? $permalink : $url;
		}

		if ( $product->is_type( 'external' ) ) {
			$ext_url = method_exists( $product, 'get_product_url' ) ? $product->get_product_url() : $url;
			return ( is_string( $ext_url ) && ! empty( $ext_url ) ) ? $ext_url : $url;
		}

		$base_url = wp_get_referer();

		if ( ! $base_url || wp_parse_url( home_url(), PHP_URL_HOST ) !== wp_parse_url( $base_url, PHP_URL_HOST ) ) {
			$base_url = wc_get_page_permalink( 'shop' );
		}

		$parsed_url = wp_parse_url( $url );
		$query      = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';

		if ( $query ) {
			parse_str( $query, $query_args );
			unset( $query_args['orderby'] );
			$url = add_query_arg( $query_args, $base_url );
		} else {
			$permalink = $product->get_permalink();
			$url       = ( is_string( $permalink ) && ! empty( $permalink ) ) ? $permalink : (string) $base_url;
		}

		return (string) $url;
	}
}
