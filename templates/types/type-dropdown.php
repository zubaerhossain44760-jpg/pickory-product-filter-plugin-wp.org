<?php
/**
 * Type: Dropdown
 * For compact single selection.
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

<div class="ppfx-dropdown-wrap">
	<select name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-dropdown-input">
		<?php /* translators: %s = filter type name */ ?>
		<option value=""><?php printf( esc_html__( 'Select %s', 'pickory-product-filter' ), esc_html( $label ) ); ?></option>
		<?php foreach ( $terms as $ppfx_term ) : ?>
			<option value="<?php echo esc_attr( $ppfx_term->slug ); ?>" data-count="<?php echo esc_attr( $ppfx_term->count ); ?>">
				<?php echo esc_html( $ppfx_term->name ); ?>
				<?php if ( ! empty( $show_count ) ) : ?>
					(<?php echo esc_html( $ppfx_term->count ); ?>)
				<?php endif; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
