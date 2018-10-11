
<?php

use EE\Model\Cron;
use function EE\Site\Utils\auto_site_name;

/**
 * Manages cron on easyengine sites and host machine.
 *
 * @package ee-cli
 */
class Cron_Command extends EE_Command {

	/**
	 * Runs cron container if it's not running
	 */
	public function __construct() {

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
	 * [--user=<user>]
	 * : User to execute command as.
	 *
	 * We also have helper to easily specify scheduling format:
	 *
	 * | Entry                  | Description                                | Equivalent To
	 * | -----                  | -----------                                | -------------
	 * | @yearly (or @annually) | Run once a year, midnight, Jan. 1st        | 0 0 1 1 *
	 * | @monthly               | Run once a month, midnight, first of month | 0 0 1 * *
	 * | @weekly                | Run once a week, midnight between Sat/Sun  | 0 0 * * 0
	 * | @daily (or @midnight)  | Run once a day, midnight                   | 0 0 * * *
	 * | @hourly                | Run once an hour, beginning of hour        | 0 * * * *
	 *
	 * You may also schedule a job to execute at fixed intervals, starting at the time it's added or cron is run.
	 * This is supported by following format:
	 *
	 * - @every <duration>
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
	 *     $ ee cron create example.com --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 *     # Adds a cron job on example.com every 1 minutes
	 *     $ ee cron create example.com --command='wp cron event run --due-now' --schedule='* * * * *'
	 *
	 *     # Adds a cron job on example.com every 1 minutes run as user www-data
	 *     $ ee cron create example.com --command='wp cron event run --due-now' --schedule='* * * * *' --user=www-data
	 *
	 *     # Adds a cron job to host running EasyEngine
	 *     $ ee cron create host --command='wp cron event run --due-now' --schedule='@every 10m'
	 *
	 *     # Adds a cron job to host running EasyEngine
	 *     $ ee cron create host --command='wp media regenerate --yes' --schedule='@weekly'
	 */
	public function create( $args, $assoc_args ) {

		if ( 'running' !== EE_DOCKER::container_status( EE_CRON_SCHEDULER ) ) {
			$cron_scheduler_run_command = 'docker run --name ' . EE_CRON_SCHEDULER . ' --restart=always -d -v ' . EE_ROOT_DIR . '/services/cron:/etc/ofelia:ro -v /var/run/docker.sock:/var/run/docker.sock:ro easyengine/cron:v' . EE_VERSION;
			if ( ! EE_DOCKER::boot_container( EE_CRON_SCHEDULER, $cron_scheduler_run_command ) ) {
				EE::error( 'There was some error in starting ' . EE_CRON_SCHEDULER . ' container. Please check logs.' );
			}
		}

		EE\Utils\delem_log( 'ee cron add start' );

		if ( ! isset( $args[0] ) || 'host' !== $args[0] ) {
			$args = auto_site_name( $args, 'cron', __FUNCTION__ );
		}

		$site     = EE\Utils\remove_trailing_slash( $args[0] );
		$command  = EE\Utils\get_flag_value( $assoc_args, 'command' );
		$schedule = EE\Utils\get_flag_value( $assoc_args, 'schedule' );
		$user     = EE\Utils\get_flag_value( $assoc_args, 'user' );

		if ( '@' !== substr( trim( $schedule ), 0, 1 ) ) {
			$schedule_length = count( array_filter( explode( ' ', $schedule ), 'trim' ) );
			if ( 5 !== $schedule_length ) {
				EE::error( 'Schedule format should be same as Linux cron or schedule helper syntax(Check help for this)' );
			}
			$schedule = '0 ' . trim( $schedule );
		}

		$this->validate_command( $command );
		$command = $this->add_sh_c_wrapper( $command );

		$cron_data = [
			'site_url' => $site,
			'command'  => $command,
			'schedule' => $schedule,
		];

		if ( $user ) {
			$cron_data['user'] = $user;
		}

		Cron::create( $cron_data );

		$this->update_cron_config();

		EE::success( 'Cron created successfully' );
		EE\Utils\delem_log( 'ee cron add end' );
	}

	/**
	 * Ensures given command will not create problem with INI syntax.
	 * Semicolons and Hash(#) in commands do not work for now due to limitation of INI style config ofelia uses.
	 * See https://github.com/EasyEngine/cron-command/issues/4.
	 *
	 * @param string $command Command whose syntax needs to be validated.
	 *
	 * @throws \EE\ExitException
	 */
	private function validate_command( $command ) {

		if ( strpos( $command, ';' ) !== false ) {
			EE::error( 'Command chaining using `;` - semi-colon is not supported currently. You can either use `&&` or `||` or creating a second cron job for the chained command.' );
		}
		if ( strpos( $command, '#' ) !== false ) {
			EE::error( 'EasyEngine does not support commands with #' );
		}
	}

	/**
	 * Adds wrapper of `sh -c` to execute composite commands through docker exec properly.
	 *
	 * @param string $command Passed command.
	 *
	 * @return string Command with properly added wrapper.
	 */
	private function add_sh_c_wrapper( $command ) {
		if ( strpos( $command, 'sh -c' ) !== false ) {
			return $command;
		}

		return "sh -c '" . $command . "'";
	}

	/**
	 * Generates cron config from DB
	 */
	private function update_cron_config() {

		$config = $this->generate_cron_config();
		file_put_contents( EE_ROOT_DIR . '/services/cron/config.ini', $config );
		EE_DOCKER::restart_container( EE_CRON_SCHEDULER );
	}

	/**
	 * Generates and returns cron config from DB
	 */
	private function generate_cron_config() {

		$config_template = file_get_contents( __DIR__ . '/../templates/config.ini.mustache' );
		$crons           = Cron::all();

		foreach ( $crons as &$cron ) {
			$job_type       = 'host' === $cron->site_url ? 'job-local' : 'job-exec';
			$id             = $cron->site_url . '-' . preg_replace( '/[^a-zA-Z0-9\@]/', '-', $cron->command ) . '-' . EE\Utils\random_password( 5 );
			$id             = preg_replace( '/--+/', '-', $id );
			$cron->job_type = $job_type;
			$cron->id       = $id;

			if ( 'host' !== $cron->site_url ) {
				$cron->container = $this->site_php_container( $cron->site_url );
			}
		}

		$me = new Mustache_Engine();

		return $me->render( $config_template, $crons );
	}

	/**
	 * Returns php container name of a site.
	 *
	 * @param string $site Name of the site whose container name is needed.
	 *
	 * @return string Container name.
	 */
	private function site_php_container( $site ) {
		return str_replace( '.', '', $site ) . '_php_1';
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
	 * [--user=<user>]
	 * : User to execute command as.
	 *
	 * We also have helper to easily specify scheduling format:
	 *
	 * | Entry                   | Description                                | Equivalent To
	 * | -----                   | -----------                                | -------------
	 * | @yearly (or @annually)  | Run once a year, midnight, Jan. 1st        | 0 0 1 1 *
	 * | @monthly                | Run once a month, midnight, first of month | 0 0 1 * *
	 * | @weekly                 | Run once a week, midnight between Sat/Sun  | 0 0 * * 0
	 * | @daily (or @midnight)   | Run once a day, midnight                   | 0 0 * * *
	 * | @hourly                 | Run once an hour, beginning of hour        | 0 * * * *
	 *
	 * You may also schedule a job to execute at fixed intervals, starting at the time it's added or cron is run.
	 * This is supported by following format:
	 *
	 * - @every <duration>
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
	 *     # Updates command and user of cron
	 *     $ ee cron update 1 --command='wp cron event run --due-now' --user=root
	 *
	 *     # Updates schedule of cron
	 *     $ ee cron update 1 --schedule='@every 1m'
	 */
	public function update( $args, $assoc_args ) {

		EE\Utils\delem_log( 'ee cron add start' );

		$data_to_update = [];
		$site           = EE\Utils\get_flag_value( $assoc_args, 'site' );
		$command        = EE\Utils\get_flag_value( $assoc_args, 'command' );
		$schedule       = EE\Utils\get_flag_value( $assoc_args, 'schedule' );
		$user           = EE\Utils\get_flag_value( $assoc_args, 'user' );
		$cron_id        = $args[0];

		if ( ! $site && ! $command && ! $schedule && ! $user ) {
			EE::error( 'You should specify at least one of - site, command, schedule or user to update' );
		}
		if ( $site ) {
			$data_to_update['site_url'] = $site;
		}
		if ( $user ) {
			$data_to_update['user'] = $user;
		}
		if ( $command ) {
			$this->validate_command( $command );
			$command                   = $this->add_sh_c_wrapper( $command );
			$data_to_update['command'] = $command;
		}
		if ( $schedule ) {
			if ( '@' !== substr( trim( $schedule ), 0, 1 ) ) {
				$schedule_length = strlen( implode( explode( ' ', trim( $schedule ) ) ) );
				if ( 5 !== $schedule_length ) {
					EE::error( 'Schedule format should be same as Linux cron or schedule helper syntax(Check help for this)' );
				}
			}
			$data_to_update['schedule'] = $schedule;
		}

		Cron::find( $cron_id )->update( $data_to_update );

		$this->update_cron_config();

		EE::success( 'Cron update Successfully');

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

		$all = EE\Utils\get_flag_value( $assoc_args, 'all' );

		if ( ( ! isset( $args[0] ) || 'host' !== $args[0] ) && ! $all ) {
			$args = auto_site_name( $args, 'cron', 'list' );
		}

		if ( isset( $args[0] ) ) {
			$crons = Cron::where( 'site_url', $args[0] );
		} else {
			$crons = Cron::all();
		}


		if ( empty( $crons ) ) {
			EE::error( 'No cron jobs found.' );
		}

		EE\Utils\format_items( 'table', $crons, [ 'id', 'site_url', 'command', 'schedule' ] );
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

		$cron = Cron::find( $args[0] );

		if ( empty( $cron ) ) {
			EE::error( 'No such cron with id ' . $args[0] );
		}

		$container = $this->site_php_container( $cron->site_url );
		$command   = $cron->command;
		$user      = empty( $cron->user ) ? 'root' : $cron->user;

		if ( 'host' === $cron->site_url ) {
			EE::exec( $command, true, true );
			return;
		}

		EE::exec( "docker exec --user=$user $container $command", true, true );
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

		$id   = $args[0];
		$cron = Cron::find( $id );

		if ( ! $cron ) {
			EE::error( 'Unable to find cron with id ' . $id );
		}

		$cron->delete();
		$this->update_cron_config();

		EE::success( 'Deleted cron with id ' . $id );

		$cron_entries = Cron::all();
		if ( empty( $cron_entries ) ) {
			EE::exec( 'docker rm -f ' . EE_CRON_SCHEDULER );
		}
	}
}
