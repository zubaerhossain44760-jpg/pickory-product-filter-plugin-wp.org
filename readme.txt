=== Pickory Product Filter ===
Contributors: zubaerhossain
Tags: woocommerce filter, ajax filter, product filter, faceted search, product search
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, AJAX-powered WooCommerce product filter with flat product index, multi-currency support, and HPOS compatibility.

== Description ==

Pickory Product Filter is a WooCommerce filtering solution designed for performance, scalability, and flexibility. Pickory uses a **flat product index** for optimized filtering even on stores with 10,000+ products.

### Why Pickory Product Filter?

Pickory uses a denormalized index table that stores the fields most commonly used for filtering in a single, optimized database table.

### Key Features

**Performance & Scalability:**
- Flat product index for fast filtering (no expensive postmeta scans)
- Object cache integration (Redis/Memcached support)
- Query response caching for logged-out users
- REST API-based filtering with abortable requests
- Debounced inputs for price slider and search

**Filter Types:**
- Price range slider (dual-thumb)
- Star rating filter
- On Sale toggle
- Category filter (checkboxes, radio, dropdown, button grid, search list)
- Product tags
- WooCommerce product attributes (color, size, brand, etc.)
- Custom taxonomies
- Multiple display types: checkboxes, radio buttons, dropdowns, swatches (color/image), button grids, toggles, search lists

**User Experience:**
- AJAX-powered filtering (no page reload)
- URL-addressable filters (shareable, bookmarkable)
- Browser back/forward button support
- Active filter chips with one-click removal
- Multiple filter instances on same page (sidebar + mobile drawer)
- Mobile-responsive drawer with smooth animations

**Developer Features:**
- REST API with comprehensive endpoint
- Shortcode: `[pickory_product_filter]`
- Gutenberg block support
- WordPress hooks for customization
- Template override system
- ES6 modular JavaScript architecture

**Enterprise Compatibility:**
- HPOS (High-Performance Order Storage) compatible
- Multi-currency support
- WooCommerce 7.0+ compatibility
- PHP 8.0+ optimized
- Multisite compatible
- Caching plugin friendly

### Architecture

Pickory uses a hybrid SSR + CSR architecture:

1. **Initial Page Load:** Server renders filtered products based on URL parameters
2. **AJAX Filtering:** Client sends REST request, receives HTML fragments, updates DOM
3. **URL Sync:** Browser URL updates with filter parameters (no reload)
4. **State Management:** Global state object keeps filters, pagination, and sorting in sync

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Pickory Product Filter"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/pickory-product-filter` directory
3. Activate through the 'Plugins' menu in WordPress

= Post-Installation Setup =

1. **Build the Index:** Go to Pickory Product Filter > Advanced tab and click "Rebuild Product Index"
2. **Configure Filters:** Enable/disable filter sections and choose display types
3. **Add to Shop Page:** Use the Gutenberg block or shortcode `[pickory_product_filter]`
4. **Test Filtering:** Visit your shop page and test filtering

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes, Pickory Product Filter requires WooCommerce 7.0 or higher.

= Will it work with my theme? =

Pickory is designed to work with any WooCommerce-compatible theme. It uses WooCommerce's native template functions to ensure compatibility with your theme's product grid layout.

If you experience styling issues, you can customize the CSS using the `.ppfx-filter-container` class.

= How does the index table improve performance? =

Pickory's index table stores price, rating, sale status, and stock status in a single row per product with proper database indexes. This provides optimized query performance on large catalogs.

= Does it support variable products? =

Yes. For variable products, the index stores the min/max price range across all variations.

= Can I use multiple filters on the same page? =

Yes. Use the shortcode with different `instance_id` parameters:

`[pickory_product_filter instance_id="sidebar"]`
`[pickory_product_filter instance_id="mobile-drawer"]`

= How do I rebuild the index? =

Navigate to Pickory Product Filter > Advanced tab and click "Rebuild Product Index". Recommended after bulk product imports or price updates.

= Does it work with caching plugins? =

Yes. Compatible with WP Rocket, W3 Total Cache, LiteSpeed Cache, and Cloudflare. Exclude `/wp-json/pickory-filter/v1/*` from page caching.

= Can I customize the filter appearance? =

Yes. Override templates by copying files to `yourtheme/pickory-product-filter/` or add custom CSS targeting `.ppfx-filter-container`.

= Does it support hierarchical categories? =

Yes. Selecting a parent category automatically includes all child categories.

= How does it handle out-of-stock products? =

Pickory respects your WooCommerce inventory settings. If "Hide out of stock items" is enabled in WooCommerce settings, out-of-stock products are excluded from filter results.

= Is there a rate limit on filter requests? =

Yes. The plugin includes built-in rate limiting (80 requests per minute per IP) to prevent abuse. You can enable/disable this in the Advanced tab.

= What happens on plugin deactivation? =

Deactivating the plugin unschedules the nightly index sync but does NOT delete custom tables or settings.

= What happens on plugin uninstall? =

Uninstalling deletes custom tables, plugin options, transients, and scheduled cron jobs.

== Screenshots ==

1. Filter sections settings page in WordPress admin.
2. Frontend product filter on WooCommerce shop page.
3. Mobile drawer filter view on small screens.

== Changelog ==

= 1.0.0 =
* Initial release
* Flat product index for fast filtering
* REST API-based AJAX filtering
* Multiple filter display types (checkbox, radio, dropdown, swatch, slider, toggle)
* Price range slider with dual thumbs
* Star rating filter
* On Sale toggle
* Category, product tag, and attribute filters
* URL-addressable filters with browser history support
* Active filter chips with one-click removal
* Mobile-responsive drawer
* Multi-currency support
* HPOS compatibility
* Shortcode and Gutenberg block support
* Template override system

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developer Documentation ==

= Shortcode Usage =

`[pickory_product_filter]` — Default filter instance
`[pickory_product_filter instance_id="custom-id" class="custom-class"]` — Custom instance

= REST API Endpoint =

`GET /wp-json/pickory-filter/v1/products`

Parameters:
- `page` (int) — Current page
- `per_page` (int) — Products per page (max: 100)
- `category` (string) — Category slugs, comma-separated
- `product_tag` (string) — Tag slugs, comma-separated
- `min_price` (float) — Minimum price
- `max_price` (float) — Maximum price
- `rating` (float) — Minimum rating (1-5)
- `on_sale` (string) — "1" for on sale only
- `filter_{attribute}` (string) — Attribute slugs, comma-separated
- `orderby` (string) — Sort field
- `order` (string) — ASC or DESC
- `instance_id` (string) — Filter instance ID

= WordPress Hooks =

**Filters:**
- `ppfx_rest_max_per_page` — Maximum products per REST request (default: 100)
- `ppfx_force_db_rate_limiting` — Force rate limiting without object cache

**Actions:**
- `ppfx_before_query_build` — Before building WP_Query args
- `ppfx_after_query_build` — After building WP_Query args
- `ppfx_index_sync_product` — When syncing product to index

= Template Override =

Copy files from `pickory-product-filter/templates/` to `yourtheme/pickory-product-filter/`.

= CSS Classes =

- `.ppfx-filter-container` — Main filter wrapper
- `.ppfx-filter-form` — Filter form element
- `.ppfx-products-wrapper` — Product grid wrapper
- `.ppfx-active-filters` — Active filter chips container
- `.ppfx-mobile-toggle` — Mobile drawer toggle button

== Privacy & Security ==

Pickory Product Filter does not collect personal data. It stores product index data and plugin settings only. No data is transmitted to third parties.

== License ==

This plugin is licensed under GPL-2.0-or-later.
Copyright (c) Pickory
