<?php
/**
 * Product filter clause compiler for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Compiles native WP_Query clauses and index-table SQL from filter parameters.
 */
class PPFX_Query_Clause_Compiler {

	/**
	 * Compile query arguments and their scoped SQL filters.
	 *
	 * @param array<string, mixed> $params Filter parameters.
	 * @param bool                 $ids_only Whether to request IDs only.
	 * @return array{args: array, scope: PPFX_Query_Hook_Scope}
	 */
	public function compile( array $params, bool $ids_only = false ): array {
		global $wpdb;

		$use_index_tables = self::index_tables_available();
		$args             = $this->build_query_args( $params, $use_index_tables );
		$price_sort_mode  = $args['ppfx_price_sort'] ?? false;
		unset( $args['ppfx_price_sort'] );

		$index_table      = $wpdb->prefix . 'ppfx_product_index';
		$term_table       = $wpdb->prefix . 'ppfx_product_terms';
		$index_conditions = array();
		$term_joins       = array();
		$needs_index_join = (bool) $price_sort_mode;

		if ( $use_index_tables && 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$needs_index_join   = true;
			$index_conditions[] = "ppfxi.stock_status = 'instock'";
		}

		$this->compile_price_rating_and_sale( $params, $args, $use_index_tables, $needs_index_join, $index_conditions );
		$having_callback = $this->compile_taxonomies( $params, $args, $use_index_tables, $term_table, $term_joins );

		if ( $use_index_tables ) {
			$visibility_term    = get_term_by( 'slug', 'exclude-from-catalog', 'product_visibility' );
			$visibility_term_id = $visibility_term ? (int) $visibility_term->term_id : 0;
			if ( $visibility_term_id > 0 ) {
				$index_conditions[] = $wpdb->prepare(
					"NOT EXISTS ( SELECT 1 FROM {$term_table} AS ppfxt_vis WHERE ppfxt_vis.product_id = {$wpdb->posts}.ID AND ppfxt_vis.taxonomy = 'product_visibility' AND ppfxt_vis.term_id = %d )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and posts identifiers are internal, fixed database identifiers.
					$visibility_term_id,
				);
			}
		}

		$join_callback    = null;
		$where_callback   = null;
		$groupby_callback = null;
		$where_sql        = '';

		if ( $needs_index_join || ! empty( $term_joins ) || ! empty( $index_conditions ) ) {
			$join_sql  = $needs_index_join ? "INNER JOIN {$index_table} AS ppfxi ON ppfxi.product_id = {$wpdb->posts}.ID" : '';
			$where_sql = $index_conditions ? ' AND ' . implode( ' AND ', $index_conditions ) : '';

			$join_callback = function ( $join, $wp_query ) use ( $join_sql, $term_joins ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $join;
				}
				if ( '' !== $join_sql ) {
					$join .= ' ' . $join_sql;
				}
				foreach ( $term_joins as $term_join ) {
					$join .= ' ' . $term_join;
				}
				return $join;
			};

			if ( '' !== $where_sql ) {
				$where_callback = function ( $where, $wp_query ) use ( $where_sql ) {
					if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
						return $where;
					}
					return $where . $where_sql;
				};
			}

