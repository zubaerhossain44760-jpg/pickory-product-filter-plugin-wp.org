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
 * Handles Gutenberg block registration and server rendering for the filter block.
 */
class PPFX_Block {

	/**
	 * Shortcode handler instance.
	 *
	 * @var PPFX_Shortcode|null
	 */
	private static $shortcode = null;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
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
	}

	/**
	 * Enqueue editor assets using the Vite asset loader.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! class_exists( 'PPFX_Assets' ) ) {
			return;
		}

		PPFX_Assets::get_instance()->enqueue_vite_asset(
			'src/js/admin/block-editor.js',
			'src/js/admin/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' )
		);
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		if ( ! class_exists( 'PPFX_Assets' ) || ! class_exists( 'PPFX_Shortcode' ) ) {
			return '';
		}

		PPFX_Assets::enqueue_frontend_assets();

		if ( null === self::$shortcode ) {
			self::$shortcode = new PPFX_Shortcode();
		}

		// Sanitize class names to prevent attribute breakout/XSS.
		$raw_class  = $attributes['className'] ?? '';
		$classes    = array_map( 'sanitize_html_class', explode( ' ', $raw_class ) );
		$safe_class = implode( ' ', array_filter( $classes ) );

		$content = self::$shortcode->render_shortcode( array( 'class' => $safe_class ) );

		// Wrap with standard Gutenberg block wrapper attributes.
		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes(),
			$content
		);
	}
}
