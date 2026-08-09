<?php
/**
 * Plugin Name:       Pickory Product Filter
 * Description:       A fast, AJAX-powered WooCommerce product filter with flexible display types and a flat product index.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.8
 * Author:            Zubaer Hossain
 * Text Domain:       pickory-product-filter
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * HPOS Compatible:   Yes
 *
 * @package pickory-product-filter
 */

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

if ( ! defined( 'WPINC' ) ) {
	die();
}

define( 'PPFX_VERSION', '1.0.0' );
define( 'PPFX_INDEX_VERSION', '1.2' );
define( 'PPFX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPFX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPFX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Vite dev server configuration.
 *
 * Override in wp-config.php before this file loads:
 * define( 'PPFX_VITE_DEV', true );
 * define( 'PPFX_VITE_SERVER', 'http://localhost:5174' );
 */
if ( ! defined( 'PPFX_VITE_DEV' ) ) {
	define( 'PPFX_VITE_DEV', false );
}
if ( ! defined( 'PPFX_VITE_SERVER' ) ) {
	define( 'PPFX_VITE_SERVER', PPFX_VITE_DEV ? 'http://localhost:5174' : '' );
}



/**
 * Plugin activation hook.
 *
 * @return void
 */
function ppfx_activate_plugin(): void {
	require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-activator.php';
	PPFX_Activator::activate();
}

register_activation_hook( __FILE__, 'ppfx_activate_plugin' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function ppfx_deactivate_plugin(): void {
	require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-index.php';
	PPFX_Index::unschedule_cron();
}

register_deactivation_hook( __FILE__, 'ppfx_deactivate_plugin' );

require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-index.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-utility.php';
require_once PPFX_PLUGIN_DIR . 'includes/services/class-ppfx-query-cache.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-assets.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-rest-api.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-shortcode.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-block.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-admin.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-term-meta.php';
require_once PPFX_PLUGIN_DIR . 'includes/class-ppfx-server-filter.php';
$ppfx_server_filter = new PPFX_Server_Filter();
$ppfx_server_filter->init();

add_action( 'plugins_loaded', 'ppfx_bootstrap' );

/**
 * Bootstrap plugin components after all plugins are loaded.
 *
 * @return void
 */
function ppfx_bootstrap(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ppfx_woocommerce_missing_notice' );
		return;
	}



	$assets = new PPFX_Assets();
	$assets->init();

	$rest_api = new PPFX_Rest_Api();
	$rest_api->init();

	$shortcode = new PPFX_Shortcode();
	$shortcode->init();

	$block = new PPFX_Block();
	$block->init();

	add_action(
		'woocommerce_product_object_updated_props',
		array( 'PPFX_Index', 'handle_product_updated' ),
		10,
		2,
	);
	add_action(
		'before_delete_post',
		array( 'PPFX_Index', 'handle_product_deleted' ),
		10,
		1,
	);
	add_action(
		'transition_post_status',
		array( 'PPFX_Index', 'handle_status_transition' ),
		10,
		3,
	);
	add_action(
		'set_object_terms',
		array( 'PPFX_Index', 'handle_term_update' ),
		10,
		1,
	);
	add_action( 'ppfx_sync_expired_sales', array( 'PPFX_Index', 'sync_expired_sales' ) );
	add_action( 'ppfx_rebuild_index_action', array( 'PPFX_Index', 'bulk_sync' ) );

	if ( is_admin() ) {
		$admin = new PPFX_Admin();
		$admin->init();

		$term_meta = new PPFX_Term_Meta();
		$term_meta->init();
	}
}

/**
 * Admin notice when WooCommerce is not active.
 *
 * @return void
 */
function ppfx_woocommerce_missing_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning is-dismissible"><p>';
	echo esc_html__(
		'Pickory Product Filter requires WooCommerce to be installed and activated.',
		'pickory-product-filter',
	);
	echo '</p></div>';
}
