<?php

if ( ! class_exists( 'EE' ) ) {
	return;
}

define( 'EE_CRON_SCHEDULER', 'ee-cron-scheduler' );

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

EE::add_command( 'cron', 'Cron_Command' );
