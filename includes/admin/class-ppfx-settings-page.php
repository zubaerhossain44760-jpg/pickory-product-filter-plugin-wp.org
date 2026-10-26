<?php
/**
 * Settings page handler for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Renders and sanitizes the product filter settings page.
 */
class PPFX_Settings_Page {

	const CONFIGURABLE_TYPES = PPFX_Section_Registry::CONFIGURABLE_TYPES;
	const CATEGORY_TYPES     = PPFX_Section_Registry::CATEGORY_TYPES;
	const FIXED_SECTIONS     = PPFX_Section_Registry::FIXED_SECTIONS;

	/**
	 * Get the default filter sections configuration.
	 *
	 * @return array
	 */
	public static function get_default_sections(): array {
		return PPFX_Section_Registry::get_default_sections();
	}

	/**
	 * Get the resolved and merged filter sections from saved options.
	 *
	 * @return array
	 */
	public static function get_resolved_sections(): array {
		return PPFX_Section_Registry::get_resolved_sections();
	}

	/**
	 * Get the default display label for a filter section key.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	public static function get_default_label( string $key ): string {
		return PPFX_Section_Registry::get_default_label( $key );
	}

	/**
	 * Render the settings page with tab navigation.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading view tab parameter, no state change.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'filter-sections';
		$tabs        = array(
			'filter-sections' => __( 'Filter Sections', 'pickory-product-filter' ),
			'advanced'        => __( 'Advanced', 'pickory-product-filter' ),
			'documentation'   => __( 'Documentation', 'pickory-product-filter' ),
		);
		$page_slug   = 'pickory-product-filter';
		?>
		<div class="wrap ppfx-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<nav class="ppfx-tab-nav nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $slug ) ); ?>"
						class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="ppfx-tab-content">
				<?php
				$view = plugin_dir_path( __FILE__ ) . 'views/tab-' . $current_tab . '.php';
				if ( file_exists( $view ) ) {
					include $view;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get default settings for ppfx_settings.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'auto_filter'              => 'yes',
			'chips_before_loop'        => false,
			'mobile_drawer_enabled'    => false,
			'mobile_drawer_breakpoint' => 768,
			'mobile_drawer_mode'       => 'dom_inject',
			'rate_limiting_enabled'    => 'yes',
			'debounce_delay'           => 300,
			'sections'                 => self::get_default_sections(),
		);
	}

	/**
	 * Get resolved settings merged with defaults.
	 *
	 * @param bool $force_refresh Whether to bypass static cache.
	 * @return array
	 */
	public static function get_settings( bool $force_refresh = false ): array {
		static $resolved = null;
		if ( null !== $resolved && ! $force_refresh ) {
			return $resolved;
		}

		$saved = get_option( 'ppfx_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$defaults = self::get_defaults();
		$settings = wp_parse_args( $saved, $defaults );

		// Normalize types and enforce valid boundary ranges.
		$settings['auto_filter']              = ( ! isset( $settings['auto_filter'] ) || 'no' !== $settings['auto_filter'] ) ? 'yes' : 'no';
		$settings['chips_before_loop']        = ! empty( $settings['chips_before_loop'] );
		$settings['mobile_drawer_enabled']    = ! empty( $settings['mobile_drawer_enabled'] );
		$settings['mobile_drawer_breakpoint'] = min( max( absint( $settings['mobile_drawer_breakpoint'] ?? 768 ), 320 ), 1600 );
		$settings['mobile_drawer_mode']       = in_array( $settings['mobile_drawer_mode'] ?? 'dom_inject', array( 'simple_toggle', 'dom_inject' ), true ) ? $settings['mobile_drawer_mode'] : 'dom_inject';
		$settings['rate_limiting_enabled']    = ( ! isset( $settings['rate_limiting_enabled'] ) || 'no' !== $settings['rate_limiting_enabled'] ) ? 'yes' : 'no';
		$settings['debounce_delay']           = min( max( absint( $settings['debounce_delay'] ?? 300 ), 0 ), 2000 );
		$settings['sections']                 = self::get_resolved_sections();

		$resolved = $settings;
		return $resolved;
	}

	/**
	 * Sanitize the settings input array.
	 *
	 * Uses an explicit _sub_tab discriminator to prevent state-loss/collision between tabs.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$existing = self::get_settings( true );
		$clean    = array();

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$sub_tab = isset( $input['_sub_tab'] ) ? sanitize_key( $input['_sub_tab'] ) : '';

		// Branch 1: Submission from Advanced Tab.
		if ( 'advanced' === $sub_tab ) {
			// Unconditionally preserve sections exactly as configured.
			$clean['sections'] = $existing['sections'];

			// Advanced fields: unchecked checkboxes are omitted from POST; presence determines 'yes'/true.
			$clean['auto_filter']              = isset( $input['auto_filter'] ) ? 'yes' : 'no';
			$clean['chips_before_loop']        = ! empty( $input['chips_before_loop'] );
			$clean['mobile_drawer_enabled']    = ! empty( $input['mobile_drawer_enabled'] );
			$raw_bp                            = absint( $input['mobile_drawer_breakpoint'] ?? 768 );
			$clean['mobile_drawer_breakpoint'] = min( max( $raw_bp, 320 ), 1600 );
			$valid_modes                       = array( 'simple_toggle', 'dom_inject' );
			$raw_mode                          = sanitize_key( $input['mobile_drawer_mode'] ?? ( $existing['mobile_drawer_mode'] ?? 'dom_inject' ) );
			$clean['mobile_drawer_mode']       = in_array( $raw_mode, $valid_modes, true ) ? $raw_mode : 'dom_inject';
			$clean['rate_limiting_enabled']    = isset( $input['rate_limiting_enabled'] ) ? 'yes' : 'no';
			$raw_debounce                      = absint( $input['debounce_delay'] ?? 300 );
			$clean['debounce_delay']           = min( max( $raw_debounce, 0 ), 2000 );

			return $clean;
		}

		// Branch 2: Submission from Filter Sections Tab.
		if ( 'sections' === $sub_tab ) {
			// Unconditionally preserve all advanced tab settings untouched.
			$clean['auto_filter']              = $existing['auto_filter'];
			$clean['chips_before_loop']        = $existing['chips_before_loop'];
			$clean['mobile_drawer_enabled']    = $existing['mobile_drawer_enabled'];
			$clean['mobile_drawer_breakpoint'] = $existing['mobile_drawer_breakpoint'];
			$clean['mobile_drawer_mode']       = $existing['mobile_drawer_mode'];
			$clean['rate_limiting_enabled']    = $existing['rate_limiting_enabled'];
			$clean['debounce_delay']           = $existing['debounce_delay'];

			// Sanitize sections reordering and properties.
			$raw_sections      = isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : array();
			$clean['sections'] = self::sanitize_sections( $raw_sections, $existing['sections'] );

			return $clean;
		}

		// Branch 3: Fallback for direct option updates or legacy payloads.
		if ( isset( $input['sections'] ) && is_array( $input['sections'] ) ) {
			$clean['sections'] = self::sanitize_sections( $input['sections'], $existing['sections'] );
		} else {
			$clean['sections'] = $existing['sections'];
		}

		$clean['auto_filter']              = isset( $input['auto_filter'] ) ? ( 'yes' === $input['auto_filter'] ? 'yes' : 'no' ) : $existing['auto_filter'];
		$clean['chips_before_loop']        = isset( $input['chips_before_loop'] ) ? ! empty( $input['chips_before_loop'] ) : $existing['chips_before_loop'];
		$clean['mobile_drawer_enabled']    = isset( $input['mobile_drawer_enabled'] ) ? ! empty( $input['mobile_drawer_enabled'] ) : $existing['mobile_drawer_enabled'];
		$raw_bp                            = absint( $input['mobile_drawer_breakpoint'] ?? $existing['mobile_drawer_breakpoint'] );
		$clean['mobile_drawer_breakpoint'] = min( max( $raw_bp, 320 ), 1600 );
		$raw_mode                          = sanitize_key( $input['mobile_drawer_mode'] ?? $existing['mobile_drawer_mode'] );
		$clean['mobile_drawer_mode']       = in_array( $raw_mode, array( 'simple_toggle', 'dom_inject' ), true ) ? $raw_mode : 'dom_inject';
		$clean['rate_limiting_enabled']    = isset( $input['rate_limiting_enabled'] ) ? ( 'yes' === $input['rate_limiting_enabled'] ? 'yes' : 'no' ) : $existing['rate_limiting_enabled'];
		$raw_debounce                      = absint( $input['debounce_delay'] ?? $existing['debounce_delay'] );
		$clean['debounce_delay']           = min( max( $raw_debounce, 0 ), 2000 );

		return $clean;
	}

	/**
	 * Sanitize filter sections array.
	 *
	 * @param array $raw_sections Raw sections input.
	 * @param array $existing_sections Existing sections to fall back to if empty.
	 * @return array
	 */
	private static function sanitize_sections( array $raw_sections, array $existing_sections ): array {
		if ( empty( $raw_sections ) ) {
			return $existing_sections;
		}

		$defaults   = self::get_default_sections();
		$valid_keys = array_fill_keys( array_keys( $defaults ), true );
		$clean      = array();

		uasort(
			$raw_sections,
			function ( $a, $b ) {
				$a_order = isset( $a['order'] ) ? (int) $a['order'] : 0;
				$b_order = isset( $b['order'] ) ? (int) $b['order'] : 0;
				return $a_order <=> $b_order;
			}
		);

		foreach ( $raw_sections as $key => $section ) {
			$key = sanitize_key( $key );
			if ( ! isset( $valid_keys[ $key ] ) ) {
				continue;
			}

			$type           = str_replace(
				'_',
				'-',
				sanitize_text_field( $section['type'] ?? 'checkbox' ),
			);
			$is_pa_taxonomy = str_starts_with( $key, 'pa_' );

			if ( 'category' === $key ) {
				$valid_types = array_keys( self::CATEGORY_TYPES );
			} elseif ( isset( self::FIXED_SECTIONS[ $key ] ) ) {
				$valid_types = array( self::FIXED_SECTIONS[ $key ]['type'] );
				$type        = self::FIXED_SECTIONS[ $key ]['type'];
			} else {
				$valid_types = array_keys( self::CONFIGURABLE_TYPES );
				if ( ! $is_pa_taxonomy ) {
					$valid_types = array_diff(
						$valid_types,
						array(
							'swatch',
							'image-swatch',
						)
					);
				}
			}

			if ( ! in_array( $type, $valid_types, true ) ) {
				$type = $valid_types[0];
			}

			$max_height = isset( $section['max_height'] )
				? trim( $section['max_height'] )
				: '';
			if ( ! empty( $max_height ) ) {
				$max_height = absint( $max_height );
				if ( $max_height < 1 ) {
					$max_height = null;
				}
			} else {
				$max_height = null;
			}

			$step = null;
			if ( 'price' === $key ) {
				$step = isset( $section['step'] ) ? max( 1, absint( $section['step'] ) ) : 1;
			}

			$raw_label     = isset( $section['label'] ) ? sanitize_text_field( $section['label'] ) : '';
			$default_label = self::get_default_label( $key );
			$clean_label   = ( '' === trim( $raw_label ) || $raw_label === $default_label ) ? '' : $raw_label;

			$clean[ $key ] = array(
				'enabled'     =>
				! empty( $section['enabled'] ) &&
				in_array( $section['enabled'], array( '1', 1, true ), true ),
				'order'       => absint( $section['order'] ?? 0 ),
				'type'        => $type,
				'label'       => $clean_label,
				'max_height'  => $max_height,
				'step'        => $step,
				'show_count'  => ! isset( self::FIXED_SECTIONS[ $key ] ) && isset( $section['show_count'] )
					&& in_array( $section['show_count'], array( '1', 1, true ), true ),
				// collapsible: only valid when max_height is also set; disabled for fixed sections except rating.
				'collapsible' => ( ! isset( self::FIXED_SECTIONS[ $key ] ) || 'rating' === $key ) &&
					'price' !== $key &&
					'sale' !== $key &&
					! empty( $max_height ) &&
					! empty( $section['collapsible'] ) &&
					in_array( $section['collapsible'], array( '1', 1, true ), true ),
			);
		}

		return empty( $clean ) ? $existing_sections : $clean;
	}
}
