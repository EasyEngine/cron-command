<?php
namespace EE\Migration;

use EE;
use EE\Migration\Base;

class CreateTableCronMigration extends Base {

	private static $pdo;

	public function __construct() {

		try {
			self::$pdo = new \PDO( 'sqlite:' . DB );
			self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		} catch ( \PDOException $exception ) {
			EE::error( $exception->getMessage() );
		}

	}

	public function up() {

		$query = 'CREATE TABLE cron (
			id INTEGER,
			site_url VARCHAR,
			command VARCHAR,
			schedule VARCHAR,
			PRIMARY KEY (id),
			FOREIGN KEY (site_url) REFERENCES sites(site_url)
		);';

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while creating table: ' . $exception->getMessage() );
		}
	}

	public function down() {

		$query = 'DROP TABLE IF EXISTS cron;';

		try {
			self::$pdo->exec( $query );
		} catch ( PDOException $exception ) {
			EE::error( 'Encountered Error while dropping table: ' . $exception->getMessage() );
		}
	}
}
