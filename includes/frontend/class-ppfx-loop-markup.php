<?php
/**
 * Frontend WooCommerce loop markup for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Builds the stable markup boundaries used by frontend filtering.
 */
class PPFX_Loop_Markup {

	/**
	 * Active filter instance IDs stack.
	 *
	 * @var array<string>
	 */
	private static $instance_ids = array();

	/**
	 * Push a filter instance ID onto the active render stack.
	 *
	 * @param string $instance_id Filter instance ID.
	 * @return void
	 */
	public static function push_instance_id( string $instance_id ): void {
		self::$instance_ids[] = $instance_id;
	}

	/**
	 * Pop a filter instance ID from the active render stack.
	 *
	 * @return string|null
	 */
	public static function pop_instance_id(): ?string {
		return array_pop( self::$instance_ids );
	}

	/**
	 * Get the current filter instance ID.
	 *
	 * @return string|null
	 */
	public static function get_current_instance_id(): ?string {
		$last = end( self::$instance_ids );
		return $last ? $last : null;
	}

	/**
	 * Register WordPress hooks for WooCommerce product loop markup.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_loop_start', array( $this, 'wrap_product_loop_start' ), 999 );
		add_filter( 'woocommerce_product_loop_end', array( $this, 'wrap_product_loop_end' ), 999 );
		add_action( 'woocommerce_no_products_found', array( $this, 'open_products_wrapper' ), 5 );
		add_action( 'woocommerce_no_products_found', array( $this, 'close_products_wrapper' ), 15 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'open_loop_toolbar_wrapper' ), -PHP_INT_MAX );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'close_loop_toolbar_wrapper' ), PHP_INT_MAX );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'output_detached_chips_container' ), 20 );
		add_action( 'wp_head', array( $this, 'output_critical_loading_css_hook' ), 1 );
	}

	/**
	 * Hook callback for wp_head critical loading CSS and JS.
	 *
	 * @return void
	 */
	public function output_critical_loading_css_hook(): void {
		$should_load = class_exists( 'PPFX_Assets' ) ? PPFX_Assets::should_load_frontend_assets() : true;
		$this->output_critical_loading_css( $should_load );
	}

	/**
	 * Wrap a WooCommerce product loop opening tag.
	 *
	 * @param string      $html Original loop opening HTML.
	 * @param string|null $instance_id Current filter instance ID.
	 * @return string
	 */
	public function wrap_product_loop_start( $html, ?string $instance_id = null ): string {
		if ( null === $instance_id ) {
			$instance_id = self::get_current_instance_id();
		}
		return $this->get_products_wrapper_open_html( $instance_id ) . $html;
	}

	/**
	 * Wrap a WooCommerce product loop closing tag.
	 *
	 * @param string $html Original loop closing HTML.
	 * @return string
	 */
	public function wrap_product_loop_end( $html ): string {
		return $html . '</div>';
	}

	/**
	 * Build the opening products wrapper for a no-results loop.
	 *
	 * @param string|null $instance_id Current filter instance ID.
	 * @return string
	 */
	public function get_products_wrapper_open_html( ?string $instance_id ): string {
		$data_attr = $instance_id ? ' data-ppfx-instance="' . esc_attr( $instance_id ) . '"' : '';
		$hash      = $this->generate_filter_hash();

		if ( '' !== $hash ) {
			$data_attr .= ' data-filter-hash="' . esc_attr( $hash ) . '"';
		}

		return '<div class="ppfx-products-wrapper"' . $data_attr . '>';
	}

