<?php
/**
 * Vite asset loading for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Registers and enqueues development and production assets.
 */
class PPFX_Asset_Loader {

	/**
	 * Check if the Vite dev server is active.
	 *
	 * @return bool
	 */
	public function is_dev_mode(): bool {
		return (bool) PPFX_VITE_DEV;
	}

	/**
	 * Add type="module" to plugin Vite bundles.
	 *
	 * @param string $tag The script tag HTML.
	 * @param string $handle The script handle.
	 * @param string $src The script source URL.
	 * @return string
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ): string {
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
			( defined( 'PPFX_VITE_SERVER' ) && PPFX_VITE_SERVER !== '' && str_contains( $src, PPFX_VITE_SERVER ) )
		) {
			if ( empty( $tag ) ) {
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Filtering script tag HTML in script_loader_tag hook.
				return '<script type="module" crossorigin src="' . esc_url( $src ) . '"></script>';
			}

			if ( ! preg_match( '/<script\b[^>]*\bsrc=[^>]*>/i', $tag, $matches ) ) {
				return $tag;
			}

			$original_script_tag = $matches[0];
			$new_script_tag      = $original_script_tag;

			if ( preg_match( '/\btype=[\'\"]text\/javascript[\'\"]/i', $new_script_tag ) ) {
				$new_script_tag = preg_replace( '/\btype=[\'\"]text\/javascript[\'\"]/i', 'type="module"', $new_script_tag, 1 );
			} elseif ( ! str_contains( $new_script_tag, 'type="module"' ) ) {
				$new_script_tag = preg_replace( '/<script\b/i', '<script type="module"', $new_script_tag, 1 );
			}

			if ( ! str_contains( $new_script_tag, 'crossorigin' ) ) {
				$new_script_tag = preg_replace( '/<script\b/i', '<script crossorigin', $new_script_tag, 1 );
			}

			return str_replace( $original_script_tag, $new_script_tag, $tag );
		}

		return $tag;
	}

	/**
	 * Enqueue the public application entrypoint.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( $this->is_dev_mode() ) {
			$this->enqueue_dev_assets();
			return;
		}

		$this->enqueue_production_assets();
	}

	/**
	 * Enqueue assets for plugin settings and attribute term screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		global $current_screen;

		$is_settings_page = str_contains( $hook, 'pickory-product-filter' );
		$is_pa_taxonomy   = isset( $current_screen->taxonomy ) && str_starts_with( $current_screen->taxonomy, 'pa_' );

		if ( ! $is_settings_page && ! $is_pa_taxonomy ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$admin_deps = array( 'jquery', 'wp-color-picker' );

		if ( $is_settings_page ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
			$admin_deps[] = 'jquery-ui-sortable';
		}

		$this->enqueue_vite_asset( 'src/css/ppfx-admin.css', 'src/css/ppfx-admin.css' );
		$this->enqueue_vite_asset( 'src/js/admin/ppfx-admin.js', 'src/js/admin/ppfx-admin.js', $admin_deps );
	}

	/**
	 * Enqueue a Vite asset used by the admin screens.
	 *
	 * @param string $entry_key The Vite manifest entry key.
	 * @param string $entry_path Source path relative to the project root.
	 * @param array  $deps Script/style dependencies.
	 * @param string $media Media type for stylesheets.
	 * @return string
	 */
	public function enqueue_vite_asset( $entry_key, $entry_path, $deps = array(), $media = 'all' ): string {
		$handle = 'ppfx-vite-' . sanitize_title( $entry_key );

		if ( $this->is_dev_mode() ) {
			$this->maybe_enqueue_vite_client();
			$url = PPFX_VITE_SERVER . '/' . ltrim( $entry_path, '/' );

			if ( preg_match( '/\.css$/', $entry_path ) ) {
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Dev-only Vite HMR asset.
				wp_enqueue_style( $handle, $url, $deps, null, $media );
			} else {
				wp_enqueue_script(
					$handle,
					$url,
					array_merge( array( 'ppfx-vite-client' ), $deps ),
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Dev-only Vite HMR asset.
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					),
				);
			}

			return $handle;
		}

		$manifest = PPFX_Manifest::get();
		if ( ! $manifest || ! isset( $manifest[ $entry_key ] ) ) {
			return $handle;
		}

		$file_info = $manifest[ $entry_key ];
		$file_url  = PPFX_PLUGIN_URL . 'build/' . $file_info['file'];
		$version   = PPFX_VERSION;

