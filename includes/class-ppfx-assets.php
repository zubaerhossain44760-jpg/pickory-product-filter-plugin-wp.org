<?php
/**
 * Asset hooks and compatibility façade for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once PPFX_PLUGIN_DIR . 'includes/assets/class-ppfx-manifest.php';
require_once PPFX_PLUGIN_DIR . 'includes/assets/class-ppfx-asset-loader.php';
require_once PPFX_PLUGIN_DIR . 'includes/frontend/class-ppfx-loop-markup.php';

/**
 * Coordinates asset hooks while preserving the plugin's existing public API.
 */
class PPFX_Assets {

	/**
	 * Current asset coordinator instance.
	 *
	 * @var PPFX_Assets|null
	 */
	private static $instance = null;

	/**
	 * Whether frontend assets have been enqueued.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Vite asset loader.
	 *
	 * @var PPFX_Asset_Loader|null
	 */
	private $asset_loader = null;

	/**
	 * Frontend loop markup builder.
	 *
	 * @var PPFX_Loop_Markup|null
	 */
	private $loop_markup = null;

	/**
	 * Get the current asset coordinator.
	 *
	 * @return PPFX_Assets
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Explicitly enqueue frontend assets on demand.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		self::$assets_enqueued = true;
		self::get_instance()->do_enqueue_frontend_assets();

		if ( class_exists( 'PPFX_Dynamic_CSS' ) ) {
			PPFX_Dynamic_CSS::output_dynamic_css( true );
		}
	}

	/**
	 * Push a filter instance ID onto the active render stack.
	 *
	 * @param string $instance_id Filter instance ID.
	 * @return void
	 */
	public static function push_instance_id( string $instance_id ): void {
		PPFX_Loop_Markup::push_instance_id( $instance_id );
	}

	/**
	 * Pop a filter instance ID from the active render stack.
	 *
	 * @return string|null
	 */
	public static function pop_instance_id(): ?string {
		return PPFX_Loop_Markup::pop_instance_id();
	}

	/**
	 * Get the current filter instance ID.
	 *
	 * @return string|null
	 */
	public static function get_current_instance_id(): ?string {
		return PPFX_Loop_Markup::get_current_instance_id();
	}

	/**
	 * Compatibility proxy for callers that access the Vite manifest directly.
	 *
	 * @return array|null
	 */
	public static function get_manifest(): ?array {
		return PPFX_Manifest::get();
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		self::$instance = $this;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_scripts' ), 10, 3 );
	}

	/**
	 * Add type="module" to Vite bundles.
	 *
	 * @param string $tag Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src Script URL.
	 * @return string
	 */
	public function add_module_type_to_scripts( $tag, $handle, $src ): string {
		return $this->asset_loader()->add_module_type_to_scripts( $tag, $handle, $src );
	}

	/**
	 * Enqueue frontend scripts and styles when the current request needs them.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( self::should_load_frontend_assets() ) {
			self::enqueue_frontend_assets();
		}
	}

	/**
	 * Enqueue admin assets for settings and attribute term screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$this->asset_loader()->enqueue_admin_assets( $hook );
	}

	/**
	 * Enqueue a Vite asset. Retained as the public API used by this coordinator.
	 *
	 * @param string $entry_key Vite manifest entry key.
	 * @param string $entry_path Source path relative to the project root.
	 * @param array  $deps Script/style dependencies.
	 * @param string $media Media type for stylesheets.
	 * @return string
	 */
	public function enqueue_vite_asset( $entry_key, $entry_path, $deps = array(), $media = 'all' ) {
		return $this->asset_loader()->enqueue_vite_asset( $entry_key, $entry_path, $deps, $media );
	}

	/**
	 * Wrap the WooCommerce product-loop opening markup.
	 *
	 * @param string $html Original loop opening HTML.
	 * @return string
	 */
	public function wrap_product_loop_start( $html ) {
		return $this->loop_markup()->wrap_product_loop_start( $html, self::get_current_instance_id() );
	}

	/**
	 * Wrap the WooCommerce product-loop closing markup.
	 *
	 * @param string $html Original loop closing HTML.
	 * @return string
	 */
	public function wrap_product_loop_end( $html ) {
		return $this->loop_markup()->wrap_product_loop_end( $html );
	}

