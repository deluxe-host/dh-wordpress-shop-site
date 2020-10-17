<?php
/**
 * @var object \WP_Offload_SES $this
 * @var bool $is_defined
 * @var bool $is_set
 * @var string $masked_licence
 */

use DeliciousBrains\WP_Offload_SES\Utils;

 $dynamic_classes = array(
	 $is_defined ? 'wposes-defined' : '',
	 $is_set ? 'wposes-saved-field' : '',
	 ( ! $is_defined && ! $is_set ) ? 'wposes-licence-not-entered' : '',
 );
?>

<section class="wposes-licence">
	<form class="wposes-licence-form" method="post">
		<h3><?php _e( 'Your License', 'wp-offload-ses' ); ?></h3>

		<?php if ( is_multisite() && ! Utils::is_network_admin() ) : ?>
 			<p><?php _e( 'Your license can be managed in the WP Offload SES network settings.', 'wp-offload-ses' ); ?></p>
		<?php else : ?>

		<div class="wposes-field-wrap wposes-licence-input-wrap <?php echo join( ' ', $dynamic_classes ); ?>">
			<input type="text" class="wposes-licence-input code"
				autocomplete="off"
				value="<?php echo esc_attr( $masked_licence ); ?>"
				<?php echo ( $is_defined || $is_set ) ? 'disabled' : '' ?>
			>
			<span class="wposes-defined-in-config"><?php _e( 'defined in wp-config.php', 'wp-offload-ses' ); ?></span>
			<button class="button button-primary wposes-activate-licence" data-wposes-licence-action="activate"><?php _e( 'Activate License', 'wp-offload-ses' ) ?></button>
			<button class="button button-secondary wposes-remove-licence" data-wposes-licence-action="remove"><?php _e( 'Remove', 'wp-offload-ses' ) ?></button>
			<span data-wposes-licence-spinner class="spinner" style="display: none;"></span>
		</div>

		<div data-wposes-licence-feedback class="notice inline" style="display:none;">
			<!-- filled by JS -->
		</div>

		<?php endif; ?>

	</form>
</section>
