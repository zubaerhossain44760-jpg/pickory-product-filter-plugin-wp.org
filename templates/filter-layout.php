<?php
/**
 * Pickory Product Filter — Main Layout Template (Dispatcher)
 *
 * This file sorts sections and dispatches to the correct /types/ template.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options     = PPFX_Settings_Page::get_settings();
$sections    = $options['sections'];
$auto_filter = 'yes' === $options['auto_filter'];

// Filter sections if a specific subset is requested in the shortcode.
if ( ! empty( $requested_sections ) && is_array( $requested_sections ) ) {
	$sections = array_filter(
		$sections,
		function ( $key ) use ( $requested_sections ) {
			if ( in_array( $key, $requested_sections, true ) ) {
				return true;
			}
			// Allow matching attributes without 'pa_' prefix (e.g. 'color' matches 'pa_color').
			if ( str_starts_with( $key, 'pa_' ) ) {
				$short_key = substr( $key, 3 );
				if ( in_array( $short_key, $requested_sections, true ) ) {
					return true;
				}
			}
			return false;
		},
		ARRAY_FILTER_USE_KEY
	);
}

// Use the current instance ID from PPFX_Assets if available, otherwise generate new.
$current_instance_id = PPFX_Assets::get_current_instance_id();
$unique_id           = ! empty( $current_instance_id ) ? $current_instance_id : wp_unique_id( 'ppfx-filter-' );
$is_primary          = empty( trim( $extra_class ?? '' ) ) && empty( $requested_sections );
$mobile_drawer       = ! empty( $options['mobile_drawer_enabled'] );
?>

<?php if ( $is_primary && $mobile_drawer ) : ?>
	<div class="ppfx-mobile-toggle-slot" data-ppfx-mobile-toggle-slot>
		<button
				class="ppfx-mobile-toggle"
				type="button"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( 'ppfx-filter-form-' . $unique_id ); ?>"
				aria-label="<?php esc_attr_e( 'Toggle filters', 'pickory-product-filter' ); ?>"
				data-ppfx-mobile-toggle="<?php echo esc_attr( $unique_id ); ?>"
		>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
				stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
				aria-hidden="true" focusable="false">
				<line x1="4" y1="6" x2="20" y2="6"/>
				<line x1="8" y1="12" x2="20" y2="12"/>
				<line x1="12" y1="18" x2="20" y2="18"/>
			</svg>
			<?php esc_html_e( 'Filters', 'pickory-product-filter' ); ?>
		</button>
	</div>
	<div class="ppfx-filter-backdrop" data-ppfx-backdrop="<?php echo esc_attr( $unique_id ); ?>" aria-hidden="true"></div>
<?php endif; ?>
<div class="ppfx-filter-root ppfx-filter-container <?php echo $is_primary ? 'ppfx-primary-filter' : ''; ?> <?php echo esc_attr( $extra_class ?? '' ); ?>" data-ppfx-filter-id="<?php echo esc_attr( $unique_id ); ?>">
	<form data-ppfx-filter-form class="ppfx-filter-form" id="<?php echo esc_attr( 'ppfx-filter-form-' . $unique_id ); ?>" data-ppfx-instance="<?php echo esc_attr( $unique_id ); ?>" aria-describedby="ppfx-clear-all-desc-<?php echo esc_attr( $unique_id ); ?>">
		<!-- Header -->
		<?php if ( empty( $requested_sections ) ) : ?>
		<div class="ppfx-panel-title">
			<?php esc_html_e( 'Filters', 'pickory-product-filter' ); ?>
			<button type="button" class="ppfx-reset-btn" data-ppfx-reset-btn="" id="ppfx-clear-all-btn-<?php echo esc_attr( $unique_id ); ?>"><?php esc_html_e( 'Clear all', 'pickory-product-filter' ); ?></button>
		</div>
		<span id="ppfx-clear-all-desc-<?php echo esc_attr( $unique_id ); ?>" class="sr-only"><?php esc_html_e( 'Remove all active filters and reset to default view', 'pickory-product-filter' ); ?></span>

		<div class="ppfx-active-filters-wrap">
			<?php if ( empty( $options['chips_before_loop'] ) ) : ?>
				<div class="ppfx-active-filters-inner">
					<span class="ppfx-no-chips" data-ppfx-no-chips><?php esc_html_e( 'No active filters', 'pickory-product-filter' ); ?></span>
					<div class="ppfx-active-filters" data-ppfx-active-filters></div>
				</div>
			<?php endif; ?>
			<button type="submit" class="ppfx-filter-btn<?php echo $auto_filter ? ' ppfx-hidden' : ''; ?>"><?php esc_html_e( 'Filter', 'pickory-product-filter' ); ?></button>
		</div>
		<?php endif; ?>

		<?php
		// Pre-fetch taxonomy terms in a single batched query for all enabled term-based sections.
		$needed_taxonomies = array();
		foreach ( $sections as $key => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			$ppfx_type = $config['type'] ?? 'checkbox';
			if ( ! in_array( $ppfx_type, array( 'slider', 'star-rating', 'toggle' ), true ) ) {
				$needed_taxonomies[] = ( 'category' === $key ) ? 'product_cat' : $key;
			}
		}

		$prefetched_terms = array();
		if ( ! empty( $needed_taxonomies ) ) {
			$all_terms = get_terms(
				array(
					'taxonomy'   => array_unique( $needed_taxonomies ),
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $all_terms ) && is_array( $all_terms ) ) {
				foreach ( $all_terms as $ppfx_term ) {
					$prefetched_terms[ $ppfx_term->taxonomy ][] = $ppfx_term;
				}
			}
		}

		foreach ( $sections as $key => $config ) :
			if ( empty( $config['enabled'] ) ) {
				continue;
			}

			$ppfx_type     = $config['type'] ?? 'checkbox';
			$default_label = PPFX_Section_Registry::get_default_label( $key );
			$label         = ! empty( trim( (string) ( $config['label'] ?? '' ) ) )
				? $config['label']
				: $default_label;
			$input_name    = class_exists( 'PPFX_Utility' ) ? PPFX_Utility::get_input_name( $key ) : $key;
			$max_height    = isset( $config['max_height'] ) ? $config['max_height'] : null;
			$show_count    = isset( $config['show_count'] ) ? (bool) $config['show_count'] : true;
			$collapsible   = ! empty( $config['collapsible'] ) && ! empty( $max_height );

			// Get terms if the section type requires them.
			$terms = array();
			if ( ! in_array( $ppfx_type, array( 'slider', 'star-rating', 'toggle' ), true ) ) {
				$ppfx_taxonomy = ( 'category' === $key ) ? 'product_cat' : $key;
				$terms         = $prefetched_terms[ $ppfx_taxonomy ] ?? array();
				if ( empty( $terms ) ) {
					continue;
				}
			}

			// Wrapper shell for every filter section.
			?>
			<div class="ppfx-filter-section ppfx-type-<?php echo esc_attr( $ppfx_type ); ?>"
				data-attr="<?php echo esc_attr( $key ); ?>"
				<?php if ( $collapsible ) : ?>
					data-ppfx-collapsible="true"
					data-collapsed-height="<?php echo esc_attr( $max_height ); ?>"
				<?php endif; ?>>
				<?php if ( ! empty( $label ) ) : ?>
				<h4 class="ppfx-section-label"><?php echo esc_html( $label ); ?></h4>
				<?php endif; ?>
				<div class="ppfx-filter-group">
					<?php
					// search-list manages its own internal scroll area so the outer
					// container must not also apply a max-height constraint.
					$needs_scroll_container = ! in_array( $ppfx_type, array( 'slider', 'toggle', 'dropdown', 'search-list' ), true );
					if ( $needs_scroll_container && $max_height ) :
						?>
						<div class="ppfx-scrollable-container" style="max-height: <?php echo (int) $max_height; ?>px; overflow-y: auto;">
					<?php endif; ?>

					<?php
					// Dispatch to the type-specific template with fallback to checkbox.
					$ppfx_type_slug = sanitize_key( $ppfx_type );
					$template_path  = PPFX_PLUGIN_DIR . "templates/types/type-{$ppfx_type_slug}.php";
					$fallback_path  = PPFX_PLUGIN_DIR . 'templates/types/type-checkbox.php';

					if ( file_exists( $template_path ) ) {
						include $template_path;
					} elseif ( file_exists( $fallback_path ) ) {
						include $fallback_path;
					} else {
						/* translators: %s = filter type name */
						printf( esc_html__( 'Filter template for "%s" not found.', 'pickory-product-filter' ), esc_html( $ppfx_type ) );
					}
					?>

					<?php if ( $needs_scroll_container && $max_height ) : ?>
						</div>
					<?php endif; ?>

					<?php if ( $collapsible && $needs_scroll_container && $max_height ) : ?>
						<button
							type="button"
							class="ppfx-see-more-btn"
							data-ppfx-see-more
							data-collapsed-height="<?php echo esc_attr( $max_height ); ?>"
							data-label-more="<?php esc_attr_e( 'See more', 'pickory-product-filter' ); ?>"
							data-label-less="<?php esc_attr_e( 'See less', 'pickory-product-filter' ); ?>"
							aria-expanded="false"
						>
							<?php esc_html_e( 'See more', 'pickory-product-filter' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach;
		?>

	</form>
</div>
