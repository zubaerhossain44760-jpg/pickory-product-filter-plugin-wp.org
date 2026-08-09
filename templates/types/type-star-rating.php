<?php

/**
 * Type: Star Rating
 * Five star rows for filtering products by review score.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ratings = array( 5, 4, 3, 2, 1 );
?>

<div class="ppfx-star-rating-list">
	<?php foreach ( $ratings as $rating ) : ?>
		<?php
		/* translators: %d = rating number */
		$label_text = ( $rating < 5 ) ? sprintf( esc_html__( '%d & Up', 'pickory-product-filter' ), $rating ) : esc_html__( '5 Stars', 'pickory-product-filter' );
		?>
		<button 
			type="button"
			class="ppfx-star-row" 
			data-rating="<?php echo esc_attr( $rating ); ?>" 
			data-label="<?php echo esc_attr( $label_text ); ?>"
			aria-label="<?php echo esc_attr( $label_text ); ?>"
		>
			<span class="ppfx-stars" aria-hidden="true">
				<?php echo esc_html( str_repeat( '★', (int) max( 0, min( 5, $rating ) ) ) ); ?>
			</span>
			<span class="ppfx-empty-stars" aria-hidden="true">
				<?php echo esc_html( str_repeat( '☆', (int) max( 0, 5 - min( 5, max( 0, $rating ) ) ) ) ); ?>
			</span>
			<span class="ppfx-rating-text">
				<?php echo esc_html( $label_text ); ?>
			</span>
		</button>
	<?php endforeach; ?>
	
	<input type="hidden" name="rating" class="ppfx-rating-input" value="">
</div>
