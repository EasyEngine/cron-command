<?php

namespace EE\Cron\Utils;

use EE;
use EE\Model\Cron;

/**
 * Generates cron config from DB
 */
function update_cron_config() {

	$config = generate_cron_config();
	file_put_contents( EE_SERVICE_DIR . '/cron/conf/config.ini', $config );
	\EE_DOCKER::restart_container( EE_CRON_SCHEDULER );
}

/**
 * Generates and returns cron config from DB
 */
function generate_cron_config() {

	$config_template = file_get_contents( __DIR__ . '/../../templates/config.ini.mustache' );
	$jobs            = [];

	foreach ( Cron::all() as $cron ) {
		$is_host = 'host' === $cron->site_url;
		$id      = $cron->site_url . '-' . preg_replace( '/[^a-zA-Z0-9\@]/', '-', $cron->command ) . '-' . EE\Utils\random_password( 5 );
		$job     = [
			'job_type'  => $is_host ? 'job-local' : 'job-exec',
			'id'        => preg_replace( '/--+/', '-', $id ),
			'schedule'  => normalize_schedule( $cron->schedule ),
			'command'   => $cron->command,
			// ofelia refuses to start when a job-local section has a user.
			'user'      => $is_host ? '' : (string) $cron->user,
			'container' => $is_host ? '' : site_php_container( $cron->site_url ),
		];

		// One value the INI parser rejects would stop the scheduler for every job, so leave that job out.
		foreach ( [ 'schedule', 'command', 'user', 'container' ] as $field ) {
			if ( ! is_ini_safe( $job[ $field ] ) ) {
				EE::warning( sprintf( 'Cron %s is left out of the scheduler config: its %s cannot be written to it.', $cron->id, $field ) );
				continue 2;
			}
		}

		$jobs[] = $job;
	}

	$me = new \Mustache_Engine();

	return $me->render( $config_template, $jobs );
}

/**
 * Validates a schedule given on the command line.
 *
 * @param string $schedule Five-field Linux cron expression or schedule helper (@daily, @every 10m, ...).
 *
 * @return string|false Schedule to store, or false if it is invalid.
 */
