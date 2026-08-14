<?php
/**
 * Documentation tab view for Pickory Product Filter settings.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$sections = array(
	array(
		'id'    => 'getting-started',
		'title' => __( 'Getting Started', 'pickory-product-filter' ),
		'open'  => true,
	),
	array(
		'id'    => 'filter-sections-reference',
		'title' => __( 'Filter Sections Reference', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'advanced-settings',
		'title' => __( 'Advanced Settings', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'how-to-use',
		'title' => __( 'How to Use', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'shortcode-block',
		'title' => __( 'Shortcode & Block', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'warnings-limitations',
		'title' => __( 'Warnings & Known Limitations', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'rest-api',
		'title' => __( 'REST API', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'changelog',
		'title' => __( 'Changelog', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'about',
		'title' => __( 'About', 'pickory-product-filter' ),
		'open'  => false,
	),
);
?>

<div class="ppfx-doc-wrap">

	<div class="ppfx-doc-main">

		<div class="ppfx-doc-header-card">
		<h2><?php esc_html_e( 'Pickory Product Filter Documentation', 'pickory-product-filter' ); ?></h2>
		<span class="ppfx-doc-version-badge">
			<?php echo esc_html( PPFX_VERSION ); ?>
		</span>
	</div>

	<?php foreach ( $sections as $section ) : ?>
		<details class="ppfx-doc-section-card" <?php echo $section['open'] ? 'open' : ''; ?>>
			<summary><?php echo esc_html( $section['title'] ); ?></summary>
			<div class="ppfx-doc-content">
				<?php if ( 'getting-started' === $section['id'] ) : ?>
					<p><?php esc_html_e( 'Pickory Product Filter is a fast, AJAX-powered WooCommerce product filter. Before installing, make sure your server meets the following requirements.', 'pickory-product-filter' ); ?></p>

					<h4><?php esc_html_e( 'Requirements', 'pickory-product-filter' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'WordPress 6.0+', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'WooCommerce 7.0+ (8.0+ recommended)', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'PHP 8.0+', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'MySQL 5.7+ or MariaDB 10.3+', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'A WooCommerce product loop on the shop / archive page (theme requirement)', 'pickory-product-filter' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'Installation Steps', 'pickory-product-filter' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Upload the plugin ZIP and activate it from Plugins → Installed Plugins.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Go to Pickory Product Filter → Filter Sections tab and enable the filters you need.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Open your shop page in the block editor, add the "Pickory Filter" block — or paste the [pickory_product_filter] shortcode into any widget or page.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Save — your filters are now live on the frontend.', 'pickory-product-filter' ); ?></li>
					</ol>

					<p class="ppfx-doc-note">
						<?php esc_html_e( 'After first activation, the plugin automatically builds the product index. If filters return no results, visit the Advanced tab and click "Rebuild Product Index" to sync the index manually.', 'pickory-product-filter' ); ?>
					</p>
				<?php elseif ( 'how-to-use' === $section['id'] ) : ?>
					<h4><?php esc_html_e( 'Block Editor (Gutenberg)', 'pickory-product-filter' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Open your shop or any page in the block editor.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Click the + button and search for "Pickory Filter", then add the block.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Alternatively, add a Shortcode block and paste [pickory_product_filter] to use the shortcode directly in your page content.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'You can add custom CSS classes to the shortcode to apply different styling (e.g. [pickory_product_filter instance_id="xxx" class="xxx"] for a custom layout).', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'All filter configuration is controlled from the Filter Settings tab in the plugin settings — the block has no per-block settings.', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Publish or update the page.', 'pickory-product-filter' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'How Filters Work', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Every filter selection updates the page URL with query parameters (e.g. ?ppfx_category=shirts&min_price=20). This means filtered views are shareable, bookmarkable, and supported by the browser back/forward buttons — no page reload required.', 'pickory-product-filter' ); ?></p>

					<h4><?php esc_html_e( 'Active Filter Chips', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Above the filter instance, active selections are shown as dismissible chips. Clicking a chip removes that filter instantly. A "Clear All" chip resets the entire filter state. Chips render automatically — no extra configuration is needed.', 'pickory-product-filter' ); ?></p>

					<h4><?php esc_html_e( 'Multiple Filter Instances', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'You can place multiple filter instances on the same page (e.g. a sidebar filter and a mobile drawer filter) using the instance_id attribute. If omitted, the plugin automatically generates a safe, unique fallback identifier:', 'pickory-product-filter' ); ?></p>
					<code class="ppfx-doc-code"><?php echo '[pickory_product_filter instance_id="sidebar"]'; ?></code>
					<code class="ppfx-doc-code"><?php echo '[pickory_product_filter instance_id="mobile-drawer"]'; ?></code>
					<p class="ppfx-doc-note"><?php esc_html_e( 'Both instances stay in sync — selecting a filter in one automatically reflects in the other.', 'pickory-product-filter' ); ?></p>

					<h4><?php esc_html_e( 'Important Note on Product Attributes', 'pickory-product-filter' ); ?></h4>
					<p class="ppfx-doc-note">
						<?php esc_html_e( 'To enable filtering by a WooCommerce product attribute (e.g. Color, Size), you must go to WooCommerce &rarr; Products &rarr; Attributes &rarr; Edit &rarr; check the "Enable Archives" option for that attribute.', 'pickory-product-filter' ); ?>
					</p>
				<?php elseif ( 'filter-sections-reference' === $section['id'] ) : ?>
				<p><?php esc_html_e( 'The Filter Sections tab (Pickory Product Filter → Filter Sections) lists every filter that can appear on the frontend. Each row is a section you can configure independently. Drag rows to reorder, toggle sections on or off, and configure how each one is displayed.', 'pickory-product-filter' ); ?></p>

				<h4><?php esc_html_e( 'Available Filter Sections', 'pickory-product-filter' ); ?></h4>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Section', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Key', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Source', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Type', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Price', 'pickory-product-filter' ); ?></strong></td>
							<td><code>price</code></td>
							<td><?php esc_html_e( 'Built-in', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Fixed — Slider', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Rating', 'pickory-product-filter' ); ?></strong></td>
							<td><code>rating</code></td>
							<td><?php esc_html_e( 'Built-in', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Fixed — Star Rating', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'On Sale', 'pickory-product-filter' ); ?></strong></td>
							<td><code>on_sale</code></td>
							<td><?php esc_html_e( 'Built-in', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Fixed — Toggle', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Category', 'pickory-product-filter' ); ?></strong></td>
							<td><code>category</code></td>
							<td><?php esc_html_e( 'WooCommerce taxonomy', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable (see Category Types below)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Product Tag', 'pickory-product-filter' ); ?></strong></td>
							<td><code>product_tag</code></td>
							<td><?php esc_html_e( 'WooCommerce taxonomy', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable (no swatches)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Product Attributes', 'pickory-product-filter' ); ?></strong></td>
							<td><code>pa_*</code></td>
							<td><?php esc_html_e( 'WooCommerce product attributes (e.g. pa_color, pa_size)', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable (all types including swatches)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Custom Taxonomies', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'taxonomy slug', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Any custom taxonomy registered for products', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable (no swatches)', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Per-Section Settings', 'pickory-product-filter' ); ?></h4>
				<p><?php esc_html_e( 'Every row in the Filter Sections tab has the following configurable columns:', 'pickory-product-filter' ); ?></p>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Column', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'What it controls', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Applies to', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Drag Handle', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Drag the ⣿ icon to reorder sections. The order here is the exact order they appear in the frontend filter widget.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Display Type', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Controls how the filter options are rendered on the frontend. Fixed sections show a badge and cannot be changed. Configurable sections show a dropdown.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable sections only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Label Override', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Custom heading text shown above the filter on the frontend. Leave blank to use the default label (taxonomy name or built-in name).', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Max Height (px)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Limits the visible height of the option list and adds a scrollbar when the list exceeds that height. Leave empty for no height limit. Useful for taxonomies with many terms.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable sections (not fixed types)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Count', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Toggle whether the number of matching products is shown next to each term label (e.g. “Red (12)”). Disable to keep the UI cleaner.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Taxonomy sections (not fixed built-in types)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Enabled', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Show or hide this filter section on the frontend. Disabled sections are saved but do not render. Use this to temporarily hide a filter without losing its settings.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Display Types Reference', 'pickory-product-filter' ); ?></h4>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Display Type', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Behaviour', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Multi-select', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Available for', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Checkboxes', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Standard checkbox list. Most versatile and familiar to shoppers.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Radio Buttons', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Allows one selection at a time. Selecting a new option deselects the previous.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Button / Pill Grid', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Renders each term as a clickable pill button. Good for short labels (sizes, colours).', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Dropdown (Single Select)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Compact native select element. Saves vertical space when the taxonomy has many terms.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Search List', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Checkbox list with a live search input. Ideal for taxonomies with 20+ terms where scrolling is impractical.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Swatches — Color', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Circular color-dot swatches. Color is set per-term from the attribute term edit screen in WooCommerce.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Product attributes (pa_*) only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Swatches — Image', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Image thumbnail swatches. Image is set per-term from the attribute term edit screen in WooCommerce.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Product attributes (pa_*) only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Slider (Price)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Dual-thumb range slider. Min/max bounds are calculated automatically from catalog prices. Currency-aware.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Range', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Price only (fixed)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Star Rating', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Shows 1–5 star options. Selecting a rating shows products rated at or above that value.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Rating only (fixed)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Toggle', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'A single on/off switch. When on, only products currently on sale are shown.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'N/A', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'On Sale only (fixed)', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<p class="ppfx-doc-note">
					<?php esc_html_e( 'Price, Rating, and On Sale sections have fixed display types that cannot be changed. Swatch types (Color and Image) are only available for WooCommerce product attributes (pa_*). Applying them to categories or custom taxonomies will silently fall back to Button Grid.', 'pickory-product-filter' ); ?>
				</p>
				<?php elseif ( 'shortcode-block' === $section['id'] ) : ?>
					<h4><?php esc_html_e( 'Shortcode', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Add the filter to any page, widget, or PHP template using the shortcode:', 'pickory-product-filter' ); ?></p>
					<code class="ppfx-doc-code"><?php echo '[pickory_product_filter]'; ?></code>

					<p><?php esc_html_e( 'Add a horizontal filter using the shortcode in a text widget, page content, shortcode block, or hook it to anywhere you want eg. the shop loop start and other locations via your theme functions.php file or a 3rd party snippet plugin.', 'pickory-product-filter' ); ?></p>
					<code class="ppfx-doc-code"><?php echo '[pickory_product_filter instance_id="top" class="ppfx-horizontal"]'; ?></code>


					<h4><?php esc_html_e( 'Shortcode Attributes', 'pickory-product-filter' ); ?></h4>
					<table class="ppfx-doc-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attribute', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Default', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Description', 'pickory-product-filter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>instance_id</code></td>
								<td><code><?php esc_html_e( 'auto-generated (wp_unique_id)', 'pickory-product-filter' ); ?></code></td>
								<td>
									<?php esc_html_e( 'Unique identifier for this filter instance. Required when placing multiple filters on the same page (e.g. sidebar + top of shop page). If omitted, the plugin automatically generates a safe, unique fallback identifier.', 'pickory-product-filter' ); ?>
									<br><br>
									<code class="ppfx-doc-code"><?php echo '[pickory_product_filter instance_id="sidebar"]'; ?></code>
								</td>
							</tr>
							<tr>
								<td><code>class</code></td>
								<td><?php esc_html_e( '(empty)', 'pickory-product-filter' ); ?></td>
								<td>
									<?php esc_html_e( 'Additional CSS class(es) appended to the filter container element for custom styling.', 'pickory-product-filter' ); ?>
									<br><br>
									<code class="ppfx-doc-code"><?php echo '[pickory_product_filter class="ppfx-horizontal"]'; ?></code>
								</td>
							</tr>
						</tbody>
					</table>



					<h4><?php esc_html_e( 'Gutenberg Block', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Search for "Pickory Filter" in the block inserter (+) and add it to your shop or archive page. The block outputs the same HTML as the shortcode. All display configuration is managed from the Filter Sections tab in the plugin admin — the block itself has no extra options.', 'pickory-product-filter' ); ?></p>

					<p class="ppfx-doc-note">
						<?php esc_html_e( 'The shortcode and block are interchangeable — they produce identical markup. Use whichever fits your page-building workflow.', 'pickory-product-filter' ); ?>
					</p>
				<?php elseif ( 'advanced-settings' === $section['id'] ) : ?>
					<p><?php esc_html_e( 'Advanced settings are located under Pickory Product Filter → Settings → Advanced tab.', 'pickory-product-filter' ); ?></p>

					<table class="ppfx-doc-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Setting', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'What it does', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Default', 'pickory-product-filter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Auto Filter', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'When enabled, the product grid refreshes instantly via AJAX on every filter interaction. When disabled, an "Apply Filters" button appears and results update only on click.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Enabled', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Rate Limiting', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Caps filter AJAX requests at 80 per minute per IP address to protect the server from abuse or bot traffic. Logged-in users with object cache disabled bypass the limit automatically.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Enabled', 'pickory-product-filter' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( 'Rebuilding the Product Index', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'The plugin maintains two custom database tables (wp_ppfx_product_index and wp_ppfx_product_terms) that power fast filtering. These sync automatically on product save, delete, or status change. Manual rebuilding is needed after:', 'pickory-product-filter' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Bulk product imports via CSV or XML', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Price or stock updates via external tools', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Database migrations or server transfers', 'pickory-product-filter' ); ?></li>
						<li><?php esc_html_e( 'Plugin updates that change the index schema', 'pickory-product-filter' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'To rebuild: go to Pickory Product Filter → Settings → Advanced → click "Rebuild Product Index". Do not close the tab while the rebuild is running. On stores with 50,000+ products this may take a few minutes.', 'pickory-product-filter' ); ?></p>
				<?php elseif ( 'warnings-limitations' === $section['id'] ) : ?>
					<table class="ppfx-doc-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Area', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Warning / Limitation', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Action Required', 'pickory-product-filter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Page Caching', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Aggressively cached REST API responses will serve stale product results to visitors.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Add /wp-json/pickory-filter/v1/* to the exclusion list of your caching plugin.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Theme Compatibility', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Themes that replace the default WooCommerce product loop with a custom markup may not have a .ppfx-products-wrapper or .products container, which the plugin uses to inject AJAX results.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Add the data-ppfx-target attribute to your shortcode pointing to your theme\'s product grid selector, e.g. [pickory_product_filter] and ensure your theme renders a standard WooCommerce product loop.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Index Rebuild on Large Stores', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'On stores with 50,000+ products, rebuilding the product index can take several minutes and consumes elevated server resources during the process.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Run the rebuild during low-traffic hours. Do not close the browser tab until the process completes.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'JavaScript Conflicts', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Themes or plugins that intercept the WooCommerce ordering form submit event may conflict with the plugin\'s sort handling.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'The plugin calls stopImmediatePropagation() on the ordering form to own sort interactions. If sorting stops working, check your browser console for conflicting scripts.[send feedback]', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Out-of-Stock Products', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Filter counts and results follow WooCommerce\'s catalog visibility rules.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'If "Hide out of stock items from the catalog" is enabled in WooCommerce → Settings → Products → Inventory, those products will be excluded from all filter results automatically.', 'pickory-product-filter' ); ?></td>
							</tr>
						</tbody>
					</table>
				<?php elseif ( 'rest-api' === $section['id'] ) : ?>
				<h4><?php esc_html_e( 'Endpoint', 'pickory-product-filter' ); ?></h4>
				<code class="ppfx-doc-code">GET /wp-json/pickory-filter/v1/products</code>

				<h4><?php esc_html_e( 'Query Parameters', 'pickory-product-filter' ); ?></h4>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Type', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Description', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>page</code></td>
							<td><?php esc_html_e( 'int', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Current page number (default: 1)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>per_page</code></td>
							<td><?php esc_html_e( 'int', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Products per page (default: WooCommerce setting, max: 100)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>category</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Comma-separated category slugs', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>product_tag</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Comma-separated tag slugs', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>min_price</code></td>
							<td><?php esc_html_e( 'float', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Minimum price', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>max_price</code></td>
							<td><?php esc_html_e( 'float', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Maximum price', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>rating</code></td>
							<td><?php esc_html_e( 'float', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Minimum star rating (1–5)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>on_sale</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Pass "1" to show on-sale products only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>filter_{attribute}</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Comma-separated attribute slugs, e.g. filter_pa_color=blue,red', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>orderby</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Sort field: menu_order, price, date, popularity, rating', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>order</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Sort direction: ASC or DESC', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><code>instance_id</code></td>
							<td><?php esc_html_e( 'string', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Filter instance identifier', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Sample Response', 'pickory-product-filter' ); ?></h4>
				<pre class="ppfx-doc-code ppfx-doc-pre"><?php echo esc_html( "{\n  \"success\": true,\n  \"fragments\": {\n    \".ppfx-products-wrapper\": \"<div>...product HTML...</div>\",\n    \".woocommerce-result-count\": \"Showing 1–12 of 48 results\",\n    \".woocommerce-ordering\": \"<select>...</select>\",\n    \".woocommerce-pagination\": \"<nav>...</nav>\"\n  }\n}" ); ?></pre>

				<h4><?php esc_html_e( 'WordPress Hooks', 'pickory-product-filter' ); ?></h4>
				<p><strong><?php esc_html_e( 'Filters:', 'pickory-product-filter' ); ?></strong></p>
				<ul>
					<li><code>ppfx_rest_max_per_page</code> — <?php esc_html_e( 'Maximum products per REST request (default: 100)', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_force_db_rate_limiting</code> — <?php esc_html_e( 'Force rate limiting even without object cache (default: false)', 'pickory-product-filter' ); ?></li>
				</ul>
				<p><strong><?php esc_html_e( 'Actions:', 'pickory-product-filter' ); ?></strong></p>
				<ul>
					<li><code>ppfx_before_query_build</code> — <?php esc_html_e( 'Fired before building WP_Query args; use to modify request params', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_after_query_build</code> — <?php esc_html_e( 'Fired after building WP_Query args; use to modify the query object', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_index_sync_product</code> — <?php esc_html_e( 'Fired when syncing a product to the index; use to modify index row data', 'pickory-product-filter' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'JavaScript Events', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><code>ppfx:filters-updated</code> — <?php esc_html_e( 'Fired after filter results are updated in the DOM', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx:destroy</code> — <?php esc_html_e( 'Fired before a filter instance is destroyed (cleanup event)', 'pickory-product-filter' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'Key CSS Classes', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><code>.ppfx-filter-container</code> — <?php esc_html_e( 'Main filter wrapper', 'pickory-product-filter' ); ?></li>
					<li><code>.ppfx-filter-form</code> — <?php esc_html_e( 'Filter form element', 'pickory-product-filter' ); ?></li>
					<li><code>.ppfx-products-wrapper</code> — <?php esc_html_e( 'Product grid wrapper', 'pickory-product-filter' ); ?></li>
					<li><code>.ppfx-active-filters</code> — <?php esc_html_e( 'Active filter chips container', 'pickory-product-filter' ); ?></li>
					<li><code>.ppfx-mobile-toggle</code> — <?php esc_html_e( 'Mobile drawer toggle button', 'pickory-product-filter' ); ?></li>
					<li><code>.ppfx-type-{type}</code> — <?php esc_html_e( 'Per-section type class, e.g. .ppfx-type-slider', 'pickory-product-filter' ); ?></li>
				</ul>

			<?php elseif ( 'changelog' === $section['id'] ) : ?>
				<h4><?php esc_html_e( 'Version 1.0.0', 'pickory-product-filter' ); ?> &mdash; <?php esc_html_e( 'Initial Release', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Flat product index for fast filtering (no expensive postmeta scans)', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'REST API-based AJAX filtering with abortable requests', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Multiple filter display types: checkbox, radio, dropdown, swatch (color/image), button grid, toggle, search list, slider', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Price range slider with dual thumbs', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Star rating filter', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'On Sale toggle filter', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Category, product tag, and WooCommerce attribute filters', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Hierarchical category support (selecting a parent includes child categories)', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'URL-addressable filters with browser history (back/forward) support', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Active filter chips with one-click removal', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Mobile-responsive off-canvas drawer with smooth animations', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Multi-currency support — price slider automatically adjusts to the active currency', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'HPOS (High-Performance Order Storage) compatibility', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Object cache integration for faster repeat queries on logged-out users', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Built-in rate limiting (default: 80 requests/min per IP)', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Shortcode [pickory_product_filter] and Gutenberg block support', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Template override system — copy templates to yourtheme/pickory-product-filter/', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Developer hooks (filters & actions) for customization', 'pickory-product-filter' ); ?></li>
				</ul>

			<?php else : ?>
				<h4><?php esc_html_e( 'About the Plugin', 'pickory-product-filter' ); ?></h4>
				<p><?php esc_html_e( 'Pickory Product Filter is an enterprise-grade,Lightweight AJAX-powered WooCommerce product filter built for speed, scalability, and developer flexibility. It replaces slow postmeta queries with a flat, denormalized product index — delivering filter responses nearly +-100–300 ms even on stores with 50,000+ products.', 'pickory-product-filter' ); ?></p>

				<h4><?php esc_html_e( 'Plugin Details', 'pickory-product-filter' ); ?></h4>
				<table class="ppfx-doc-table">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Plugin Name', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Pickory Product Filter', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Developer', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Md Zubaer Hossain', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Author / Brand', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Pickory', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'License', 'pickory-product-filter' ); ?></strong></td>
							<td>
								<a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'GPL-2.0-or-later', 'pickory-product-filter' ); ?>
								</a>
							</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'WooCommerce Compatibility', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'HPOS compatible · Tested up to WooCommerce 9.8', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Frontend Asset Size', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( '~12.03 KB · May vary slightly in future updates', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Tech Stack', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Backend: PHP 8.0+, WordPress REST API, custom flat DB index (wp_ppfx_product_index, wp_ppfx_product_terms)', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Frontend: Vanilla JavaScript (ES6 modules), no jQuery dependency', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Build tooling: Vite', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Caching: WordPress object cache integration with 5-minute query TTL for logged-out users', 'pickory-product-filter' ); ?></li>
				</ul>

				<p class="ppfx-doc-note">
					<?php esc_html_e( 'For bug reports or feature requests, please contact support at <a href="mailto:pickory143@gmail.com">pickory143@gmail.com</a>.', 'pickory-product-filter' ); ?>
				</p>
				<?php endif; ?>
			</div>
		</details>
	<?php endforeach; ?>
	</div>

	<div class="ppfx-doc-sidebar">
		<div class="card ppfx-doc-sidebar-card">
			<h3>🤝 <?php esc_html_e( 'Need Human Assistance?', 'pickory-product-filter' ); ?></h3>
			<p class="description"><?php esc_html_e( "Can't find the answer in the docs? Our engineering team and community are ready to help.", 'pickory-product-filter' ); ?></p>
			
			<ul class="pickory-support-list">
				<li>
					<svg class="pickory-support-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
					<div class="pickory-support-text">
						<a href="mailto:pickory143@gmail.com" target="_blank">
							<h4><?php esc_html_e( 'Submit a Ticket', 'pickory-product-filter' ); ?></h4>
							<p><?php esc_html_e( 'Get priority technical support.', 'pickory-product-filter' ); ?></p>
						</a>
					</div>
				</li>
				
				<li>
					<svg class="pickory-support-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
					<div class="pickory-support-text">
						<a href="mailto:pickory143@gmail.com" target="_blank">
							<h4><?php esc_html_e( 'Community Forum', 'pickory-product-filter' ); ?></h4>
							<p><?php esc_html_e( 'Ask questions and share tips.', 'pickory-product-filter' ); ?></p>
						</a>
					</div>
				</li>

				<li>
					<svg class="pickory-support-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
					<div class="pickory-support-text">
						<a href="mailto:pickory143@gmail.com" target="_blank">
							<h4><?php esc_html_e( 'Report a Bug', 'pickory-product-filter' ); ?></h4>
							<p><?php esc_html_e( 'Help us improve Pickory.', 'pickory-product-filter' ); ?></p>
						</a>
					</div>
				</li>
			</ul>

			<a href="https://wordpress.org/support/plugin/pickory-product-filter/" target="_blank" class="button button-primary ppfx-btn-block"><?php esc_html_e( 'Visit Support Forum →', 'pickory-product-filter' ); ?></a>
		</div>
	</div>

</div>
