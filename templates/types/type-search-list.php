<?php
/**
 * Type: Search List
 * Checkbox list with a live filter search box on top.
 *
 * When $max_height is set, the scrollable wrapper is applied only around the
 * term list — not the search input. The outer ppfx-scrollable-container added
 * by filter-layout.php is neutralised via an inline style override so that
 * only the inner list scrolls.
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $terms ) || ! is_iterable( $terms ) ) {
	return;
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
		<span class="ppfx-search-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
		</span>
	</div>

	<?php if ( $has_max_height ) : ?>
		<div class="ppfx-inner-scroll-area" style="max-height: <?php echo (int) $max_height; ?>px; overflow-y: auto;">
	<?php endif; ?>

		<div class="ppfx-items-list ppfx-searchable-items">
			<?php foreach ( $terms as $ppfx_term ) : ?>
				<?php
				$term_search_name = function_exists( 'mb_strtolower' )
					? mb_strtolower( $ppfx_term->name, 'UTF-8' )
					: strtolower( $ppfx_term->name );
				?>
				<label class="ppfx-item" data-term-name="<?php echo esc_attr( $term_search_name ); ?>">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $input_name ); ?>"
						value="<?php echo esc_attr( $ppfx_term->slug ); ?>"
						data-label="<?php echo esc_attr( $ppfx_term->name ); ?>"
					>
					<span class="ppfx-item-label">
						<?php echo esc_html( $ppfx_term->name ); ?>
						<?php if ( ! empty( $show_count ) ) : ?>
							<span class="ppfx-item-count">(<?php echo esc_html( $ppfx_term->count ); ?>)</span>
						<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</div>

	<?php if ( $has_max_height ) : ?>
		</div>
	<?php endif; ?>
</div>
