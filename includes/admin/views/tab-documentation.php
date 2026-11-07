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
		'title' => __( 'Filter Sections Settings', 'pickory-product-filter' ),
		'open'  => false,
	),
	array(
		'id'    => 'advanced-settings',
		'title' => __( 'Advanced Settings', 'pickory-product-filter' ),
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
		'title' => __( 'REST API & Developer Reference', 'pickory-product-filter' ),
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

				<?php elseif ( 'filter-sections-reference' === $section['id'] ) : ?>
				<p><?php esc_html_e( 'The Filter Sections tab lets you customize which filters appear on your store, choose their display formats, and configure their behavior.', 'pickory-product-filter' ); ?></p>

				<h4><?php esc_html_e( 'Display Types Reference', 'pickory-product-filter' ); ?></h4>
				<p><?php esc_html_e( 'Pickory offers dedicated display types tailored for different product attributes and taxonomy sizes:', 'pickory-product-filter' ); ?></p>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Display Type', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Behavior & Best Use', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Multi-select', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Available For', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Checkboxes', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Vertical multi-select list. The most versatile and familiar format for shoppers.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'All configurable taxonomies & attributes', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Radio Buttons', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Single-select list where selecting one option immediately deselects any previously selected option.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'All configurable taxonomies & attributes', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Button Grid', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Compact clickable pill buttons. Ideal for short labels like sizes (S, M, L, XL) or dimensions.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'All configurable taxonomies & attributes', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Dropdown', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Single-select native select dropdown. Conserves vertical sidebar space when a category has many terms.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'All configurable taxonomies & attributes', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Search List', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Checkbox list with an integrated live search bar. Perfect for taxonomies with 20+ terms (e.g. brands, authors, manufacturers).', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'All configurable taxonomies & attributes', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Color Swatch', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Visual circular color dots. Colors are configured per term under Products → Attributes → Configure terms.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'Product Attributes (pa_*) only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Image Swatch', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Thumbnail image swatches. Ideal for patterns, materials, fabrics, and textures configured under Products → Attributes.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Yes', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'Product Attributes (pa_*) only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Price Slider (Fixed)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Dual-thumb interactive price slider for filtering products within a minimum and maximum price range.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'Range', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'Price (price)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Star Rating (Fixed)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Interactive 1 to 5 star rating list. Filters catalog by minimum average review rating.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'Rating (rating)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'On Sale Toggle (Fixed)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Toggle switch that instantly filters products to show only items currently on sale.', 'pickory-product-filter' ); ?></td>
							<td><code><?php esc_html_e( 'No', 'pickory-product-filter' ); ?></code></td>
							<td><?php esc_html_e( 'On Sale (sale)', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Filter Section Settings & Columns', 'pickory-product-filter' ); ?></h4>
				<p><?php esc_html_e( 'Each row in the Filter Sections table represents a distinct filter. The table offers the following settings for each section:', 'pickory-product-filter' ); ?></p>
				<table class="ppfx-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Setting / Column', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'pickory-product-filter' ); ?></th>
							<th><?php esc_html_e( 'Applies to', 'pickory-product-filter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Drag Handle (⣿)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Click and drag rows to change their visual order. The order here matches the top-to-bottom order shoppers see on the frontend.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Section (Key)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Displays the section name and underlying taxonomy key (e.g. category, product_tag, price, rating, sale, pa_color). Use these keys when filtering specific sections in shortcodes.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Display Type', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Selects how filter terms are presented (Checkboxes, Radio, Button Grid, Dropdown, Search List, Swatches). Fixed sections display a locked badge.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All configurable sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Label Override', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Custom title displayed above the filter on the frontend. Leave blank to display the default taxonomy or attribute name.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Max Height (px)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Limits the visible height of the term list in pixels and adds an internal scrollbar if options exceed that height. Leave empty for no limit.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable sections & Rating', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Step (Price only)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Defines the slider snap step (default: 1). For stores with higher-priced products, increase to 5 or 10 to allow smooth, sensible price range selection.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Price section only', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Count', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Toggle to display product count badges next to each term (e.g. "Hoodies (8)"). Turn off for a cleaner, minimalist layout.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Taxonomy & attribute sections', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Truncate (Pro)', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Adds a "See more / See less" expander link to expand and collapse long term lists. Requires Max Height to be set first to activate.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'Configurable sections & Rating (with Max Height)', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'On / Off', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Toggle to show or hide the section on the frontend. Disabled sections remain saved and can be re-enabled at any time.', 'pickory-product-filter' ); ?></td>
							<td><?php esc_html_e( 'All sections', 'pickory-product-filter' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="ppfx-doc-note">
					<strong><?php esc_html_e( 'Configuring Color & Image Swatches:', 'pickory-product-filter' ); ?></strong>
					<ul style="margin: 6px 0 0 18px; list-style-type: disc;">
						<li><strong><?php esc_html_e( 'Where to configure:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Go to WooCommerce → Products → Attributes, then click "Configure terms" next to your attribute (e.g. Color, Size).', 'pickory-product-filter' ); ?></li>
						<li><strong><?php esc_html_e( 'Assign Color or Image:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Edit each term and set the "Swatch Color" (color picker) or "Swatch Image" (Upload/Select Image). If no color or image is set, it will fallback to button grid.', 'pickory-product-filter' ); ?></li>
				</div>
				<?php elseif ( 'shortcode-block' === $section['id'] ) : ?>
					<h4><?php esc_html_e( 'Shortcode & Block', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Place the filter anywhere on your site using the shortcode, its alias, or the block editor. All filter configuration comes from the Filter Sections tab.', 'pickory-product-filter' ); ?></p>

					<p><strong><?php esc_html_e( 'Shortcodes:', 'pickory-product-filter' ); ?></strong></p>
					<code class="ppfx-doc-code">[pickory_product_filter]</code>
					<code class="ppfx-doc-code">[ppfx_filter]</code>

					<h4><?php esc_html_e( 'Shortcode Attributes', 'pickory-product-filter' ); ?></h4>
					<table class="ppfx-doc-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attribute', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Default', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Description & Examples', 'pickory-product-filter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>instance_id</code></td>
								<td><code>auto-generated</code></td>
								<td>
									<?php esc_html_e( 'Unique ID for the filter widget. Use different IDs when placing multiple filters on the same page (e.g. sidebar and top bar). All instances stay in sync automatically.', 'pickory-product-filter' ); ?>
									<br><br>
									<code class="ppfx-doc-code">[pickory_product_filter instance_id="sidebar"]</code>
									<code class="ppfx-doc-code">[pickory_product_filter instance_id="top-bar"]</code>
								</td>
							</tr>
							<tr>
								<td><code>class</code></td>
								<td><code>(empty)</code></td>
								<td>
									<?php esc_html_e( 'Adds custom CSS classes to the container. Use "ppfx-horizontal" for a built in horizontal layout.', 'pickory-product-filter' ); ?>
									<br><br>
									<code class="ppfx-doc-code">[pickory_product_filter class="ppfx-horizontal"]</code>
								</td>
							</tr>
							<tr>
								<td><code>sections</code></td>
								<td><code>(Enabled)</code></td>
								<td>
								<?php esc_html_e( 'Shows only the listed filter sections: category, product_tag, price, rating, sale, or attribute names like color. Section-specific shortcodes work when Auto Filter is enabled.', 'pickory-product-filter' ); ?>
									<br><br>
									<code class="ppfx-doc-code">[pickory_product_filter sections="color"]</code>
								</td>
							</tr>
						</tbody>
					</table>

					<div class="ppfx-doc-note">
						<strong><?php esc_html_e( 'Notes:', 'pickory-product-filter' ); ?></strong>
						<ul style="margin: 6px 0 0 18px; list-style-type: disc;">
							<li><strong><?php esc_html_e( 'sections:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Hides the title, chips, and Clear all button for a compact, modular look.', 'pickory-product-filter' ); ?></li>
							<li><strong><?php esc_html_e( 'Mobile drawer:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'The mobile toggle button only appears on the main filter without custom classes or sections.', 'pickory-product-filter' ); ?></li>
						</ul>
					</div>

					<h4><?php esc_html_e( 'Gutenberg Block ("Pickory Filter")', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'In the block editor, click (+) and search for "Pickory Filter". It outputs the same markup as the shortcode and needs no extra setup.', 'pickory-product-filter' ); ?></p>
				<?php elseif ( 'advanced-settings' === $section['id'] ) : ?>
					<p><?php esc_html_e( 'Manage global filter behavior, mobile responsiveness, performance, and product index synchronization under Pickory Product Filter → Advanced.', 'pickory-product-filter' ); ?></p>

					<h4><?php esc_html_e( 'Settings Reference', 'pickory-product-filter' ); ?></h4>
					<table class="ppfx-doc-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Setting', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'Default', 'pickory-product-filter' ); ?></th>
								<th><?php esc_html_e( 'What it does & Options', 'pickory-product-filter' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Auto Filter on Change', 'pickory-product-filter' ); ?></strong></td>
								<td><code><?php esc_html_e( 'Enabled', 'pickory-product-filter' ); ?></code></td>
								<td><?php esc_html_e( 'Updates products instantly via AJAX when a filter is changed. When disabled, a "Filter" button appears so shoppers can select multiple terms before updating.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Active Filter Chips', 'pickory-product-filter' ); ?></strong></td>
								<td><code><?php esc_html_e( 'Disabled', 'pickory-product-filter' ); ?></code></td>
								<td><?php esc_html_e( 'Displays active filter selections as dismissible badges above the product catalog with an instant "Clear all" button.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Mobile Filter Drawer', 'pickory-product-filter' ); ?></strong></td>
								<td><code><?php esc_html_e( 'Disabled', 'pickory-product-filter' ); ?></code></td>
								<td>
									<?php esc_html_e( 'Turns the filter into a slide-in off-canvas drawer on mobile screens with a toggle button placed above the products.', 'pickory-product-filter' ); ?>
									<ul style="margin: 6px 0 0 16px; list-style-type: disc;">
										<li><strong><?php esc_html_e( 'Breakpoint:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Screen width in pixels where the mobile drawer activates (default: 768px).', 'pickory-product-filter' ); ?></li>
										<li><strong><?php esc_html_e( 'DOM Inject (Recommended):', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Moves the toggle button into the shop toolbar and attaches the drawer to <body> so it is never clipped by parent sidebar overflow.', 'pickory-product-filter' ); ?></li>
										<li><strong><?php esc_html_e( 'Simple Toggle:', 'pickory-product-filter' ); ?></strong> <?php esc_html_e( 'Keeps the toggle button and drawer in their original DOM location without re-parenting elements.', 'pickory-product-filter' ); ?></li>
									</ul>
								</td>
							</tr>

							<tr>
								<td><strong><?php esc_html_e( 'Debounce Delay', 'pickory-product-filter' ); ?></strong></td>
								<td><code>300ms</code></td>
								<td><?php esc_html_e( 'Time to wait before firing an AJAX request after user input. Applies to the price slider and text search inputs only.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'API Rate Limiting', 'pickory-product-filter' ); ?></strong></td>
								<td><code><?php esc_html_e( 'Enabled', 'pickory-product-filter' ); ?></code></td>
								<td><?php esc_html_e( 'Limits requests to 80 per minute per IP address on the products REST endpoint to protect against bots and scrapers.', 'pickory-product-filter' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( 'Product Index', 'pickory-product-filter' ); ?></h4>
					<p><?php esc_html_e( 'Pickory uses high-speed indexed database tables (wp_ppfx_product_index and wp_ppfx_product_terms) to deliver lightning-fast filtering. Products sync automatically on create, update, or delete.', 'pickory-product-filter' ); ?></p>
					
					<div class="ppfx-doc-note">
						<strong><?php esc_html_e( 'When to click "Rebuild Product Index":', 'pickory-product-filter' ); ?></strong>
						<ul style="margin: 6px 0 0 16px; list-style-type: disc;">
							<li><?php esc_html_e( 'After bulk product imports (CSV, XML, WP All Import).', 'pickory-product-filter' ); ?></li>
							<li><?php esc_html_e( 'After price or stock updates from external tools (ERP, POS, direct DB queries).', 'pickory-product-filter' ); ?></li>
							<li><?php esc_html_e( 'After database migration or restoring a site backup.', 'pickory-product-filter' ); ?></li>
						</ul>
						<p style="margin-top: 8px;">
							<?php esc_html_e( 'Rebuilding runs via Action Scheduler (or WP-Cron fallback) and is queued immediately after clicking the button.', 'pickory-product-filter' ); ?>
						</p>
					</div>
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
								<td><strong><?php esc_html_e( 'Section Shortcodes with Manual Filtering', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'A section-specific shortcode does not display its own Filter button. With Auto Filter disabled, another filter instance\'s button submits only that instance\'s selections.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Enable Auto Filter or use a full filter shortcode to apply selections from that form.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Page Caching', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'A page cache, proxy, or CDN that ignores the REST response\'s no-store header can serve stale product results.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'If your cache is configured to cache REST responses or ignores no-store, exclude /wp-json/pickory-filter/v1/ from its cache.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Theme Compatibility', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Themes that replace the default WooCommerce product loop with a custom markup may not have a .ppfx-products-wrapper or .products container, which the plugin uses to inject AJAX results.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Ensure your theme uses standard WooCommerce product loop hooks (woocommerce_product_loop_start/end), or wrap your custom product grid in a container with class .ppfx-products-wrapper.', 'pickory-product-filter' ); ?></td>

							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Index Rebuild on Large Stores', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Rebuild time and server load depend on catalog size and available resources. The index is cleared and rebuilt in batches, so filter results may be incomplete until the rebuild finishes.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Queue the rebuild during low-traffic hours. It runs in the background via Action Scheduler or WP-Cron, so you can leave this page after submitting.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'JavaScript Conflicts', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'Scripts that intercept changes to the WooCommerce ordering select may conflict with AJAX sorting.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'The plugin handles the ordering select\'s change event and stops further propagation. If sorting fails, check for other handlers on that event and review the browser console.', 'pickory-product-filter' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Rate Limiting Behind Proxies', 'pickory-product-filter' ); ?></strong></td>
								<td><?php esc_html_e( 'When API rate limiting is enabled behind a reverse proxy, visitors may share the proxy\'s IP address unless the client IP is detected correctly.', 'pickory-product-filter' ); ?></td>
								<td><?php esc_html_e( 'Configure the proxy to supply a verified client IP and set PPFX_TRUST_PROXY to true. Cloudflare\'s CF-Connecting-IP header is recognized automatically.', 'pickory-product-filter' ); ?></td>
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
				<p><?php esc_html_e( 'Parameters can be passed using clean parameter names or with the "ppfx_" prefix (e.g. ppfx_category, ppfx_min_price) matching the frontend URL.', 'pickory-product-filter' ); ?></p>
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
				<pre class="ppfx-doc-code ppfx-doc-pre"><?php echo esc_html( "{\n  \"success\": true,\n  \"loop\": {\n    \"has_results\": true\n  },\n  \"fragments\": {\n    \".ppfx-products-wrapper\": \"<div>...product HTML...</div>\",\n    \".woocommerce-result-count\": \"Showing 1–12 of 48 results\",\n    \".woocommerce-ordering\": \"<select>...</select>\",\n    \".woocommerce-pagination\": \"<nav>...</nav>\"\n  }\n}" ); ?></pre>

				<h4><?php esc_html_e( 'WordPress Hooks', 'pickory-product-filter' ); ?></h4>
				<p><strong><?php esc_html_e( 'Filters:', 'pickory-product-filter' ); ?></strong></p>
				<ul>
					<li><code>ppfx_rest_max_per_page</code> — <?php esc_html_e( 'Maximum products per REST request (default: 100)', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_force_db_rate_limiting</code> — <?php esc_html_e( 'Force rate limiting via database even without external object cache (default: false)', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_cache_key_context</code> — <?php esc_html_e( 'Filter contextual array passed when generating query cache keys (e.g. active currency or tax location)', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx_price_cache_currencies</code> — <?php esc_html_e( 'Filter the list of currency codes indexed for price filtering', 'pickory-product-filter' ); ?></li>
				</ul>
				<p><strong><?php esc_html_e( 'Actions:', 'pickory-product-filter' ); ?></strong></p>
				<ul>
					<li><code>ppfx_rebuild_index_action</code> — <?php esc_html_e( 'Action Scheduler hook executed to rebuild the flat product index in the background', 'pickory-product-filter' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'JavaScript Events', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><code>ppfx_products_rendered</code> — <?php esc_html_e( 'Fired on document with { detail: { instanceId, data } } after filter HTML fragments are updated in the DOM', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx:ajax_loaded</code> — <?php esc_html_e( 'Fired on document with { detail: { instanceId, data } } when a filter AJAX request finishes', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx:filter_error</code> — <?php esc_html_e( 'Fired on document with { detail: { error, instanceId } } if an AJAX filter request fails', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx-filters-updated</code> — <?php esc_html_e( 'Fired on window with { detail: { state } } when active filter parameters update across instances', 'pickory-product-filter' ); ?></li>
					<li><code>ppfx:destroy</code> — <?php esc_html_e( 'Custom event you can dispatch on window to programmatically destroy and clean up the mobile drawer instance', 'pickory-product-filter' ); ?></li>
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
				<h4><?php esc_html_e( 'Version 1.0.3', 'pickory-product-filter' ); ?> &mdash; <?php esc_html_e( 'Compatibility & Maintenance Release', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Verified compatibility with WordPress 7.1 and WooCommerce 11.2.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Updated plugin headers and WooCommerce compatibility declarations.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Updated REST API and Developer Reference with verified hooks, events, and response models.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Cleaned up documentation references and aligned admin index sync behavior with Action Scheduler.', 'pickory-product-filter' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'Version 1.0.2', 'pickory-product-filter' ); ?> &mdash; <?php esc_html_e( 'Maintenance & Standards Release', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'WordPress.org repository readiness and strict coding standards compliance.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Stripped UTF-8 BOM markers and normalized line endings across codebase.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Complete translation catalog (.pot) generation containing all 317 strings.', 'pickory-product-filter' ); ?></li>
					<li><?php esc_html_e( 'Sanitized URL filter parameters and isolated template loop variable scopes.', 'pickory-product-filter' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'Version 1.0.1', 'pickory-product-filter' ); ?> &mdash; <?php esc_html_e( 'Maintenance Release', 'pickory-product-filter' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Maintenance release.', 'pickory-product-filter' ); ?></li>
				</ul>

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
							<td><?php esc_html_e( 'HPOS compatible · Tested up to WooCommerce 11.2', 'pickory-product-filter' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Frontend Asset Size', 'pickory-product-filter' ); ?></strong></td>
							<td><?php esc_html_e( 'Less than 14 KB - May vary slightly in future updates', 'pickory-product-filter' ); ?></td>
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
					<?php esc_html_e( 'For bug reports or feature requests, please contact support at pickory.com.', 'pickory-product-filter' ); ?>
				</p>
				<?php endif; ?>
			</div>
		</details>
	<?php endforeach; ?>
	</div>

	<div class="ppfx-doc-sidebar">
		<div class="card ppfx-doc-sidebar-card">
			<h3>🤝 <?php esc_html_e( 'Need Human Assistance?', 'pickory-product-filter' ); ?></h3>
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
