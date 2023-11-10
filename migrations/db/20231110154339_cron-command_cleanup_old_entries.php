<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Model\Cron;
use EE\Model\Site;

class CleanupOldEntries extends Base {

	public function __construct() {

		parent::__construct();
		$this->sites = Site::all();
		if ( $this->is_first_execution || ! $this->sites ) {
			$this->skip_this_migration = true;
		}
	}

	/**
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping cron-cleanup migration as it is not needed.' );

			return;
		}

		$crons = Cron::all();

		foreach ( $crons as $cron ) {
			$site = Site::find( $cron->site_url );
			if ( ! $site ) {
				$cron->delete();
			}
		}

		EE\Cron\Utils\update_cron_config();
	}

	/**
	 * @throws EE\ExitException
	 */
	public function down() {

	}

}
