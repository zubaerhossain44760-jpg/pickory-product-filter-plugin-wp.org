<?php
/**
 * Flat product index table management for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * PPFX_Index — Flat product index table.
 *
 * Maintains a denormalised snapshot of the fields most commonly used
 * by the filter (price, sale status, average rating, stock status) so
 * that filter queries can JOIN against a single indexed table instead
 * of scanning postmeta rows.
 *
 * Lifecycle:
 *   create_table()  — called on plugin activation
 *   drop_table()    — called on plugin uninstall only
 *   bulk_sync()     — called on activation when the index has not yet
 *                     been built (ppfx_index_built option is absent)
 *   sync_product()  — called by WooCommerce save/publish hooks
 *   delete_product()— called by WooCommerce trash/delete hooks
 */
class PPFX_Index {


	// -------------------------------------------------------------------------
	// Schema helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the fully-qualified table name for the current site.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ppfx_product_index';
	}

	/**
	 * Return the fully-qualified term table name for the current site.
	 *
	 * @return string
	 */
	public static function get_term_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ppfx_product_terms';
	}

	/**
	 * Create (or upgrade) the flat index table using dbDelta().
	 *
	 * Safe to call on every activation — dbDelta() is idempotent.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = self::get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  product_id bigint(20) unsigned NOT NULL,
  min_price decimal(10,2) NOT NULL DEFAULT 0.00,
  max_price decimal(10,2) NOT NULL DEFAULT 0.00,
  on_sale tinyint(1) NOT NULL DEFAULT 0,
  avg_rating decimal(3,2) NOT NULL DEFAULT 0.00,
  stock_status varchar(20) NOT NULL DEFAULT 'instock',
  PRIMARY KEY  (product_id),
  KEY idx_min_price (min_price),
  KEY idx_max_price (max_price),
  KEY idx_price_range (min_price, max_price),
  KEY idx_on_sale (on_sale),
  KEY idx_avg_rating (avg_rating),
  KEY idx_stock_status (stock_status),
  KEY idx_stock_product (stock_status, product_id)
) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verifies if both custom index tables actually exist in the database.
	 *
	 * @return bool True if both tables exist, false otherwise.
	 */
	public static function index_tables_available(): bool {
		global $wpdb;

		$index_table = self::get_table_name();
		$term_table  = self::get_term_table_name();

		// Table name is built from $wpdb->prefix (trusted) — no user input.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$index_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$index_table}'" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$term_table}'" );

		return ( ! empty( $index_exists ) && ! empty( $term_exists ) );
	}

	/**
	 * Create (or upgrade) the term-join index table.
	 *
	 * One row per (product, taxonomy, term) triple — used by the REST endpoint
	 * to filter by category, tag, and attribute via fast indexed JOINs instead
	 * of WP_Query tax_query.
	 *
	 * @return void
	 */
	public static function create_term_table(): void {
		global $wpdb;

		$table   = self::get_term_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  taxonomy varchar(128) NOT NULL,
  term_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_product_tax_term (product_id, taxonomy, term_id),
  KEY idx_product (product_id),
  KEY idx_taxonomy_term (taxonomy, term_id),
  KEY idx_tax_term_product (taxonomy, term_id, product_id)
) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the term-join index table entirely.
	 *
	 * @return void
	 */
	public static function drop_term_table(): void {
		global $wpdb;

		$table = self::get_term_table_name();
		// Table name is built from $wpdb->prefix (trusted) — no user input.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Drop the flat index table entirely.
	 *
	 * Called only on plugin uninstall — not on deactivation.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::get_table_name();
		// Table name is built from $wpdb->prefix (trusted) — no user input.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Version tracking.

	/**
	 * Get the current index version string.
	 *
	 * @return string
	 */
	public static function get_version(): string {
		return get_option( 'ppfx_index_version', '' );
	}

	/**
	 * Check whether the index needs to be rebuilt.
	 *
	 * @return bool
	 */
	public static function needs_rebuild(): bool {
		return self::get_version() !== PPFX_INDEX_VERSION;
	}

	/**
	 * Mark the index as built at the current version.
	 *
	 * @return void
	 */
	public static function mark_built(): void {
		update_option( 'ppfx_index_version', PPFX_INDEX_VERSION );
	}

	// -------------------------------------------------------------------------
	// Row-level sync
	// -------------------------------------------------------------------------

	/**
	 * Bumps the global query cache version string using high precision time.
	 *
	 * @return void
	 */
	public static function bump_cache_version(): void {
		update_option( 'ppfx_query_cache_version', (string) microtime( true ), false );
	}

	/**
	 * Delete price range cache for all active currencies.
	 *
	 * Currency switcher plugins may create separate caches per currency.
	 * This ensures all currency variants are invalidated when products change.
	 *
	 * @return void
	 */
	private static function delete_price_range_cache(): void {
		// Delete base cache key (legacy).
		delete_transient( 'ppfx_price_range' );

		// Delete currency-specific cache keys for common currencies.
		// This covers most currency switcher scenarios without needing to
		// detect which specific currencies are active.
		$common_currencies = array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN' );
		foreach ( $common_currencies as $currency ) {
			delete_transient( 'ppfx_price_range_' . $currency );
		}

		// Also delete for current WooCommerce currency (may be switched).
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			delete_transient( 'ppfx_price_range_' . get_woocommerce_currency() );
		}
	}

	/**
	 * Insert or update a single product row in the index.
	 *
	 * Skips product variations — they are not queried directly by the filter.
	 *
	 * @param int  $product_id WooCommerce product post ID.
	 * @param bool $bump_cache Whether to trigger a cache version bump.
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

		if (
			'variable' === $product->get_type() &&
			$product instanceof WC_Product_Variable
		) {
			// Use WooCommerce's own is_on_sale() for the parent product.
			// This correctly excludes out-of-stock and hidden variations,
			// and respects scheduled sale dates — avoiding false positives
			// from variations that are not purchasable.
			$on_sale = $product->is_on_sale() ? 1 : 0;

			$prices           = $product->get_variation_prices( true );
			$variation_prices = isset( $prices['price'] ) && ! empty( $prices['price'] )
				? array_map( 'floatval', $prices['price'] )
				: array();
			$min_price        = ! empty( $variation_prices ) ? min( $variation_prices ) : 0.0;
			$max_price        = ! empty( $variation_prices ) ? max( $variation_prices ) : 0.0;

			$stock = $product->is_in_stock() ? 'instock' : 'outofstock';
		} else {
			$min_price = (float) $product->get_price();
			$max_price = $min_price;
			$on_sale   = $product->is_on_sale() ? 1 : 0;
			$stock     = $product->get_stock_status();
		}

		$avg_rating = (float) $product->get_average_rating();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->replace(
			self::get_table_name(),
			array(
				'product_id'   => $product_id,
				'min_price'    => $min_price,
				'max_price'    => $max_price,
				'on_sale'      => $on_sale,
				'avg_rating'   => $avg_rating,
				'stock_status' => $stock,
			),
			array( '%d', '%f', '%f', '%d', '%f', '%s' ),
		);

		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		if ( false === $result ) {
			// Silent failure — no logging in production.
		}

		// Delete price range cache for all currencies (currency switcher compatibility).
		self::delete_price_range_cache();

		// Sync term index.
		$term_table = self::get_term_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $term_table, array( 'product_id' => $product_id ), array( '%d' ) );

		$taxonomies = get_object_taxonomies( 'product', 'objects' );

		$insert_rows = array();
		foreach ( $taxonomies as $taxonomy_obj ) {
			if ( ! $taxonomy_obj->public ) {
				continue;
			}
			$taxonomy = $taxonomy_obj->name;
			$terms    = get_the_terms( $product_id, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$insert_rows[] = array(
					'product_id' => $product_id,
					'taxonomy'   => $taxonomy,
					'term_id'    => (int) $term->term_id,
				);
			}
		}

		if ( ! empty( $insert_rows ) ) {
			$values       = array();
			$placeholders = array();
			foreach ( $insert_rows as $row ) {
				$placeholders[] = '( %d, %s, %d )';
				$values[]       = $row['product_id'];
				$values[]       = $row['taxonomy'];
				$values[]       = $row['term_id'];
			}

			$query = "INSERT INTO {$term_table} ( product_id, taxonomy, term_id ) VALUES " . implode( ', ', $placeholders );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query( $wpdb->prepare( $query, $values ) );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( false === $result ) {
				// Silent failure — no logging in production.
			}
		}
	}

	/**
	 * Remove a product row from the index.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return void
	 */
	public static function delete_product( int $product_id ): void {
		self::bump_cache_version();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::get_table_name(),
			array( 'product_id' => $product_id ),
			array( '%d' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::get_term_table_name(),
			array( 'product_id' => $product_id ),
			array( '%d' ),
		);

		// Delete price range cache for all currencies (currency switcher compatibility).
		self::delete_price_range_cache();
	}

	// -------------------------------------------------------------------------
	// Bulk sync
	// -------------------------------------------------------------------------

	/**
	 * Rebuilds the entire index table. Bumps cache version once at completion.
	 *
	 * @return void
	 */
	public static function bulk_sync(): void {
		if ( get_transient( 'ppfx_bulk_sync_lock' ) ) {
			return;
		}
		set_transient( 'ppfx_bulk_sync_lock', 1, 10 * MINUTE_IN_SECONDS );

		global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$truncate_index = $wpdb->query( 'TRUNCATE TABLE ' . self::get_table_name() );
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		if ( false === $truncate_index ) {
			// Silent failure — no logging in production.
		}
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$truncate_term = $wpdb->query( 'TRUNCATE TABLE ' . self::get_term_table_name() );
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		if ( false === $truncate_term ) {
			// Silent failure — no logging in production.
		}

		$last_id      = 0;
		$batch        = 200;
		$synced_count = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID
                     FROM {$wpdb->posts}
                     WHERE post_type = 'product'
                       AND post_status = 'publish'
                       AND ID > %d
                     ORDER BY ID ASC
                     LIMIT %d",
					$last_id,
					$batch
				)
			);

			foreach ( $ids as $id ) {
				self::sync_product( (int) $id, false );
				++$synced_count;
			}

			if ( ! empty( $ids ) ) {
				$last_id = (int) end( $ids );
			}
			$ids_count = count( $ids );
		} while ( $ids_count === $batch );

		// Single database option write for the whole batch.
		self::bump_cache_version();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_published_products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
		if ( $synced_count > 0 || 0 === $total_published_products ) {
			self::mark_built();
		}

		delete_transient( 'ppfx_bulk_sync_lock' );
	}

	// -------------------------------------------------------------------------
	// WooCommerce hook handlers
	// -------------------------------------------------------------------------

	/**
	 * Register the nightly cron event for expired-sale cleanup.
	 * Called once on plugin activation.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'ppfx_sync_expired_sales' ) ) {
			wp_schedule_event( time(), 'daily', 'ppfx_sync_expired_sales' );
		}
	}

	/**
	 * Unregister the cron event on plugin deactivation.
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( 'ppfx_sync_expired_sales' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ppfx_sync_expired_sales' );
		}
	}

	/**
	 * Nightly cron callback: re-sync any product whose scheduled sale has
	 * expired but is still marked on_sale = 1 in the index.
	 *
	 * WooCommerce does not fire a product-save hook when a scheduled sale
	 * expires — it just stops returning the sale price dynamically. This
	 * job catches those stale rows and corrects them.
	 */
	public static function sync_expired_sales(): void {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$on_sale_ids = $wpdb->get_col( "SELECT product_id FROM {$table} WHERE on_sale = 1" );

		if ( empty( $on_sale_ids ) ) {
			return;
		}

		foreach ( $on_sale_ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( $product && ! $product->is_on_sale() ) {
				self::sync_product( (int) $product_id );
			}
		}
	}

	/**
	 * Sync a product after its props are saved.
	 *
	 * Hooked to: woocommerce_product_object_updated_props (priority 10)
	 *
	 * @param \WC_Product $product The product object.
	 * @param array       $updated_props List of updated property names.
	 * @return void
	 */
	public static function handle_product_updated(
		WC_Product $product,
		array $updated_props,
	): void {
		// Only sync if filter-relevant properties changed.
		// Skips unnecessary index rebuilds on title, description, SEO meta changes.
		$relevant = array(
			'price',
			'sale_price',
			'regular_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'stock_status',
			'stock_quantity',
			'average_rating',
			'category_ids',
			'tag_ids',
			'total_sales',
			'attributes',
		);

		if ( empty( array_intersect( $updated_props, $relevant ) ) ) {
			return;
		}

		self::sync_product( (int) $product->get_id() );
	}

	/**
	 * Sync a product after its taxonomy terms change.
	 *
	 * Intended for the WordPress set_object_terms hook.
	 *
	 * @param int $object_id Object ID whose terms changed.
	 * @return void
	 */
	public static function handle_term_update( int $object_id ): void {
		if ( 'product' === get_post_type( $object_id ) ) {
			self::sync_product( $object_id );
		}
	}

	/**
	 * Remove a product from the index when its post is permanently deleted.
	 *
	 * Hooked to: before_delete_post (priority 10)
	 *
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public static function handle_product_deleted( int $post_id ): void {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::delete_product( $post_id );
		}
	}

	/**
	 * Sync or remove a product when its publish status changes.
	 *
	 * Hooked to: transition_post_status (priority 10)
	 *
	 * - publish → anything else : remove from index
	 * - anything → publish      : add/update in index
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post The post object.
	 * @return void
	 */
	public static function handle_status_transition(
		string $new_status,
		string $old_status,
		WP_Post $post,
	): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			self::sync_product( (int) $post->ID );
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			self::delete_product( (int) $post->ID );
		}
	}
}
