<?php
/**
 * Type: Color Swatch
 * Displays terms as interactive color circles with fallback to pills.
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

<div class="ppfx-swatch-list" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php
	foreach ( $terms as $ppfx_term ) :
		$raw_color = get_term_meta( $ppfx_term->term_id, 'ppfx_color', true );
		$color     = sanitize_hex_color( $raw_color );

		// Fallback: If no valid color, use a pill-style button (Button Grid style).
		if ( ! $color ) :
			?>
				<button 
					type="button" 
					class="ppfx-grid-btn" 
					data-value="<?php echo esc_attr( $ppfx_term->slug ); ?>"
					data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
					aria-pressed="false"
					title="<?php echo esc_attr( $ppfx_term->name . ( ! empty( $show_count ) ? ' (' . $ppfx_term->count . ')' : '' ) ); ?>"
				>
					<?php echo esc_html( $ppfx_term->name ); ?>
					<?php if ( ! empty( $show_count ) ) : ?>
						<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $ppfx_term->count ); ?></span>
					<?php endif; ?>
				</button>
		<?php else : ?>
			<button 
				type="button"
				class="ppfx-swatch-item ppfx-color-swatch" 
				data-value="<?php echo esc_attr( $ppfx_term->slug ); ?>" 
				data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
				aria-label="<?php echo esc_attr( $ppfx_term->name . ( ! empty( $show_count ) ? ' (' . $ppfx_term->count . ')' : '' ) ); ?>"
				aria-pressed="false"
				title="<?php echo esc_attr( $ppfx_term->name . ( ! empty( $show_count ) ? ' (' . $ppfx_term->count . ')' : '' ) ); ?>"
			>
				<span class="ppfx-swatch-inner" style="background-color: <?php echo esc_attr( $color ); ?>;"></span>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $ppfx_term->count ); ?></span>
				<?php endif; ?>
			</button>
		<?php endif; ?>
	<?php endforeach; ?>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-swatch-hidden-input" value="">
</div>
