<?php
/**
 * Asset enqueueing and frontend rendering hooks for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Manages all script/style enqueueing, Vite manifest integration, and front-end hooks.
 */
class PPFX_Assets {

	/**
	 * Stack of active filter instance IDs.
	 *
	 * @var array
	 */
	private static $instance_ids = array();

	/**
	 * Push a filter instance ID onto the stack.
	 *
	 * @param string $instance_id The filter instance ID.
	 * @return void
	 */
	public static function push_instance_id( string $instance_id ): void {
		self::$instance_ids[] = $instance_id;
	}

	/**
	 * Pop a filter instance ID from the stack.
	 *
	 * @return string|null
	 */
	public static function pop_instance_id(): ?string {
		return array_pop( self::$instance_ids );
	}

	/**
	 * Get the current filter instance ID from the top of the stack.
	 *
	 * @return string|null
	 */
	public static function get_current_instance_id(): ?string {
		$last = end( self::$instance_ids );
		return $last ? $last : null;
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Ensure module scripts are loaded correctly for plugin Vite assets.
		add_filter(
			'script_loader_tag',
			array( $this, 'add_module_type_to_scripts' ),
			10,
			3,
		);

		// Keep the AJAX product target stable across normal and no-result states.
		add_filter(
			'woocommerce_product_loop_start',
			array( $this, 'wrap_product_loop_start' ),
			999,
		);
		add_filter(
			'woocommerce_product_loop_end',
			array( $this, 'wrap_product_loop_end' ),
			999,
		);
		add_action(
			'woocommerce_no_products_found',
			array( $this, 'open_products_wrapper' ),
			5,
		);
		add_action(
			'woocommerce_no_products_found',
			array( $this, 'close_products_wrapper' ),
			15,
		);

		// Output critical inline CSS to prevent flash.
		add_action( 'wp_head', array( $this, 'output_critical_loading_css' ), 1 );

		// Reserve the mobile filter toggle's final shop-loop position before JS hydrates it.
		add_action( 'woocommerce_before_shop_loop', array( $this, 'output_mobile_toggle_slot' ), 1 );
	}

	/**
	 * Check if the Vite dev server is active.
	 */
	private function is_dev_mode(): bool {
		return (bool) PPFX_VITE_DEV;
	}

	/**
	 * Get the Vite manifest (cached).
	 */
	private function get_manifest() {
		static $manifest = null;
		if ( null !== $manifest ) {
			return $manifest;
		}
		$manifest_path = PPFX_PLUGIN_DIR . 'build/.vite/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return null;
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$manifest = json_decode( file_get_contents( $manifest_path ), true );
		return $manifest;
	}

	/**
	 * Add type="module" to plugin Vite bundles (dev + production).
	 *
	 * @param string $tag The script tag HTML.
	 * @param string $handle The script handle.
	 * @param string $src The script source URL.
	 * @return string
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ): string {
		// Vite bundles use top-level const/let. In classic scripts these can leak
		// into the global lexical scope and shadow WP's underscore `_` in Customizer.
		// Force module scope for all plugin Vite entry handles (dev + production).
		$module_handles = array(
			'ppfx-vite-client',
			'ppfx-vite-app',
			'ppfx-app-scripts',
			'ppfx-block-editor',
			'ppfx-vite-frontpagejs',
			'ppfx-vite-frontpagecss',
		);

		if (
				in_array( $handle, $module_handles, true ) ||
				str_starts_with( $handle, 'ppfx-vite-' ) ||
				( defined( 'PPFX_VITE_SERVER' ) &&
						PPFX_VITE_SERVER !== '' &&
						str_contains( $src, PPFX_VITE_SERVER ) )
		) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this is the inline module tag for Vite HMR client, not a registered script.
			return '<script type="module" crossorigin src="' .
					esc_url( $src ) .
					'"></script>';
		}
		return $tag;
	}

	/**
	 * Check whether frontend filter assets should load on the current request.
	 *
	 * @return bool
	 */
	private function should_load_frontend_assets(): bool {
		static $should_load = null;

		if ( null !== $should_load ) {
			return $should_load;
		}

		$post         = get_post();
		$post_content = $post ? $post->post_content : '';

		$should_load = is_shop() ||
				is_tax( get_object_taxonomies( 'product' ) ) ||
				has_shortcode( $post_content, 'pickory_product_filter' ) ||
				has_shortcode( $post_content, 'ppfx_filter' ) ||
				has_block( 'pickory/product-filter', $post_content );

		return $should_load;
	}

