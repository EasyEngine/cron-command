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
	public function __construct() {
		if ( 'running' !== EE_DOCKER::container_status( 'ee-cron-scheduler' ) ) {
			$cron_scheduler_run_command = 'docker run --name ee-cron-scheduler --restart=always -d -v ' . EE_CONF_ROOT . '/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro easyengine/cron:v' . EE_VERSION;
			if ( ! EE_DOCKER::boot_container( 'ee-cron-scheduler', $cron_scheduler_run_command ) ) {
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
	 * : Time to schedule. Format is same as Linux cron.
	 *
	 * We also have helper to easily specify scheduling format:
	 *
	 *  Entry                  | Description                                | Equivalent To
	 *  -----                  | -----------                                | -------------
	 *  @yearly (or @annually) | Run once a year, midnight, Jan. 1st        | 0 0 1 1 *
	 *  @monthly               | Run once a month, midnight, first of month | 0 0 1 * *
	 *  @weekly                | Run once a week, midnight between Sat/Sun  | 0 0 * * 0
	 *  @daily (or @midnight)  | Run once a day, midnight                   | 0 0 * * *
	 *  @hourly                | Run once an hour, beginning of hour        | 0 * * * *
	 *
	 * You may also schedule a job to execute at fixed intervals, starting at the time it's added or cron is run.
	 * This is supported by following format:
	 *
	 * @every <duration>
	 *
	 * Where duration can be combination of:
	 *    <number>h  - hour
	 *    <number>m  - minute
	 *    <number>s  - second
	 *
	 *    So 1h10m2s is also a valid duration
	 *
	 * ## EXAMPLES
	 *
	 *     # Adds a cron job on example.com every 10 minutes
	 *     $ ee cron add example.com --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 *     # Adds a cron job on example.com every 1 minutes
	 *     $ ee cron add example.com --command='wp cron event run --due-now' --schedule='* * * * *'
	 *
	 *     # Adds a cron job to host running EasyEngine
	 *     $ ee cron add host --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 *     # Adds a cron job to host running EasyEngine
	 *     $ ee cron add host --command='wp media regenerate --yes' --schedule='@weekly'
	 *
	 */
	public function add( $args, $assoc_args ) {
		EE\Utils\delem_log( 'ee cron add start' );

		if ( ! isset( $args[0] ) || $args[0] !== 'host' ) {
			$args = EE\Utils\set_site_arg( $args, 'cron' );
		}

		$site     = EE\Utils\remove_trailing_slash( $args[0] );
		$command  = EE\Utils\get_flag_value( $assoc_args, 'command' );
		$schedule = EE\Utils\get_flag_value( $assoc_args, 'schedule' );

		if ( '@' !== substr( trim( $schedule ), 0, 1 ) ) {
			$schedule_length = strlen( explode( ' ', trim( $schedule ) ) );
			if ( $schedule_length <= 5 ) {
				$schedule = '0 ' . trim( $schedule );
			}
		}

		$this->semi_colon_check( $command );
		$command = $this->add_sh_c_wrapper( $command );

		EE::db()->insert(
			[
				'sitename' => $site,
				'command'  => $command,
				'schedule' => $schedule
			], 'cron'
		);

		$this->update_cron_config();

		EE\Utils\delem_log( 'ee cron add end' );
	}

	/**
	 * Updates a cron job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of cron to update.
	 *
	 * [--site=<site>]
	 * : Command to schedule.
	 *
	 * [--command=<command>]
	 * : Command to schedule.
	 *
	 * [--schedule=<schedule>]
	 * : Time to schedule. Format is same as Linux cron.
	 *
	 * We also have helper to easily specify scheduling format:
	 *
	 *  Entry                  | Description                                | Equivalent To
	 *  -----                  | -----------                                | -------------
	 * @yearly (or @annually) | Run once a year, midnight, Jan. 1st        | 0 0 1 1 *
	 * @monthly               | Run once a month, midnight, first of month | 0 0 1 * *
	 * @weekly                | Run once a week, midnight between Sat/Sun  | 0 0 * * 0
	 * @daily (or @midnight)  | Run once a day, midnight                   | 0 0 * * *
	 * @hourly                | Run once an hour, beginning of hour        | 0 * * * *
	 *
	 * You may also schedule a job to execute at fixed intervals, starting at the time it's added or cron is run.
	 * This is supported by following format:
	 *
	 * @every <duration>
	 *
	 * Where duration can be combination of:
	 *    <number>h  - hour
	 *    <number>m  - minute
	 *    <number>s  - second
	 *
	 *    So 1h10m2s is also a valid duration
	 *
	 * ## EXAMPLES
	 *
	 *     # Updates site to run cron on
	 *     $ ee cron update 1 --site='example1.com'
	 *
	 *     # Updates command of cron
	 *     $ ee cron update 1 --command='wp cron event run --due-now'
	 *
	 *     # Updates schedule of cron
	 *     $ ee cron update 1 --schedule='@every 1m'
	 *
	 */
	public function update( $args, $assoc_args ) {
		EE\Utils\delem_log( 'ee cron add start' );

		$data_to_update = [];
		$site           = EE\Utils\get_flag_value( $assoc_args, 'site' );
		$command        = EE\Utils\get_flag_value( $assoc_args, 'command' );
		$schedule       = EE\Utils\get_flag_value( $assoc_args, 'schedule' );

		if ( ! $site && ! $command && ! $schedule ) {
			EE::error( 'You should specify atleast one of - site, command or schedule to update' );
		}
		if ( $site ) {
			$data_to_update['sitename'] = $site;
		}
		if ( $command ) {
			$this->semi_colon_check( $command );
			$command                   = $this->add_sh_c_wrapper( $command );
			$data_to_update['command'] = $command;
		}
		if ( $schedule ) {
			if ( '@' !== substr( trim( $schedule ), 0, 1 ) ) {
				$schedule_length = strlen( explode( ' ', trim( $schedule ) ) );
				if ( $schedule_length <= 5 ) {
					$schedule = '0 ' . trim( $schedule );
				}
			}
			$data_to_update['schedule'] = $schedule;
		}


		EE::db()->update( $data_to_update, [ 'id' => $args[0] ], 'cron' );

		$this->update_cron_config();

		EE\Utils\delem_log( 'ee cron add end' );
	}

	/**
	 * Lists scheduled cron jobs.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of site whose cron will be displayed.
	 *
	 * [--all]
	 * : View all cron jobs.
	 *
	 * ## EXAMPLES
	 *
	 *     # Lists all scheduled cron jobs
	 *     $ ee cron list
	 *
	 *     # Lists all scheduled cron jobs of a site
	 *     $ ee cron list example.com
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {
		$where = [];
		$all   = EE\Utils\get_flag_value( $assoc_args, 'all' );

		if ( ( ! isset( $args[0] ) || $args[0] !== 'host' ) && ! $all ) {
			$args = EE\Utils\set_site_arg( $args, 'cron' );
		}

		if ( isset( $args[0] ) ) {
			$where = [ 'sitename' => $args[0] ];
		}

		$crons = EE::db()->select( [], $where, 'cron' );

		if ( false === $crons ) {
			EE::error( 'No cron jobs found.' );
		}

		EE\Utils\format_items( 'table', $crons, [ 'id', 'sitename', 'command', 'schedule' ] );
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
		$config_template = file_get_contents( __DIR__ . '/../templates/config.ini.mustache' );
		$crons           = EE::db()->select( [], [], 'cron' );
		$crons           = $crons === false ? [] : $crons;
		foreach ( $crons as &$cron ) {
			$job_type         = $cron['sitename'] === 'host' ? 'job-local' : 'job-exec';
			$id               = $cron['sitename'] . '-' . preg_replace( '/[^a-zA-Z0-9\@]/', '-', $cron['command'] ) . '-' . EE\Utils\random_password( 5 );
			$id               = preg_replace( '/--+/', '-', $id );
			$cron['job_type'] = $job_type;
			$cron['id']       = $id;

			if ( $cron['sitename'] !== 'host' ) {
				$cron['container'] = $this->site_php_container( $cron['sitename'] );
			}
		}

		$me = new Mustache_Engine();

		return $me->render( $config_template, $crons );
	}

	/**
	 * Runs a cron job
	 *
	 * ## OPTIONS
	 *
	 * <cron-id>
	 * : ID of cron to run.
	 *
	 * ## EXAMPLES
	 *
	 *     # Runs a cron job
	 *     $ ee cron run-now 1
	 *
	 * @subcommand run-now
	 */
	public function run_now( $args ) {
		$result = EE::db()->select( [ 'sitename', 'command' ], [ 'id' => $args[0] ], 'cron' );
		if ( empty( $result ) ) {
			EE::error( 'No such cron with id ' . $args[0] );
		}
		$container = $this->site_php_container( $result[0]['sitename'] );
		$command   = $result[0]['command'];
		\EE\Utils\default_launch( "docker exec $container $command", true, true );
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
	 *     # Deletes a cron jobs
	 *     $ ee cron delete 1
	 *
	 */
	public function delete( $args ) {

		$id = $args[0];

		if ( ! EE::db()->select( [ 'id' ], [ 'id' => $id ], 'cron' ) ) {
			EE::error( 'Unable to find cron with id ' . $id );
		}

		EE::db()->delete( [ 'id' => $id ], 'cron' );
		$this->update_cron_config();

		EE::success( 'Deleted cron with id ' . $id );
	}


	/**
	 * Returns php container name of a site
	 */
	private function site_php_container( $site ) {
		return str_replace( '.', '', $site ) . '_php_1';
	}

	private function semi_colon_check( $command ) {
		// Semicolons in commands do not work for now due to limitation of INI style config ofelia uses
		// See https://github.com/EasyEngine/cron-command/issues/4
		if ( strpos( $command, ';' ) !== false ) {
			EE::error( 'Command chaining using `;` - semi-colon is not supported currently. You can either use `&&` or `||` or creating a second cron job for the chained command.' );
		}
	}

	private function add_sh_c_wrapper( $command ) {
		if ( strpos( $command, 'sh -c' ) !== false ) {
			return $command;
		}

		return "sh -c '" . $command . "'";
	}
}
