<?php

if ( ! class_exists( 'EE' ) ) {
	return;
}

use EE\Model\Cron;
use EE\Model\Site;

/**
 * Hook to cleanup cron entries if any.
 *
 * @param string $site_url The site to be cleaned up.
 */
function cleanup_cron_entries( $site_url ) {

	if ( ! Site::find( $site_url ) ) {
		return;
	}

	$cron_jobs = Cron::where( [ 'site_url' => $site_url ] );

	if ( ! empty( $cron_jobs ) ) {
		foreach ( $cron_jobs as $cron_job ) {
			$cron_job->delete();
		}
		regenerate_cron_config();
	}
}

/**
 * Regenerates cron config from DB.
 */
function regenerate_cron_config() {

	$config_template = file_get_contents( __DIR__ . '/../../templates/config.ini.mustache' );
	$crons           = Cron::all();

	if ( empty( $crons ) ) {
		EE::exec( 'docker rm -f ' . EE_CRON_SCHEDULER );
	}

	foreach ( $crons as &$cron ) {
		$job_type       = 'host' === $cron->site_url ? 'job-local' : 'job-exec';
		$id             = $cron->site_url . '-' . preg_replace( '/[^a-zA-Z0-9\@]/', '-', $cron->command ) . '-' . EE\Utils\random_password( 5 );
		$id             = preg_replace( '/--+/', '-', $id );
		$cron->job_type = $job_type;
		$cron->id       = $id;

		if ( 'host' !== $cron->site_url ) {
			$cron->container = str_replace( '.', '', $cron->site_url ) . '_php_1';
		}
	}

	$me     = new Mustache_Engine();
	$config = $me->render( $config_template, $crons );

	file_put_contents( EE_ROOT_DIR . '/services/cron/config.ini', $config );
	EE_DOCKER::restart_container( EE_CRON_SCHEDULER );
}

EE::add_hook( 'site_cleanup', 'cleanup_cron_entries' );
