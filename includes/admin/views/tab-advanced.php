<?php
/**
 * Tab 2: Advanced
 *
 * Contains: Product Index card + Auto Filter checkbox, Mobile Drawer checkbox + breakpoint input, API Rate Limiting checkbox.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = PPFX_Settings_Page::get_settings();
?>
<div style="margin-top: 20px;">
	<?php settings_errors(); ?>
	<!-- Product Index Card -->
	<div class="ppfx-index-card">
		<h2 class="title">
			<?php esc_html_e( '🗄️ Product Index', 'pickory-product-filter' ); ?>
		</h2>
		<p>
			<?php esc_html_e( 'The product index powers the fast filter queries. Use this tool to rebuild it manually — for example, after a bulk CSV import or if filters are returning no results.', 'pickory-product-filter' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( '⚠️ For large catalogues (>1,000 products) this may take several seconds. Do not navigate away while the sync is running.', 'pickory-product-filter' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ppfx_rebuild_index">
			<?php wp_nonce_field( 'ppfx_rebuild_index_nonce', 'ppfx_rebuild_nonce' ); ?>
			<?php
			submit_button(
				'🔄 ' . __( 'Rebuild Product Index', 'pickory-product-filter' ),
				'primary',
				'ppfx_rebuild_submit',
				false,
			);
			?>
		</form>
	</div>

	<form method="post" action="options.php" id="ppfx-advanced-form">
		<?php settings_fields( 'ppfx_option_group' ); ?>
		<input type="hidden" name="ppfx_settings[_sub_tab]" value="advanced">
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Auto Filter on Change', 'pickory-product-filter' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ppfx_settings[auto_filter]" value="yes"
								<?php checked( ! isset( $options['auto_filter'] ) || 'no' !== $options['auto_filter'] ); ?>>
							<?php esc_html_e( 'Automatically update products when a filter changes (no button click needed).', 'pickory-product-filter' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Mobile Filter Drawer', 'pickory-product-filter' ); ?>
					</th>
					<td>
						<?php
						$breakpoint_val = ! empty( $options['mobile_drawer_breakpoint'] )
							? absint( $options['mobile_drawer_breakpoint'] )
							: '';
						?>
						<div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
							<label style="display: flex; align-items: center; gap: 8px; margin: 0;">
								<input
									type="checkbox"
									id="ppfx_mobile_drawer_enabled"
									name="ppfx_settings[mobile_drawer_enabled]"
									value="1"
									<?php checked( ! empty( $options['mobile_drawer_enabled'] ) ); ?>>
								<?php esc_html_e( 'Show filter as a mobile slide-in drawer.', 'pickory-product-filter' ); ?>
							</label>
							<div style="display: flex; align-items: center; gap: 8px;">
								<label for="ppfx_mobile_drawer_breakpoint" style="margin: 0;">
									<?php esc_html_e( 'Breakpoint', 'pickory-product-filter' ); ?>
								</label>
								<input
									type="number"
									id="ppfx_mobile_drawer_breakpoint"
									name="ppfx_settings[mobile_drawer_breakpoint]"
									value="<?php echo esc_attr( (string) $breakpoint_val ); ?>"
									placeholder="768"
									min="320"
									max="1600"
									step="1"
									style="width: 80px;"
									<?php disabled( empty( $options['mobile_drawer_enabled'] ) ); ?>>
								<span><?php esc_html_e( '(px)', 'pickory-product-filter' ); ?></span>
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'API Rate Limiting', 'pickory-product-filter' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ppfx_settings[rate_limiting_enabled]" value="yes"
								<?php checked( ! isset( $options['rate_limiting_enabled'] ) || 'no' !== $options['rate_limiting_enabled'] ); ?>>
							<?php esc_html_e( 'Enable API rate limiting (80 requests per minute) on the products filtering REST endpoint to prevent scraping and abuse.', 'pickory-product-filter' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Save Settings', 'pickory-product-filter' ) ); ?>
	</form>
</div>
