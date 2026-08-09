<?php

/**
 * Type: Image Swatch
 * Displays terms as interactive image squares with fallback to pills.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-swatch-list ppfx-image-swatch-list" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php
	foreach ( $terms as $term ) :
		$image_id  = get_term_meta( $term->term_id, 'ppfx_image', true );
		$image_url = $image_id ? wp_get_attachment_thumb_url( $image_id ) : '';

		// Fallback: If no image, use a pill-style button (Button Grid style)
		if ( ! $image_url ) :
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
					class="ppfx-swatch-item ppfx-image-swatch" 
					data-value="<?php echo esc_attr( $term->slug ); ?>" 
					data-label="<?php echo esc_attr( $term->name ); ?>"
					aria-label="<?php echo esc_attr( $term->name . ( ! empty( $show_count ) ? ' (' . $term->count . ')' : '' ) ); ?>"
				>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" />
				</button>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $term->count ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	<?php endforeach; ?>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-swatch-hidden-input" value="">
</div>
