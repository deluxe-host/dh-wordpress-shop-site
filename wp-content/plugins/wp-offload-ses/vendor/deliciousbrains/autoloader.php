<?php

$mapping = array(
	'DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API'          => __DIR__ . '/api.php',
	'DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Plugin'   => __DIR__ . '/plugin.php',
	'DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Base'     => __DIR__ . '/base.php',
	'DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Licences' => __DIR__ . '/licences.php',
	'DeliciousBrains\WP_Offload_SES\Licences\Delicious_Brains_API_Updates'  => __DIR__ . '/updates.php',
);

spl_autoload_register( function ( $class ) use ( $mapping ) {
	if ( isset( $mapping[ $class ] ) ) {
		require $mapping[ $class ];
	}
}, true );

