<?php
/**
 * Pickory Product Filter — Term Metadata Handler.
 *
 * Adds Color and Image fields to WooCommerce attribute terms.
 *
 * @package pickory-product-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles swatch color/image meta fields on attribute terms.
 */
class PPFX_Term_Meta {


	/**
	 * Register WordPress hooks for attribute term meta fields.
	 *
	 * @return void
	 */
	public function init() {
		// Only hook in the admin area.
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'register_term_hooks' ) );
	}

	/**
	 * Register term meta hooks after admin has initialized.
	 *
	 * @return void
	 */
	public function register_term_hooks() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = 'pa_' . $tax->attribute_name;

			// Add fields to "Add New Term" screen.
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'add_term_fields' ), 10 );

			// Add fields to "Edit Term" screen.
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'edit_term_fields' ), 10, 2 );

			// Save the data.
			add_action( "created_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
		}
	}

	/**
	 * Display fields on the "Add New" screen.
	 *
	 * @param string $taxonomy The current taxonomy slug (required by hook signature; not used directly).
	 * @return void
	 */
	public function add_term_fields( $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by hook.
		wp_nonce_field( 'ppfx_term_meta_nonce', 'ppfx_term_meta_nonce_field' );
		?>
		<div class="form-field ppfx-term-meta-wrap">
			<label for="ppfx_color"><?php esc_html_e( 'Swatch Color', 'pickory-product-filter' ); ?></label>
			<input type="text" name="ppfx_color" id="ppfx_color" class="ppfx-color-picker" value=""
					data-default-color="#ffffff"/>
			<p class="description"><?php esc_html_e( 'Choose a color for the swatch display.', 'pickory-product-filter' ); ?></p>
		</div>
		<div class="form-field ppfx-term-meta-wrap">
			<label for="ppfx_image"><?php esc_html_e( 'Swatch Image', 'pickory-product-filter' ); ?></label>
			<div class="ppfx-image-upload-wrapper">
				<div class="ppfx-image-preview" style="margin-bottom: 10px; display: none;">
					<img src="" style="max-width: 100px; height: auto; border: 1px solid #ddd;"/>
				</div>
				<input type="hidden" name="ppfx_image" id="ppfx_image" value=""/>
				<button type="button"
						class="button ppfx-image-upload-btn"><?php esc_html_e( 'Upload/Select Image', 'pickory-product-filter' ); ?></button>
				<button type="button" class="button ppfx-image-remove-btn"
						style="display: none;"><?php esc_html_e( 'Remove', 'pickory-product-filter' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Upload an image/pattern for the swatch. This takes priority over the color field in "Image Swatch" mode.', 'pickory-product-filter' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display fields on the "Edit" screen.
	 *
	 * @param WP_Term $term The current term object.
	 * @param string  $taxonomy The current taxonomy slug (required by hook signature; not used directly).
	 * @return void
	 */
	public function edit_term_fields( $term, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by hook.
		$color     = get_term_meta( $term->term_id, 'ppfx_color', true );
		$image_id  = get_term_meta( $term->term_id, 'ppfx_image', true );
		$image_url = $image_id ? wp_get_attachment_thumb_url( $image_id ) : '';
		wp_nonce_field( 'ppfx_term_meta_nonce', 'ppfx_term_meta_nonce_field' );
		?>
		<tr class="form-field ppfx-term-meta-wrap">
			<th scope="row"><label
						for="ppfx_color"><?php esc_html_e( 'Swatch Color', 'pickory-product-filter' ); ?></label></th>
			<td>
				<input type="text" name="ppfx_color" id="ppfx_color" class="ppfx-color-picker"
						value="<?php echo esc_attr( $color ); ?>" data-default-color="#ffffff"/>
				<p class="description"><?php esc_html_e( 'Choose a color for the swatch display.', 'pickory-product-filter' ); ?></p>
			</td>
		</tr>
		<tr class="form-field ppfx-term-meta-wrap">
			<th scope="row"><label
						for="ppfx_image"><?php esc_html_e( 'Swatch Image', 'pickory-product-filter' ); ?></label></th>
			<td>
				<div class="ppfx-image-upload-wrapper">
					<div class="ppfx-image-preview"
						style="margin-bottom: 10px; <?php echo $image_url ? '' : 'display: none;'; ?>">
						<img src="<?php echo esc_url( $image_url ); ?>"
							style="max-width: 100px; height: auto; border: 1px solid #ddd;"/>
					</div>
					<input type="hidden" name="ppfx_image" id="ppfx_image" value="<?php echo esc_attr( $image_id ); ?>"/>
					<button type="button"
							class="button ppfx-image-upload-btn"><?php esc_html_e( 'Upload/Select Image', 'pickory-product-filter' ); ?></button>
					<button type="button"
							class="button ppfx-image-remove-btn" <?php echo $image_url ? '' : 'style="display: none;"'; ?>><?php esc_html_e( 'Remove', 'pickory-product-filter' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Upload an image/pattern for the swatch. This takes priority over the color field in "Image Swatch" mode.', 'pickory-product-filter' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the term metadata.
	 *
	 * @param int $term_id The term ID being saved.
	 * @return void
	 */
	public function save_term_meta( $term_id ) {
		// Security check: verify nonce.
		if ( ! isset( $_POST['ppfx_term_meta_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppfx_term_meta_nonce_field'] ) ), 'ppfx_term_meta_nonce' ) ) {
			return;
		}

		// Capability check: verify user can edit this term.
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( isset( $_POST['ppfx_color'] ) ) {
			update_term_meta( $term_id, 'ppfx_color', sanitize_hex_color( wp_unslash( $_POST['ppfx_color'] ) ) );
		}

		if ( isset( $_POST['ppfx_image'] ) ) {
			update_term_meta( $term_id, 'ppfx_image', absint( wp_unslash( $_POST['ppfx_image'] ) ) );
		}
	}
}