	/**
	 * Enqueue all frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}

		if ( ! wp_script_is( 'lapilli-ui-styles', 'registered' ) ) {
			wp_register_script(
				'lapilli-ui-styles',
				false,
				array(),
				PPFX_VERSION,
				true,
			);
		}

		if ( $this->is_dev_mode() ) {
			$this->enqueue_dev_assets();
		} else {
			$this->enqueue_production_assets();
		}
	}

	/**
	 * Helper to enqueue Vite client in dev mode.
	 */
	private function maybe_enqueue_vite_client() {
		if (
				! $this->is_dev_mode() ||
				wp_script_is( 'ppfx-vite-client', 'enqueued' )
		) {
			return;
		}
		wp_enqueue_script(
			'ppfx-vite-client',
			PPFX_VITE_SERVER . '/@vite/client',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev-only Vite HMR client; null intentionally disables caching.
			false,
		);
	}

	/**
	 * DEV MODE: Serve assets directly from the Vite dev server with HMR.
	 */
	private function enqueue_dev_assets() {
		$this->maybe_enqueue_vite_client();

		// 2. Enqueue JS entry point
		wp_enqueue_script(
			'ppfx-vite-app',
			PPFX_VITE_SERVER . '/src/js/app.js',
			array(
				'ppfx-vite-client',
				'jquery',
				'underscore',
				'wp-util',
				'lapilli-ui-styles',
			),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev-only Vite HMR asset; null intentionally disables caching.
			true,
		);

		// 3. Localize script data
		$this->localize_script( 'ppfx-vite-app' );
	}