		if ( preg_match( '/\.css$/', $file_info['file'] ) ) {
			wp_enqueue_style( $handle, $file_url, $deps, $version, $media );
		} else {
			wp_enqueue_script(
				$handle,
				$file_url,
				$deps,
				$version,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				),
			);
		}

		if ( ! empty( $file_info['css'] ) && is_array( $file_info['css'] ) ) {
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

		return $handle;
	}

	/**
	 * Enqueue the Vite HMR client once in development.
	 *
	 * @return void
	 */
	private function maybe_enqueue_vite_client(): void {
		if ( ! $this->is_dev_mode() || wp_script_is( 'ppfx-vite-client', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'ppfx-vite-client',
			PPFX_VITE_SERVER . '/@vite/client',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Dev-only Vite HMR client.
			true,
		);
	}

	/**
	 * Enqueue development assets from the Vite server.
	 *
	 * @return void
	 */
	private function enqueue_dev_assets(): void {
		$this->maybe_enqueue_vite_client();

		wp_enqueue_script(
			'ppfx-vite-app',
			PPFX_VITE_SERVER . '/src/js/app.js',
			array( 'ppfx-vite-client', 'jquery', 'underscore', 'wp-util' ),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Dev-only Vite HMR asset.
			true,
		);

		$this->localize_script( 'ppfx-vite-app' );
	}

	/**
	 * Enqueue production assets from the Vite manifest.
	 *
	 * @return void
	 */
	private function enqueue_production_assets(): void {
		$manifest = PPFX_Manifest::get();
		if ( ! $manifest || ! isset( $manifest['src/js/app.js'] ) ) {
			return;
		}

		$file_info = $manifest['src/js/app.js'];
		wp_enqueue_script(
			'ppfx-app-scripts',
			PPFX_PLUGIN_URL . 'build/' . $file_info['file'],
			array( 'jquery', 'underscore', 'wp-util' ),
			PPFX_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			),
		);

		$this->localize_script( 'ppfx-app-scripts' );

		if ( empty( $file_info['css'] ) || ! is_array( $file_info['css'] ) ) {
			return;
		}

		foreach ( $file_info['css'] as $index => $css_file ) {
			wp_enqueue_style(
				'ppfx-app-js-css-' . $index,
				PPFX_PLUGIN_URL . 'build/' . $css_file,
				array(),
				PPFX_VERSION,
			);
		}
	}

	/**
	 * Localize frontend configuration for an application script handle.
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	private function localize_script( string $handle ): void {
		$options       = PPFX_Settings_Page::get_settings();
		$auto_filter   = 'yes' === $options['auto_filter'];
		$mobile_drawer = ! empty( $options['mobile_drawer_enabled'] );
		$drawer_mode   = $mobile_drawer ? $options['mobile_drawer_mode'] : null;
		$breakpoint    = $mobile_drawer ? $options['mobile_drawer_breakpoint'] : null;

		$current_context = array();
		if ( is_product_category() ) {
			$current_context['category'] = get_queried_object()->slug;
		} elseif ( is_product_tag() ) {
			$current_context['product_tag'] = get_queried_object()->slug;
		} elseif ( is_tax() ) {
			$term                               = get_queried_object();
			$current_context[ $term->taxonomy ] = $term->slug;
		}

		wp_localize_script(
			$handle,
			'ppfxFilterData',
			array(
				'restUrl'                => esc_url_raw( rest_url( 'pickory-filter/v1/products' ) ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'autoFilter'             => $auto_filter,
				'mobileDrawer'           => $mobile_drawer,
				'mobileDrawerBreakpoint' => $breakpoint,
				'mobileDrawerMode'       => $drawer_mode,
				'debounceDelay'          => (int) ( $options['debounce_delay'] ?? 300 ),
				'currentContext'         => $current_context,
				'filterKeys'             => PPFX_Utility::get_filter_keys(),
				'cacheVersion'           => class_exists( 'PPFX_Query_Cache' ) ? PPFX_Query_Cache::get_query_cache_version() : '1',
				'cacheContext'           => class_exists( 'PPFX_Query_Cache' ) ? PPFX_Query_Cache::get_cache_context() : array(),
				'i18n'                   => array(
					'onSale' => __( 'On Sale', 'pickory-product-filter' ),
					'stars'  => __( ' Stars', 'pickory-product-filter' ),
					'filter' => __( 'Filter', 'pickory-product-filter' ),
				),
			),
		);
	}
}
