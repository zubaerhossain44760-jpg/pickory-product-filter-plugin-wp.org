<?php
/**
 * Type: Button / Pill Grid
 * Compact buttons that toggle values. Support multi-select via hidden inputs.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $terms ) || ! is_iterable( $terms ) ) {
	return;
}
?>

<div class="ppfx-button-grid" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php foreach ( $terms as $ppfx_term ) : ?>
		<button 
			type="button" 
			class="ppfx-grid-btn" 
			data-value="<?php echo esc_attr( $ppfx_term->slug ); ?>"
			data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
			aria-pressed="false"
		>
			<?php echo esc_html( $ppfx_term->name ); ?>
			<?php if ( ! empty( $show_count ) ) : ?>
				<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $ppfx_term->count ); ?></span>
			<?php endif; ?>
		</button>
	<?php endforeach; ?>
	
	<!-- Hidden input to store multiple selected values as a comma-separated list -->
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-grid-hidden-input" value="">
</div>