	/**
	 * PRODUCTION MODE: Serve built assets via the Vite manifest.
	 */
	private function enqueue_production_assets() {
		$manifest = $this->get_manifest();

		if ( ! $manifest ) {
			return;
		}

		// The Vite 5+ manifest uses relative paths as keys, not custom rollup input names.
		if ( isset( $manifest['src/js/app.js'] ) ) {
			$js_file = $manifest['src/js/app.js']['file'];
			wp_enqueue_script(
				'ppfx-app-scripts',
				PPFX_PLUGIN_URL . 'build/' . $js_file,
				array( 'jquery', 'underscore', 'wp-util', 'lapilli-ui-styles' ),
				PPFX_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				),
			);

			$this->localize_script( 'ppfx-app-scripts' );

			// If the JS entry imports CSS, load it too.
			if ( isset( $manifest['src/js/app.js']['css'] ) ) {
				foreach (
						$manifest['src/js/app.js']['css']
						as $index => $css_file
				) {
					wp_enqueue_style(
						'ppfx-app-js-css-' . $index,
						PPFX_PLUGIN_URL . 'build/' . $css_file,
						array(),
						PPFX_VERSION,
					);
				}
			}
		}
	}

	/**
	 * Localize filter data to the given script handle.
	 *
	 * @param string $handle Registered script handle to attach data to.
	 * @return void
	 */
	private function localize_script( string $handle ) {
		$options     = get_option( 'ppfx_settings' );
		$auto_filter =
				isset( $options['auto_filter'] ) && 'no' === $options['auto_filter']
						? false
						: true;

		$current_context = array();
		if ( is_product_category() ) {
			$current_context['category'] = get_queried_object()->slug;
		} elseif ( is_product_tag() ) {
			$current_context['product_tag'] = get_queried_object()->slug;
		} elseif ( is_tax() ) {
			$term                            = get_queried_object();
			$current_context[ $term->taxonomy ] = $term->slug;
		}

		wp_localize_script(
			$handle,
			'ppfxFilterData',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'pickory-filter/v1/products' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'autoFilter'     => $auto_filter,

				'currentContext' => $current_context,
				'filterKeys'     => $this->get_filter_keys(),
				'cacheVersion'   => class_exists( 'PPFX_Query_Cache' ) ? PPFX_Query_Cache::get_query_cache_version() : '1',
				'i18n'           => array(
					'onSale' => __( 'On Sale', 'pickory-product-filter' ),
					'stars'  => __( ' Stars', 'pickory-product-filter' ),
					'filter' => __( 'Filter', 'pickory-product-filter' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets for settings and term meta pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		global $current_screen;

		// 1. Plugin Settings Page - Match any hook containing our slug.
		if ( str_contains( $hook, 'pickory-product-filter' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'jquery-ui-sortable' );
			$this->enqueue_vite_asset(
				'src/css/ppfx-admin.css',
				'src/css/ppfx-admin.css',
			);
			$this->enqueue_vite_asset(
				'src/js/admin/ppfx-admin.js',
				'src/js/admin/ppfx-admin.js',
				array( 'jquery', 'jquery-ui-sortable' ),
			);

			return;
		}

		// 2. Term Meta (Attribute Screens).
		if (
				isset( $current_screen->taxonomy ) &&
				str_starts_with( $current_screen->taxonomy, 'pa_' )
		) {
			// WordPress Core Assets.
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			// Plugin-specific assets.
			$this->enqueue_vite_asset(
				'src/css/ppfx-term-meta.css',
				'src/css/ppfx-term-meta.css',
			);
			$this->enqueue_vite_asset(
				'src/js/admin/ppfx-term-meta.js',
				'src/js/admin/ppfx-term-meta.js',
				array( 'jquery', 'wp-color-picker' ),
			);
		}
	}

	/**
	 * Smart loader for plugin Vite assets.
	 *
	 * @param string $entry_key The Vite manifest entry key.
	 * @param string $entry_path The source path relative to the project root.
	 * @param array  $deps Script/style dependencies.
	 * @param string $media Media type for stylesheets.
	 * @return string The registered handle.
	 */
	public function enqueue_vite_asset(
		$entry_key,
		$entry_path,
		$deps = array(),
		$media = 'all',
	) {
		static $manifest = null;
		$handle          = 'ppfx-vite-' . sanitize_title( $entry_key );

		if ( $this->is_dev_mode() ) {
			$this->maybe_enqueue_vite_client();
			$url = PPFX_VITE_SERVER . '/' . ltrim( $entry_path, '/' );

			if ( preg_match( '/\.css$/', $entry_path ) ) {
                // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev-only Vite HMR asset; null intentionally disables caching.
				wp_enqueue_style( $handle, $url, $deps, null, $media );
			} else {
				wp_enqueue_script(
					$handle,
					$url,
					array_merge( array( 'ppfx-vite-client' ), $deps ),
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev-only Vite HMR asset; null intentionally disables caching.
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					),
				);
			}
		} else {
			if ( null === $manifest ) {
				$manifest = $this->get_manifest();
			}

			if ( $manifest && isset( $manifest[ $entry_key ] ) ) {
				$file_info = $manifest[ $entry_key ];
				$file_url  = PPFX_PLUGIN_URL . 'build/' . $file_info['file'];
				$version   = PPFX_VERSION;

				if ( preg_match( '/\.css$/', $file_info['file'] ) ) {
					wp_enqueue_style(
						$handle,
						$file_url,
						$deps,
						$version,
						$media,
					);
				} else {
					wp_enqueue_script(
						$handle,
						$file_url,
						$deps,
						$version,
						array(
							'in_footer' => true,
							'strategy'  => 'defer',
						)
					);
				}

				if ( isset( $file_info['css'] ) ) {
					foreach ( $file_info['css'] as $index => $css_file ) {
						wp_enqueue_style(
							$handle . '-css-' . $index,
							PPFX_PLUGIN_URL . 'build/' . $css_file,
							array(),
							$version,
							$media,
						);
					}
				}
			}
		}

		return $handle;
	}

	/**
	 * Wrap woocommerce_product_loop_start with the products wrapper div.
	 *
	 * @param string $html The original loop start HTML.
	 * @return string
	 */
	public function wrap_product_loop_start( $html ) {
		return $this->get_products_wrapper_open_html() . $html;
	}

	/**
	 * Wrap woocommerce_product_loop_end by closing the products wrapper div.
	 *
	 * @param string $html The original loop end HTML.
	 * @return string
	 */
	public function wrap_product_loop_end( $html ) {
		return $html . '</div>';
	}

	/**
	 * Echo the opening products wrapper div (used on no-results).
	 *
	 * @return void
	 */
	public function open_products_wrapper() {
		echo $this->get_products_wrapper_open_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Echo the closing products wrapper div (used on no-results).
	 *
	 * @return void
	 */
	public function close_products_wrapper() {
		echo '</div>';
	}

	/**
	 * Output a stable mobile toggle mount above the shop toolbar.
	 *
	 * @return void
	 */
	public function output_mobile_toggle_slot() {
		$options = get_option( 'ppfx_settings' );
		if ( empty( $options['mobile_drawer_enabled'] ) ) {
			return;
		}

		echo '<div class="ppfx-mobile-toggle-slot" data-ppfx-mobile-toggle-slot></div>';
	}

	// Mobile drawer toggle and backdrop are now output per-filter in filter-layout.php.

	/**
	 * Build and return the opening HTML for the products wrapper div.
	 *
	 * @return string
	 */
	private function get_products_wrapper_open_html(): string {
		$classes = 'ppfx-products-wrapper';

		$instance_id = self::get_current_instance_id();
		$data_attr   = $instance_id ? ' data-ppfx-instance="' . esc_attr( $instance_id ) . '"' : '';

		$hash = $this->generate_filter_hash();
		if ( ! empty( $hash ) ) {
			$data_attr .= ' data-filter-hash="' . esc_attr( $hash ) . '"';
		}

		return '<div class="' . esc_attr( $classes ) . '"' . $data_attr . '>';
	}

	/**
	 * Build a stable hash of active URL filter params for SSR verification.
	 *
	 * @return string
	 */
	public function generate_filter_hash() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL filter params.
		$raw_get = wp_unslash( $_GET );

		$params = PPFX_Utility::normalize_public_filter_params( $raw_get );

		$excluded = array( 'post_type', 'paged', 'page', 's', 'orderby', 'order', 'instance_id' );
		foreach ( $excluded as $key ) {
			unset( $params[ $key ] );
		}

		if ( empty( $params ) ) {
			return '';
		}

		// Convert all values to strings to match JS URLSearchParams behavior.
		$normalized = array();
		foreach ( $params as $key => $value ) {
			$normalized[ (string) $key ] = (string) $value;
		}
		ksort( $normalized );

		return md5( (string) wp_json_encode( $normalized ) );
	}

	/**
	 * Output critical CSS to prevent first-paint layout flashes.
	 *
	 * @return void
	 */
	public function output_critical_loading_css() {
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}

		$styles = array();

		$js = '';

		if ( $this->has_url_filters() ) {
			$js .= '(function(){try{document.documentElement.classList.add("ppfx-has-url-filters");}catch(e){}}());';
		}

		$options       = get_option( 'ppfx_settings' );
		$mobile_drawer = ! empty( $options['mobile_drawer_enabled'] );

		if ( $mobile_drawer ) {
			$breakpoint = min(
				max( absint( $options['mobile_drawer_breakpoint'] ?? 768 ), 320 ),
				1400,
			);

			$js .= sprintf(
				'(function(){try{if(window.matchMedia("(max-width: %1$dpx)").matches){document.documentElement.classList.add("ppfx-mobile-drawer-view");}}catch(e){}}());',
				$breakpoint
			);

			$styles[] = sprintf(
				'@media (max-width:%1$dpx){html.ppfx-mobile-drawer-view .ppfx-mobile-toggle-slot{display:flex;align-items:center;min-height:36px}}',
				$breakpoint,
			);

			$drawer_width = '300px';

			$styles[] = sprintf(
				'@media (max-width:%1$dpx){html.ppfx-mobile-drawer-view .ppfx-filter-container.ppfx-primary-filter{position:fixed;top:0;left:0;bottom:0;z-index:99999;width:%2$s;overflow-y:auto;overscroll-behavior:contain;transform:translateX(-100%%);border-radius:0 12px 12px 0;box-shadow:4px 0 24px rgba(0,0,0,.15)}}',
				$breakpoint,
				$drawer_width,
			);
		}

		if ( ! empty( $js ) ) {
			wp_register_script( 'ppfx-critical-js', false );
			wp_enqueue_script( 'ppfx-critical-js' );
			wp_add_inline_script( 'ppfx-critical-js', $js );
		}

		if ( ! empty( $styles ) ) {
			wp_register_style( 'ppfx-critical-css', false );
			wp_enqueue_style( 'ppfx-critical-css' );
			wp_add_inline_style( 'ppfx-critical-css', implode( '', $styles ) );
		}
	}

	/**
	 * Detect if URL contains filter parameters (with sanitization).
	 *
	 * @return bool
	 */
	private function has_url_filters() {
		$filter_keys = $this->get_filter_keys();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading GET params for display detection only, no state change.
		foreach ( $_GET as $key => $value ) {
			$safe_key   = preg_replace( '/[^a-z0-9_]/i', '', (string) $key );
			$safe_value = sanitize_text_field( (string) $value );

			// Check for direct match or filter type.
			if (
					in_array( $safe_key, $filter_keys, true ) ||
					str_starts_with( $safe_key, 'ppfx_filter_' )
			) {
				// Skip empty or zero values to avoid false positives
				// (e.g. on_sale=0, min_price=0, rating=0 are effectively "no filter").
				if ( in_array( $safe_value, array( '', '0' ), true ) ) {
					continue;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Get list of valid filter keys.
	 * Central source of truth for both PHP detection and JS URL sync.
	 * Dynamically includes WooCommerce attribute taxonomy keys when WC is active.
	 */
	private function get_filter_keys(): array {
		static $keys = null;

		if ( null !== $keys ) {
			return $keys;
		}

		$keys = array(
			'ppfx_category',
			'ppfx_product_tag',
			'ppfx_min_price',
			'ppfx_max_price',
			'ppfx_rating',
			'ppfx_on_sale',
		);

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $taxonomy ) {
					$keys[] = 'ppfx_filter_' . $taxonomy->attribute_name;
				}
			}
		}

		// Include other public product taxonomies (mirrors REST endpoint args).
		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->public && ! in_array( 'ppfx_' . $tax->name, $keys, true ) ) {
				$keys[] = 'ppfx_' . $tax->name;
			}
		}

		return $keys;
	}
}
