<?php
/**
 * Gutenberg block registration for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the pickory/product-filter Gutenberg block.
 */
class PPFX_Block {


	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the product filter block type.
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'pickory/product-filter',
			array(
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'className' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'editor_script'   => 'ppfx-block-editor',
			)
		);

		// Register the editor script so WP can find it for the block.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Enqueue the block editor script from the Vite manifest.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$manifest_path = PPFX_PLUGIN_DIR . 'build/.vite/manifest.json';
		if ( file_exists( $manifest_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local manifest file, not a remote URL.
			$manifest = json_decode( file_get_contents( $manifest_path ), true );
			if ( isset( $manifest['src/js/block-editor.js'] ) ) {
				$js_file = $manifest['src/js/block-editor.js']['file'];
				wp_enqueue_script(
					'ppfx-block-editor',
					PPFX_PLUGIN_URL . 'build/' . $js_file,
					array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
					PPFX_VERSION,
					true
				);
			}
		}
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$class     = $attributes['className'] ?? '';
		$shortcode = new PPFX_Shortcode();
		return $shortcode->render_shortcode( array( 'class' => $class ) );
	}
}
