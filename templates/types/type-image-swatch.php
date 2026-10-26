<?php
/**
 * Type: Image Swatch
 * Displays terms as interactive image squares with fallback to pills.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $terms ) || ! is_iterable( $terms ) ) {
	return;
}

// Pre-collect attachment IDs and batch prime post/meta caches to eliminate N+1 queries.
$attachment_ids = array();
foreach ( $terms as $ppfx_term ) {
	$term_img_id = get_term_meta( $ppfx_term->term_id, 'ppfx_image', true );
	if ( ! empty( $term_img_id ) ) {
		$attachment_ids[] = (int) $term_img_id;
	}
}

if ( ! empty( $attachment_ids ) && function_exists( '_prime_post_caches' ) ) {
	_prime_post_caches( array_unique( $attachment_ids ), false, true );
}
?>

<div class="ppfx-swatch-list ppfx-image-swatch-list" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
	<?php
	foreach ( $terms as $ppfx_term ) :
		$image_id  = get_term_meta( $ppfx_term->term_id, 'ppfx_image', true );
		$image_tag = $image_id ? wp_get_attachment_image(
			$image_id,
			'thumbnail',
			false,
			array(
				'class'       => 'ppfx-swatch-thumb',
				'alt'         => '',
				'aria-hidden' => 'true',
			)
		) : '';

		// Fallback: If no image, use a pill-style button (Button Grid style).
		if ( ! $image_tag ) :
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
				class="ppfx-swatch-item ppfx-image-swatch" 
				data-value="<?php echo esc_attr( $ppfx_term->slug ); ?>" 
				data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
				aria-label="<?php echo esc_attr( $ppfx_term->name . ( ! empty( $show_count ) ? ' (' . $ppfx_term->count . ')' : '' ) ); ?>"
				aria-pressed="false"
				title="<?php echo esc_attr( $ppfx_term->name . ( ! empty( $show_count ) ? ' (' . $ppfx_term->count . ')' : '' ) ); ?>"
			>
				<?php echo $image_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image generates safe core HTML. ?>
				<?php if ( ! empty( $show_count ) ) : ?>
					<span class="ppfx-count-badge" aria-hidden="true"><?php echo esc_html( $ppfx_term->count ); ?></span>
				<?php endif; ?>
			</button>
		<?php endif; ?>
	<?php endforeach; ?>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-swatch-hidden-input" value="">
</div>
