<?php
/**
 * Tab 1: Filter Sections
 *
 * Contains: Filter Sections table.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections = PPFX_Settings_Page::get_resolved_sections();
?>
<div style="margin-top: 20px;">
	<?php settings_errors(); ?>
	<!-- Filter Sections Table -->
	<h2 class="title">
		<?php esc_html_e( 'Filter Sections', 'pickory-product-filter' ); ?>
	</h2>
	<p class="description" style="margin-bottom: 16px;">
		<?php esc_html_e( 'Drag rows to reorder. Toggle the switch to show or hide a section. Set how each section should be displayed.', 'pickory-product-filter' ); ?>
	</p>
	<form method="post" action="options.php" id="ppfx-sections-form">
		<?php settings_fields( 'ppfx_option_group' ); ?>
		<table class="widefat ppfx-sections-table" style="border-collapse: collapse;">
			<thead>
				<tr>
					<th style="width: 30px;"></th>
					<th><?php esc_html_e( 'Section', 'pickory-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Display Type', 'pickory-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Label Override', 'pickory-product-filter' ); ?></th>
					<th><?php esc_html_e( 'Max Height', 'pickory-product-filter' ); ?></th>
					<th style="width: 80px; text-align: center;">
						<?php esc_html_e( 'Count', 'pickory-product-filter' ); ?>
					</th>
					<th style="width: 80px; text-align: center;">
						<?php esc_html_e( 'On/Off', 'pickory-product-filter' ); ?>
					</th>
				</tr>
			</thead>
			<tbody id="ppfx-sortable">
				<?php foreach ( $sections as $key => $config ) : ?>
					<?php
					$enabled        = ! empty( $config['enabled'] );
					$filter_type    = $config['type'] ?? 'checkbox';
					$label_val      = $config['label'] ?? '';
					$max_height_val = $config['max_height'] ?? '';
					$row_class      = $enabled ? '' : 'ppfx-row-disabled';
					$default_label  = PPFX_Settings_Page::get_default_label( $key );
					$is_pa_taxonomy = str_starts_with( $key, 'pa_' );
					$show_count     = isset( $config['show_count'] ) ? (bool) $config['show_count'] : true;
					$has_terms      = ! array_key_exists( $key, PPFX_Settings_Page::FIXED_SECTIONS );

					if ( 'category' === $key ) {
						$type_options = PPFX_Settings_Page::CATEGORY_TYPES;
						$fixed_type   = false;
					} elseif ( array_key_exists( $key, PPFX_Settings_Page::FIXED_SECTIONS ) ) {
						$type_options = array();
						$fixed_type   = true;
					} else {
						$type_options = PPFX_Settings_Page::CONFIGURABLE_TYPES;
						if ( ! $is_pa_taxonomy ) {
							$type_options = array_diff_key(
								$type_options,
								array(
									'swatch'       => '',
									'image-swatch' => '',
								)
							);
						}
						$fixed_type = false;
					}
					?>
					<tr class="ppfx-section-row <?php echo esc_attr( $row_class ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-is-pa="<?php echo $is_pa_taxonomy ? '1' : '0'; ?>">
						<td class="ppfx-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pickory-product-filter' ); ?>">
							<span class="ppfx-drag-dots">⢿</span>
						</td>
						<td>
							<strong><?php echo esc_html( $default_label ); ?></strong>
							<br><small class="ppfx-section-key"><?php echo esc_html( $key ); ?></small>
							<input type="hidden" name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][order]" class="ppfx-order-input" value="<?php echo esc_attr( $config['order'] ?? 0 ); ?>">
						</td>
						<td>
							<?php if ( $fixed_type ) : ?>
								<span class="ppfx-fixed-type-badge">
									<?php echo esc_html( ucwords( str_replace( '-', ' ', $filter_type ) ) ); ?>
								</span>
							<?php else : ?>
								<select name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][type]" class="ppfx-type-select">
									<?php foreach ( $type_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_type, $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( ! $is_pa_taxonomy ) : ?>
									<div class="ppfx-swatch-warning" style="display: none; margin-top: 8px; padding: 8px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 13px; color: #856404;">
										<?php esc_html_e( 'Swatch filter types only work for product attributes. This will fall back to Button Grid.', 'pickory-product-filter' ); ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $label_val ); ?>" placeholder="<?php echo esc_attr( $default_label ); ?>" style="width: 100%;">
						</td>
						<td>
							<?php if ( ! $fixed_type ) : ?>
								<input type="number" name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][max_height]" value="<?php echo esc_attr( $max_height_val ); ?>" placeholder="px — leave empty for no limit" style="width: 100%;" min="1">
							<?php else : ?>
								<span style="color: #999;">—</span>
							<?php endif; ?>
						</td>
						<td style="text-align: center; vertical-align: middle;">
							<?php if ( $has_terms ) : ?>
								<div class="ppfx-toggle-switch <?php echo $show_count ? 'ppfx-toggle-on' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>-count" role="switch" aria-checked="<?php echo $show_count ? 'true' : 'false'; ?>">
									<span class="ppfx-toggle-knob"></span>
								</div>
								<input type="hidden" name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][show_count]" class="ppfx-show-count-input" value="<?php echo $show_count ? '1' : '0'; ?>">
							<?php else : ?>
								<span style="color: #999;">—</span>
							<?php endif; ?>
						</td>
						<td style="text-align: center; vertical-align: middle;">
							<div class="ppfx-toggle-switch <?php echo $enabled ? 'ppfx-toggle-on' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>">
								<span class="ppfx-toggle-knob"></span>
							</div>
							<input type="hidden" name="ppfx_settings[sections][<?php echo esc_attr( $key ); ?>][enabled]" class="ppfx-enabled-input" value="<?php echo $enabled ? '1' : '0'; ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Save Settings', 'pickory-product-filter' ) ); ?>
	</form>
</div>
