<?php

/**
 * Type: Toggle Switch
 * iOS style sliding toggle for boolean filters like "Sale".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ppfx-toggle-row">
	<span class="ppfx-toggle-label"><?php echo esc_html( $label ); ?></span>
	
	<div 
		class="ppfx-frontend-toggle ppfx-toggle-switch" 
		data-label="<?php echo esc_attr( $label ); ?>"
		role="switch" 
		aria-checked="false"
		tabindex="0"
	>
		<span class="ppfx-toggle-knob"></span>
	</div>
	
	<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" class="ppfx-toggle-input" value="0">
</div>
