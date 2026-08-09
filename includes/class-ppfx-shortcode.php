<?php
/**
 * Shortcode handler for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the product filter shortcodes.
 */
class PPFX_Shortcode {


	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'pickory_product_filter', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Helper to determine input name based on section key
	 *
	 * @param string $key The section key.
	 * @return string
	 */
	public static function get_input_name( $key ) {
		if ( 'category' === $key ) {
			return 'category';
		}
		if ( 'product_tag' === $key ) {
			return 'product_tag';
		}
		if ( 'price' === $key ) {
			return 'price';
		}
		if ( 'rating' === $key ) {
			return 'rating';
		}
		if ( 'sale' === $key ) {
			return 'on_sale';
		}
		if ( str_starts_with( $key, 'pa_' ) ) {
			return 'filter_' . substr( $key, 3 );
		}
		return $key;
	}


	/**
	 * Render the product filter shortcode.
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'       => '',
				'instance_id' => '',
				'sections'    => '',
			),
			$atts,
			'pickory_product_filter'
		);

		ob_start();

		$template_path = locate_template( 'pickory-product-filter/filter-layout.php' );
		if ( ! $template_path ) {
			$template_path = PPFX_PLUGIN_DIR . 'templates/filter-layout.php';
		}

		if ( file_exists( $template_path ) ) {
			$extra_class        = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';
			$unique_id          = empty( $atts['instance_id'] ) ? wp_unique_id( 'ppfx-filter-' ) : $atts['instance_id'];
			$requested_sections = ! empty( $atts['sections'] ) ? array_map( 'trim', explode( ',', $atts['sections'] ) ) : array();
			// Push the instance ID to a stack so class-ppfx-assets.php knows which ID to use.
			PPFX_Assets::push_instance_id( $unique_id );
			include $template_path;
			// Pop the instance ID after rendering.
			PPFX_Assets::pop_instance_id();
		} else {
			echo '<!-- Filter Template Missing -->';
		}

		return ob_get_clean();
	}
}
