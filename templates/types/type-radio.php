<?php
/**
 * Type: Radio List
 * For single-select attributes.
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

<div class="ppfx-items-list">
	<?php foreach ( $terms as $ppfx_term ) : ?>
		<label class="ppfx-item">
			<input 
				type="radio" 
				name="<?php echo esc_attr( $input_name ); ?>" 
				value="<?php echo esc_attr( $ppfx_term->slug ); ?>"
				data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
			>
			<span class="ppfx-item-label">
				<?php echo esc_html( $ppfx_term->name ); ?>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-item-count">(<?php echo esc_html( $ppfx_term->count ); ?>)</span>
				<?php endif; ?>
			</span>
		</label>
	<?php endforeach; ?>
</div>
