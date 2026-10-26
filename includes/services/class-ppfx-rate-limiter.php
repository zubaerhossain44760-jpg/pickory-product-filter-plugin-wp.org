<?php
/**
 * Rate limiter utility for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides transient-based rate limiting for REST endpoints.
 */
class PPFX_Rate_Limiter {

	/**
	 * Get the client's real IP address
	 *
	 * @return string
	 */
	public static function get_client_ip(): string {
		// Cloudflare provides the connecting client IP in HTTP_CF_CONNECTING_IP.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip_string = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			$cf_ips       = explode( ',', $cf_ip_string );
			$cf_ip        = trim( $cf_ips[0] );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $cf_ip;
			}
		}

		// Generic proxy headers require explicit opt-in via PPFX_TRUST_PROXY to prevent spoofing.
		if ( defined( 'PPFX_TRUST_PROXY' ) && PPFX_TRUST_PROXY ) {
			$forwarded_headers = array(
				'HTTP_X_REAL_IP',
				'HTTP_X_FORWARDED_FOR',
			);
			foreach ( $forwarded_headers as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					$ip_string = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
					$ips       = explode( ',', $ip_string );
					$ip        = trim( $ips[0] );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- IP addresses do not contain slashes.
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
	}

	/**
	 * Check if a client has exceeded their rate limit
	 *
	 * @param string $prefix Transient prefix for this limit.
	 * @param int    $max_hits Maximum allowed hits.
	 * @param int    $window_in_seconds Time window for rate limiting.
	 * @return bool True if rate limited, false otherwise
	 */
	public static function is_rate_limited( string $prefix, int $max_hits, int $window_in_seconds = MINUTE_IN_SECONDS ): bool {
		$options          = PPFX_Settings_Page::get_settings();
		$is_explicitly_on = 'yes' === $options['rate_limiting_enabled'];

		// Bypass writing DB transients if external object caching is not present
		// to protect option table bloat, unless rate limiting is explicitly enabled via settings or filter.
		if ( ! $is_explicitly_on && ! wp_using_ext_object_cache() && ! apply_filters( 'ppfx_force_db_rate_limiting', false ) ) {
			return false;
		}

		$ip            = self::get_client_ip();
		$transient_key = 'ppfx_' . md5( $prefix . ':' . $ip );

		if ( wp_using_ext_object_cache() ) {
			$hit_count = wp_cache_incr( $transient_key, 1, 'pickory-product-filter' );

			if ( false === $hit_count ) {
				wp_cache_set( $transient_key, 1, 'pickory-product-filter', $window_in_seconds );
				$hit_count = 1;
			}

			return $hit_count > $max_hits;
		}

		$data = get_transient( $transient_key );
		if ( ! is_array( $data ) || empty( $data['expires'] ) || time() > (int) $data['expires'] ) {
			$data = array(
				'hits'    => 0,
				'expires' => time() + $window_in_seconds,
			);
		}

		if ( (int) $data['hits'] >= $max_hits ) {
			return true;
		}

		++$data['hits'];
		$remaining_ttl = max( 1, (int) $data['expires'] - time() );
		set_transient( $transient_key, $data, $remaining_ttl );

		return false;
	}
}
