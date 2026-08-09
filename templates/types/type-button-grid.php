<?php
/**
 * Type: Button / Pill Grid
 * Compact buttons that toggle values. Support multi-select via hidden inputs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-button-grid" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php foreach ( $terms as $term ) : ?>
		<span class="ppfx-btn-wrap">
			<button 
				type="button" 
				class="ppfx-grid-btn" 
				data-value="<?php echo esc_attr( $term->slug ); ?>"
				data-label="<?php echo esc_attr( $term->name ); ?>"
				aria-pressed="false"
			>
				<?php echo esc_html( $term->name ); ?>
			</button>
			<?php if ( ! empty( $show_count ) ) : ?>
				<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $term->count ); ?></span>
			<?php endif; ?>
		</span>
	<?php endforeach; ?>
	
	<!-- Hidden input to store multiple selected values as a comma-separated list -->
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-grid-hidden-input" value="">
</div>
