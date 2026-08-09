<?php

/**
 * Type: Color Swatch
 * Displays terms as interactive color circles with fallback to pills.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-swatch-list" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php
	foreach ( $terms as $term ) :
		$raw_color = get_term_meta( $term->term_id, 'ppfx_color', true );
		$color     = sanitize_hex_color( $raw_color ) ?: $raw_color;

		// Fallback: If no color, use a pill-style button (Button Grid style)
		if ( ! $color ) :
			?>
			<span class="ppfx-btn-wrap">
				<button 
					type="button" 
					class="ppfx-grid-btn" 
					data-value="<?php echo esc_attr( $term->slug ); ?>"
					data-label="<?php echo esc_attr( $term->name ); ?>"
					aria-pressed="false"
					title="<?php echo esc_attr( $term->name . ( ! empty( $show_count ) ? ' (' . $term->count . ')' : '' ) ); ?>"
				>
					<?php echo esc_html( $term->name ); ?>
				</button>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $term->count ); ?></span>
				<?php endif; ?>
			</span>
		<?php else : ?>
			<span class="ppfx-swatch-wrap">
				<button 
					type="button"
					class="ppfx-swatch-item ppfx-color-swatch" 
					data-value="<?php echo esc_attr( $term->slug ); ?>" 
					data-label="<?php echo esc_attr( $term->name ); ?>"
					aria-label="<?php echo esc_attr( $term->name . ( ! empty( $show_count ) ? ' (' . $term->count . ')' : '' ) ); ?>"
				>
					<span class="ppfx-swatch-inner" style="background-color: <?php echo esc_attr( $color ); ?>;"></span>
				</button>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $term->count ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	<?php endforeach; ?>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-swatch-hidden-input" value="">
</div>
