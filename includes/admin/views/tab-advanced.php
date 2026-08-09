<?php
/**
 * Tab 2: Advanced
 *
 * Contains: Product Index card + Auto Filter checkbox, API Rate Limiting checkbox.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = get_option( 'ppfx_settings', array() );
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
						<?php esc_html_e( 'API Rate Limiting', 'pickory-product-filter' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ppfx_settings[rate_limiting_enabled]" value="yes"
								<?php checked( ! isset( $options['rate_limiting_enabled'] ) || 'no' !== $options['rate_limiting_enabled'] ); ?>>
							<?php esc_html_e( 'Enable API rate limiting (100 requests per minute) on the products filtering REST endpoint to prevent scraping and abuse.', 'pickory-product-filter' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Save Settings', 'pickory-product-filter' ) ); ?>
	</form>
</div>
