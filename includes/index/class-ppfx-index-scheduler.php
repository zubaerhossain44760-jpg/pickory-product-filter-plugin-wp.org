<?php
/**
 * Index scheduling for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Schedules and runs maintenance for the product index.
 */
class PPFX_Index_Scheduler {

	/**
	 * Schedule the nightly expired-sale synchronization event.
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'ppfx_sync_expired_sales' ) ) {
			wp_schedule_event( time(), 'daily', 'ppfx_sync_expired_sales' );
		}
	}

	/**
	 * Remove the nightly expired-sale synchronization event.
	 *
	 * @return void
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( 'ppfx_sync_expired_sales' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ppfx_sync_expired_sales' );
		}
	}

	/**
	 * Refresh rows whose scheduled sales have expired.
	 *
	 * @return void
	 */
	public static function sync_expired_sales(): void {
		global $wpdb;
		$table = PPFX_Index_Schema::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is built from the trusted database prefix.
		$on_sale_ids = $wpdb->get_col( "SELECT product_id FROM {$table} WHERE on_sale = 1" );
		if ( empty( $on_sale_ids ) ) {
			return;
		}

		foreach ( $on_sale_ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( $product && ! $product->is_on_sale() ) {
				PPFX_Product_Indexer::sync_product( (int) $product_id );
			}
		}
	}
}
