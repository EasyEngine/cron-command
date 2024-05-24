<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Model\Cron;
use EE\Model\Site;

class RecreateCrons extends Base {

	public function __construct() {

		parent::__construct();
		$this->sites = Site::all();
		$crons       = Cron::all();
		if ( $this->is_first_execution || ! $this->sites || ! $crons ) {
			$this->skip_this_migration = true;
		}
	}

	/**
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping cron recreate migration as it is not needed.' );

			return;
		}

		EE\Cron\Utils\update_cron_config();
	}

	/**
	 * @throws EE\ExitException
	 */
	public function down() {

	}

}
