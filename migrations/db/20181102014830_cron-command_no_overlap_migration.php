<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Model\Cron;

class NoOverlapMigration extends Base {

	protected $source_file;

	public function __construct() {

		parent::__construct();
		$crons = Cron::all();
		if ( $this->is_first_execution || ! $crons ) {
			$this->skip_this_migration = true;
		}
		$this->source_file = EE_ROOT_DIR . '/services/cron/config.ini';
		$this->backup_file = $this->backup_dir . '/cron/config.ini.' . time() . '.bak';
	}

	/**
	 * Execute Updating cron config with no-overlap.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping no-overlap migration as it is not needed.' );

			return;
		}

		// Create backup.
		$this->fs->copy( $this->source_file, $this->backup_file );

		// Fix scheduling.
		$crons = Cron::all();
		foreach ( $crons as &$cron ) {
			$schedule        = array_filter( explode( ' ', $cron->schedule ), function ( $value ) {
				return preg_match( '#\S#', $value );
			} );
			$schedule_length = count( $schedule );

			if ( $schedule_length > 6 && '0' === array_shift( $schedule ) ) {
				$cron->schedule = implode( ' ', $schedule );
				$cron->save();
			}
		}
		// Fix no-overlap.
		EE\Cron\Utils\update_cron_config();
	}

	/**
	 * Execute drop table query for cron table.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {

		$this->fs->copy( $this->backup_file, $this->source_file );
		\EE_DOCKER::restart_container( EE_CRON_SCHEDULER );
	}
}
