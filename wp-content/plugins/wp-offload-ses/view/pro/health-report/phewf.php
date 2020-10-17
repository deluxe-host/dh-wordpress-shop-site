<table width="100%">
	<tr>
		<td>
			<h3 style="font-size: 18px; font-weight: normal;"><?php _e( 'Phewf, good thing you\'re using WP Offload SES! 🎉', 'wp-offload-ses' ); ?></h3>
		</td>
	</tr>
	<tr>
		<td>
			<?php
			printf(
				__( 'Most other email sending plugins would have just quietly dropped those %s email failures.', 'wp-offload-ses' ),
				number_format_i18n( $num_failed )
			);

			if ( $retried_automatically ) {
				echo '&nbsp;';
				_e( 'Not only has WP Offload SES informed you about all the failures, but it has retried them all automatically and successfully sent some of them.', 'wp-offload-ses' );
			}
			?>
		</td>
	</tr>
</table>