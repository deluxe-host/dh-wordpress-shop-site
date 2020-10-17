<?php if ( $retried_automatically ) : ?>
<p style="margin: 0;">
	<?php
	printf(
		_n(
			'%s email was retried automatically and sent successfully',
			'%s emails were retried automatically and sent successfully',
			$retried_automatically,
			'wp-offload-ses'
		),
		number_format_i18n( $retried_automatically )
	);
	?>
</p>
<?php endif; ?>

<?php if ( $retried_manually ) : ?>
<p style="margin: 0;">
	<?php
	printf(
		_n(
			'%s email was manually retried and sent successfully',
			'%s emails were manually retried and sent successfully',
			$retried_manually,
			'wp-offload-ses'
		),
		number_format_i18n( $retried_manually )
	);
	?>
</p>
<?php endif; ?>

<?php if ( $num_failed ) : ?>
<p style="margin: 0;">
	<?php
	printf(
		_n(
			'%s email failed after automatically retrying 3 times',
			'%s emails failed after automatically retrying 3 times',
			$num_failed,
			'wp-offload-ses'
		),
		number_format_i18n( $num_failed )
	);
	?>
</p>
<?php endif; ?>
