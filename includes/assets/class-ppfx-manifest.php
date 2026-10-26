<?php
/**
 * Vite manifest access for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Reads and caches the Vite build manifest.
 */
class PPFX_Manifest {

	/**
	 * Get the Vite manifest from memory, transient cache, or disk.
	 *
	 * @return array|null
	 */
	public static function get(): ?array {
		static $memory_manifest = null;

		if ( null !== $memory_manifest ) {
			return $memory_manifest;
		}

		$manifest_path = PPFX_PLUGIN_DIR . 'build/.vite/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return null;
		}

		$mtime_raw = filemtime( $manifest_path );
		$mtime     = false !== $mtime_raw ? $mtime_raw : 0;
		$cache_key = 'ppfx_manifest_' . md5( PPFX_VERSION . '_' . $mtime );
		$manifest  = get_transient( $cache_key );

		if ( false === $manifest || ! is_array( $manifest ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw_content = file_get_contents( $manifest_path );
			$manifest    = $raw_content ? json_decode( $raw_content, true ) : null;
			if ( is_array( $manifest ) ) {
				set_transient( $cache_key, $manifest, DAY_IN_SECONDS );
			}
		}

		$memory_manifest = is_array( $manifest ) ? $manifest : null;
		return $memory_manifest;
	}
}
