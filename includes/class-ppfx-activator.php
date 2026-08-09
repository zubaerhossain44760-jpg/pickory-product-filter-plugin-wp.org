<?php
/**
 * Plugin activator for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation tasks.
 */
class PPFX_Activator {

	/**
	 * Run all activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		// Actions to perform on plugin activation (e.g. flush rewrite rules).
		flush_rewrite_rules();

		// Create the flat product index table (idempotent — safe on re-activation).
		require_once plugin_dir_path( __FILE__ ) . 'class-ppfx-index.php';
		PPFX_Index::create_table();
		PPFX_Index::create_term_table();

		// Schedule the nightly cron job for expired-sale cleanup.
		PPFX_Index::schedule_cron();

		// Run the initial bulk sync only if the index has never been built.
		// Subsequent saves are handled by the woocommerce_product_object_updated_props hook.
		if ( PPFX_Index::needs_rebuild() ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'ppfx_rebuild_index_action' );
			} elseif ( ! wp_next_scheduled( 'ppfx_rebuild_index_action' ) ) {
				wp_schedule_single_event( time(), 'ppfx_rebuild_index_action' );
			}
		}
	}
}