function validate_schedule( $schedule ) {

	$schedule = trim( (string) $schedule );

	if ( '@' === substr( $schedule, 0, 1 ) ) {
		$descriptors = [ '@yearly', '@annually', '@monthly', '@weekly', '@daily', '@midnight', '@hourly' ];
		$duration    = '([0-9]+(?:\.[0-9]*)?|\.[0-9]+)(ns|us|µs|ms|s|m|h)';
		$units       = [ 'ns' => 1, 'us' => 1e3, 'µs' => 1e3, 'ms' => 1e6, 's' => 1e9, 'm' => 6e10, 'h' => 3.6e12 ];

		if ( in_array( $schedule, $descriptors, true ) ) {
			return $schedule;
		}
		if ( ! preg_match( "/^@every (?:$duration)+$/u", $schedule ) ) {
			return false;
		}
		// ofelia rejects durations that overflow Go's int64 nanoseconds (about 292 years).
		preg_match_all( "/$duration/u", substr( $schedule, 7 ), $parts, PREG_SET_ORDER );
		$nanoseconds = 0;
		foreach ( $parts as $part ) {
			$nanoseconds += (float) $part[1] * $units[ $part[2] ];
		}

		return $nanoseconds < 9.2e18 ? $schedule : false;
	}

	$fields = preg_split( '/\s+/', $schedule );
	$months = array_combine( [ 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec' ], range( 1, 12 ) );
	$days   = array_flip( [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ] );
	// Minute, hour, day of month, month and day of week with ofelia's bounds (Sunday is 0 only).
	$ranges = [ [ 0, 59, [] ], [ 0, 23, [] ], [ 1, 31, [] ], [ 1, 12, $months ], [ 0, 6, $days ] ];

	if ( 5 !== count( $fields ) ) {
		return false;
	}

	foreach ( $fields as $i => $field ) {
		if ( ! is_valid_cron_field( $field, $ranges[ $i ][0], $ranges[ $i ][1], $ranges[ $i ][2] ) ) {
			return false;
		}
	}

	return normalize_schedule( $schedule );
}

/**
 * Adds the seconds field ofelia expects to a five-field cron expression, so it is not read as seconds.
 * Six-field expressions and schedule helpers are returned as they are.
 *
 * @param string $schedule Schedule from the command line or the DB.
 *
 * @return string
 */
function normalize_schedule( $schedule ) {

	$schedule = trim( (string) $schedule );

	if ( '' !== $schedule && '@' !== $schedule[0] && 5 === count( preg_split( '/\s+/', $schedule ) ) ) {
		return '0 ' . $schedule;
	}

	return $schedule;
}

/**
 * Checks one field of a cron expression: lists of `*`, `?`, values or ranges, each with an optional step.
 *
 * @param string $field Field to check.
 * @param int    $min   Lowest allowed value.
 * @param int    $max   Highest allowed value.
 * @param array  $names Allowed names (lowercase) mapped to their values.
 *
 * @return bool
 */
function is_valid_cron_field( $field, $min, $max, array $names ) {

	foreach ( explode( ',', strtolower( $field ) ) as $part ) {
		// A step past 18 digits overflows ofelia's int parser.
		if ( ! preg_match( '#^(?:[*?]|(\w+)(?:-(\w+))?)(?:/0*[1-9][0-9]{0,17})?$#', $part, $matches ) ) {
			return false;
		}
		// Empty for `*` and `?`, else the start and optional end of the range.
		$bounds = array_slice( $matches, 1 );
		foreach ( $bounds as $key => $bound ) {
			$bounds[ $key ] = isset( $names[ $bound ] ) ? $names[ $bound ] : ( ctype_digit( $bound ) ? (int) $bound : -1 );
			if ( $bounds[ $key ] < $min || $bounds[ $key ] > $max ) {
				return false;
			}
		}
		if ( 2 === count( $bounds ) && $bounds[0] > $bounds[1] ) {
			return false;
		}
	}

	return true;
}

/**
 * Checks that a value can be written to ofelia's INI config without a parse error, which would stop the scheduler.
 * Mirrors gcfg: `"` toggles quoting, `\` may only escape `"` (and `\`, `n`, `t`, `b` inside quotes), and an
 * unquoted `;` or `#` starts a comment.
 *
 * @param string $value Value to check.
 *
 * @return bool
 */
function is_ini_safe( $value ) {

	$value  = (string) $value;
	$quoted = false;
	$length = strlen( $value );

	if ( preg_match( '/[\r\n]/', $value ) ) {
		return false;
	}

	for ( $i = 0; $i < $length; $i ++ ) {
		$char = $value[ $i ];
		if ( '\\' === $char ) {
			$next = $i + 1 < $length ? $value[ $i + 1 ] : '';
			if ( '"' !== $next && ! ( $quoted && in_array( $next, [ '\\', 'n', 't', 'b' ], true ) ) ) {
				return false;
			}
			$i ++;
		} elseif ( '"' === $char ) {
			$quoted = ! $quoted;
		} elseif ( ! $quoted && ( ';' === $char || '#' === $char ) ) {
			break;
		}
	}

	return ! $quoted;
}

/**
 * Returns php container name of a site.
 *
 * @param string $site Name of the site whose container name is needed.
 *
 * @return string Container name.
 */
function site_php_container( $site ) {

	$site_data     = \EE\Site\Utils\get_site_info( [ $site ], false, false, true );
	$command       = 'cd ' . $site_data['site_fs_path'] . " && docker-compose ps --format '{{.Name}}'  php";
	$php_container = trim( EE::launch( $command )->stdout );

	return $php_container;
}
