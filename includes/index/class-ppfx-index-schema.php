<?php
/**
 * Index-table schema management for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Manages index-table names, DDL, availability, and version tracking.
 */
class PPFX_Index_Schema {

	/**
	 * Cached flat index table name.
	 *
	 * @var string|null
	 */
	private static $table_name = null;

	/**
	 * Cached taxonomy-term index table name.
	 *
	 * @var string|null
	 */
	private static $term_table_name = null;

	/**
	 * Cached index-table availability state.
	 *
	 * @var bool|null
	 */
	private static $tables_exist = null;

	/**
	 * Get the flat product index table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		return self::$table_name ??= $GLOBALS['wpdb']->prefix . 'ppfx_product_index';
	}

	/**
	 * Get the product taxonomy-term index table name.
	 *
	 * @return string
	 */
	public static function get_term_table_name(): string {
		return self::$term_table_name ??= $GLOBALS['wpdb']->prefix . 'ppfx_product_terms';
	}

	/**
	 * Create or upgrade the flat product index table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$table   = self::get_table_name();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
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

		require_once wp_normalize_path( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		self::reset_cache();
	}

	/**
	 * Create or upgrade the product taxonomy-term index table.
	 *
	 * @return void
	 */
	public static function create_term_table(): void {
		global $wpdb;
		$table   = self::get_term_table_name();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
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

		require_once wp_normalize_path( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		self::reset_cache();
	}

	/**
	 * Check whether both custom index tables exist.
	 *
	 * @return bool
	 */
	public static function index_tables_available(): bool {
		if ( null !== self::$tables_exist ) {
			return self::$tables_exist;
		}

		global $wpdb;
		$index_table = self::get_table_name();
		$term_table  = self::get_term_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$index_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $index_table ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $term_table ) ) );

		self::$tables_exist = ! empty( $index_exists ) && ! empty( $term_exists );
		return self::$tables_exist;
	}

	/**
	 * Drop the taxonomy-term index table.
	 *
	 * @return void
	 */
	public static function drop_term_table(): void {
		global $wpdb;
		$table = self::get_term_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name is built from the trusted database prefix.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		self::reset_cache();
	}

	/**
	 * Drop the flat product index table.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name is built from the trusted database prefix.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		self::reset_cache();
	}

	/**
	 * Get the installed index schema version.
	 *
	 * @return string
	 */
	public static function get_version(): string {
		return get_option( 'ppfx_index_version', '' );
	}

	/**
	 * Determine whether the installed schema version needs rebuilding.
	 *
	 * @return bool
	 */
	public static function needs_rebuild(): bool {
		return self::get_version() !== PPFX_INDEX_VERSION;
	}

	/**
	 * Record that the current schema version has been built.
	 *
	 * @return void
	 */
	public static function mark_built(): void {
		update_option( 'ppfx_index_version', PPFX_INDEX_VERSION );
	}

	/**
	 * Reset memoized table names and availability after a schema change.
	 *
	 * @return void
	 */
	private static function reset_cache(): void {
		self::$tables_exist    = null;
		self::$table_name      = null;
		self::$term_table_name = null;
	}
}