	/**
	 * Output the opening wrapper for a no-results product loop.
	 *
	 * @return void
	 */
	public function open_products_wrapper() {
		$this->loop_markup()->open_products_wrapper( self::get_current_instance_id() );
	}

	/**
	 * Output the closing wrapper for a no-results product loop.
	 *
	 * @return void
	 */
	public function close_products_wrapper() {
		$this->loop_markup()->close_products_wrapper();
	}

	/**
	 * Output the opening wrapper for before-loop markup.
	 *
	 * @return void
	 */
	public function open_loop_toolbar_wrapper() {
		$this->loop_markup()->open_loop_toolbar_wrapper();
	}

	/**
	 * Output the closing wrapper for before-loop markup.
	 *
	 * @return void
	 */
	public function close_loop_toolbar_wrapper() {
		$this->loop_markup()->close_loop_toolbar_wrapper();
	}

	/**
	 * Output the mobile-toggle mount point.
	 *
	 * @return void
	 */
	public function output_mobile_toggle_slot() {
		$this->loop_markup()->output_mobile_toggle_slot();
	}

	/**
	 * Output the detached active-filter chips container.
	 *
	 * @return void
	 */
	public function output_detached_chips_container() {
		$this->loop_markup()->output_detached_chips_container();
	}

	/**
	 * Generate the URL-filter hash used for server-rendered markup.
	 *
	 * @return string
	 */
	public function generate_filter_hash() {
		return $this->loop_markup()->generate_filter_hash();
	}

	/**
	 * Output critical first-paint frontend CSS and JavaScript.
	 *
	 * @return void
	 */
	public function output_critical_loading_css() {
		$this->loop_markup()->output_critical_loading_css(
			self::should_load_frontend_assets(),
		);
	}

	/**
	 * Check whether frontend assets should load on the current request.
	 *
	 * @return bool
	 */
	public static function should_load_frontend_assets(): bool {
		static $should_load = null;

		if ( null !== $should_load ) {
			return $should_load;
		}

		// Single product detail pages do not load catalog filters unless the description explicitly uses a shortcode or block.
		if ( function_exists( 'is_product' ) && is_product() ) {
			$post         = get_post();
			$post_content = $post ? $post->post_content : '';
			$should_load  = has_shortcode( $post_content, 'pickory_product_filter' ) ||
				has_shortcode( $post_content, 'ppfx_filter' ) ||
				has_block( 'pickory/product-filter', $post_content );
			return $should_load;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$should_load = true;
			return $should_load;
		}

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$should_load = true;
			return $should_load;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$should_load = true;
			return $should_load;
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			$should_load = true;
			return $should_load;
		}

		if ( is_tax( get_object_taxonomies( 'product' ) ) ) {
			$should_load = true;
			return $should_load;
		}

		if ( is_search() && 'product' === get_query_var( 'post_type' ) ) {
			$should_load = true;
			return $should_load;
		}

		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			$should_load = true;
			return $should_load;
		}

		$post         = get_post();
		$post_content = $post ? $post->post_content : '';
		$should_load  = has_shortcode( $post_content, 'pickory_product_filter' ) ||
			has_shortcode( $post_content, 'ppfx_filter' ) ||
			has_block( 'pickory/product-filter', $post_content );

		return $should_load;
	}

	/**
	 * Delegate frontend asset enqueueing to the loader.
	 *
	 * @return void
	 */
	private function do_enqueue_frontend_assets(): void {
		$this->asset_loader()->enqueue_frontend_assets();
	}

	/**
	 * Get the Vite asset loader.
	 *
	 * @return PPFX_Asset_Loader
	 */
	private function asset_loader(): PPFX_Asset_Loader {
		if ( null === $this->asset_loader ) {
			$this->asset_loader = new PPFX_Asset_Loader();
		}

		return $this->asset_loader;
	}

	/**
	 * Get the frontend loop markup builder.
	 *
	 * @return PPFX_Loop_Markup
	 */
	private function loop_markup(): PPFX_Loop_Markup {
		if ( null === $this->loop_markup ) {
			$this->loop_markup = new PPFX_Loop_Markup();
		}

		return $this->loop_markup;
	}
}
