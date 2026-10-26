<?php
/**
 * Pickory Product Filter Uninstall
 *
 * Deletes custom tables, options, transients, and cron hooks.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstaller table and transient cleanup.

// 1. Drop Custom Tables.
$table = $wpdb->prefix . 'ppfx_product_index';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

$term_table = $wpdb->prefix . 'ppfx_product_terms';
$wpdb->query( "DROP TABLE IF EXISTS {$term_table}" );

// 2. Delete Options.
$options_to_delete = array(
	'ppfx_settings',
	'ppfx_appearance_settings',
	'ppfx_index_version',
	'ppfx_tables_created',
	'ppfx_index_built',
	'ppfx_query_cache_version',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// 3. Delete All Plugin Transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ppfx_%' OR option_name LIKE '_transient_timeout_ppfx_%'" );

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// 4. Clear Scheduled Cron Events (Using modern wp_clear_scheduled_hook).
wp_clear_scheduled_hook( 'ppfx_sync_expired_sales' );
wp_clear_scheduled_hook( 'ppfx_rebuild_index_action' );

// 5. Unschedule Action Scheduler tasks if present.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'ppfx_rebuild_index_action' );
}
