<?php
/**
 * Type: Slider
 * Dual thumb range slider for Price.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Make cache key currency-aware for compatibility with currency switcher plugins.
$currency_code = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
$cache_key     = 'ppfx_price_range_' . $currency_code;
$range         = get_transient( $cache_key );

if ( false === $range ) {
	$range = null;

	// Try the custom index table first.
	if ( PPFX_Index::index_tables_available() ) {
		$index_table = PPFX_Index::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is trusted, result cached via transient.
		$range = $wpdb->get_row( "SELECT MIN(min_price) as min_price, MAX(max_price) as max_price FROM {$index_table}" );
	}

	// Fallback to WooCommerce product meta lookup table if index is unavailable or empty.
	if ( ! $range || null === $range->min_price || null === $range->max_price ) {
		$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result cached via transient.
		$range = $wpdb->get_row(
			"SELECT MIN(pm.min_price) as min_price,
                    MAX(pm.max_price) as max_price
             FROM {$lookup_table} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.product_id
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	set_transient( $cache_key, $range, HOUR_IN_SECONDS );
}

$min = $range ? floor( $range->min_price ) : null;
$max = $range ? ceil( $range->max_price ) : null;

if ( null !== $min && null !== $max && $min < $max ) :
	$decimals = wc_get_price_decimals();
	$min      = wc_format_decimal( (float) $min, $decimals );
	$max      = wc_format_decimal( (float) $max, $decimals );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only frontend URL filter params, no state change.
	$requested_min = isset( $_GET['ppfx_min_price'] )
		? (float) sanitize_text_field( wp_unslash( $_GET['ppfx_min_price'] ) )
		: ( isset( $_GET['min_price'] ) ? (float) sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) : null );

	$requested_max = isset( $_GET['ppfx_max_price'] )
		? (float) sanitize_text_field( wp_unslash( $_GET['ppfx_max_price'] ) )
		: ( isset( $_GET['max_price'] ) ? (float) sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) : null );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$current_min = $min;
	$current_max = $max;

	if ( null !== $requested_min ) {
		$val         = wc_format_decimal( (float) $requested_min, $decimals );
		$current_min = max( $min, min( $val, $max ) ); // Clamp between min and max.
	}

	if ( null !== $requested_max ) {
		$val         = wc_format_decimal( (float) $requested_max, $decimals );
		$current_max = max( $min, min( $val, $max ) ); // Clamp between min and max.
	}

	if ( (float) $current_min > (float) $current_max ) {
		$temp        = $current_min;
		$current_min = $current_max;
		$current_max = $temp;
	}

	$default_step = $decimals > 0 ? 1 / ( 10 ** $decimals ) : 1;
	$step         = isset( $config['step'] ) ? (float) $config['step'] : $default_step;
	$step         = $step > 0 ? wc_format_decimal( $step, $decimals ) : wc_format_decimal( $default_step, $decimals );
	// Get price format - use WooCommerce's get_woocommerce_price_format() if available,
	// otherwise use a sensible fallback.
	$price_format_string = function_exists( 'get_woocommerce_price_format' )
		? get_woocommerce_price_format()
		: '%1$s%2$s'; // Fallback: currency symbol followed by value.

	// Get currency symbol - supports currency switcher plugins that filter this value.
	$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
		? get_woocommerce_currency_symbol()
		: '$';

	$price_format = html_entity_decode(
		sprintf( $price_format_string, $currency_symbol, '%v' ),
		ENT_QUOTES,
		get_bloginfo( 'charset' )
	);

	if ( 1 === (int) (float) $step ) {
		$min_display = wc_price( (float) $current_min, array( 'decimals' => 0 ) );
		$max_display = wc_price( (float) $current_max, array( 'decimals' => 0 ) );
	} else {
		$min_display = wc_price( $current_min );
		$max_display = wc_price( $current_max );
	}
	?>
<div class="ppfx-price-group" data-min="<?php echo esc_attr( $min ); ?>" data-max="<?php echo esc_attr( $max ); ?>" data-currency="<?php echo esc_attr( $currency_symbol ); ?>" data-price-format="<?php echo esc_attr( $price_format ); ?>">
	<div class="ppfx-price-slider">
		<div class="ppfx-price-track-bg">
			<div class="ppfx-price-track-fill"></div>
		</div>
		<input type="range" name="min_price" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $current_min ); ?>" step="<?php echo esc_attr( $step ); ?>" aria-label="<?php esc_attr_e( 'Minimum price', 'pickory-product-filter' ); ?>" />
		<input type="range" name="max_price" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $current_max ); ?>" step="<?php echo esc_attr( $step ); ?>" aria-label="<?php esc_attr_e( 'Maximum price', 'pickory-product-filter' ); ?>" />
	</div>
	<div class="ppfx-price-labels">
		<span><?php esc_html_e( 'Min', 'pickory-product-filter' ); ?> <b class="ppfx-price-min-label"><?php echo $min_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() returns sanitized core HTML. ?></b></span>
		<span><?php esc_html_e( 'Max', 'pickory-product-filter' ); ?> <b class="ppfx-price-max-label"><?php echo $max_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() returns sanitized core HTML. ?></b></span>
	</div>
</div>
<?php endif; ?>
