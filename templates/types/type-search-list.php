<?php

/**
 * Type: Search List
 * Checkbox list with a live filter search box on top.
 *
 * When $max_height is set, the scrollable wrapper is applied only around the
 * term list — not the search input. The outer ppfx-scrollable-container added
 * by filter-layout.php is neutralised via an inline style override so that
 * only the inner list scrolls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_max_height = ! empty( $max_height );
?>

<?php
// filter-layout.php skips the outer ppfx-scrollable-container for search-list,
// so the scroll constraint is applied directly to the inner list via inline style.
?>

<div class="ppfx-search-list-container">
	<div class="ppfx-search-box-wrap">
		<input
			type="text"
			class="ppfx-search-box-input"
			placeholder="<?php esc_attr_e( 'Search terms...', 'pickory-product-filter' ); ?>"
			aria-label="<?php esc_attr_e( 'Filter terms by name', 'pickory-product-filter' ); ?>"
		>
		<span class="ppfx-search-icon" aria-hidden="true">🔍</span>
	</div>

	<?php if ( $has_max_height ) : ?>
		<div class="ppfx-inner-scroll-area" style="max-height: <?php echo esc_attr( $max_height ); ?>px; overflow-y: auto;">
	<?php endif; ?>

		<div class="ppfx-items-list ppfx-searchable-items">
			<?php foreach ( $terms as $term ) : ?>
				<label class="ppfx-item" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>">
					<input
						type="checkbox"
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

	<?php if ( $has_max_height ) : ?>
		</div>
	<?php endif; ?>
</div>
