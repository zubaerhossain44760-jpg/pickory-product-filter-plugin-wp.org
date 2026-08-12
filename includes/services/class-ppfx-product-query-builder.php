<?php
/**
 * Product query builder for Pickory Product Filter REST endpoint.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Builds WP_Query args and JOIN clauses from a REST request.
 */
class PPFX_Product_Query_Builder {

	/**
	 * REST request instance.
	 *
	 * @var WP_REST_Request|null
	 */
	private $request = null;

	/**
	 * Parsed query parameters.
	 *
	 * @var array<string, mixed>
	 */
	private $params = array();

	/**
	 * Constructor.
	 *
	 * @param WP_REST_Request|array|null $request REST request or plain SSR params.
	 */
	public function __construct( $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			$this->request = $request;
		} else {
			$this->request = null;
			$this->params  = is_array( $request ) ? $request : array();
		}
	}

	/**
	 * Read a query parameter from REST request or SSR params.
	 *
	 * @param string $key Parameter key.
	 * @param mixed  $fallback_value Default value.
	 * @return mixed
	 */
	private function get_param( $key, $fallback_value = null ) {
		if ( $this->request instanceof WP_REST_Request ) {
			return $this->request->get_param( $key ) ?? $fallback_value;
		}

		return $this->params[ $key ] ?? $fallback_value;
	}

	/**
	 * Read all query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function get_params(): array {
		if ( $this->request instanceof WP_REST_Request ) {
			return $this->request->get_params();
		}

		return $this->params;
	}

	/**
	 * Build WP_Query args from the REST request parameters.
	 *
	 * @param bool $use_index_tables Whether custom index tables are available.
	 * @param bool $use_index_price_sort_override Whether to override index-based price sorting with WooCommerce native price sorting.
	 * @return array WP_Query args array.
	 */
	private function build_query_args( bool $use_index_tables = false, bool $use_index_price_sort_override = false ): array {
		$orderby              = $this->get_param( 'orderby' );
		$is_price_sort        = in_array( $orderby, array( 'price', 'price-desc' ), true );
		$use_index_price_sort = $use_index_tables && $is_price_sort && ! $use_index_price_sort_override;
		$hide_out_of_stock    = get_option( 'woocommerce_hide_out_of_stock_items' );

		if ( 'rand' === $orderby ) {
			$orderby = 'menu_order';
		}

		if ( $use_index_price_sort ) {
			// Price sort is handled via a custom posts_orderby filter on the index table.
			// Do not call WC ordering args or set meta_key for this mode.
			$ordering = array(
				'orderby' => 'none',
				'order'   => 'ASC',
			);
		} elseif ( $is_price_sort ) {
			// WC_Query only parses "price-desc" when it reads orderby from the
			// request itself. When passing explicit args, provide price + order.
			$ordering = WC()->query->get_catalog_ordering_args(
				'price',
				'price-desc' === $orderby ? 'DESC' : 'ASC',
			);
		} elseif ( empty( $orderby ) ) {
			$ordering = WC()->query->get_catalog_ordering_args(
				'menu_order',
				'ASC',
			);
		} else {
			$ordering = WC()->query->get_catalog_ordering_args(
				$orderby,
				$this->get_param( 'order' ),
			);
		}

		$per_page = $this->get_param( 'per_page' );

		if ( empty( $per_page ) ) {
			$per_page = apply_filters(
				'loop_shop_per_page',
				wc_get_default_products_per_row() *
					wc_get_default_product_rows_per_page(),
			);
		}

		$paged = $this->get_param( 'page' );

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'paged'               => ! empty( $paged ) ? absint( $paged ) : 1,
			'posts_per_page'      => absint( $per_page ),
			'orderby'             => $ordering['orderby'],
			'order'               => $ordering['order'],
			'ppfx_is_filter_query' => true,
			'ppfx_price_sort'      => $use_index_price_sort ? $orderby : false,
		);

		if ( ! $use_index_price_sort && ! empty( $ordering['meta_key'] ) ) {
			$args['meta_key'] = $ordering['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required by WooCommerce ordering.
		}

		// Exclude hidden products.
		if ( ! $use_index_tables ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			);
		}

		// Hide out of stock items if configured.
		if ( ! $use_index_tables && 'yes' === $hide_out_of_stock ) {
			$args['meta_query'][] = array(
				'key'   => '_stock_status',
				'value' => 'instock',
			);
		}

		return $args;
	}

	/**
	 * Apply filters from the request and return the WP_Query object.
	 *
	 * @param bool $ids_only When true, return only post IDs (lightweight query).
	 * @return WP_Query
	 */
	public function get_query( bool $ids_only = false ): WP_Query {
		global $wpdb;

		$hide_out_of_stock = get_option( 'woocommerce_hide_out_of_stock_items' );

		$params           = $this->get_params();
		$index_table      = $wpdb->prefix . 'ppfx_product_index';
		$term_table       = $wpdb->prefix . 'ppfx_product_terms';
		$index_conditions = array();
		$term_joins       = array();
		$needs_index_join = false;
		$use_index_tables = self::index_tables_available();
		$orderby          = $this->get_param( 'orderby' );

		// Separation of filtering and sorting: use custom index tables for building the WHERE clause.
		// Price sorting is also handled via the index table for optimal performance.
		$use_index_price_sort_override = false;

		$args = $this->build_query_args( $use_index_tables, $use_index_price_sort_override );

		// Extract price sort flag set by build_query_args(), then remove it
		// from $args so WP_Query never sees it as a query var.
		$price_sort_mode = $args['ppfx_price_sort'] ?? false;
		unset( $args['ppfx_price_sort'] );

		if ( $price_sort_mode && $use_index_tables ) {
			// Price sort requires the index table join to access ppfxi.min_price / ppfxi.max_price.
			$needs_index_join = true;
		}

		if ( $use_index_tables && 'yes' === $hide_out_of_stock ) {
			$needs_index_join   = true;
			$index_conditions[] = "ppfxi.stock_status = 'instock'";
		}

		// Price range.
		$min_price = floatval( $this->get_param( 'min_price' ) );
		$max_price = floatval( $this->get_param( 'max_price' ) );

		if ( $min_price > 0 || $max_price > 0 ) {
			if ( $use_index_tables ) {
				$needs_index_join = true;
				if ( $min_price > 0 && $max_price > 0 ) {
					$index_conditions[] = $wpdb->prepare(
						'ppfxi.min_price <= %f AND ppfxi.max_price >= %f',
						$max_price,
						$min_price,
					);
				} elseif ( $min_price > 0 ) {
					$index_conditions[] = $wpdb->prepare(
						'ppfxi.max_price >= %f',
						$min_price,
					);
				} elseif ( $max_price > 0 ) {
					$index_conditions[] = $wpdb->prepare(
						'ppfxi.min_price <= %f',
						$max_price,
					);
				}
			} elseif ( function_exists( 'wc_get_min_max_price_meta_query' ) ) {
				$args['meta_query'][] = wc_get_min_max_price_meta_query(
					array(
						'min_price' => $min_price,
						'max_price' => $max_price,
					)
				);
			} else {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => self::get_price_meta_value(
						$min_price,
						$max_price,
					),
					'compare' => self::get_price_meta_compare(
						$min_price,
						$max_price,
					),
					'type'    => 'NUMERIC',
				);
			}
		}

		// Rating filter.
		if ( ! empty( $this->get_param( 'rating' ) ) ) {
			if ( $use_index_tables ) {
				$needs_index_join   = true;
				$index_conditions[] = $wpdb->prepare(
					'ppfxi.avg_rating >= %f',
					(float) $this->get_param( 'rating' ),
				);
			} else {
				$args['meta_query'][] = array(
					'key'     => '_wc_average_rating',
					'value'   => (float) $this->get_param( 'rating' ),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
		}

		// On Sale filter.
		if ( '1' === $this->get_param( 'on_sale' ) ) {
			if ( $use_index_tables ) {
				$needs_index_join   = true;
				$index_conditions[] = 'ppfxi.on_sale = 1';
			} else {
				$args['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => '_sale_price',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_min_variation_sale_price',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				);
			}
		}

		// Category filter.
		if ( ! empty( $params['category'] ) ) {
			$slugs    = array_map(
				'sanitize_title',
				explode( ',', $params['category'] ),
			);
			$term_ids = self::get_term_ids_by_slugs( 'product_cat', $slugs );
			if ( ! empty( $term_ids ) ) {
				// Include child terms for hierarchical category support.
				$all_term_ids = array();
				foreach ( $term_ids as $term_id ) {
					$all_term_ids[] = $term_id;
					$children       = get_term_children( $term_id, 'product_cat' );
					if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
						$all_term_ids = array_merge( $all_term_ids, $children );
					}
				}
				$all_term_ids = array_unique( $all_term_ids );

				if ( $use_index_tables ) {
					$in    = implode( ',', array_fill( 0, count( $all_term_ids ), '%d' ) );
					$alias = 'ppfxt_cat';
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Variables are safe.
					$term_joins[] = $wpdb->prepare(
						"INNER JOIN {$term_table} AS {$alias} ON {$alias}.product_id = {$wpdb->posts}.ID AND {$alias}.taxonomy = 'product_cat' AND {$alias}.term_id IN ({$in})",
						$all_term_ids,
					);
				} else {
					$args['tax_query'][] = array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $all_term_ids,
						'include_children' => false, // Already expanded manually.
					);
				}
			}
		}

		// Product tag filter.
		if ( ! empty( $params['product_tag'] ) ) {
			$slugs    = array_map(
				'sanitize_title',
				explode( ',', $params['product_tag'] ),
			);
			$term_ids = self::get_term_ids_by_slugs( 'product_tag', $slugs );
			if ( ! empty( $term_ids ) ) {
				if ( $use_index_tables ) {
					$in    = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
					$alias = 'ppfxt_tag';
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Variables are safe.
					$term_joins[] = $wpdb->prepare(
						"INNER JOIN {$term_table} AS {$alias} ON {$alias}.product_id = {$wpdb->posts}.ID AND {$alias}.taxonomy = 'product_tag' AND {$alias}.term_id IN ({$in})",
						$term_ids,
					);
				} else {
					$args['tax_query'][] = array(
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}
		}

		// Consolidate Attribute Filters (Finding 4).
		$attribute_filters = array();
		foreach ( $params as $key => $value ) {
			if ( ! str_starts_with( $key, 'filter_' ) || empty( $value ) ) {
				continue;
			}

			$attr_name = sanitize_title( substr( $key, 7 ) );
			if ( '' === $attr_name ) {
				continue;
			}
			$taxonomy = wc_attribute_taxonomy_name( $attr_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$term_slugs = array_map( 'sanitize_title', explode( ',', $value ) );
			$term_ids   = self::get_term_ids_by_slugs( $taxonomy, $term_slugs );
			if ( empty( $term_ids ) ) {
				continue;
			}

			$attribute_filters[ $taxonomy ] = $term_ids;
		}

		$having_cb = null;
		if ( ! empty( $attribute_filters ) ) {
			if ( $use_index_tables ) {
				$or_clauses   = array();
				$prepare_args = array();
				foreach ( $attribute_filters as $tax => $term_ids ) {
					$in             = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
					$or_clauses[]   = "(ppfxt_attr.taxonomy = %s AND ppfxt_attr.term_id IN ({$in}))";
					$prepare_args[] = $tax;
					foreach ( $term_ids as $id ) {
						$prepare_args[] = $id;
					}
				}
				$or_sql = implode( ' OR ', $or_clauses );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Variables are safe.
				$term_joins[] = $wpdb->prepare(
					"INNER JOIN {$term_table} AS ppfxt_attr ON ppfxt_attr.product_id = {$wpdb->posts}.ID AND ({$or_sql})",
					$prepare_args
				);

				$num_attrs = count( $attribute_filters );
				$having_cb = function ( $having, $wp_query ) use ( $num_attrs ) {
					if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
						return $having;
					}
					$attr_having = "COUNT(DISTINCT ppfxt_attr.taxonomy) >= {$num_attrs}";
					return empty( $having ) ? $attr_having : $having . ' AND ' . $attr_having;
				};
				add_filter( 'posts_having', $having_cb, 10, 2 );
			} else {
				foreach ( $attribute_filters as $tax => $term_ids ) {
					$args['tax_query'][] = array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}
		}

		// Generic public taxonomy filters (e.g. product_brand, etc.).
		// Mirrors the REST args registered in PPFX_Products_Controller::get_endpoint_args().
		$handled_taxonomies = array( 'product_cat', 'product_tag' );
		$product_taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $product_taxonomies as $tax ) {
			if ( ! $tax->public || in_array( $tax->name, $handled_taxonomies, true ) ) {
				continue;
			}
			$tax_param = $this->get_param( $tax->name );
			if ( empty( $tax_param ) ) {
				continue;
			}
			$slugs    = array_map( 'sanitize_title', explode( ',', $tax_param ) );
			$term_ids = self::get_term_ids_by_slugs( $tax->name, $slugs );
			if ( empty( $term_ids ) ) {
				continue;
			}
			if ( $use_index_tables ) {
				$in    = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
				$alias = 'ppfxt_tax_' . sanitize_key( $tax->name );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Variables are safe.
				$term_joins[] = $wpdb->prepare(
					"INNER JOIN {$term_table} AS {$alias} ON {$alias}.product_id = {$wpdb->posts}.ID AND {$alias}.taxonomy = %s AND {$alias}.term_id IN ({$in})",
					array_merge( array( $tax->name ), $term_ids ),
				);
			} else {
				$args['tax_query'][] = array(
					'taxonomy' => $tax->name,
					'field'    => 'term_id',
					'terms'    => $term_ids,
				);
			}
		}

		// Product visibility query (Finding 8).
		if ( $use_index_tables ) {
			$visibility_term    = get_term_by( 'slug', 'exclude-from-catalog', 'product_visibility' );
			$visibility_term_id = $visibility_term ? (int) $visibility_term->term_id : 0;
			if ( $visibility_term_id > 0 ) {
				$index_conditions[] = $wpdb->prepare(
					"NOT EXISTS (
						SELECT 1 FROM {$term_table} AS ppfxt_vis
						WHERE ppfxt_vis.product_id = {$wpdb->posts}.ID
						  AND ppfxt_vis.taxonomy = 'product_visibility'
						  AND ppfxt_vis.term_id = %d
					)",
					$visibility_term_id
				);
			}
		}

		$join_cb    = null;
		$where_cb   = null;
		$group_cb   = null;
		$orderby_cb = null;

		if ( $needs_index_join || ! empty( $term_joins ) || ! empty( $index_conditions ) ) {
			$join_sql  = $needs_index_join ? "INNER JOIN {$index_table} AS ppfxi ON ppfxi.product_id = {$wpdb->posts}.ID" : '';
			$where_sql = $index_conditions
				? ' AND ' . implode( ' AND ', $index_conditions )
				: '';

			$join_cb = function ( $j, $wp_query ) use ( $join_sql, $term_joins ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $j;
				}

				if ( ! empty( $join_sql ) ) {
					$j .= ' ' . $join_sql;
				}
				foreach ( $term_joins as $tj ) {
					$j .= ' ' . $tj;
				}
				return $j;
			};

			$where_cb = function ( $w, $wp_query ) use ( $where_sql ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $w;
				}

				return $w . $where_sql;
			};

			$group_cb = function ( $groupby, $wp_query ) use ( $wpdb ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $groupby;
				}

				$ppfx_groupby = "{$wpdb->posts}.ID";
				return empty( $groupby ) ? $ppfx_groupby : $groupby . ', ' . $ppfx_groupby;
			};

			add_filter( 'posts_join', $join_cb, 10, 2 );
			if ( ! empty( $where_sql ) ) {
				add_filter( 'posts_where', $where_cb, 10, 2 );
			}
			add_filter( 'posts_groupby', $group_cb, 10, 2 );
		}

		// Price sort ORDER BY — replaces WP_Query's default ordering with an
		// index-table expression. Only active when ppfxi join is present.
		if ( $price_sort_mode && $use_index_tables ) {
			$price_sort_mode_local = $price_sort_mode;
			$orderby_cb            = function ( $orderby, $wp_query ) use ( $wpdb, $price_sort_mode_local ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $orderby;
				}
				if ( 'price-desc' === $price_sort_mode_local ) {
					return "ppfxi.max_price DESC, {$wpdb->posts}.ID DESC";
				}
				return "ppfxi.min_price ASC, {$wpdb->posts}.ID ASC";
			};
			add_filter( 'posts_orderby', $orderby_cb, 10, 2 );
		} elseif ( ( $needs_index_join || ! empty( $term_joins ) ) && ! $price_sort_mode ) {
			// For grouped non-price queries, append a deterministic tie-breaker
			// so MySQL does not pick arbitrary row values within each group.
			$tie_order  = ( isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ) ? 'DESC' : 'ASC';
			$orderby_cb = function ( $orderby, $wp_query ) use ( $wpdb, $tie_order ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $orderby;
				}
				if ( empty( $orderby ) ) {
					return "{$wpdb->posts}.ID {$tie_order}";
				}
				// Only append if the tie-breaker isn't already present.
				if ( false === strpos( $orderby, "{$wpdb->posts}.ID" ) ) {
					$orderby .= ", {$wpdb->posts}.ID {$tie_order}";
				}
				return $orderby;
			};
			add_filter( 'posts_orderby', $orderby_cb, 10, 2 );
		}

		if ( $ids_only ) {
			$args['fields']                 = 'ids';
			$args['posts_per_page']         = -1;
			$args['no_found_rows']          = true;
			$args['update_post_meta_cache'] = false;
			$args['update_post_term_cache'] = false;
		}

		try {
			$query = new WP_Query( $args );
		} finally {
			if ( $needs_index_join || ! empty( $term_joins ) || ! empty( $index_conditions ) ) {
				remove_filter( 'posts_join', $join_cb, 10 );
				if ( ! empty( $where_sql ) ) {
					remove_filter( 'posts_where', $where_cb, 10 );
				}
				remove_filter( 'posts_groupby', $group_cb, 10 );
			}
			if ( $having_cb ) {
				remove_filter( 'posts_having', $having_cb, 10 );
			}
			if ( $orderby_cb ) {
				remove_filter( 'posts_orderby', $orderby_cb, 10 );
			}
		}

		return $query;
	}

	// Cache key building and version management have been moved to
	// includes/services/class-ppfx-query-cache.php (PPFX_Query_Cache).

	/**
	 * Resolve taxonomy slugs to term IDs with object-cache support.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $slugs    Term slugs.
	 * @return array<int>
	 */
	private static function get_term_ids_by_slugs(
		string $taxonomy,
		array $slugs,
	): array {
		$slugs = array_values(
			array_filter(
				array_map( 'sanitize_title', $slugs )
			)
		);

		if ( empty( $slugs ) ) {
			return array();
		}

		sort( $slugs );
		$cache_key = 'ppfx_terms_' . md5( $taxonomy . ':' . implode( ',', $slugs ) );
		$cached    = wp_cache_get( $cache_key, 'pickory-product-filter' );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => $slugs,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		$term_ids = is_wp_error( $terms ) ? array() : array_map( 'absint', $terms );

		wp_cache_set(
			$cache_key,
			$term_ids,
			'pickory-product-filter',
			5 * MINUTE_IN_SECONDS
		);

		return $term_ids;
	}



	/**
	 * Check whether the custom product index tables are available.
	 *
	 * @return bool
	 */
	private static function index_tables_available(): bool {
		$cache_key = 'ppfx_index_tables_available';
		$cached    = wp_cache_get( $cache_key, 'pickory-product-filter' );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$available = PPFX_Index::index_tables_available();

		wp_cache_set(
			$cache_key,
			$available,
			'pickory-product-filter',
			5 * MINUTE_IN_SECONDS,
		);

		return $available;
	}

	/**
	 * Build the fallback meta value for price queries.
	 *
	 * @param float $min_price Minimum price.
	 * @param float $max_price Maximum price.
	 * @return float|array
	 */
	private static function get_price_meta_value(
		float $min_price,
		float $max_price,
	): float|array {
		if ( $min_price > 0 && $max_price > 0 ) {
			return array( $min_price, $max_price );
		}

		return $min_price > 0 ? $min_price : $max_price;
	}

	/**
	 * Build the fallback meta comparison for price queries.
	 *
	 * @param float $min_price Minimum price.
	 * @param float $max_price Maximum price.
	 * @return string
	 */
	private static function get_price_meta_compare(
		float $min_price,
		float $max_price,
	): string {
		if ( $min_price > 0 && $max_price > 0 ) {
			return 'BETWEEN';
		}

		return $min_price > 0 ? '>=' : '<=';
	}
}
