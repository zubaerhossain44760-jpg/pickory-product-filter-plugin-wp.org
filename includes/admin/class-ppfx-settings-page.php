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

	const CONFIGURABLE_TYPES = array(
		'checkbox'     => 'Checkboxes',
		'radio'        => 'Radio Buttons',
		'button-grid'  => 'Button / Pill Grid',
		'dropdown'     => 'Dropdown (Single Select)',
		'search-list'  => 'Search List (Checkbox + Search)',
		'swatch'       => 'Swatches — Color',
		'image-swatch' => 'Swatches — Image',
	);

	const CATEGORY_TYPES = array(
		'checkbox'    => 'Checkboxes',
		'radio'       => 'Radio Buttons',
		'button-grid' => 'Button / Pill Grid',
		'dropdown'    => 'Dropdown (Single Select)',
		'search-list' => 'Search List (Checkbox + Search)',
	);

	const FIXED_SECTIONS = array(
		'price'  => array(
			'label'           => 'Price',
			'type'            => 'slider',
			'default_enabled' => true,
		),
		'rating' => array(
			'label'           => 'Rating',
			'type'            => 'star-rating',
			'default_enabled' => false,
		),
		'sale'   => array(
			'label'           => 'On Sale',
			'type'            => 'toggle',
			'default_enabled' => false,
		),
	);

	/**
	 * Get the default filter sections configuration.
	 *
	 * @return array
	 */
	public static function get_default_sections(): array {
		$sections = array(
			'category'    => array(
				'enabled'    => true,
				'order'      => 1,
				'type'       => 'checkbox',
				'label'      => '',
				'show_count' => true,
			),
			'price'       => array(
				'enabled' => true,
				'order'   => 2,
				'type'    => 'slider',
				'label'   => '',
			),
			'rating'      => array(
				'enabled' => false,
				'order'   => 3,
				'type'    => 'star-rating',
				'label'   => '',
			),
			'sale'        => array(
				'enabled' => false,
				'order'   => 4,
				'type'    => 'toggle',
				'label'   => '',
			),
			'product_tag' => array(
				'enabled'    => false,
				'order'      => 5,
				'type'       => 'checkbox',
				'label'      => '',
				'show_count' => true,
			),
		);

		$attribute_taxonomies = function_exists( 'wc_get_attribute_taxonomies' )
			? wc_get_attribute_taxonomies()
			: array();
		$order                = 6;
		foreach ( $attribute_taxonomies as $tax ) {
			$key              = 'pa_' . $tax->attribute_name;
			$sections[ $key ] = array(
				'enabled'    => true,
				'order'      => $order++,
				'type'       => 'checkbox',
				'label'      => '',
				'show_count' => true,
			);
		}

		$all_taxonomies = get_object_taxonomies( 'product', 'objects' );
		$exclude        = array(
			'product_cat',
			'product_tag',
			'product_visibility',
			'product_type',
			'product_shipping_class',
		);

		foreach ( $all_taxonomies as $tax_obj ) {
			if (
				! $tax_obj->public ||
				in_array( $tax_obj->name, $exclude, true ) ||
				str_starts_with( $tax_obj->name, 'pa_' )
			) {
				continue;
			}

			if ( ! isset( $sections[ $tax_obj->name ] ) ) {
				$sections[ $tax_obj->name ] = array(
					'enabled'    => false,
					'order'      => $order++,
					'type'       => 'checkbox',
					'label'      => $tax_obj->label,
					'show_count' => true,
				);
			}
		}

		return $sections;
	}

	/**
	 * Get the resolved and merged filter sections from saved options.
	 *
	 * @return array
	 */
	public static function get_resolved_sections(): array {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		$options = get_option( 'ppfx_settings', array() );
		$saved   = $options['sections'] ?? array();

		foreach ( $saved as &$sec ) {
			if ( isset( $sec['type'] ) ) {
				$sec['type'] = str_replace( '_', '-', $sec['type'] );
			}
		}
		unset( $sec );

		$defaults = self::get_default_sections();

		foreach ( $defaults as $key => $config ) {
			if ( ! isset( $saved[ $key ] ) ) {
				$max_order = 0;
				if ( ! empty( $saved ) ) {
					$orders    = array_column( $saved, 'order' );
					$max_order = ! empty( $orders ) ? max( $orders ) : 0;
				}

				$saved[ $key ]          = $config;
				$saved[ $key ]['order'] = $max_order + 1;
			}
		}

		$valid_keys = array_keys( $defaults );
		foreach ( $saved as $key => $config ) {
			if ( ! in_array( $key, $valid_keys, true ) ) {
				unset( $saved[ $key ] );
			}
		}

		uasort(
			$saved,
			function ( $a, $b ) {
				$a_order = isset( $a['order'] ) ? (int) $a['order'] : 999;
				$b_order = isset( $b['order'] ) ? (int) $b['order'] : 999;
				return $a_order <=> $b_order;
			}
		);

		$cached = $saved;
		return $cached;
	}

	/**
	 * Get the default display label for a filter section key.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	public static function get_default_label( string $key ): string {
		static $attribute_taxonomies = null;
		if ( null === $attribute_taxonomies ) {
			$attribute_taxonomies = function_exists(
				'wc_get_attribute_taxonomies',
			)
				? wc_get_attribute_taxonomies()
				: array();
		}

		if ( 'category' === $key ) {
			return __( 'Categories', 'pickory-product-filter' );
		}
		if ( 'product_tag' === $key ) {
			return __( 'Tags', 'pickory-product-filter' );
		}
		if ( isset( self::FIXED_SECTIONS[ $key ] ) ) {
			return self::FIXED_SECTIONS[ $key ]['label'];
		}

		if ( str_starts_with( $key, 'pa_' ) ) {
			$attr_name = substr( $key, 3 );
			foreach ( $attribute_taxonomies as $tax ) {
				if ( $tax->attribute_name === $attr_name ) {
					return $tax->attribute_label;
				}
			}
		}
		return ucwords( str_replace( array( 'pa_', '_' ), array( '', ' ' ), $key ) );
	}

	/**
	 * Render the settings page with tab navigation.
	 *
	 * @return void
	 */
	public static function render_page(): void {
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
	 * Sanitize the settings input array.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$existing = get_option( 'ppfx_settings', array() );
		$clean    = array();

		// Sections tab fields.
		$clean['auto_filter']           = isset( $input['auto_filter'] ) ? 'yes' : 'no';
		$clean['rate_limiting_enabled'] = isset( $input['rate_limiting_enabled'] ) ? 'yes' : 'no';
		$defaults                       = self::get_default_sections();
		$valid_keys                     = array_fill_keys( array_keys( $defaults ), true );

		$clean['sections'] = array();
		if ( ! empty( $input['sections'] ) && is_array( $input['sections'] ) ) {
			uasort(
				$input['sections'],
				function ( $a, $b ) {
					$a_order = isset( $a['order'] ) ? (int) $a['order'] : 0;
					$b_order = isset( $b['order'] ) ? (int) $b['order'] : 0;
					return $a_order <=> $b_order;
				}
			);

			foreach ( $input['sections'] as $key => $section ) {
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

				$clean['sections'][ $key ] = array(
					'enabled'    =>
					! empty( $section['enabled'] ) &&
					in_array( $section['enabled'], array( '1', 1, true ), true ),
					'order'      => absint( $section['order'] ?? 0 ),
					'type'       => $type,
					'label'      => sanitize_text_field( $section['label'] ?? '' ),
					'max_height' => $max_height,
					'show_count' => ! isset( self::FIXED_SECTIONS[ $key ] ) && isset( $section['show_count'] )
						&& in_array( $section['show_count'], array( '1', 1, true ), true ),
				);
			}
		}

		// Preserve fields not submitted by this tab.
		if ( empty( $clean['sections'] ) ) {
			$clean['sections'] = $existing['sections'] ?? array();
		}
		if ( ! isset( $input['auto_filter'] ) && ! isset( $input['rate_limiting_enabled'] ) ) {
			$clean['auto_filter']           = $existing['auto_filter'] ?? 'yes';
			$clean['rate_limiting_enabled'] = $existing['rate_limiting_enabled'] ?? 'yes';
		}

		return $clean;
	}
}
