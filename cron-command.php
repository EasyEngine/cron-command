<?php

if ( ! class_exists( 'EE' ) ) {
	return;
}

if ( ! defined( 'EE_CRON_SERVICE' ) ) {
	define( 'EE_CRON_SERVICE', 'global-cron' );
}

if ( ! defined( 'EE_CRON_SCHEDULER' ) ) {
	define( 'EE_CRON_SCHEDULER', 'services_global-cron_1' );
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

EE::add_command( 'cron', 'Cron_Command' );
