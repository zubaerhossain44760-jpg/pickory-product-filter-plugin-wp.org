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
		add_shortcode( 'ppfx_filter', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Helper to determine input name based on section key.
	 *
	 * @deprecated Use PPFX_Utility::get_input_name() instead.
	 * @param string $key The section key.
	 * @return string
	 */
	public static function get_input_name( $key ) {
		return PPFX_Utility::get_input_name( (string) $key );
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

		// Ensure frontend assets (scripts and styles) are enqueued in wp_footer if rendering via shortcode on non-standard pages.
		PPFX_Assets::enqueue_frontend_assets();

		ob_start();

		$template_path = locate_template( 'pickory-product-filter/filter-layout.php' );
		if ( ! $template_path ) {
			$template_path = PPFX_PLUGIN_DIR . 'templates/filter-layout.php';
		}

		if ( file_exists( $template_path ) ) {
			$extra_class = '';
			if ( ! empty( $atts['class'] ) ) {
				$classes     = array_filter( array_map( 'sanitize_html_class', explode( ' ', (string) $atts['class'] ) ) );
				$extra_class = $classes ? ' ' . implode( ' ', $classes ) : '';
			}

			$unique_id = empty( $atts['instance_id'] ) ? wp_unique_id( 'ppfx-filter-' ) : sanitize_key( $atts['instance_id'] );
			if ( empty( $unique_id ) ) {
				$unique_id = wp_unique_id( 'ppfx-filter-' );
			}

			$requested_sections = ! empty( $atts['sections'] ) ? array_map( 'trim', explode( ',', $atts['sections'] ) ) : array();
			// Push the instance ID to a stack so class-ppfx-assets.php knows which ID to use.
			PPFX_Assets::push_instance_id( $unique_id );
			try {
				include $template_path;
			} finally {
				// Pop the instance ID after rendering, guaranteed even if an exception occurs.
				PPFX_Assets::pop_instance_id();
			}
		} else {
			echo '<!-- Filter Template Missing -->';
		}

		return ob_get_clean();
	}
}
