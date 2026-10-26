<?php
/**
 * Product-row indexing for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Synchronizes product and product-term index rows.
 */
class PPFX_Product_Indexer {

	/**
	 * Bump the shared filtered-query cache version.
	 *
	 * @return void
	 */
	public static function bump_cache_version(): void {
		update_option( 'ppfx_query_cache_version', (string) microtime( true ), false );
	}

	/**
	 * Insert or update a single product and its public taxonomy terms.
	 *
	 * @param int  $product_id WooCommerce product post ID.
	 * @param bool $bump_cache Whether to invalidate query caches.
	 * @return void
	 */
	public static function sync_product( int $product_id, bool $bump_cache = true ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'variation' === $product->get_type() ) {
			return;
		}
		if ( $bump_cache ) {
			self::bump_cache_version();
		}

		global $wpdb;
		if ( 'variable' === $product->get_type() && $product instanceof WC_Product_Variable ) {
			$on_sale          = $product->is_on_sale() ? 1 : 0;
			$prices           = $product->get_variation_prices( true );
			$variation_prices = isset( $prices['price'] ) && ! empty( $prices['price'] ) ? array_map( 'floatval', $prices['price'] ) : array();
			$min_price        = ! empty( $variation_prices ) ? min( $variation_prices ) : 0.0;
			$max_price        = ! empty( $variation_prices ) ? max( $variation_prices ) : 0.0;
			$stock            = $product->is_in_stock() ? 'instock' : 'outofstock';
		} else {
			$min_price = (float) $product->get_price();
			$max_price = $min_price;
			$on_sale   = $product->is_on_sale() ? 1 : 0;
			$stock     = $product->get_stock_status();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->replace(
			PPFX_Index_Schema::get_table_name(),
			array(
				'product_id'   => $product_id,
				'min_price'    => $min_price,
				'max_price'    => $max_price,
				'on_sale'      => $on_sale,
				'avg_rating'   => (float) $product->get_average_rating(),
				'stock_status' => $stock,
			),
			array( '%d', '%f', '%f', '%d', '%f', '%s' ),
		);

		self::delete_price_range_cache();
		self::sync_term_rows( $product_id );
	}

	/**
	 * Remove a product and its taxonomy rows from both index tables.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return void
	 */
	public static function delete_product( int $product_id ): void {
		self::bump_cache_version();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( PPFX_Index_Schema::get_table_name(), array( 'product_id' => $product_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( PPFX_Index_Schema::get_term_table_name(), array( 'product_id' => $product_id ), array( '%d' ) );
		self::delete_price_range_cache();
	}

	/**
	 * Rebuild both index tables in product-ID batches.
	 *
	 * @return void
	 */
	public static function bulk_sync(): void {
		if ( get_transient( 'ppfx_bulk_sync_lock' ) ) {
			return;
		}
		set_transient( 'ppfx_bulk_sync_lock', 1, 10 * MINUTE_IN_SECONDS );

		try {
			global $wpdb;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( 'TRUNCATE TABLE ' . PPFX_Index_Schema::get_table_name() );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( 'TRUNCATE TABLE ' . PPFX_Index_Schema::get_term_table_name() );

			$last_id      = 0;
			$batch        = 200;
			$synced_count = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d", $last_id, $batch ) );
				foreach ( $ids as $id ) {
					self::sync_product( (int) $id, false );
					++$synced_count;
				}
				if ( ! empty( $ids ) ) {
					$last_id = (int) end( $ids );
				}
				$ids_count = count( $ids );
			} while ( $ids_count === $batch );

			self::bump_cache_version();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Posts table is a trusted database identifier.
			$total_published_products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
			if ( $synced_count > 0 || 0 === $total_published_products ) {
				PPFX_Index_Schema::mark_built();
			}
		} finally {
			delete_transient( 'ppfx_bulk_sync_lock' );
		}
	}

	/**
	 * Delete cached price ranges for common and active currencies.
	 *
	 * @return void
	 */
	private static function delete_price_range_cache(): void {
		delete_transient( 'ppfx_price_range' );
		$currencies = apply_filters( 'ppfx_price_cache_currencies', array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN' ) );
		foreach ( $currencies as $currency ) {
			delete_transient( 'ppfx_price_range_' . $currency );
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			delete_transient( 'ppfx_price_range_' . get_woocommerce_currency() );
		}
	}

	/**
	 * Replace all public taxonomy-term rows for one product atomically.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return void
	 */
	private static function sync_term_rows( int $product_id ): void {
		global $wpdb;
		$term_table = PPFX_Index_Schema::get_term_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $term_table, array( 'product_id' => $product_id ), array( '%d' ) );
		if ( false === $deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		$rows = array();
		foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy_object ) {
			if ( ! $taxonomy_object->public ) {
				continue;
			}
			$terms = get_the_terms( $product_id, $taxonomy_object->name );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$rows[] = array(
					'product_id' => $product_id,
					'taxonomy'   => $taxonomy_object->name,
					'term_id'    => (int) $term->term_id,
				);
			}
		}

		if ( ! empty( $rows ) ) {
			$values       = array();
			$placeholders = array();
			foreach ( $rows as $row ) {
				$placeholders[] = '( %d, %s, %d )';
				$values[]       = $row['product_id'];
				$values[]       = $row['taxonomy'];
				$values[]       = $row['term_id'];
			}
			$query = "INSERT INTO {$term_table} ( product_id, taxonomy, term_id ) VALUES " . implode( ', ', $placeholders );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic placeholders are prepared immediately.
			$result = $wpdb->query( $wpdb->prepare( $query, $values ) );
			if ( false === $result ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );
	}
}
