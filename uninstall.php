<?php
/**
 * Pickory Product Filter Uninstall
 *
 * Uninstalling Pickory Product Filter deletes user options, transients, and custom tables.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'ppfx_product_index';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$term_table = $wpdb->prefix . 'ppfx_product_terms';
$wpdb->query( "DROP TABLE IF EXISTS {$term_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'ppfx_settings' );
delete_option( 'ppfx_index_version' );
delete_option( 'ppfx_tables_created' );
delete_option( 'ppfx_index_built' );
delete_option( 'ppfx_query_cache_version' );

// Delete price range cache for all currencies (currency switcher compatibility).
delete_transient( 'ppfx_price_range' );
$common_currencies = array( 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN' );
foreach ( $common_currencies as $currency ) {
	delete_transient( 'ppfx_price_range_' . $currency );
}

$timestamp = wp_next_scheduled( 'ppfx_sync_expired_sales' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'ppfx_sync_expired_sales' );
}
