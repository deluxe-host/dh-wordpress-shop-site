<section class="wposes-licence-info support support-section">
	<h3 class="wposes-section-heading"><?php _e( 'Email Support', 'wp-offload-ses' ); ?></h3>

	<?php /* Must use "support-content" class as required by markup in API response. */ ?>
	<div class="support-content">
		<?php if ( ! empty( $licence ) ) : ?>
			<p>
				<?php _e( 'Fetching support form for your license, please wait...', 'wp-offload-ses' ); ?>
				<span data-wposes-licence-spinner class="spinner"></span>
			</p>
		<?php else : ?>
			<p>
				<?php _e( 'We couldn\'t find your license information.', 'wp-offload-ses' ); ?>
				<a href="#licence">
					<?php _e( 'Please enter a valid license key.', 'wp-offload-ses' ); ?>
				</a>
			</p>
			<p><?php _e( 'Once entered, you can view your support details.', 'wp-offload-ses' ); ?></p>
		<?php endif; ?>
	</div>
</section>