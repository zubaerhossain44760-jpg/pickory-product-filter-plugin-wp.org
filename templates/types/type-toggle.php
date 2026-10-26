<?php
/**
 * Type: Toggle Switch
 * iOS style sliding toggle for boolean filters like "Sale".
 *
 * @package Pickory_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The section heading is configurable, but the sale toggle itself has a fixed label.
$toggle_label = esc_html__( 'On Sale', 'pickory-product-filter' );
?>

<div class="ppfx-toggle-row">
	<span class="ppfx-toggle-label" id="ppfx-toggle-label-<?php echo esc_attr( $input_name ); ?>"><?php echo esc_html( $toggle_label ); ?></span>
	
	<div 
		class="ppfx-frontend-toggle ppfx-toggle-switch" 
		data-label="<?php echo esc_attr( $toggle_label ); ?>"
		role="switch" 
		aria-checked="false"
		aria-labelledby="ppfx-toggle-label-<?php echo esc_attr( $input_name ); ?>"
		tabindex="0"
	>
		<span class="ppfx-toggle-knob"></span>
	</div>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-toggle-input" value="0">
</div>
