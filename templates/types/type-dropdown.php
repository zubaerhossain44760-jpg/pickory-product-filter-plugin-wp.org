<?php

/**
 * Type: Dropdown
 * For compact single selection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-dropdown-wrap">
	<select name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-dropdown-input">
		<option value=""><?php
			/* translators: %s = filter label */
			printf( esc_html__( 'Select %s', 'pickory-product-filter' ), esc_html( $label ) );
		?></option>
		<?php foreach ( $terms as $term ) : ?>
			<option value="<?php echo esc_attr( $term->slug ); ?>" data-count="<?php echo esc_attr( $term->count ); ?>">
				<?php echo esc_html( $term->name ); ?>
				<?php if ( ! empty( $show_count ) ) : ?>
					(<?php echo esc_html( $term->count ); ?>)
				<?php endif; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
