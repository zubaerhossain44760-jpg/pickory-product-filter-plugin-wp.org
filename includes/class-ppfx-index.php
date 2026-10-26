<?php
/**
 * Product index compatibility façade for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once PPFX_PLUGIN_DIR . 'includes/index/class-ppfx-index-schema.php';
require_once PPFX_PLUGIN_DIR . 'includes/index/class-ppfx-product-indexer.php';
require_once PPFX_PLUGIN_DIR . 'includes/index/class-ppfx-index-scheduler.php';

/**
 * Preserves the static index API and handles WooCommerce lifecycle decisions.
 */
class PPFX_Index {

	/** Get the flat index table name. @return string */
	public static function get_table_name(): string {
		return PPFX_Index_Schema::get_table_name();
	}

	/** Get the term index table name. @return string */
	public static function get_term_table_name(): string {
		return PPFX_Index_Schema::get_term_table_name();
	}

	/** Create the flat index table. @return void */
	public static function create_table(): void {
		PPFX_Index_Schema::create_table();
	}

	/** Check whether index tables are available. @return bool */
	public static function index_tables_available(): bool {
		return PPFX_Index_Schema::index_tables_available();
	}

	/** Create the term index table. @return void */
	public static function create_term_table(): void {
		PPFX_Index_Schema::create_term_table();
	}

	/** Drop the term index table. @return void */
	public static function drop_term_table(): void {
		PPFX_Index_Schema::drop_term_table();
	}

	/** Drop the flat index table. @return void */
	public static function drop_table(): void {
		PPFX_Index_Schema::drop_table();
	}

	/** Get the stored index version. @return string */
	public static function get_version(): string {
		return PPFX_Index_Schema::get_version();
	}

	/** Check whether the index needs rebuilding. @return bool */
	public static function needs_rebuild(): bool {
		return PPFX_Index_Schema::needs_rebuild();
	}

	/** Mark the current index version as built. @return void */
	public static function mark_built(): void {
		PPFX_Index_Schema::mark_built();
	}

	/** Bump the filtered-query cache version. @return void */
	public static function bump_cache_version(): void {
		PPFX_Product_Indexer::bump_cache_version();
	}

	/**
	 * Invalidate caches for a product lifecycle event.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return void
	 */
	public static function handle_product_cache_invalidation( int $product_id ): void {
		if ( in_array( get_post_type( $product_id ), array( 'product', 'product_variation' ), true ) ) {
			self::bump_cache_version();
		}
	}

	/**
	 * Synchronize the index after a stock change.
	 *
	 * @param WC_Product $product Product whose stock changed.
	 * @return void
	 */
	public static function handle_stock_changed( WC_Product $product ): void {
		self::sync_related_product( (int) $product->get_id() );
	}

	/**
	 * Synchronize one product index row.
	 *
	 * @param int  $product_id WooCommerce product post ID.
	 * @param bool $bump_cache Whether to invalidate query caches.
	 * @return void
	 */
	public static function sync_product( int $product_id, bool $bump_cache = true ): void {
		PPFX_Product_Indexer::sync_product( $product_id, $bump_cache );
	}

	/**
	 * Remove one product from the index.
	 *
	 * @param int $product_id WooCommerce product post ID.
	 * @return void
	 */
	public static function delete_product( int $product_id ): void {
		PPFX_Product_Indexer::delete_product( $product_id );
	}

	/** Run a full product index rebuild. @return void */
	public static function bulk_sync(): void {
		PPFX_Product_Indexer::bulk_sync();
	}

	/** Schedule index maintenance. @return void */
	public static function schedule_cron(): void {
		PPFX_Index_Scheduler::schedule_cron();
	}

	/** Unschedule index maintenance. @return void */
	public static function unschedule_cron(): void {
		PPFX_Index_Scheduler::unschedule_cron();
	}

	/** Synchronize products with expired sales. @return void */
	public static function sync_expired_sales(): void {
		PPFX_Index_Scheduler::sync_expired_sales();
	}

	/**
	 * Synchronize a product after filter-relevant property changes.
	 *
	 * @param WC_Product $product Updated product.
	 * @param array      $updated_props Updated property names.
	 * @return void
	 */
	public static function handle_product_updated( WC_Product $product, array $updated_props ): void {
		$index_relevant = array( 'price', 'sale_price', 'regular_price', 'date_on_sale_from', 'date_on_sale_to', 'stock_status', 'stock_quantity', 'average_rating', 'category_ids', 'tag_ids', 'total_sales', 'attributes' );
		if ( ! empty( array_intersect( $updated_props, $index_relevant ) ) ) {
			self::sync_related_product( (int) $product->get_id() );
			return;
		}
		if ( ! empty( array_intersect( $updated_props, array( 'name', 'menu_order' ) ) ) ) {
			self::bump_cache_version();
		}
	}

	/**
	 * Synchronize a product after public taxonomy terms change.
	 *
	 * @param int    $object_id Object ID whose terms changed.
	 * @param array  $terms Object terms.
	 * @param array  $tt_ids Term taxonomy IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public static function handle_term_update( int $object_id, array $terms = array(), array $tt_ids = array(), string $taxonomy = '' ): void {
		if ( '' !== $taxonomy ) {
			static $product_taxonomies = null;
			if ( null === $product_taxonomies ) {
				$product_taxonomies = get_object_taxonomies( 'product' );
			}
			if ( ! in_array( $taxonomy, $product_taxonomies, true ) ) {
				return;
			}
		}
		if ( 'product' === get_post_type( $object_id ) ) {
			self::sync_product( $object_id );
		}
	}

	/**
	 * Remove a deleted product from the index.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public static function handle_product_deleted( int $post_id ): void {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::delete_product( $post_id );
		}
	}

	/**
	 * Restore an untrashed product or variation in the index.
	 *
	 * @param int $post_id Untrashed post ID.
	 * @return void
	 */
	public static function handle_product_untrashed( int $post_id ): void {
		self::sync_related_product( $post_id );
	}

	/**
	 * Refresh affected index rows after a post deletion.
	 *
	 * @param int     $post_id Deleted post ID.
	 * @param WP_Post $post Deleted post object.
	 * @return void
	 */
	public static function handle_post_deleted( int $post_id, WP_Post $post ): void {
		if ( 'product' === $post->post_type ) {
			self::bump_cache_version();
			return;
		}
		if ( 'product_variation' === $post->post_type ) {
			$parent_id = (int) $post->post_parent;
			if ( $parent_id > 0 ) {
				self::sync_product( $parent_id );
			} else {
				self::bump_cache_version();
			}
		}
	}

	/**
	 * Synchronize index rows when a product status changes.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function handle_status_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'product_variation' === $post->post_type ) {
			self::sync_related_product( (int) $post->ID );
			return;
		}
		if ( 'product' !== $post->post_type ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			self::sync_product( (int) $post->ID );
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			self::delete_product( (int) $post->ID );
		}
	}

	/**
	 * Resolve a variation change to its parent product index row.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return void
	 */
	private static function sync_related_product( int $product_id ): void {
		if ( 'product_variation' === get_post_type( $product_id ) ) {
			$parent_id = (int) wp_get_post_parent_id( $product_id );
			if ( $parent_id > 0 ) {
				self::sync_product( $parent_id );
			} else {
				self::bump_cache_version();
			}
			return;
		}
		if ( 'product' === get_post_type( $product_id ) ) {
			self::sync_product( $product_id );
		}
	}
}
