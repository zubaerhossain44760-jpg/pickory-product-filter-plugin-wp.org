<?php
/**
 * PHPStan bootstrap stub.
 *
 * Defines plugin constants that are normally set at runtime by the WordPress
 * plugin loader. These stubs let PHPStan resolve usages of the constants
 * without flagging them as "constant not found".
 *
 * PPFX_VITE_DEV and PPFX_VITE_SERVER are intentionally omitted here — they are
 * listed under dynamicConstantNames in phpstan.neon so PHPStan treats them as
 * unknowable at analysis time and avoids false "ternary always false" errors.
 *
 * This file is NOT loaded by WordPress — it is only used during static analysis.
 */

// Plugin path/URL constants (defined in pickory-product-filter.php at runtime).
if ( ! defined( 'PPFX_VERSION' ) ) {
	define( 'PPFX_VERSION', '1.0.0' );
}
if ( ! defined( 'PPFX_INDEX_VERSION' ) ) {
	define( 'PPFX_INDEX_VERSION', '1.1' );
}
if ( ! defined( 'PPFX_PLUGIN_DIR' ) ) {
	define( 'PPFX_PLUGIN_DIR', __DIR__ . '/' );
}
if ( ! defined( 'PPFX_PLUGIN_URL' ) ) {
	define( 'PPFX_PLUGIN_URL', 'https://example.com/wp-content/plugins/pickory-product-filter/' );
}
if ( ! defined( 'PPFX_PLUGIN_FILE' ) ) {
	define( 'PPFX_PLUGIN_FILE', __DIR__ . '/pickory-product-filter.php' );
}
if ( ! defined( 'PPFX_PLUGIN_BASENAME' ) ) {
	define( 'PPFX_PLUGIN_BASENAME', 'pickory-product-filter/pickory-product-filter.php' );
}

// WordPress time constants (normally loaded with wp-includes/functions.php).
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// WordPress path constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__, 2 ) );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}
