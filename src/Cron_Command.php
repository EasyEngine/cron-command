<?php

/**
 * Manages cron on easyengine.
 *
 * @package ee-cli
 */

class Cron_Command extends EE_Command {

	/**
	 * Runs cron container if it's not running
	 */
	public function __construct()
	{
		if ( 'running' !== EE_DOCKER::container_status( 'ee-cron-scheduler' ) ) {
			$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro mcuadros/ofelia:latest';
			if ( EE_DOCKER::boot_container( 'ee-cron-scheduler', $cron_scheduler_run_command) ) {
			} else {
				EE::error( "There was some error in starting ee-cron-scheduler container. Please check logs." );
			}
		}
	}

	/**
	 * Adds a cron job to run a command at specific interval etc.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of site to run cron on.
	 *
	 * --command=<command>
	 * : Command to schedule.
	 *
	 * --schedule=<schedule>
	 * : Time to schedule. Format is same as linux cron.
	 *   TODO: Add @every format
	 *
	 * ## EXAMPLES
	 *
	 *     # Adds a cron job to a site
	 *     $ ee cron add example.com --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 *     # Adds a cron job to host running EasyEngine
	 *     $ ee cron add host --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 */
	public function add( $args, $assoc_args ) {
		EE\Utils\delem_log( 'ee cron add start' );

		if( !isset($args[0]) || $args[0] !== 'host' ) {
			$args = EE\Utils\set_site_arg( $args, 'cron' );
		}

		$site = EE\Utils\remove_trailing_slash( $args[0] );
		$command   = $assoc_args['command'];
		$schedule  = $assoc_args['schedule'];

		// TODO: check if id exists before insert
		EE::db()->insert([
			'site' => $site,
			'command' => $command,
			'schedule' => $schedule
		], 'cron' );

		$this->update_cron_config();

		EE\Utils\delem_log( 'ee cron add end' );
	}


	/**
	 * Generates cron config from DB
	 */
	private function update_cron_config() {

		$config = $this->generate_cron_config();

		file_put_contents( EE_CONF_ROOT . '/cron/config.ini', $config );
		EE_DOCKER::restart_container( 'ee-cron-scheduler' );
	}

	/**
	 * Generates and returns cron config from DB
	 */
	private function generate_cron_config() {
		$config_template = file_get_contents(__DIR__ . '/../templates/config.ini.mustache' );
		$crons = EE::db()->select( [], [],'cron' );
		$crons = $crons === false ? [] : $crons ;
		foreach ( $crons as &$cron ) {
			$job_type = $cron['site'] === 'host' ? 'job-local' : 'job-exec' ;
			$cron['job_type'] = $job_type;

			if($cron['site'] !== 'host')
				$cron['container'] = $this->site_php_container($cron['site']);
		}

		$me = new Mustache_Engine();
		return $me->render( $config_template, $crons );
	}



	/**
	 * Deletes a cron job
	 *
	 * ## OPTIONS
	 *
	 * <cron-id>
	 * : ID of cron to be deleted.
	 *
	 * ## EXAMPLES
	 *
	 *     # Lists all scheduled cron jobs
	 *     $ ee cron delete 1
	 *		TODO: Add relatable ID
	 *
	 */
	public function delete( $args, $assoc_args ) {

		EE::db()->delete(['id' => $args[0]], 'cron' );
		$this->update_cron_config();
	}



	/**
	 * Returns php container name of a site
	 */
	private function site_php_container( $site ) {
		return str_replace('.','', $site ) . '_php_1';
	}
}