			$groupby_callback = function ( $groupby, $wp_query ) use ( $wpdb ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $groupby;
				}
				$ppfx_groupby = "{$wpdb->posts}.ID";
				return empty( $groupby ) ? $ppfx_groupby : $groupby . ', ' . $ppfx_groupby;
			};
		}

		$orderby_callback = $this->compile_orderby_callback( $price_sort_mode, $use_index_tables, $needs_index_join, $term_joins, $args );

		if ( $ids_only ) {
			$args['fields']                 = 'ids';
			$args['posts_per_page']         = -1;
			$args['no_found_rows']          = true;
			$args['update_post_meta_cache'] = false;
			$args['update_post_term_cache'] = false;
		}

		return array(
			'args'  => $args,
			'scope' => new PPFX_Query_Hook_Scope( $join_callback, $where_callback, $groupby_callback, $having_callback, $orderby_callback ),
		);
	}

	/**
	 * Build base WP_Query arguments and native visibility constraints.
	 *
	 * @param array<string, mixed> $params Filter parameters.
	 * @param bool                 $use_index_tables Whether index tables are available.
	 * @return array
	 */
	private function build_query_args( array $params, bool $use_index_tables ): array {
		$raw_orderby     = (string) $this->param( $params, 'orderby', '' );
		$allowed_orderby = array( 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc', 'rand', 'title', 'id' );
		$orderby         = in_array( $raw_orderby, $allowed_orderby, true ) ? $raw_orderby : '';

		$raw_order = strtoupper( (string) $this->param( $params, 'order', '' ) );
		$order     = in_array( $raw_order, array( 'ASC', 'DESC' ), true ) ? $raw_order : '';

		$is_price_sort  = in_array( $orderby, array( 'price', 'price-desc' ), true );
		$use_index_sort = $use_index_tables && $is_price_sort;

		if ( 'rand' === $orderby ) {
			$orderby = 'menu_order';
		}

		if ( $use_index_sort ) {
			$ordering = array(
				'orderby' => 'none',
				'order'   => 'ASC',
			);
		} elseif ( $is_price_sort ) {
			$ordering = WC()->query->get_catalog_ordering_args( 'price', 'price-desc' === $orderby ? 'DESC' : 'ASC' );
		} elseif ( empty( $orderby ) ) {
			$ordering = WC()->query->get_catalog_ordering_args();
		} else {
			$ordering = WC()->query->get_catalog_ordering_args( $orderby, $order );
		}

		$per_page = $this->param( $params, 'per_page' );
		if ( empty( $per_page ) ) {
			$per_page = apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() );
		}

		$args = array(
			'post_type'            => 'product',
			'post_status'          => 'publish',
			'paged'                => ! empty( $this->param( $params, 'page' ) ) ? absint( $this->param( $params, 'page' ) ) : 1,
			'posts_per_page'       => absint( $per_page ),
			'orderby'              => $ordering['orderby'],
			'order'                => $ordering['order'],
			'ppfx_is_filter_query' => true,
			'ppfx_price_sort'      => $use_index_sort ? $orderby : false,
		);

		if ( ! $use_index_sort && ! empty( $ordering['meta_key'] ) ) {
			$args['meta_key'] = $ordering['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required by WooCommerce ordering.
		}
		if ( ! $use_index_tables ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			);
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$args['meta_query'][] = array(
					'key'   => '_stock_status',
					'value' => 'instock',
				);
			}
		}

		return $args;
	}

	/**
	 * Compile price, rating, and sale constraints.
	 *
	 * @param array<string, mixed> $params Filter parameters.
	 * @param array                $args Query args, passed by reference.
	 * @param bool                 $use_index_tables Whether index tables are available.
	 * @param bool                 $needs_index_join Whether the index table join is required.
	 * @param array                $index_conditions Index-table WHERE fragments.
	 * @return void
	 */
	private function compile_price_rating_and_sale( array $params, array &$args, bool $use_index_tables, bool &$needs_index_join, array &$index_conditions ): void {
		global $wpdb;
		$min_price = max( 0.0, (float) $this->param( $params, 'min_price', 0 ) );
		$max_price = max( 0.0, (float) $this->param( $params, 'max_price', 0 ) );

		if ( $min_price > 0 && $max_price > 0 && $min_price > $max_price ) {
			$temp      = $min_price;
			$min_price = $max_price;
			$max_price = $temp;
		}

		if ( $min_price > 0 || $max_price > 0 ) {
			if ( function_exists( 'wc_get_min_max_price_meta_query' ) ) {
				$args['meta_query'][] = wc_get_min_max_price_meta_query(
					array(
						'min_price' => $min_price,
						'max_price' => $max_price,
					)
				);
			} else {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => self::price_meta_value( $min_price, $max_price ),
					'compare' => self::price_meta_compare( $min_price, $max_price ),
					'type'    => 'NUMERIC',
				);
			}
		}

		$rating = $this->param( $params, 'rating' );
		if ( ! empty( $rating ) ) {
			if ( $use_index_tables ) {
				$needs_index_join   = true;
				$index_conditions[] = $wpdb->prepare( 'ppfxi.avg_rating >= %f', (float) $rating );
			} else {
				$args['meta_query'][] = array(
					'key'     => '_wc_average_rating',
					'value'   => (float) $rating,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
		}

		if ( '1' === $this->param( $params, 'on_sale' ) ) {
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
	}

	/**
	 * Compile category, tag, attribute, and public taxonomy constraints.
	 *
	 * @param array<string, mixed> $params Filter parameters.
	 * @param array                $args Query args, passed by reference.
	 * @param bool                 $use_index_tables Whether index tables are available.
	 * @param string               $term_table Product-term table name.
	 * @param array                $term_joins Index-table JOIN fragments.
	 * @return callable|null posts_having callback for attribute filters.
	 */
	private function compile_taxonomies( array $params, array &$args, bool $use_index_tables, string $term_table, array &$term_joins ) {
		global $wpdb;
		$this->compile_taxonomy( 'category', 'product_cat', $params, $args, $use_index_tables, $term_table, $term_joins, true, 'ppfxt_cat' );
		$this->compile_taxonomy( 'product_tag', 'product_tag', $params, $args, $use_index_tables, $term_table, $term_joins, false, 'ppfxt_tag' );

		$attribute_filters = array();
		foreach ( $params as $key => $value ) {
			if ( ! str_starts_with( $key, 'filter_' ) || empty( $value ) ) {
				continue;
			}
			$attribute_name = sanitize_title( substr( $key, 7 ) );
			$taxonomy       = '' === $attribute_name ? '' : wc_attribute_taxonomy_name( $attribute_name );
			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$slugs    = is_array( $value ) ? $value : ( str_contains( (string) $value, ',' ) ? explode( ',', (string) $value ) : array( (string) $value ) );
			$term_ids = self::get_term_ids_by_slugs( $taxonomy, $slugs );
			if ( ! empty( $term_ids ) ) {
				$attribute_filters[ $taxonomy ] = $term_ids;
			}
		}

		$having_callback = null;
		if ( ! empty( $attribute_filters ) ) {
			if ( $use_index_tables ) {
				$or_clauses   = array();
				$prepare_args = array();
				foreach ( $attribute_filters as $taxonomy => $term_ids ) {
					$placeholders   = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
					$or_clauses[]   = "(ppfxt_attr.taxonomy = %s AND ppfxt_attr.term_id IN ({$placeholders}))";
					$prepare_args[] = $taxonomy;
					foreach ( $term_ids as $term_id ) {
						$prepare_args[] = $term_id;
					}
				}
				$or_sql = implode( ' OR ', $or_clauses );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders and table name are controlled.
				$term_joins[]    = $wpdb->prepare( "INNER JOIN {$term_table} AS ppfxt_attr ON ppfxt_attr.product_id = {$wpdb->posts}.ID AND ({$or_sql})", $prepare_args );
				$attribute_count = count( $attribute_filters );
				$having_callback = function ( $having, $wp_query ) use ( $attribute_count ) {
					if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
						return $having;
					}
					$attribute_having = "COUNT(DISTINCT ppfxt_attr.taxonomy) >= {$attribute_count}";
					return empty( $having ) ? $attribute_having : $having . ' AND ' . $attribute_having;
				};
			} else {
				foreach ( $attribute_filters as $taxonomy => $term_ids ) {
					$args['tax_query'][] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}
		}

		foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public || in_array( $taxonomy->name, array( 'product_cat', 'product_tag' ), true ) ) {
				continue;
			}
			$this->compile_taxonomy( $taxonomy->name, $taxonomy->name, $params, $args, $use_index_tables, $term_table, $term_joins, false, 'ppfxt_tax_' . sanitize_key( $taxonomy->name ) );
		}

		return $having_callback;
	}

	/**
	 * Compile one taxonomy filter into its index or native equivalent.
	 *
	 * @param string               $param_key Request parameter key.
	 * @param string               $taxonomy Taxonomy name.
	 * @param array<string, mixed> $params Filter parameters.
	 * @param array                $args Query args, passed by reference.
	 * @param bool                 $use_index_tables Whether index tables are available.
	 * @param string               $term_table Product-term table name.
	 * @param array                $term_joins Index-table JOIN fragments.
	 * @param bool                 $include_children Include category descendants.
	 * @param string               $alias SQL alias.
	 * @return void
	 */
	private function compile_taxonomy( string $param_key, string $taxonomy, array $params, array &$args, bool $use_index_tables, string $term_table, array &$term_joins, bool $include_children, string $alias ): void {
		global $wpdb;
		$value = $this->param( $params, $param_key );
		if ( empty( $value ) ) {
			return;
		}

		$slugs    = is_array( $value ) ? $value : ( str_contains( (string) $value, ',' ) ? explode( ',', (string) $value ) : array( (string) $value ) );
		$term_ids = self::get_term_ids_by_slugs( $taxonomy, $slugs );
		if ( empty( $term_ids ) ) {
			return;
		}

		if ( $include_children ) {
			$all_term_ids = array();
			foreach ( $term_ids as $term_id ) {
				$all_term_ids[] = $term_id;
				$children       = get_term_children( $term_id, $taxonomy );
				if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
					$all_term_ids = array_merge( $all_term_ids, $children );
				}
			}
			$term_ids = array_unique( $all_term_ids );
		}

		if ( $use_index_tables ) {
			$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
			if ( 'product_cat' === $taxonomy || 'product_tag' === $taxonomy ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Alias, table and placeholders are controlled.
				$term_joins[] = $wpdb->prepare( "INNER JOIN {$term_table} AS {$alias} ON {$alias}.product_id = {$wpdb->posts}.ID AND {$alias}.taxonomy = '{$taxonomy}' AND {$alias}.term_id IN ({$placeholders})", $term_ids );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Alias, table and placeholders are controlled.
				$term_joins[] = $wpdb->prepare( "INNER JOIN {$term_table} AS {$alias} ON {$alias}.product_id = {$wpdb->posts}.ID AND {$alias}.taxonomy = %s AND {$alias}.term_id IN ({$placeholders})", array_merge( array( $taxonomy ), $term_ids ) );
			}
			return;
		}

		$args['tax_query'][] = array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => $term_ids,
			'include_children' => false,
		);
	}

	/**
	 * Build the optional index-table ORDER BY callback.
	 *
	 * @param string|false $price_sort_mode Price sort mode.
	 * @param bool         $use_index_tables Whether index tables are available.
	 * @param bool         $needs_index_join Whether an index join is present.
	 * @param array        $term_joins Index joins.
	 * @param array        $args Query args.
	 * @return callable|null
	 */
	private function compile_orderby_callback( $price_sort_mode, bool $use_index_tables, bool $needs_index_join, array $term_joins, array $args ) {
		global $wpdb;
		if ( $price_sort_mode && $use_index_tables ) {
			return function ( $orderby, $wp_query ) use ( $wpdb, $price_sort_mode ) {
				if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
					return $orderby;
				}
				return 'price-desc' === $price_sort_mode ? "ppfxi.max_price DESC, {$wpdb->posts}.ID DESC" : "ppfxi.min_price ASC, {$wpdb->posts}.ID ASC";
			};
		}
		if ( ! $needs_index_join && empty( $term_joins ) ) {
			return null;
		}

		$tie_order = isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		return function ( $orderby, $wp_query ) use ( $wpdb, $tie_order ) {
			if ( ! $wp_query->get( 'ppfx_is_filter_query' ) ) {
				return $orderby;
			}
			if ( empty( $orderby ) ) {
				return "{$wpdb->posts}.ID {$tie_order}";
			}
			return false === strpos( $orderby, "{$wpdb->posts}.ID" ) ? $orderby . ", {$wpdb->posts}.ID {$tie_order}" : $orderby;
		};
	}

	/**
	 * Resolve one parameter while preserving absent-value semantics.
	 *
	 * @param array<string, mixed> $params Filter parameters.
	 * @param string               $key Parameter key.
	 * @param mixed                $fallback Fallback value.
	 * @return mixed
	 */
	private function param( array $params, string $key, $fallback = null ) {
		if ( array_key_exists( $key, $params ) && null !== $params[ $key ] && '' !== $params[ $key ] ) {
			return $params[ $key ];
		}

		$ppfx_key = 'ppfx_' . $key;
		if ( array_key_exists( $ppfx_key, $params ) && null !== $params[ $ppfx_key ] && '' !== $params[ $ppfx_key ] ) {
			return $params[ $ppfx_key ];
		}

		return $fallback;
	}

	/**
	 * Resolve taxonomy slugs to cached term IDs.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $slugs Term slugs.
	 * @return array<int>
	 */
	private static function get_term_ids_by_slugs( string $taxonomy, array $slugs ): array {
		$slugs = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
		if ( empty( $slugs ) ) {
			return array();
		}

		sort( $slugs );
		$cache_key = 'ppfx_terms_' . md5( $taxonomy . ':' . implode( ',', $slugs ) );
		$cached    = wp_cache_get( $cache_key, 'pickory-product-filter' );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		$terms    = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => $slugs,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$term_ids = is_wp_error( $terms ) ? array() : array_map( 'absint', $terms );
		wp_cache_set( $cache_key, $term_ids, 'pickory-product-filter', 5 * MINUTE_IN_SECONDS );

		return $term_ids;
	}

	/**
	 * Check and cache custom index-table availability.
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
		wp_cache_set( $cache_key, $available, 'pickory-product-filter', 5 * MINUTE_IN_SECONDS );

		return $available;
	}

	/**
	 * Build a fallback meta value for native price queries.
	 *
	 * @param float $min_price Minimum price.
	 * @param float $max_price Maximum price.
	 * @return float|array
	 */
	private static function price_meta_value( float $min_price, float $max_price ) {
		return $min_price > 0 && $max_price > 0 ? array( $min_price, $max_price ) : ( $min_price > 0 ? $min_price : $max_price );
	}

	/**
	 * Build a fallback comparison operator for native price queries.
	 *
	 * @param float $min_price Minimum price.
	 * @param float $max_price Maximum price.
	 * @return string
	 */
	private static function price_meta_compare( float $min_price, float $max_price ): string {
		if ( $min_price > 0 && $max_price > 0 ) {
			return 'BETWEEN';
		}

		return $min_price > 0 ? '>=' : '<=';
	}
}
