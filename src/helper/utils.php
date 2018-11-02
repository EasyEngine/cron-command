<?php

namespace EE\Cron\Utils;

use EE;
use EE\Model\Cron;

/**
 * Generates cron config from DB
 */
function update_cron_config() {

	$config = generate_cron_config();
	file_put_contents( EE_ROOT_DIR . '/services/cron/config.ini', $config );
	\EE_DOCKER::restart_container( EE_CRON_SCHEDULER );
}

/**
 * Generates and returns cron config from DB
 */
function generate_cron_config() {

	$config_template = file_get_contents( __DIR__ . '/../../templates/config.ini.mustache' );
	$crons           = Cron::all();

	foreach ( $crons as &$cron ) {
		$job_type       = 'host' === $cron->site_url ? 'job-local' : 'job-exec';
		$id             = $cron->site_url . '-' . preg_replace( '/[^a-zA-Z0-9\@]/', '-', $cron->command ) . '-' . EE\Utils\random_password( 5 );
		$id             = preg_replace( '/--+/', '-', $id );
		$cron->job_type = $job_type;
		$cron->id       = $id;

		if ( 'host' !== $cron->site_url ) {
			$cron->container = site_php_container( $cron->site_url );
		}
	}

	$me = new \Mustache_Engine();

	return $me->render( $config_template, $crons );
}

/**
 * Returns php container name of a site.
 *
 * @param string $site Name of the site whose container name is needed.
 *
 * @return string Container name.
 */
function site_php_container( $site ) {
	return str_replace( '.', '', $site ) . '_php_1';
}