	/**
	 * Output the opening products wrapper for a no-results loop.
	 *
	 * @param string|null $instance_id Current filter instance ID.
	 * @return void
	 */
	public function open_products_wrapper( ?string $instance_id = null ): void {
		if ( null === $instance_id ) {
			$instance_id = self::get_current_instance_id();
		}
		echo $this->get_products_wrapper_open_html( $instance_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the closing products wrapper for a no-results loop.
	 *
	 * @return void
	 */
	public function close_products_wrapper(): void {
		echo '</div>';
	}

	/**
	 * Output the opening wrapper for theme-rendered before-loop content.
	 *
	 * @return void
	 */
	public function open_loop_toolbar_wrapper(): void {
		echo '<div class="ppfx-loop-toolbar" data-ppfx-loop-toolbar>';
	}

	/**
	 * Output the closing wrapper for theme-rendered before-loop content.
	 *
	 * @return void
	 */
	public function close_loop_toolbar_wrapper(): void {
		echo '</div>';
	}

	/**
	 * Output the mobile-toggle mount when the drawer uses DOM injection.
	 *
	 * @return void
	 */
	public function output_mobile_toggle_slot(): void {
		$options     = PPFX_Settings_Page::get_settings();
		$drawer_mode = $options['mobile_drawer_mode'];
		if ( ! $options['mobile_drawer_enabled'] || 'simple_toggle' === $drawer_mode ) {
			return;
		}

		echo '<div class="ppfx-mobile-toggle-slot" data-ppfx-mobile-toggle-slot></div>';
	}

	/**
	 * Build a stable hash of active URL filter params for SSR verification.
	 *
	 * @return string
	 */
	public function generate_filter_hash(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL filter params.
		$raw_get = wp_unslash( $_GET );
		$params  = PPFX_Utility::normalize_public_filter_params( $raw_get );

		foreach ( array( 'post_type', 'paged', 'page', 's', 'orderby', 'order', 'instance_id' ) as $key ) {
			unset( $params[ $key ] );
		}

		if ( empty( $params ) ) {
			return '';
		}

		$normalized = array();
		foreach ( $params as $key => $value ) {
			$normalized[ (string) $key ] = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		}
		ksort( $normalized );

		return md5( (string) wp_json_encode( $normalized ) );
	}

	/**
	 * Output the detached chips container when enabled.
	 *
	 * @return void
	 */
	public function output_detached_chips_container(): void {
		$options = PPFX_Settings_Page::get_settings();
		if ( empty( $options['chips_before_loop'] ) ) {
			return;
		}

		echo '<div class="ppfx-active-filters-wrap ppfx-detached ppfx-hidden" data-ppfx-chips-for="*">
    <div class="ppfx-active-filters-inner">
        <div class="ppfx-active-filters" data-ppfx-active-filters></div>
    </div>
</div>';
	}

	/**
	 * Output first-paint JS and CSS required by the mobile drawer and URL filters.
	 *
	 * @param bool $should_load Whether frontend assets should load on this request.
	 * @return void
	 */
	public function output_critical_loading_css( bool $should_load ): void {
		if ( ! $should_load ) {
			return;
		}

		$styles = array();
		$js     = '';

		if ( $this->has_url_filters() ) {
			$js .= '(function(){try{document.documentElement.classList.add("ppfx-has-url-filters");}catch(e){}}());';
		}

		$options       = PPFX_Settings_Page::get_settings();
		$mobile_drawer = ! empty( $options['mobile_drawer_enabled'] );

		if ( $mobile_drawer ) {
			$breakpoint  = $options['mobile_drawer_breakpoint'];
			$drawer_mode = $options['mobile_drawer_mode'];

			$js .= sprintf(
				'(function(){try{if(window.matchMedia("(max-width: %1$dpx)").matches){document.documentElement.classList.add("ppfx-mobile-drawer-view");}}catch(e){}}());',
				$breakpoint,
			);

			if ( 'dom_inject' === $drawer_mode ) {
				$styles[] = sprintf(
					'@media (max-width:%1$dpx){html.ppfx-mobile-drawer-view .ppfx-mobile-toggle-slot:not(:empty){display:flex;align-items:center;min-height:36px}}',
					$breakpoint,
				);
			}

			$raw_drawer_width = class_exists( 'PPFX_Appearance' ) ? (string) ( PPFX_Appearance::get_settings()['drawer-width'] ?? '300px' ) : '300px';
			$drawer_width     = preg_match( '/^[0-9]+(px|%|vw|rem|em)$/', $raw_drawer_width ) ? $raw_drawer_width : '300px';

			$styles[] = sprintf(
				'@media (max-width:%1$dpx){html.ppfx-mobile-drawer-view .ppfx-filter-container.ppfx-primary-filter{position:fixed;top:0;left:0;bottom:0;z-index:99999;width:%2$s;overflow-y:auto;overscroll-behavior:contain;transform:translateX(-100%%)}}',
				$breakpoint,
				$drawer_width,
			);
		}

		if ( '' !== $js ) {
			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $js, array( 'id' => 'ppfx-critical-js' ) );
			} else {
				echo '<script id="ppfx-critical-js">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( ! empty( $styles ) ) {
			wp_register_style( 'ppfx-critical-css', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only style.
			wp_enqueue_style( 'ppfx-critical-css' );
			wp_add_inline_style( 'ppfx-critical-css', implode( '', $styles ) );
		}
	}

	/**
	 * Detect whether the request includes an active filter.
	 *
	 * @return bool
	 */
	private function has_url_filters(): bool {
		$filter_keys = PPFX_Utility::get_filter_keys();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL params for display detection.
		foreach ( $_GET as $key => $value ) {
			$safe_key = preg_replace( '/[^a-z0-9_]/i', '', (string) $key );

			if ( in_array( $safe_key, $filter_keys, true ) || str_starts_with( $safe_key, 'ppfx_filter_' ) ) {
				if ( is_array( $value ) ) {
					if ( ! empty( array_filter( $value ) ) ) {
						return true;
					}
					continue;
				}

				$safe_value = sanitize_text_field( (string) $value );
				if ( in_array( $safe_value, array( '', '0' ), true ) ) {
					continue;
				}
				return true;
			}
		}

		return false;
	}
}
