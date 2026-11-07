<?php
/**
 * Filter sections registry for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Registry and resolver for available and configured filter sections.
 */
class PPFX_Section_Registry {

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
				'enabled'     => true,
				'order'       => 1,
				'type'        => 'checkbox',
				'label'       => '',
				'show_count'  => true,
				'collapsible' => false,
			),
			'price'       => array(
				'enabled' => true,
				'order'   => 2,
				'type'    => 'slider',
				'label'   => '',
				'step'    => 1,
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
				'enabled'     => false,
				'order'       => 5,
				'type'        => 'checkbox',
				'label'       => '',
				'show_count'  => true,
				'collapsible' => false,
			),
		);

		$attribute_taxonomies = function_exists( 'wc_get_attribute_taxonomies' )
			? wc_get_attribute_taxonomies()
			: array();
		$order                = 6;
		foreach ( $attribute_taxonomies as $tax ) {
			$key              = 'pa_' . $tax->attribute_name;
			$sections[ $key ] = array(
				'enabled'     => true,
				'order'       => $order++,
				'type'        => 'checkbox',
				'label'       => '',
				'show_count'  => true,
				'collapsible' => false,
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
					'enabled'     => false,
					'order'       => $order++,
					'type'        => 'checkbox',
					'label'       => '',
					'show_count'  => true,
					'collapsible' => false,
				);
			}
		}

		return $sections;
	}

	/**
	 * Get the resolved and merged filter sections from saved options.
	 *
	 * @param bool $force_refresh Whether to bypass static cache.
	 * @return array
	 */
	public static function get_resolved_sections( bool $force_refresh = false ): array {
		static $cached = null;
		if ( null !== $cached && ! $force_refresh ) {
			return $cached;
		}

		$options = get_option( 'ppfx_settings', array() );
		$saved   = $options['sections'] ?? array();

		foreach ( $saved as $key => &$sec ) {
			if ( isset( $sec['type'] ) ) {
				$sec['type'] = str_replace( '_', '-', $sec['type'] );
			}
			$default_lbl = self::get_default_label( (string) $key );
			if ( isset( $sec['label'] ) && ( (string) $sec['label'] === $default_lbl || '' === trim( (string) $sec['label'] ) ) ) {
				$sec['label'] = '';
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
	 * Get the valid filter types for a given section key.
	 *
	 * Centralizes the logic for determining which display types are available
	 * for each section type (category, fixed sections, attributes, etc.).
	 *
	 * @param string $key Section key.
	 * @return array Array of valid type keys with their labels.
	 */
	public static function get_valid_types_for_section( string $key ): array {
		$is_pa_taxonomy = str_starts_with( $key, 'pa_' );

		if ( 'category' === $key ) {
			return self::CATEGORY_TYPES;
		}

		if ( isset( self::FIXED_SECTIONS[ $key ] ) ) {
			// Fixed sections have only one valid type.
			$fixed_type = self::FIXED_SECTIONS[ $key ]['type'];
			return array( $fixed_type => self::FIXED_SECTIONS[ $key ]['label'] );
		}

		// For product attributes (pa_*), all configurable types are valid including swatches.
		if ( $is_pa_taxonomy ) {
			return self::CONFIGURABLE_TYPES;
		}

		// For other taxonomies, exclude swatch types (only work for product attributes).
		return array_diff_key(
			self::CONFIGURABLE_TYPES,
			array_flip( array( 'swatch', 'image-swatch' ) )
		);
	}

	/**
	 * Check if a section has a fixed (non-configurable) type.
	 *
	 * @param string $key Section key.
	 * @return bool True if the section has a fixed type, false otherwise.
	 */
	public static function is_fixed_section( string $key ): bool {
		return isset( self::FIXED_SECTIONS[ $key ] );
	}

	/**
	 * Check if a section supports the "show count" feature.
	 *
	 * Fixed sections (price, rating, sale) do not support count display.
	 *
	 * @param string $key Section key.
	 * @return bool True if the section supports count display, false otherwise.
	 */
	public static function section_supports_count( string $key ): bool {
		return ! self::is_fixed_section( $key );
	}

	/**
	 * Check if a section supports the collapsible (See More) feature.
	 *
	 * Collapsible requires the section to have terms (not fixed except rating)
	 * and is not available for price or sale sections.
	 *
	 * @param string $key Section key.
	 * @return bool True if the section supports collapsible, false otherwise.
	 */
	public static function section_supports_collapsible( string $key ): bool {
		// Price and sale never support collapsible.
		if ( 'price' === $key || 'sale' === $key ) {
			return false;
		}

		// Rating is a fixed section but supports collapsible.
		if ( 'rating' === $key ) {
			return true;
		}

		// Fixed sections other than rating don't support collapsible.
		if ( self::is_fixed_section( $key ) ) {
			return false;
		}

		// All other sections (taxonomies with terms) support collapsible.
		return true;
	}
}
