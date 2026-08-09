<?php
/**
 * Admin controller for Pickory Product Filter.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once PPFX_PLUGIN_DIR . 'includes/admin/class-ppfx-settings-page.php';

/**
 * Handles admin menu pages, settings registration, and admin-side logic.
 */
class PPFX_Admin {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_post_ppfx_rebuild_index', array( $this, 'handle_rebuild_index' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @return void
	 */
	public function add_plugin_page(): void {
		add_menu_page(
			__( 'Pickory Product Filter', 'pickory-product-filter' ),
			__( 'Product Filter', 'pickory-product-filter' ),
			'manage_options',
			'pickory-product-filter',
			array( $this, 'create_admin_page' ),
			'dashicons-filter',
			56
		);
	}

	/**
	 * Render the main admin page.
	 *
	 * @return void
	 */
	public function create_admin_page(): void {
		PPFX_Settings_Page::render_page();
	}

	/**
	 * Handle the product index rebuild admin POST action.
	 *
	 * @return void
	 */
	public function handle_rebuild_index(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pickory-product-filter' ), 403 );
		}

		if ( ! isset( $_POST['ppfx_rebuild_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppfx_rebuild_nonce'] ) ), 'ppfx_rebuild_index_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'pickory-product-filter' ), 403 );
		}

		$redirect_base = admin_url( 'admin.php?page=pickory-product-filter' );

		try {
			PPFX_Index::create_table();
			PPFX_Index::create_term_table();
			PPFX_Index::bulk_sync();
			wp_safe_redirect( add_query_arg( 'ppfx_index_rebuilt', '1', $redirect_base ) );
		} catch ( Throwable $e ) {
			wp_safe_redirect( add_query_arg( 'ppfx_index_error', '1', $redirect_base ) );
		}

		exit;
	}

	/**
	 * Display admin notices on the plugin page.
	 *
	 * @return void
	 */
	public function show_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id, 'pickory-product-filter' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading query var after redirect, no state change.
		if ( isset( $_GET['ppfx_index_rebuilt'] ) && '1' === $_GET['ppfx_index_rebuilt'] ) {
			echo wp_kses_post(
				'<div class="notice notice-success is-dismissible"><p><strong>' .
				esc_html__( 'Product index rebuilt successfully!', 'pickory-product-filter' ) .
				'</strong> ' .
				esc_html__( 'All published products have been synced into the filter index. Your frontend filters should now return results correctly.', 'pickory-product-filter' ) .
				'</p></div>'
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading query var after redirect, no state change.
		if ( isset( $_GET['ppfx_index_error'] ) && '1' === $_GET['ppfx_index_error'] ) {
			echo wp_kses_post(
				'<div class="notice notice-error is-dismissible"><p><strong>' .
				esc_html__( 'Product index rebuild failed.', 'pickory-product-filter' ) .
				'</strong> ' .
				esc_html__( 'An unexpected error occurred. Please check your PHP error log for details.', 'pickory-product-filter' ) .
				'</p></div>'
			);
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function page_init(): void {
		register_setting(
			'ppfx_option_group',
			'ppfx_settings',
			array( 'PPFX_Settings_Page', 'sanitize' )
		);
	}
}
