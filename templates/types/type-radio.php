<?php

/**
 * Type: Radio List
 * For single-select attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-items-list">
	<?php foreach ( $terms as $term ) : ?>
		<label class="ppfx-item">
			<input 
				type="radio" 
				name="<?php echo esc_attr( $input_name ); ?>" 
				value="<?php echo esc_attr( $term->slug ); ?>"
				data-label="<?php echo esc_attr( $term->name ); ?>"
			>
			<span class="ppfx-item-label">
				<?php echo esc_html( $term->name ); ?>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-item-count">(<?php echo esc_html( $term->count ); ?>)</span>
				<?php endif; ?>
			</span>
		</label>
	<?php endforeach; ?>
</div>
