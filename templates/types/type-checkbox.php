<?php

/**
 * Type: Checkbox List
 * For multi-select attributes and categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-items-list">
	<?php foreach ( $terms as $term ) : ?>
		<label class="ppfx-item">
			<input 
				type="checkbox" 
				name="<?php echo esc_attr( $input_name ); ?>" 
				value="<?php echo esc_attr( $term->slug ); ?>"
				data-label="<?php echo esc_attr( $term->name ); ?>"
				<?php // Note: Selection state is rehydrated by JS via URL params ?>
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
